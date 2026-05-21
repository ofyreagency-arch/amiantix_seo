<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\SeoPage;
use App\Models\SeoRecommendation;
use App\Models\SeoSemanticLink;
use App\Models\SeoSite;
use App\Models\SeoSiteCrawl;
use App\Models\SeoSitePage;
use App\Models\SeoSuggestion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardIntelligenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_dashboard_renders_real_engine_intelligence_widgets(): void
    {
        $this->withoutVite();

        $site = SeoSite::query()->create([
            'site_id' => 'dashboard-site',
            'name' => 'Dashboard Site',
            'url' => 'https://dashboard.test',
            'niche' => 'amiante',
            'locale' => 'fr',
            'preset' => 'generic',
            'api_token_hash' => hash('sha256', 'token'),
            'is_active' => true,
        ]);

        $pageA = SeoSitePage::query()->create([
            'site_id' => $site->site_id,
            'normalized_url' => 'https://dashboard.test/diagnostic-amiante',
            'url_hash' => sha1('https://dashboard.test/diagnostic-amiante'),
            'path' => '/diagnostic-amiante',
            'title' => 'Diagnostic amiante',
            'indexability_state' => 'indexable',
            'latest_word_count' => 240,
            'internal_inlinks' => 0,
            'internal_outlinks' => 1,
            'authority_score' => 0.18,
            'orphan_score' => 0.92,
            'pillar_likelihood' => 0.78,
            'cluster_label' => 'diagnostic',
            'discovered_at' => now(),
            'last_seen_at' => now(),
        ]);

        $pageB = SeoSitePage::query()->create([
            'site_id' => $site->site_id,
            'normalized_url' => 'https://dashboard.test/diagnostic-amiante-prix',
            'url_hash' => sha1('https://dashboard.test/diagnostic-amiante-prix'),
            'path' => '/diagnostic-amiante-prix',
            'title' => 'Diagnostic amiante prix',
            'indexability_state' => 'noindex',
            'latest_word_count' => 180,
            'internal_inlinks' => 1,
            'internal_outlinks' => 0,
            'authority_score' => 0.12,
            'orphan_score' => 0.81,
            'pillar_likelihood' => 0.31,
            'cluster_label' => 'diagnostic',
            'discovered_at' => now(),
            'last_seen_at' => now(),
        ]);

        SeoPage::query()->create([
            'site_id' => $site->site_id,
            'keyword' => 'diagnostic amiante paris',
            'slug' => 'diagnostic-amiante-paris',
            'status' => 'published',
            'seo_score' => 54,
        ]);

        SeoRecommendation::query()->create([
            'site_id' => $site->site_id,
            'site_page_id' => 1,
            'type' => 'refresh_page',
            'priority' => 20,
            'estimated_impact' => 'high',
            'difficulty' => 'medium',
            'cluster' => 'diagnostic',
            'title' => 'Strengthen weak observed page',
            'reasoning' => 'Thin page.',
            'suggested_action' => 'Rewrite.',
            'status' => 'pending',
            'generated_at' => now(),
        ]);

        $page = SeoPage::query()->firstOrFail();

        SeoSuggestion::query()->create([
            'seo_page_id' => $page->id,
            'source' => 'feedback_loop:auto',
            'signals_json' => [],
            'suggestions_json' => ['rationale' => ['low_ctr']],
            'status' => 'pending',
        ]);

        SeoSuggestion::query()->create([
            'seo_page_id' => $page->id,
            'source' => 'rewrite_engine:enrich',
            'signals_json' => [],
            'suggestions_json' => ['rationale' => 'rewrite_context'],
            'status' => 'pending',
        ]);

        SeoSuggestion::query()->create([
            'seo_page_id' => $page->id,
            'source' => 'feedback_loop:auto',
            'signals_json' => [],
            'suggestions_json' => ['rationale' => ['resolved']],
            'status' => 'applied',
            'applied_at' => now(),
        ]);

        SeoSemanticLink::query()->create([
            'site_id' => $site->site_id,
            'relation_type' => 'observed_overlap',
            'source_key' => $pageA->normalized_url,
            'source_id' => $pageA->id,
            'target_key' => $pageB->normalized_url,
            'target_id' => $pageB->id,
            'label' => 'diagnostic overlap',
            'reason' => 'overlap_detection',
            'similarity_score' => 0.91,
            'meta_json' => ['same_cluster' => true],
        ]);

        SeoSemanticLink::query()->create([
            'site_id' => $site->site_id,
            'relation_type' => 'observed_query_match',
            'source_key' => $pageA->normalized_url,
            'source_id' => $pageA->id,
            'target_key' => 'observed-query:diagnostic-amiante-prix',
            'target_id' => null,
            'label' => 'diagnostic amiante prix',
            'reason' => 'refresh_existing_page',
            'similarity_score' => 0.88,
            'meta_json' => [
                'query' => 'diagnostic amiante prix',
                'impressions' => 44,
                'position' => 16.4,
                'recommended_action' => 'refresh_existing_page',
            ],
        ]);

        SeoSiteCrawl::query()->create([
            'site_id' => $site->site_id,
            'base_url' => $site->url,
            'status' => 'completed',
            'max_pages' => 80,
            'discovered_url_count' => 12,
            'crawled_url_count' => 10,
            'started_at' => now()->subMinutes(10),
            'completed_at' => now()->subMinutes(2),
        ]);

        $response = $this
            ->withSession(['admin_authenticated' => true])
            ->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('SEO Brain Runtime');
        $response->assertSee('Queues réelles du moteur');
        $response->assertSee('Sources de vérité du cockpit');
        $response->assertSee('Backlog prioritaire');
        $response->assertSee('Santé multi-sites');
        $response->assertSee('Dashboard Site');
        $response->assertSee('Strengthen weak observed page');
        $response->assertSee('Lifecycle des actions');
        $response->assertSee('Hotspots du graph');
        $response->assertSee('Query hotspots');
        $response->assertSee('Pages observées sous tension');
        $response->assertSee('diagnostic amiante prix');
    }
}
