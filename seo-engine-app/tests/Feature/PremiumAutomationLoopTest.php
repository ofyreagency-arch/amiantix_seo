<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\SeoPage;
use App\Models\SeoSearchConsoleMetric;
use App\Models\SeoSite;
use App\Models\SeoSiteCrawl;
use App\Models\SeoSiteGoogleConnection;
use App\Models\SeoSuggestion;
use App\Runtime\PremiumAutomationLoopService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Mockery;
use Ofyre\SeoEngine\Services\Console\SeoGeneratePageRunner;
use App\Services\Media\SeoPageImageGenerator;
use Tests\TestCase;

class PremiumAutomationLoopTest extends TestCase
{
    use RefreshDatabase;

    public function test_premium_loop_can_generate_a_new_article_when_signal_is_ready(): void
    {
        $site = $this->makePremiumReadySite();

        SeoSearchConsoleMetric::query()->create([
            'site_id' => $site->site_id,
            'metric_date' => now()->toDateString(),
            'window_days' => 28,
            'query' => 'logiciel amiante ss3',
            'url' => null,
            'impressions' => 7,
            'clicks' => 0,
            'ctr' => 0,
            'position' => 16.4,
        ]);

        $generator = Mockery::mock(SeoGeneratePageRunner::class);
        $generator->shouldReceive('run')
            ->once()
            ->with('logiciel amiante ss3', 'published', false)
            ->andReturnUsing(function () use ($site): array {
                $page = SeoPage::query()->create([
                    'site_id' => $site->site_id,
                    'keyword' => 'logiciel amiante ss3',
                    'slug' => 'logiciel-amiante-ss3',
                    'status' => 'published',
                    'published_at' => now(),
                    'title' => 'Logiciel amiante SS3',
                    'content' => '<p>Contenu premium.</p>',
                    'generation_source' => 'ai',
                ]);

                return [
                    'page' => $page,
                    'warning' => null,
                ];
            });
        $this->app->instance(SeoGeneratePageRunner::class, $generator);

        $result = $this->app->make(PremiumAutomationLoopService::class)->runForSite($site->fresh());

        $site->refresh();

        $this->assertTrue($result['executed']);
        $this->assertSame('generation', $result['action']);
        $this->assertSame('article_generated', $result['reason']);
        $this->assertSame('completed', data_get($site->settings_json, 'automation.actions.generation.state'));
        $this->assertSame('auto_article_generated', data_get($site->settings_json, 'automation.history.0.kind'));

        $page = SeoPage::query()->where('site_id', $site->site_id)->where('slug', 'logiciel-amiante-ss3')->first();
        $this->assertNotNull($page);
        $this->assertSame('ai', $page->generation_source);
    }

    public function test_premium_loop_skips_new_article_when_auto_blog_guardrails_are_reached(): void
    {
        $site = $this->makePremiumReadySite([
            'automation' => [
                'autoblog' => [
                    'min_hours_between_articles' => 72,
                    'max_articles_per_28_days' => 3,
                    'minimum_query_impressions' => 3,
                    'maximum_query_position' => 35,
                ],
                'actions' => [
                    'publication' => ['state' => 'completed', 'updated_at' => now()->toIso8601String()],
                    'linking' => ['state' => 'completed', 'updated_at' => now()->toIso8601String()],
                    'rewrite' => ['state' => 'completed', 'updated_at' => now()->toIso8601String()],
                    'images' => ['state' => 'completed', 'updated_at' => now()->toIso8601String()],
                ],
            ],
        ]);

        SeoPage::query()->create([
            'site_id' => $site->site_id,
            'keyword' => 'ancien sujet premium',
            'slug' => 'ancien-sujet-premium',
            'status' => 'published',
            'published_at' => now()->subDay(),
            'published_live' => true,
            'published_live_at' => now()->subDay(),
            'live_url' => 'https://amiantix.test/ressources/ancien-sujet-premium',
            'canonical_url' => 'https://amiantix.test/ressources/ancien-sujet-premium',
            'title' => 'Ancien sujet premium',
            'content' => '<p>Déjà publié.</p>',
            'generation_source' => 'ai',
            'image_path' => 'images/ancien-sujet-premium.jpg',
            'image_status' => 'approved',
        ]);

        SeoSearchConsoleMetric::query()->create([
            'site_id' => $site->site_id,
            'metric_date' => now()->toDateString(),
            'window_days' => 28,
            'query' => 'nouveau sujet amiante',
            'url' => null,
            'impressions' => 8,
            'clicks' => 0,
            'ctr' => 0,
            'position' => 18.2,
        ]);

        $generator = Mockery::mock(SeoGeneratePageRunner::class);
        $generator->shouldNotReceive('run');
        $this->app->instance(SeoGeneratePageRunner::class, $generator);

        $result = $this->app->make(PremiumAutomationLoopService::class)->runForSite($site->fresh());

        $this->assertFalse($result['executed']);
        $this->assertNull($result['action']);
        $this->assertSame('no_actionable_step', $result['reason']);
        $this->assertSame(1, SeoPage::query()->where('site_id', $site->site_id)->count());
    }

