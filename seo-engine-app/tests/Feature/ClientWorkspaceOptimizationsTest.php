<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\SeoPage;
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
}
