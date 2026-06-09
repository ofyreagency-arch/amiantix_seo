<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\SeoPage;
use App\Models\SeoSite;
use App\Models\SeoSiteCrawl;
use App\Models\SeoSitePage;
use App\Models\SeoSitePageSnapshot;
use App\Services\Publication\ConfirmPreviewPublicationService;
use App\Services\Publication\SeoLivePublicationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GenericNativeUpdatePublicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_native_update_works_for_generic_client_outside_bridge_prefix(): void
    {
        $siteUrl = 'https://cabinet-martin.test';
        $nativePath = '/nos-services';
        $liveUrl = $siteUrl.$nativePath;
        $bridgePrefix = 'articles';

        Http::fake([
            $siteUrl.'/api/praeviseo/bridge/publish' => Http::response([
                'status' => 'ok',
                'scope' => 'native_update',
                'target_path' => $nativePath,
                'live_url' => $liveUrl,
            ], 200),
            $liveUrl => Http::response('<html><body><h1>Nos services</h1></body></html>', 200),
        ]);

        $site = $this->makeGenericClientSite($siteUrl, $bridgePrefix);
        $this->seedObservedPage($site, $liveUrl, $nativePath, 'Nos services plomberie', 'Nous intervenons sur Paris et la petite couronne.');

        $result = app(ConfirmPreviewPublicationService::class)->confirm($site, 'nos-services');

        $this->assertSame('native_update', $result['publication_scope']);
        $this->assertTrue($result['published_live']);
        $this->assertSame($liveUrl, $result['live_url']);

        $page = SeoPage::query()->where('site_id', $site->site_id)->where('slug', 'nos-services')->first();
        $this->assertNotNull($page);
        $this->assertSame('native_update', app(SeoLivePublicationService::class)->publicationScopeFor($page, $site));

        Http::assertSent(function ($request) use ($nativePath): bool {
            $body = json_decode((string) $request->body(), true);

            return is_array($body)
                && ($body['publication']['scope'] ?? null) === 'native_update'
                && ($body['publication']['target_path'] ?? null) === $nativePath
                && ($body['site']['site_id'] ?? null) === 'cabinet-martin';
        });
    }

    public function test_bridge_article_scope_stays_on_prefixed_observed_paths(): void
    {
        $siteUrl = 'https://cabinet-martin.test';
        $bridgePath = '/articles/guide-entretien';
        $liveUrl = $siteUrl.$bridgePath;

        Http::fake([
            $siteUrl.'/api/praeviseo/bridge/publish' => Http::response([
                'status' => 'ok',
                'scope' => 'bridge_article',
                'live_url' => $liveUrl,
            ], 200),
        ]);

        $site = $this->makeGenericClientSite($siteUrl, 'articles');

        $page = SeoPage::query()->create([
            'site_id' => $site->site_id,
            'keyword' => 'guide entretien',
            'slug' => 'guide-entretien',
            'status' => 'published',
            'published_at' => now(),
            'title' => 'Guide entretien',
            'content' => '<p>Guide.</p>',
        ]);

        $observed = SeoSitePage::query()->create([
            'site_id' => $site->site_id,
            'normalized_url' => $liveUrl,
            'url_hash' => hash('sha256', $liveUrl),
            'path' => $bridgePath,
            'title' => 'Guide entretien',
        ]);

        $page->forceFill([
            'observed_site_page_id' => $observed->id,
            'observed_page_match_rule' => 'canonical_url_exact',
        ])->save();

        $scope = app(SeoLivePublicationService::class)->publicationScopeFor($page->fresh(['observedPage']), $site);

        $this->assertSame('bridge_article', $scope);
    }

    private function makeGenericClientSite(string $siteUrl, string $bridgePrefix): SeoSite
    {
        return SeoSite::query()->create([
            'site_id' => 'cabinet-martin',
            'name' => 'Cabinet Martin Plomberie',
            'url' => $siteUrl,
            'niche' => 'plomberie',
            'locale' => 'fr',
            'preset' => 'generic',
            'api_token_hash' => hash('sha256', 'generic-token'),
            'is_active' => true,
            'webhook_url' => $siteUrl.'/api/praeviseo/bridge/publish',
            'settings_json' => [
                'publication' => [
                    'mode' => 'laravel_bridge',
                    'webhook_url' => $siteUrl.'/api/praeviseo/bridge/publish',
                    'shared_secret' => 'generic-bridge-secret',
                    'path_prefix' => $bridgePrefix,
                    'bridge_status' => 'connected',
                ],
            ],
        ]);
    }

    private function seedObservedPage(
        SeoSite $site,
        string $liveUrl,
        string $path,
        string $title,
        string $contentText,
    ): SeoSitePage {
        $crawl = SeoSiteCrawl::query()->create([
            'site_id' => $site->site_id,
            'base_url' => $site->url,
            'status' => 'completed',
            'max_pages' => 10,
            'meta_json' => [],
        ]);

        $observed = SeoSitePage::query()->create([
            'site_id' => $site->site_id,
            'normalized_url' => $liveUrl,
            'url_hash' => hash('sha256', $liveUrl),
            'path' => $path,
            'title' => $title,
            'latest_word_count' => 320,
        ]);

        $snapshot = SeoSitePageSnapshot::query()->create([
            'site_id' => $site->site_id,
            'site_crawl_id' => $crawl->id,
            'site_page_id' => $observed->id,
            'url' => $liveUrl,
            'title' => $title,
            'h2_json' => ['Interventions'],
            'content_text' => $contentText,
            'word_count' => 320,
            'observed_at' => now(),
        ]);

        $observed->forceFill(['last_snapshot_id' => $snapshot->id])->save();

        return $observed;
    }
}
