<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\SeoPage;
use App\Models\SeoSite;
use App\Models\SeoSitePage;
use App\Models\SeoSiteSitemap;
use App\Services\Publication\SeoLivePublicationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LaravelBridgePublicationChainTest extends TestCase
{
    use RefreshDatabase;

    public function test_publish_live_through_laravel_bridge_observes_and_links_page(): void
    {
        $liveUrl = 'https://client.test/ressources/diagnostic-amiante-paris';
        $sitemapUrl = 'https://client.test/ressources-sitemap.xml';

        Http::fake([
            'https://client.test/api/praeviseo/bridge/publish' => Http::response([
                'status' => 'ok',
                'live_url' => $liveUrl,
                'sitemap_url' => $sitemapUrl,
            ], 200),
            $liveUrl => Http::response(
                '<html><head><title>Diagnostic amiante Paris</title></head><body><h1>Diagnostic</h1><p>Contenu publié.</p></body></html>',
                200,
                ['Content-Type' => 'text/html; charset=UTF-8'],
            ),
        ]);

        $site = SeoSite::query()->create([
            'site_id' => 'amiantix',
            'name' => 'Amiantix',
            'url' => 'https://client.test',
            'niche' => 'amiante',
            'locale' => 'fr',
            'preset' => 'amiantix',
            'api_token_hash' => hash('sha256', 'token'),
            'is_active' => true,
            'webhook_url' => 'https://client.test/api/praeviseo/bridge/publish',
            'settings_json' => [
                'publication' => [
                    'mode' => 'laravel_bridge',
                    'webhook_url' => 'https://client.test/api/praeviseo/bridge/publish',
                    'shared_secret' => 'bridge-secret',
                    'path_prefix' => 'ressources',
                    'bridge_status' => 'connected',
                ],
            ],
        ]);

        $page = SeoPage::query()->create([
            'site_id' => $site->site_id,
            'keyword' => 'diagnostic amiante paris',
            'slug' => 'diagnostic-amiante-paris',
            'status' => 'published',
            'published_at' => now(),
            'title' => 'Diagnostic amiante Paris',
            'content' => '<p>Contenu.</p>',
        ]);

        $published = app(SeoLivePublicationService::class)->publish($page, $site);

        $this->assertTrue($published->published_live);
        $this->assertSame($liveUrl, $published->live_url);
        $this->assertSame($liveUrl, $published->canonical_url);

        $observed = SeoSitePage::query()
            ->where('site_id', $site->site_id)
            ->where('normalized_url', $liveUrl)
            ->first();

        $this->assertNotNull($observed);
        $this->assertSame(200, (int) $observed->last_status_code);
        $this->assertSame('/ressources/diagnostic-amiante-paris', $observed->path);

        $published->refresh();
        $site->refresh();

        $this->assertSame($observed->id, $published->observed_site_page_id);
        $this->assertSame('canonical_url_exact', $published->observed_page_match_rule);
        $this->assertSame($sitemapUrl, data_get($site->settings_json, 'publication.last_sitemap_url'));

        $sitemap = SeoSiteSitemap::query()
            ->where('site_id', $site->site_id)
            ->where('url', $sitemapUrl)
            ->first();

        $this->assertNotNull($sitemap);
        $this->assertSame('published_pages', $sitemap->sitemap_type);
        $this->assertSame($liveUrl, data_get($sitemap->meta_json, 'last_live_url'));
    }
}
