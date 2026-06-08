<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\RemoteInstallation;
use App\Models\SeoPage;
use App\Models\SeoSite;
use App\Models\SeoSiteCrawl;
use App\Models\SeoSiteCrawlIssue;
use App\Models\User;
use App\Models\UserAccessToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductCoherenceStabilizationV1Test extends TestCase
{
    use RefreshDatabase;

    public function test_observed_crawl_issues_only_counts_last_successful_crawl(): void
    {
        [$token, $site] = $this->makeSite();

        $oldCrawl = SeoSiteCrawl::query()->create([
            'site_id' => $site->site_id,
            'status' => 'completed',
            'base_url' => 'https://amiantix.test',
            'max_pages' => 20,
            'discovered_url_count' => 5,
            'crawled_url_count' => 5,
            'started_at' => now()->subDays(2),
            'completed_at' => now()->subDays(2),
        ]);

        $latestCrawl = SeoSiteCrawl::query()->create([
            'site_id' => $site->site_id,
            'status' => 'completed',
            'base_url' => 'https://amiantix.test',
            'max_pages' => 20,
            'discovered_url_count' => 8,
            'crawled_url_count' => 8,
            'started_at' => now()->subHour(),
            'completed_at' => now()->subMinutes(20),
        ]);

        SeoSiteCrawlIssue::query()->create([
            'site_id' => $site->site_id,
            'site_crawl_id' => $oldCrawl->id,
            'issue_type' => 'stale_issue',
            'severity' => 'warning',
            'details' => 'Old crawl issue',
            'detected_at' => now()->subDays(2),
        ]);

        SeoSiteCrawlIssue::query()->create([
            'site_id' => $site->site_id,
            'site_crawl_id' => $latestCrawl->id,
            'issue_type' => 'missing_title',
            'severity' => 'warning',
            'details' => 'Current crawl issue',
            'detected_at' => now()->subMinutes(20),
        ]);

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/client/sites/'.$site->site_id);

        $response->assertOk();
        $response->assertJsonPath('site.summary.observed_crawl_issues', 1);
        $response->assertJsonPath('site.crawl_report.produced_data.crawl_issues', 1);
    }

    public function test_stale_running_action_status_is_reconciled_on_site_read(): void
    {
        [$token, $site] = $this->makeSite([
            'automation' => [
                'actions' => [
                    'rewrite' => [
                        'state' => 'running',
                        'detail' => 'Réécriture en cours',
                        'updated_at' => now()->subMinutes(30)->toIso8601String(),
                        'error' => null,
                    ],
                ],
            ],
        ]);

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/client/sites/'.$site->site_id);

        $response->assertOk();
        $response->assertJsonPath('site.action_statuses.rewrite.state', 'completed');
        $response->assertJsonPath(
            'site.action_statuses.rewrite.detail',
            'PraeviSEO a clôturé automatiquement cette action restée en cours sans activité récente.'
        );
    }

    public function test_failed_installation_is_ignored_when_bridge_is_connected(): void
    {
        [$token, $site] = $this->makeSite([
            'publication' => [
                'bridge_status' => 'connected',
                'mode' => 'symfony_bridge',
                'webhook_url' => 'https://amiantix.test/api/publish',
                'shared_secret' => 'secret',
            ],
        ]);

        RemoteInstallation::query()->create([
            'site_id' => $site->site_id,
            'status' => RemoteInstallation::STATUS_FAILED,
            'current_step' => 'failed',
            'progress' => 0,
            'hosting_provider' => 'other',
            'connection_type' => 'ssh',
            'encrypted_credentials' => 'encrypted',
            'connection_metadata' => [],
            'logs_json' => [],
            'error_message' => 'SSH unreachable',
        ]);

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/client/sites/'.$site->site_id);

        $response->assertOk();
        $response->assertJsonPath('site.publication_bridge_status', 'connected');
        self::assertNotSame('installation_failed', $response->json('site.next_action.kind'));
        self::assertNotSame('Installation PraeviSEO à relancer', $response->json('site.next_action.label'));
    }

    public function test_publication_live_gap_is_exposed_when_engine_pages_exist_without_live_url(): void
    {
        [$token, $site] = $this->makeSite([
            'publication' => [
                'bridge_status' => 'pending',
                'mode' => 'symfony_bridge',
            ],
        ]);

        SeoPage::query()->create([
            'site_id' => $site->site_id,
            'keyword' => 'test article',
            'slug' => 'test-article',
            'status' => 'published',
            'published_at' => now(),
            'title' => 'Test article',
            'content' => '<p>Contenu</p>',
        ]);

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/client/sites/'.$site->site_id);

        $response->assertOk();
        $response->assertJsonPath('site.summary.pages_published', 1);
        $response->assertJsonPath('site.summary.pages_live', 0);
        $response->assertJsonPath('site.publication_target.live_gap', 'bridge_not_ready');
    }

    /**
     * @param  array<string,mixed>  $settings
     * @return array{0:string,1:SeoSite}
     */
    private function makeSite(array $settings = []): array
    {
        $user = User::factory()->create();
        $token = 'coherence-token';

        UserAccessToken::query()->create([
            'user_id' => $user->id,
            'name' => 'frontend',
            'token_hash' => hash('sha256', $token),
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
            'settings_json' => $settings,
        ]);

        $user->seoSites()->attach($site->id, ['role' => 'owner']);

        return [$token, $site];
    }
}
