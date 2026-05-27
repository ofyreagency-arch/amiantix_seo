<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\SeoSearchConsoleMetric;
use App\Models\SeoSite;
use App\Models\SeoSiteCrawlIssue;
use App\Models\SeoSiteGoogleConnection;
use App\Models\SeoSitePage;
use App\Models\SeoSiteSnapshot;
use App\Models\User;
use App\Models\UserAccessToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientSitesDashboardMetricsTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_sites_summary_prefers_site_level_gsc_totals(): void
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
            'url' => 'https://amiantix.com',
            'niche' => 'amiante',
            'locale' => 'fr',
            'preset' => 'amiantix',
            'api_token_hash' => hash('sha256', 'site-token'),
            'is_active' => true,
        ]);

        $user->seoSites()->attach($site->id, ['role' => 'owner']);

        SeoSearchConsoleMetric::query()->create([
            'site_id' => $site->site_id,
            'metric_date' => '2026-05-25',
            'window_days' => 28,
            'query' => null,
            'url' => null,
            'clicks' => 9,
            'impressions' => 20,
            'ctr' => 0.45,
            'position' => 6.2,
            'payload_json' => ['scope' => 'site_totals'],
        ]);

        SeoSearchConsoleMetric::query()->create([
            'site_id' => $site->site_id,
            'metric_date' => '2026-05-25',
            'window_days' => 28,
            'query' => null,
            'url' => 'https://amiantix.com/page-a',
            'clicks' => 11,
            'impressions' => 36,
            'ctr' => 0.306,
            'position' => 5.8,
            'is_indexed' => false,
            'payload_json' => [],
        ]);

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$rawToken)
            ->getJson('/api/client/sites');

        $response->assertOk();
        $response->assertJsonPath('sites.0.summary.gsc_clicks', 9);
        $response->assertJsonPath('sites.0.summary.gsc_impressions', 20);
        $response->assertJsonPath('sites.0.summary.gsc_ctr', 0.45);
        $response->assertJsonPath('sites.0.summary.gsc_indexation_synced', true);
        $response->assertJsonPath('sites.0.summary.gsc_indexation_scope', 'inspected_urls');
        $response->assertJsonPath('sites.0.summary.gsc_indexation_scope_label', 'URLs inspectées via Google');
    }

    public function test_client_sites_mark_gsc_as_connected_when_property_is_configured(): void
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
            'url' => 'https://amiantix.com',
            'niche' => 'amiante',
            'locale' => 'fr',
            'preset' => 'amiantix',
            'api_token_hash' => hash('sha256', 'site-token'),
            'is_active' => true,
        ]);

        $user->seoSites()->attach($site->id, ['role' => 'owner']);

        SeoSiteGoogleConnection::query()->create([
            'site_id' => $site->site_id,
            'connection_mode' => 'oauth',
            'property_url' => 'sc-domain:amiantix.com',
            'connection_status' => 'configured',
        ]);

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$rawToken)
            ->getJson('/api/client/sites');

        $response->assertOk();
        $response->assertJsonPath('sites.0.gsc_connection_status', 'configured');
        $response->assertJsonPath('sites.0.readiness.gsc_connected', true);
    }

    public function test_client_sites_detect_when_google_indexation_state_is_available(): void
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
            'url' => 'https://amiantix.com',
            'niche' => 'amiante',
            'locale' => 'fr',
            'preset' => 'amiantix',
            'api_token_hash' => hash('sha256', 'site-token'),
            'is_active' => true,
        ]);

        $user->seoSites()->attach($site->id, ['role' => 'owner']);

        SeoSearchConsoleMetric::query()->create([
            'site_id' => $site->site_id,
            'metric_date' => '2026-05-25',
            'window_days' => 28,
            'query' => null,
            'url' => 'https://amiantix.com/page-a',
            'clicks' => 11,
            'impressions' => 36,
            'ctr' => 0.306,
            'position' => 5.8,
            'is_indexed' => false,
            'payload_json' => ['coverage_state' => 'Detected, currently not indexed'],
        ]);

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$rawToken)
            ->getJson('/api/client/sites');

        $response->assertOk();
        $response->assertJsonPath('sites.0.summary.gsc_indexation_synced', true);
        $response->assertJsonPath('sites.0.summary.gsc_indexed_pages', 0);
    }

    public function test_client_sites_collapse_google_canonical_variants_when_counting_indexed_pages(): void
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
            'url' => 'https://amiantix.com',
            'niche' => 'amiante',
            'locale' => 'fr',
            'preset' => 'amiantix',
            'api_token_hash' => hash('sha256', 'site-token'),
            'is_active' => true,
        ]);

        $user->seoSites()->attach($site->id, ['role' => 'owner']);

        SeoSearchConsoleMetric::query()->create([
            'site_id' => $site->site_id,
            'metric_date' => '2026-05-25',
            'window_days' => 28,
            'query' => null,
            'url' => 'https://www.amiantix.com/veille-reglementaire',
            'clicks' => 0,
            'impressions' => 2,
            'ctr' => 0.0,
            'position' => 5.8,
            'is_indexed' => true,
            'payload_json' => [
                'inspection' => [
                    'inspectionResult' => [
                        'indexStatusResult' => [
                            'googleCanonical' => 'https://amiantix.com/veille-reglementaire',
                        ],
                    ],
                ],
            ],
        ]);

        SeoSearchConsoleMetric::query()->create([
            'site_id' => $site->site_id,
            'metric_date' => '2026-05-25',
            'window_days' => 28,
            'query' => null,
            'url' => 'https://amiantix.com/veille-reglementaire',
            'clicks' => 0,
            'impressions' => 0,
            'ctr' => 0.0,
            'position' => 5.8,
            'is_indexed' => true,
            'payload_json' => [
                'inspection' => [
                    'inspectionResult' => [
                        'indexStatusResult' => [
                            'googleCanonical' => 'https://amiantix.com/veille-reglementaire',
                        ],
                    ],
                ],
            ],
        ]);

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$rawToken)
            ->getJson('/api/client/sites');

        $response->assertOk();
        $response->assertJsonPath('sites.0.summary.gsc_indexation_synced', true);
        $response->assertJsonPath('sites.0.summary.gsc_indexed_pages', 1);
    }

    public function test_client_sites_summary_ignores_latest_zero_inspection_rows_when_building_page_movements(): void
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
            'url' => 'https://amiantix.com',
            'niche' => 'amiante',
            'locale' => 'fr',
            'preset' => 'amiantix',
            'api_token_hash' => hash('sha256', 'site-token'),
            'is_active' => true,
        ]);

        $user->seoSites()->attach($site->id, ['role' => 'owner']);

        SeoSearchConsoleMetric::query()->create([
            'site_id' => $site->site_id,
            'metric_date' => '2026-05-24',
            'window_days' => 28,
            'query' => null,
            'url' => 'https://amiantix.com/faq',
            'clicks' => 0,
            'impressions' => 3,
            'ctr' => 0.0,
            'position' => 10.0,
            'payload_json' => [
                'analytics' => [
                    'url' => 'https://amiantix.com/faq',
                ],
            ],
        ]);

        SeoSearchConsoleMetric::query()->create([
            'site_id' => $site->site_id,
            'metric_date' => '2026-05-25',
            'window_days' => 28,
            'query' => null,
            'url' => 'https://amiantix.com/faq',
            'clicks' => 1,
            'impressions' => 5,
            'ctr' => 0.2,
            'position' => 9.0,
            'payload_json' => [
                'analytics' => [
                    'url' => 'https://amiantix.com/faq',
                ],
            ],
        ]);

        SeoSearchConsoleMetric::query()->create([
            'site_id' => $site->site_id,
            'metric_date' => '2026-05-26',
            'window_days' => 28,
            'query' => null,
            'url' => 'https://amiantix.com/faq',
            'clicks' => 0,
            'impressions' => 0,
            'ctr' => 0.0,
            'position' => 0.0,
            'is_indexed' => false,
            'payload_json' => [
                'inspection' => [
                    'inspectionResult' => [
                        'indexStatusResult' => [
                            'coverageState' => 'Discovered - currently not indexed',
                        ],
                    ],
                ],
            ],
        ]);

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$rawToken)
            ->getJson('/api/client/sites');

        $response->assertOk();
        $response->assertJsonPath('sites.0.summary.top_rising_pages.0.slug', 'faq');
        $response->assertJsonPath('sites.0.summary.top_rising_pages.0.delta_impressions', 2);
        $response->assertJsonPath('sites.0.summary.top_falling_pages', []);
    }

    public function test_client_sites_summary_exposes_observed_page_health_signals(): void
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
            'url' => 'https://amiantix.com',
            'niche' => 'amiante',
            'locale' => 'fr',
            'preset' => 'amiantix',
            'api_token_hash' => hash('sha256', 'site-token'),
            'is_active' => true,
        ]);

        $user->seoSites()->attach($site->id, ['role' => 'owner']);

        SeoSitePage::query()->create([
            'site_id' => $site->site_id,
            'normalized_url' => 'https://amiantix.com/faq',
            'url_hash' => hash('sha256', 'https://amiantix.com/faq'),
            'path' => '/faq',
            'title' => 'Faq',
            'indexability_state' => 'indexable',
            'latest_word_count' => 860,
            'internal_inlinks' => 1,
            'internal_outlinks' => 8,
            'authority_score' => 0.42,
            'orphan_score' => 0.18,
            'overlap_score' => 0.10,
            'pillar_likelihood' => 0.78,
        ]);

        SeoSitePage::query()->create([
            'site_id' => $site->site_id,
            'normalized_url' => 'https://amiantix.com/mentions-legales',
            'url_hash' => hash('sha256', 'https://amiantix.com/mentions-legales'),
            'path' => '/mentions-legales',
            'title' => 'Mentions légales',
            'indexability_state' => 'unknown',
            'latest_word_count' => 120,
            'internal_inlinks' => 0,
            'internal_outlinks' => 1,
            'authority_score' => 0.08,
            'orphan_score' => 0.92,
            'overlap_score' => 0.04,
            'pillar_likelihood' => 0.18,
        ]);

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$rawToken)
            ->getJson('/api/client/sites');

        $response->assertOk();
        $response->assertJsonPath('sites.0.summary.observed_pages', 2);
        $response->assertJsonPath('sites.0.summary.observed_weak_pages', 1);
        $response->assertJsonPath('sites.0.summary.observed_orphan_pages', 1);
        $response->assertJsonPath('sites.0.summary.observed_pillar_candidates', 1);
        $response->assertJsonPath('sites.0.summary.observed_avg_authority', 25);
        $response->assertJsonPath('sites.0.summary.observed_pillar_pages.0.slug', 'faq');
        $response->assertJsonPath('sites.0.summary.observed_link_gap_pages.0.slug', 'faq');
        $response->assertJsonPath('sites.0.summary.observed_orphan_alerts.0.slug', 'mentions-legales');
        $response->assertJsonPath('sites.0.summary.observed_weak_page_details.0.slug', 'mentions-legales');
    }

    public function test_client_sites_summary_exposes_observed_site_health_snapshot(): void
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
            'url' => 'https://amiantix.com',
            'niche' => 'amiante',
            'locale' => 'fr',
            'preset' => 'amiantix',
            'api_token_hash' => hash('sha256', 'site-token'),
            'is_active' => true,
        ]);

        $user->seoSites()->attach($site->id, ['role' => 'owner']);

        SeoSiteSnapshot::query()->create([
            'site_id' => $site->site_id,
            'health_score' => 74,
            'page_count' => 12,
            'published_count' => 8,
            'avg_seo_score' => 71,
            'avg_quality_score' => 68,
            'avg_topical_score' => 73,
            'snapshot_date' => '2026-05-26',
        ]);

        SeoSiteCrawlIssue::query()->create([
            'site_id' => $site->site_id,
            'site_crawl_id' => 1,
            'site_page_id' => null,
            'issue_type' => 'missing_title',
            'severity' => 'warning',
            'url' => 'https://amiantix.com/faq',
            'details' => 'Missing title',
            'detected_at' => now(),
        ]);

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$rawToken)
            ->getJson('/api/client/sites');

        $response->assertOk();
        $response->assertJsonPath('sites.0.summary.observed_site_health_score', 74);
        $response->assertJsonPath('sites.0.summary.observed_snapshot_date', '2026-05-26');
        $response->assertJsonPath('sites.0.summary.observed_avg_seo_score', 71);
        $response->assertJsonPath('sites.0.summary.observed_avg_quality_score', 68);
        $response->assertJsonPath('sites.0.summary.observed_avg_topical_score', 73);
        $response->assertJsonPath('sites.0.summary.observed_crawl_issues', 1);
    }
}
