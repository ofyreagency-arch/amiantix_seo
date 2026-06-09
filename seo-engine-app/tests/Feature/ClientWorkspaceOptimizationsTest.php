<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\SeoPage;
use App\Models\SeoRecommendation;
use App\Models\SeoSearchConsoleMetric;
use App\Models\SeoSite;
use App\Models\SeoSuggestion;
use App\Models\User;
use App\Models\UserAccessToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientWorkspaceOptimizationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_optimizations_endpoint_exposes_gsc_opportunities_for_client_sites(): void
    {
        $user = User::factory()->create();
        $rawToken = 'frontend-optimizations-token';

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
            'gsc_site_url' => 'sc-domain:amiantix.com',
            'gsc_credentials_path' => 'storage/google/service-account.json',
            'is_active' => true,
        ]);

        $user->seoSites()->attach($site->id, ['role' => 'owner']);

        $page = SeoPage::query()->create([
            'site_id' => $site->site_id,
            'keyword' => 'diagnostic amiante copropriete',
            'slug' => 'diagnostic-amiante-copropriete',
            'title' => 'Diagnostic amiante copropriete',
            'status' => 'published',
        ]);

        SeoSearchConsoleMetric::query()->create([
            'site_id' => $site->site_id,
            'seo_page_id' => $page->id,
            'metric_date' => now()->subDays(3)->toDateString(),
            'window_days' => 28,
            'query' => null,
            'url' => 'https://amiantix.com/diagnostic-amiante-copropriete',
            'clicks' => 2,
            'impressions' => 86,
            'ctr' => 0.023,
            'position' => 9.4,
            'payload_json' => [],
        ]);

        SeoSuggestion::query()->create([
            'seo_page_id' => $page->id,
            'source' => 'rewrite_engine:enrich',
            'status' => 'pending',
            'signals_json' => [
                'summary' => 'Renforcer la page sur une opportunité GSC.',
            ],
            'suggestions_json' => [
                'summary' => 'Ajouter une section plus utile sur les cas de copropriété.',
                'impact_expected' => 'Mieux convertir une visibilité déjà acquise.',
            ],
        ]);

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$rawToken)
            ->getJson('/api/client/optimizations');

        $response->assertOk();
        $response->assertJsonPath('stats.pending', 1);
        $response->assertJsonPath('gsc_opportunities.summary.near_top_10', 1);
        $response->assertJsonPath('gsc_opportunities.summary.total', 1);
        $response->assertJsonPath('gsc_opportunities.summary.ready', 1);
        $response->assertJsonPath('gsc_opportunities.items.0.type', 'near_top_10');
        $response->assertJsonPath('gsc_opportunities.items.0.site_id', 'amiantix');
        $response->assertJsonPath('gsc_opportunities.items.0.slug', 'diagnostic-amiante-copropriete');
        $response->assertJsonPath('items.0.page.slug', 'diagnostic-amiante-copropriete');
        $response->assertJsonStructure([
            'business_copilot' => [
                'headline',
                'subheadline',
                'daily_priority',
                'top_action' => [
                    'apply_workflow',
                    'apply_ready',
                    'modification_plan' => [
                        'sections',
                        'topics',
                        'faq',
                        'content_summary',
                    ],
                    'gain_basis',
                ],
            ],
        ]);
        $response->assertJsonPath('business_copilot.top_action.apply_workflow', 'rewrite');
        $response->assertJsonPath('business_copilot.daily_priority.0.rank', 1);
        $response->assertJsonPath('business_copilot.daily_priority.0.site_id', 'amiantix');
        $response->assertJsonPath('business_copilot.top_action.rank', 1);
        $this->assertStringContainsString(
            'visiteur',
            (string) $response->json('business_copilot.top_action.gain_display'),
        );
    }

    public function test_optimizations_endpoint_exposes_gsc_opportunities_without_installed_seo_pages(): void
    {
        $user = User::factory()->create();
        $rawToken = 'frontend-free-optimizations-token';

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
            'gsc_site_url' => 'sc-domain:amiantix.com',
            'gsc_credentials_path' => 'storage/google/service-account.json',
            'is_active' => true,
        ]);

        $user->seoSites()->attach($site->id, ['role' => 'owner']);

        SeoSearchConsoleMetric::query()->create([
            'site_id' => $site->site_id,
            'seo_page_id' => null,
            'metric_date' => now()->subDays(2)->toDateString(),
            'window_days' => 28,
            'query' => null,
            'url' => 'https://amiantix.com/diagnostic-amiante-copropriete',
            'clicks' => 2,
            'impressions' => 86,
            'ctr' => 0.023,
            'position' => 9.4,
            'payload_json' => [],
        ]);

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$rawToken)
            ->getJson('/api/client/optimizations');

        $response->assertOk();
        $response->assertJsonPath('gsc_opportunities.summary.near_top_10', 1);
        $response->assertJsonPath('gsc_opportunities.summary.total', 1);
        $response->assertJsonPath('gsc_opportunities.summary.ready', 1);
        $response->assertJsonPath('gsc_opportunities.items.0.type', 'near_top_10');
        $response->assertJsonPath('gsc_opportunities.items.0.slug', 'diagnostic-amiante-copropriete');
        $response->assertJsonPath('gsc_opportunities.items.0.site_id', 'amiantix');
    }

    public function test_optimizations_endpoint_falls_back_to_site_url_metrics_when_pages_exist_but_are_not_mapped(): void
    {
        $user = User::factory()->create();
        $rawToken = 'frontend-unmapped-optimizations-token';

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
            'gsc_site_url' => 'sc-domain:amiantix.com',
            'gsc_credentials_path' => 'storage/google/service-account.json',
            'is_active' => true,
        ]);

        $user->seoSites()->attach($site->id, ['role' => 'owner']);

        SeoPage::query()->create([
            'site_id' => $site->site_id,
            'keyword' => 'diagnostic amiante copropriete',
            'slug' => 'diagnostic-amiante-copropriete',
            'title' => 'Diagnostic amiante copropriete',
            'status' => 'published',
        ]);

        SeoSearchConsoleMetric::query()->create([
            'site_id' => $site->site_id,
            'seo_page_id' => null,
            'metric_date' => now()->subDays(2)->toDateString(),
            'window_days' => 28,
            'query' => null,
            'url' => 'https://amiantix.com/diagnostic-amiante-copropriete',
            'clicks' => 2,
            'impressions' => 86,
            'ctr' => 0.023,
            'position' => 9.4,
            'payload_json' => [],
        ]);

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$rawToken)
            ->getJson('/api/client/optimizations');

        $response->assertOk();
        $response->assertJsonPath('gsc_opportunities.summary.near_top_10', 1);
        $response->assertJsonPath('gsc_opportunities.summary.total', 1);
        $response->assertJsonPath('gsc_opportunities.items.0.type', 'near_top_10');
        $response->assertJsonPath('gsc_opportunities.items.0.slug', 'diagnostic-amiante-copropriete');
        $response->assertJsonPath('gsc_opportunities.items.0.page_id', null);
    }

    public function test_optimizations_endpoint_surfaces_small_volume_near_top_10_gsc_pages(): void
    {
        $user = User::factory()->create();
        $rawToken = 'frontend-small-volume-optimizations-token';

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
            'gsc_site_url' => 'sc-domain:amiantix.com',
            'gsc_credentials_path' => 'storage/google/service-account.json',
            'is_active' => true,
        ]);

        $user->seoSites()->attach($site->id, ['role' => 'owner']);

        SeoSearchConsoleMetric::query()->create([
            'site_id' => $site->site_id,
            'seo_page_id' => null,
            'metric_date' => now()->subDay()->toDateString(),
            'window_days' => 28,
            'query' => null,
            'url' => 'https://www.amiantix.com/faq',
            'clicks' => 1,
            'impressions' => 5,
            'ctr' => 0.2,
            'position' => 9.0,
            'payload_json' => [],
        ]);

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$rawToken)
            ->getJson('/api/client/optimizations');

        $response->assertOk();
        $response->assertJsonPath('gsc_opportunities.summary.near_top_10', 1);
        $response->assertJsonPath('gsc_opportunities.summary.total', 1);
        $response->assertJsonPath('gsc_opportunities.items.0.type', 'near_top_10');
        $response->assertJsonPath('gsc_opportunities.items.0.slug', 'faq');
        $response->assertJsonPath('gsc_opportunities.items.0.page_id', null);
    }

    public function test_optimizations_endpoint_uses_latest_snapshot_instead_of_summing_rolling_gsc_windows(): void
    {
        $user = User::factory()->create();
        $rawToken = 'frontend-rolling-window-optimizations-token';

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
            'gsc_site_url' => 'sc-domain:amiantix.com',
            'gsc_credentials_path' => 'storage/google/service-account.json',
            'is_active' => true,
        ]);

        $user->seoSites()->attach($site->id, ['role' => 'owner']);

        SeoSearchConsoleMetric::query()->create([
            'site_id' => $site->site_id,
            'seo_page_id' => null,
            'metric_date' => now()->subDays(2)->toDateString(),
            'window_days' => 28,
            'query' => null,
            'url' => 'https://www.amiantix.com/faq',
            'clicks' => 1,
            'impressions' => 4,
            'ctr' => 0.25,
            'position' => 9.4,
            'payload_json' => [
                'analytics' => [
                    'url' => 'https://www.amiantix.com/faq',
                ],
            ],
        ]);

        SeoSearchConsoleMetric::query()->create([
            'site_id' => $site->site_id,
            'seo_page_id' => null,
            'metric_date' => now()->subDay()->toDateString(),
            'window_days' => 28,
            'query' => null,
            'url' => 'https://www.amiantix.com/faq',
            'clicks' => 1,
            'impressions' => 5,
            'ctr' => 0.2,
            'position' => 9.0,
            'payload_json' => [
                'analytics' => [
                    'url' => 'https://www.amiantix.com/faq',
                ],
            ],
        ]);

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$rawToken)
            ->getJson('/api/client/optimizations');

        $response->assertOk();
        $response->assertJsonPath('gsc_opportunities.items.0.slug', 'faq');
        $response->assertJsonPath('gsc_opportunities.items.0.metrics.impressions', 5);
        $response->assertJsonPath('gsc_opportunities.items.0.metrics.position', 9);
    }

    public function test_optimizations_endpoint_exposes_pending_observed_recommendations(): void
    {
        $user = User::factory()->create();
        $rawToken = 'frontend-observed-recommendations-token';

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

        SeoRecommendation::query()->create([
            'site_id' => $site->site_id,
            'type' => 'refresh_page',
            'priority' => 20,
            'estimated_impact' => 'high',
            'difficulty' => 'medium',
            'cluster' => 'diagnostic-amiante',
            'title' => 'Refresh the FAQ cluster page',
            'reasoning' => 'The page already ranks but still lacks enough depth to convert the current visibility.',
            'suggested_action' => 'Expand the answer structure and strengthen supporting evidence.',
            'status' => 'pending',
            'generated_at' => now()->subHour(),
        ]);

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$rawToken)
            ->getJson('/api/client/optimizations');

        $response->assertOk();
        $response->assertJsonPath('recommendations.summary.total', 1);
        $response->assertJsonPath('recommendations.summary.high_priority', 1);
        $response->assertJsonPath('recommendations.summary.refresh', 1);
        $response->assertJsonPath('recommendations.items.0.type', 'refresh_page');
        $response->assertJsonPath('recommendations.items.0.site_id', 'amiantix');
        $response->assertJsonPath('recommendations.items.0.cluster', 'diagnostic-amiante');
        $response->assertJsonPath('recommendations.items.0.title', 'Refresh the FAQ cluster page');
    }
}
