<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\SeoPage;
use App\Models\SeoSite;
use App\Models\SeoSiteCrawl;
use App\Models\SeoSitePage;
use App\Models\SeoSitePageSnapshot;
use App\Services\Publication\ConfirmPreviewPublicationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ObservedNativePreviewPublicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirm_preview_imports_observed_page_and_publishes_native_update(): void
    {
        $liveUrl = 'https://client.test/faq';

        Http::fake([
            'https://client.test/api/praeviseo/bridge/publish' => Http::response([
                'status' => 'ok',
                'scope' => 'native_update',
                'target_path' => '/faq',
                'live_url' => $liveUrl,
                'sitemap_url' => 'https://client.test/sitemap.xml',
            ], 200),
            $liveUrl => Http::response(
                '<html><head><title>FAQ</title></head><body><h1>FAQ</h1><p>Contenu enrichi.</p></body></html>',
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

        $crawl = SeoSiteCrawl::query()->create([
            'site_id' => $site->site_id,
            'base_url' => 'https://client.test',
            'status' => 'completed',
            'max_pages' => 10,
            'meta_json' => [],
        ]);

        $observed = SeoSitePage::query()->create([
            'site_id' => $site->site_id,
            'normalized_url' => $liveUrl,
            'url_hash' => hash('sha256', $liveUrl),
            'path' => '/faq',
            'title' => 'FAQ',
            'latest_word_count' => 280,
        ]);

        $snapshot = SeoSitePageSnapshot::query()->create([
            'site_id' => $site->site_id,
            'site_crawl_id' => $crawl->id,
            'site_page_id' => $observed->id,
            'url' => $liveUrl,
            'title' => 'FAQ amiante',
            'h2_json' => ['Questions générales'],
            'content_text' => 'Contenu actuel de la FAQ sur le diagnostic amiante.',
            'word_count' => 280,
            'observed_at' => now(),
        ]);

        $observed->forceFill(['last_snapshot_id' => $snapshot->id])->save();

        $result = app(ConfirmPreviewPublicationService::class)->confirm($site, 'faq');

        $this->assertTrue($result['published_live']);
        $this->assertSame($liveUrl, $result['live_url']);
        $this->assertSame('native_update', $result['publication_scope']);

        $page = SeoPage::query()->where('site_id', $site->site_id)->where('slug', 'faq')->first();

        $this->assertNotNull($page);
        $this->assertSame($observed->id, $page->observed_site_page_id);
        $this->assertTrue($page->isPublishedLive());
        $this->assertSame($liveUrl, $page->live_url);
        $this->assertNotSame('', trim(strip_tags((string) $page->content)));

        Http::assertSent(function ($request): bool {
            $body = json_decode((string) $request->body(), true);

            return is_array($body)
                && ($body['publication']['scope'] ?? null) === 'native_update'
                && ($body['publication']['target_path'] ?? null) === '/faq';
        });
    }
}
