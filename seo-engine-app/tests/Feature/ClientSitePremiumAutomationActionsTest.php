<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\SeoPage;
use App\Models\SeoSite;
use App\Models\SeoSuggestion;
use App\Models\User;
use App\Models\UserAccessToken;
use App\Services\Media\SeoPageImageGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Ofyre\SeoEngine\Services\Rewrite\SeoRewriteService;
use Tests\TestCase;

class ClientSitePremiumAutomationActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_rewrite_action_applies_and_republishes_a_real_page(): void
    {
        Queue::fake();
        Http::fake([
            'https://amiantix.test/api/praeviseo/bridge/publish' => Http::response([
                'live_url' => 'https://amiantix.test/ressources/diagnostic-amiante',
                'sitemap_url' => 'https://amiantix.test/ressources-sitemap.xml',
            ], 200),
        ]);

        [$user, $token, $site] = $this->makeAuthenticatedPremiumSite();

        $page = SeoPage::query()->create([
            'site_id' => $site->site_id,
            'keyword' => 'diagnostic amiante',
            'slug' => 'diagnostic-amiante',
            'status' => 'published',
            'published_at' => now()->subDay(),
            'title' => 'Diagnostic amiante ancien titre',
            'meta_description' => 'Ancienne meta.',
            'content' => '<p>Version initiale.</p>',
            'seo_score' => 72,
        ]);

        $suggestion = SeoSuggestion::query()->create([
            'seo_page_id' => $page->id,
            'source' => 'premium_manual',
            'status' => 'pending',
            'signals_json' => ['reason' => 'Improve CTR'],
            'suggestions_json' => [
                'title' => 'Diagnostic amiante complet',
                'meta_description' => 'Nouvelle meta plus précise.',
                'content' => '<p>Version enrichie.</p>',
            ],
        ]);

        $rewrite = Mockery::mock(SeoRewriteService::class);
        $rewrite->shouldNotReceive('createSuggestion');
        $this->app->instance(SeoRewriteService::class, $rewrite);

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/client/sites/amiantix/rewrite');

        $response->assertAccepted();
        $response->assertJsonPath('rewrite.suggestion_id', $suggestion->id);
        $response->assertJsonPath('rewrite.published_live', true);
        $response->assertJsonPath('rewrite.live_url', 'https://amiantix.test/ressources/diagnostic-amiante');

        $page->refresh();
        $suggestion->refresh();
        $site->refresh();

        self::assertSame('Diagnostic amiante complet', $page->title);
        self::assertSame('Nouvelle meta plus précise.', $page->meta_description);
        self::assertSame('<p>Version enrichie.</p>', $page->content);
        self::assertTrue($page->published_live);
        self::assertSame('https://amiantix.test/ressources/diagnostic-amiante', $page->live_url);
        self::assertSame('applied', $suggestion->status);
        self::assertSame('completed', data_get($site->settings_json, 'automation.actions.rewrite.state'));
        self::assertTrue(collect(data_get($site->settings_json, 'automation.history', []))->contains(fn (array $entry): bool => ($entry['kind'] ?? null) === 'rewrite_published'));
        self::assertSame('after_publication', data_get(SeoSite::query()->whereKey($site->id)->first()->latestObservedCrawl?->meta_json, 'trigger'));
    }

    public function test_client_image_action_generates_associates_and_republishes_a_real_page(): void
    {
        Queue::fake();
        Http::fake([
            'https://amiantix.test/api/praeviseo/bridge/publish' => Http::response([
                'live_url' => 'https://amiantix.test/ressources/fiche-retrait-amiante',
                'sitemap_url' => 'https://amiantix.test/ressources-sitemap.xml',
            ], 200),
        ]);

        [$user, $token, $site] = $this->makeAuthenticatedPremiumSite();

        $page = SeoPage::query()->create([
            'site_id' => $site->site_id,
            'keyword' => 'fiche retrait amiante',
            'slug' => 'fiche-retrait-amiante',
            'status' => 'published',
            'published_at' => now()->subDay(),
            'title' => 'Fiche retrait amiante',
            'content' => '<p>Page publiée.</p>',
            'seo_score' => 68,
        ]);

        $images = Mockery::mock(SeoPageImageGenerator::class);
        $images->shouldReceive('generate')
            ->once()
            ->andReturnUsing(function (SeoPage $page): SeoPage {
                $page->forceFill([
                    'image_path' => 'seo-pages/amiantix/fiche-retrait-amiante.png',
                    'image_alt' => 'Fiche retrait amiante',
                    'image_status' => 'generated',
                ])->save();

                return $page->refresh();
            });
        $images->shouldReceive('approve')
            ->once()
            ->andReturnUsing(function (SeoPage $page): SeoPage {
                $page->forceFill([
                    'image_status' => 'approved',
                ])->save();

                return $page->refresh();
            });
        $this->app->instance(SeoPageImageGenerator::class, $images);

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/client/sites/amiantix/images');

        $response->assertAccepted();
        $response->assertJsonPath('image.published_live', true);
        $response->assertJsonPath('image.live_url', 'https://amiantix.test/ressources/fiche-retrait-amiante');

        $page->refresh();
        $site->refresh();

        self::assertSame('seo-pages/amiantix/fiche-retrait-amiante.png', $page->image_path);
        self::assertSame('approved', $page->image_status);
        self::assertTrue($page->published_live);
        self::assertSame('https://amiantix.test/ressources/fiche-retrait-amiante', $page->live_url);
        self::assertSame('completed', data_get($site->settings_json, 'automation.actions.images.state'));
        self::assertTrue(collect(data_get($site->settings_json, 'automation.history', []))->contains(fn (array $entry): bool => ($entry['kind'] ?? null) === 'image_published'));
        self::assertSame('after_publication', data_get(SeoSite::query()->whereKey($site->id)->first()->latestObservedCrawl?->meta_json, 'trigger'));
    }

    /**
     * @return array{0:User,1:string,2:SeoSite}
     */
    private function makeAuthenticatedPremiumSite(): array
    {
        $user = User::factory()->create();
        $rawToken = 'frontend-token';

        UserAccessToken::query()->create([
            'user_id' => $user->id,
            'name' => 'frontend',
            'token_hash' => hash('sha256', $rawToken),
        ]);

        $site = SeoSite::query()->create([
            'site_id' => 'amiantix',
            'name' => 'Amiantix',
            'url' => 'https://amiantix.test',
            'niche' => 'amiante',
            'locale' => 'fr',
            'preset' => 'amiantix',
            'api_token_hash' => hash('sha256', 'site-token'),
            'is_active' => true,
            'settings_json' => [
                'publication' => [
                    'mode' => 'symfony_bridge',
                    'bridge_status' => 'connected',
                    'path_prefix' => 'ressources',
                    'webhook_url' => 'https://amiantix.test/api/praeviseo/bridge/publish',
                    'shared_secret' => 'bridge-secret',
                ],
            ],
        ]);

        $user->seoSites()->attach($site->id, ['role' => 'owner']);

        return [$user, $rawToken, $site];
    }
}
