<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Copilot\ActionPreviewService;
use App\Models\SeoSite;
use App\Models\SeoSiteCrawl;
use App\Models\SeoSitePage;
use App\Models\SeoSitePageSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActionPreviewServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_builds_before_after_preview_for_observed_page(): void
    {
        $site = SeoSite::query()->create([
            'site_id' => 'amiantix',
            'name' => 'Amiantix',
            'url' => 'https://amiantix.com',
            'niche' => 'amiante',
            'locale' => 'fr',
            'preset' => 'amiantix',
            'api_token_hash' => hash('sha256', 'token'),
            'is_active' => true,
        ]);

        $crawl = SeoSiteCrawl::query()->create([
            'site_id' => $site->site_id,
            'base_url' => 'https://amiantix.com',
            'status' => 'completed',
            'max_pages' => 10,
            'meta_json' => [],
        ]);

        $observed = SeoSitePage::query()->create([
            'site_id' => $site->site_id,
            'normalized_url' => 'https://amiantix.com/faq',
            'url_hash' => hash('sha256', 'https://amiantix.com/faq'),
            'path' => '/faq',
            'title' => 'FAQ',
            'latest_word_count' => 280,
        ]);

        $snapshot = SeoSitePageSnapshot::query()->create([
            'site_id' => $site->site_id,
            'site_crawl_id' => $crawl->id,
            'site_page_id' => $observed->id,
            'url' => 'https://amiantix.com/faq',
            'title' => 'FAQ amiante',
            'h2_json' => ['Questions générales'],
            'content_text' => 'Contenu actuel de la FAQ sur le diagnostic amiante.',
            'word_count' => 280,
            'observed_at' => now(),
        ]);

        $observed->forceFill(['last_snapshot_id' => $snapshot->id])->save();

        $payload = app(ActionPreviewService::class)->build($site->site_id, 'faq');

        $this->assertNotNull($payload);
        $this->assertSame('observed_crawl', $payload['current']['source']);
        $this->assertSame('FAQ amiante', $payload['current']['title']);
        $this->assertContains('Questions générales', $payload['current']['h2_headings']);
        $this->assertFalse($payload['apply_ready']);
        $this->assertSame('advisory_only', $payload['apply_context']['live_site_impact']);
        $this->assertFalse($payload['can_confirm_publish']);
        $this->assertNotEmpty($payload['proposed']['sections_to_add'] ?? $payload['proposed']['faq_to_add'] ?? $payload['proposed']['content_summary']);
    }

    public function test_preview_allows_confirm_publish_when_bridge_is_connected(): void
    {
        $site = SeoSite::query()->create([
            'site_id' => 'amiantix',
            'name' => 'Amiantix',
            'url' => 'https://amiantix.com',
            'niche' => 'amiante',
            'locale' => 'fr',
            'preset' => 'amiantix',
            'api_token_hash' => hash('sha256', 'token'),
            'is_active' => true,
            'webhook_url' => 'https://amiantix.com/api/praeviseo/bridge/publish',
            'settings_json' => [
                'publication' => [
                    'mode' => 'laravel_bridge',
                    'webhook_url' => 'https://amiantix.com/api/praeviseo/bridge/publish',
                    'shared_secret' => 'bridge-secret',
                    'path_prefix' => 'ressources',
                    'bridge_status' => 'connected',
                ],
            ],
        ]);

        $crawl = SeoSiteCrawl::query()->create([
            'site_id' => $site->site_id,
            'base_url' => 'https://amiantix.com',
            'status' => 'completed',
            'max_pages' => 10,
            'meta_json' => [],
        ]);

        $observed = SeoSitePage::query()->create([
            'site_id' => $site->site_id,
            'normalized_url' => 'https://amiantix.com/faq',
            'url_hash' => hash('sha256', 'https://amiantix.com/faq'),
            'path' => '/faq',
            'title' => 'FAQ',
            'latest_word_count' => 280,
        ]);

        $snapshot = SeoSitePageSnapshot::query()->create([
            'site_id' => $site->site_id,
            'site_crawl_id' => $crawl->id,
            'site_page_id' => $observed->id,
            'url' => 'https://amiantix.com/faq',
            'title' => 'FAQ amiante',
            'h2_json' => ['Questions générales'],
            'content_text' => 'Contenu actuel de la FAQ sur le diagnostic amiante.',
            'word_count' => 280,
            'observed_at' => now(),
        ]);

        $observed->forceFill(['last_snapshot_id' => $snapshot->id])->save();

        $payload = app(ActionPreviewService::class)->build($site->site_id, 'faq');

        $this->assertNotNull($payload);
        $this->assertTrue($payload['can_confirm_publish']);
        $this->assertSame('Confirmer et publier', $payload['confirm_publish_button_label']);
    }
}
