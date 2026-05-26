<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\SeoPage;
use App\Models\SeoSearchConsoleMetric;
use App\Models\SeoSite;
use App\Models\User;
use App\Models\UserAccessToken;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ClientWorkspacePublicationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_publications_endpoint_tolerates_missing_live_publication_columns(): void
    {
        $user = User::factory()->create();
        $rawToken = 'frontend-publications-token';

        UserAccessToken::query()->create([
            'user_id' => $user->id,
            'name' => 'frontend',
            'token_hash' => hash('sha256', $rawToken),
        ]);

        $site = SeoSite::query()->create([
            'site_id' => 'amiantix',
            'name' => 'Amiantix',
            'url' => 'https://amiantix.com',
            'niche' => 'amiante',
            'locale' => 'fr',
            'preset' => 'amiantix',
            'api_token_hash' => hash('sha256', 'site-token'),
            'is_active' => true,
        ]);

        $user->seoSites()->attach($site->id, ['role' => 'owner']);

        SeoPage::query()->create([
            'site_id' => $site->site_id,
            'keyword' => 'diagnostic amiante paris',
            'slug' => 'diagnostic-amiante-paris',
            'title' => 'Diagnostic amiante Paris',
            'status' => 'published',
            'published_at' => now()->subHour(),
        ]);

        Schema::table('seo_pages', function (Blueprint $table): void {
            if (Schema::hasColumn('seo_pages', 'last_observed_at')) {
                $table->dropColumn('last_observed_at');
            }

            if (Schema::hasColumn('seo_pages', 'live_url')) {
                $table->dropColumn('live_url');
            }

            if (Schema::hasColumn('seo_pages', 'published_live_at')) {
                $table->dropColumn('published_live_at');
            }

            if (Schema::hasColumn('seo_pages', 'published_live')) {
                $table->dropColumn('published_live');
            }
        });

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$rawToken)
            ->getJson('/api/client/publications');

        $response->assertOk();
        $response->assertJsonPath('stats.engine_published', 1);
        $response->assertJsonPath('stats.live_published', 0);
        $response->assertJsonPath('stats.with_live_url', 0);
        $response->assertJsonPath('items.0.slug', 'diagnostic-amiante-paris');
        $response->assertJsonPath('items.0.published_live', false);
        $response->assertJsonPath('items.0.live_url', null);
    }

    public function test_publications_endpoint_uses_latest_available_analytics_metrics_for_a_page(): void
    {
        $user = User::factory()->create();
        $rawToken = 'frontend-publications-gsc-token';

        UserAccessToken::query()->create([
            'user_id' => $user->id,
            'name' => 'frontend',
            'token_hash' => hash('sha256', $rawToken),
        ]);

        $site = SeoSite::query()->create([
            'site_id' => 'amiantix',
            'name' => 'Amiantix',
            'url' => 'https://amiantix.com',
            'niche' => 'amiante',
            'locale' => 'fr',
            'preset' => 'amiantix',
            'api_token_hash' => hash('sha256', 'site-token'),
            'is_active' => true,
        ]);

        $user->seoSites()->attach($site->id, ['role' => 'owner']);

        $page = SeoPage::query()->create([
            'site_id' => $site->site_id,
            'keyword' => 'diagnostic amiante paris',
            'slug' => 'diagnostic-amiante-paris',
            'title' => 'Diagnostic amiante Paris',
            'status' => 'published',
            'published_at' => now()->subHour(),
            'published_live' => true,
            'live_url' => 'https://amiantix.com/diagnostic-amiante-paris',
        ]);

        SeoSearchConsoleMetric::query()->create([
            'site_id' => $site->site_id,
            'seo_page_id' => $page->id,
            'metric_date' => '2026-05-25',
            'window_days' => 28,
            'query' => null,
            'url' => 'https://amiantix.com/diagnostic-amiante-paris',
            'clicks' => 3,
            'impressions' => 12,
            'ctr' => 0.25,
            'position' => 8.7,
            'payload_json' => [
                'analytics' => [
                    'url' => 'https://amiantix.com/diagnostic-amiante-paris',
                ],
            ],
        ]);

        SeoSearchConsoleMetric::query()->create([
            'site_id' => $site->site_id,
            'seo_page_id' => $page->id,
            'metric_date' => '2026-05-26',
            'window_days' => 28,
            'query' => null,
            'url' => 'https://amiantix.com/diagnostic-amiante-paris',
            'clicks' => 0,
            'impressions' => 0,
            'ctr' => 0.0,
            'position' => 0.0,
            'payload_json' => [
                'inspection' => [
                    'inspectionResult' => [
                        'indexStatusResult' => [
                            'verdict' => 'PASS',
                        ],
                    ],
                ],
            ],
        ]);

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$rawToken)
            ->getJson('/api/client/publications');

        $response->assertOk();
        $response->assertJsonPath('items.0.gsc_metrics.impressions', 12);
        $response->assertJsonPath('items.0.gsc_metrics.clicks', 3);
        $response->assertJsonPath('items.0.gsc_metrics.ctr', 25);
        $response->assertJsonPath('items.0.gsc_metrics.position', 8.7);
    }
}