    public function test_premium_loop_can_apply_and_republish_a_rewrite(): void
    {
        Http::fake([
            'https://amiantix.test/api/praeviseo/bridge/publish' => Http::response([
                'live_url' => 'https://amiantix.test/ressources/diagnostic-amiante',
            ], 200),
        ]);

        $site = $this->makePremiumReadySite([
            'automation' => [
                'actions' => [
                    'publication' => ['state' => 'completed', 'updated_at' => now()->subHour()->toIso8601String()],
                    'linking' => ['state' => 'completed', 'updated_at' => now()->subHour()->toIso8601String()],
                ],
            ],
        ]);

        $page = SeoPage::query()->create([
            'site_id' => $site->site_id,
            'keyword' => 'diagnostic amiante',
            'slug' => 'diagnostic-amiante',
            'status' => 'published',
            'published_at' => now()->subDay(),
            'published_live' => true,
            'published_live_at' => now()->subDay(),
            'live_url' => 'https://amiantix.test/ressources/diagnostic-amiante',
            'title' => 'Titre initial',
            'meta_description' => 'Meta initiale',
            'content' => '<p>Avant.</p>',
            'seo_score' => 74,
        ]);

        $suggestion = SeoSuggestion::query()->create([
            'seo_page_id' => $page->id,
            'source' => 'premium_loop',
            'status' => 'pending',
            'signals_json' => ['source' => 'gsc'],
            'suggestions_json' => [
                'title' => 'Titre optimisé',
                'content' => '<p>Apres.</p>',
            ],
        ]);

        $result = $this->app->make(PremiumAutomationLoopService::class)->runForSite($site->fresh());

        $page->refresh();
        $suggestion->refresh();
        $site->refresh();

        $this->assertTrue($result['executed']);
        $this->assertSame('rewrite', $result['action']);
        $this->assertSame('rewrite_published', $result['reason']);
        $this->assertSame('Titre optimisé', $page->title);
        $this->assertSame('<p>Apres.</p>', $page->content);
        $this->assertTrue($page->published_live);
        $this->assertSame('applied', $suggestion->status);
        $this->assertSame('completed', data_get($site->settings_json, 'automation.actions.rewrite.state'));
    }

    public function test_premium_loop_can_generate_and_republish_an_image(): void
    {
        Http::fake([
            'https://amiantix.test/api/praeviseo/bridge/publish' => Http::response([
                'live_url' => 'https://amiantix.test/ressources/fiche-retrait-amiante',
            ], 200),
        ]);

        $site = $this->makePremiumReadySite([
            'automation' => [
                'actions' => [
                    'publication' => ['state' => 'completed', 'updated_at' => now()->subHour()->toIso8601String()],
                    'linking' => ['state' => 'completed', 'updated_at' => now()->toIso8601String()],
                    'rewrite' => ['state' => 'completed', 'updated_at' => now()->toIso8601String()],
                    'generation' => ['state' => 'completed', 'updated_at' => now()->toIso8601String()],
                ],
            ],
        ]);

        $page = SeoPage::query()->create([
            'site_id' => $site->site_id,
            'keyword' => 'fiche retrait amiante',
            'slug' => 'fiche-retrait-amiante',
            'status' => 'published',
            'published_at' => now()->subDay(),
            'published_live' => true,
            'published_live_at' => now()->subDay(),
            'live_url' => 'https://amiantix.test/ressources/fiche-retrait-amiante',
            'title' => 'Fiche retrait amiante',
            'content' => '<p>Page publiée.</p>',
            'seo_score' => 69,
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

        $result = $this->app->make(PremiumAutomationLoopService::class)->runForSite($site->fresh());

        $page->refresh();
        $site->refresh();

        $this->assertTrue($result['executed']);
        $this->assertSame('images', $result['action']);
        $this->assertSame('image_published', $result['reason']);
        $this->assertSame('seo-pages/amiantix/fiche-retrait-amiante.png', $page->image_path);
        $this->assertSame('approved', $page->image_status);
        $this->assertTrue($page->published_live);
        $this->assertSame('completed', data_get($site->settings_json, 'automation.actions.images.state'));
    }

    /**
     * @param  array<string,mixed>  $settings
     */
    private function makePremiumReadySite(array $settings = []): SeoSite
    {
        $site = SeoSite::query()->create([
            'site_id' => 'amiantix',
            'name' => 'Amiantix',
            'url' => 'https://amiantix.test',
            'niche' => 'amiante',
            'locale' => 'fr',
            'preset' => 'amiantix',
            'api_token_hash' => hash('sha256', 'site-token'),
            'is_active' => true,
            'settings_json' => array_replace_recursive([
                'publication' => [
                    'mode' => 'laravel_bridge',
                    'bridge_status' => 'connected',
                    'path_prefix' => 'ressources',
                    'webhook_url' => 'https://amiantix.test/api/praeviseo/bridge/publish',
                    'shared_secret' => 'bridge-secret',
                ],
            ], $settings),
        ]);

        SeoSiteGoogleConnection::query()->create([
            'site_id' => $site->site_id,
            'connection_mode' => 'oauth',
            'property_url' => 'sc-domain:amiantix.test',
            'connection_status' => 'configured',
        ]);

        SeoSiteCrawl::query()->create([
            'site_id' => $site->site_id,
            'base_url' => 'https://amiantix.test',
            'status' => 'completed',
            'max_pages' => 10,
            'started_at' => now()->subMinutes(10),
            'completed_at' => now()->subMinutes(5),
        ]);

        return $site;
    }
}
