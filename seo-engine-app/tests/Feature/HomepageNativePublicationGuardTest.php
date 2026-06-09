<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Copilot\ActionPreviewService;
use App\Models\SeoSite;
use App\Models\SeoSiteCrawl;
use App\Models\SeoSitePage;
use App\Models\SeoSitePageSnapshot;
use App\Services\Publication\ConfirmPreviewPublicationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class HomepageNativePublicationGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_preview_requires_manual_validation_before_publish(): void
    {
        $site = $this->makeConnectedSite();
        $this->seedHomepage($site);

        $preview = app(ActionPreviewService::class)->build($site->site_id, 'accueil');

        $this->assertNotNull($preview);
        $this->assertFalse($preview['can_confirm_publish']);
        $this->assertTrue($preview['requires_manual_validation']);
        $this->assertStringContainsString('validation humaine obligatoire', (string) $preview['confirm_publish_blocked_reason']);
    }

    public function test_confirm_preview_rejects_homepage_publication(): void
    {
        $site = $this->makeConnectedSite();
        $this->seedHomepage($site);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('validation humaine obligatoire');

        app(ConfirmPreviewPublicationService::class)->confirm($site, 'accueil');
    }

    private function makeConnectedSite(): SeoSite
    {
        return SeoSite::query()->create([
            'site_id' => 'cabinet-martin',
            'name' => 'Cabinet Martin',
            'url' => 'https://cabinet-martin.test',
            'niche' => 'plomberie',
            'locale' => 'fr',
            'preset' => 'generic',
            'api_token_hash' => hash('sha256', 'token'),
            'is_active' => true,
            'webhook_url' => 'https://cabinet-martin.test/api/praeviseo/bridge/publish',
            'settings_json' => [
                'publication' => [
                    'mode' => 'laravel_bridge',
                    'webhook_url' => 'https://cabinet-martin.test/api/praeviseo/bridge/publish',
                    'shared_secret' => 'secret',
                    'path_prefix' => 'articles',
                    'bridge_status' => 'connected',
                ],
            ],
        ]);
    }

    private function seedHomepage(SeoSite $site): void
    {
        $liveUrl = 'https://cabinet-martin.test/';

        $crawl = SeoSiteCrawl::query()->create([
            'site_id' => $site->site_id,
            'base_url' => $site->url,
            'status' => 'completed',
            'max_pages' => 5,
            'meta_json' => [],
        ]);

        $observed = SeoSitePage::query()->create([
            'site_id' => $site->site_id,
            'normalized_url' => rtrim($liveUrl, '/'),
            'url_hash' => hash('sha256', rtrim($liveUrl, '/')),
            'path' => '/',
            'title' => 'Accueil',
            'latest_word_count' => 420,
        ]);

        $snapshot = SeoSitePageSnapshot::query()->create([
            'site_id' => $site->site_id,
            'site_crawl_id' => $crawl->id,
            'site_page_id' => $observed->id,
            'url' => rtrim($liveUrl, '/'),
            'title' => 'Plombier Paris',
            'h2_json' => ['Nos engagements'],
            'content_text' => 'Cabinet de plomberie à Paris.',
            'word_count' => 420,
            'observed_at' => now(),
        ]);

        $observed->forceFill(['last_snapshot_id' => $snapshot->id])->save();
    }
}
