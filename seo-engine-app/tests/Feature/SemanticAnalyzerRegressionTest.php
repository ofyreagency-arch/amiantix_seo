<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\SeoSearchConsoleMetric;
use App\Models\SeoSemanticLink;
use App\Models\SeoSite;
use App\Models\SeoSitePage;
use App\Models\SeoSitePageSnapshot;
use App\ObservedSite\ObservedPageEmbeddingService;
use App\ObservedSite\ObservedQueryEmbeddingService;
use App\Services\SemanticGraph\Analyzers\CannibalizationAnalyzer;
use App\Services\SemanticGraph\Analyzers\InternalLinkingAnalyzer;
use App\Services\SemanticGraph\Analyzers\QueryOpportunityAnalyzer;
use App\Services\SemanticGraph\Analyzers\SemanticNeighborAnalyzer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Ofyre\SeoEngine\Contracts\EmbeddingProvider;
use Ofyre\SeoEngine\Contracts\VectorStore;
use Tests\Support\FakeEmbeddingProvider;
use Tests\Support\InMemoryVectorStore;
use Tests\TestCase;

class SemanticAnalyzerRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('seo-engine.embeddings.internal_link_threshold', 0.80);
        config()->set('seo-engine.embeddings.cannibalization_threshold', 0.84);
        config()->set('seo-engine.embeddings.cannibalization_impression_threshold', 50);
        config()->set('seo-engine.embeddings.cannibalization_position_gap_threshold', 5.0);
        config()->set('seo-engine.embeddings.query_match_threshold', 0.78);
        config()->set('seo-engine.embeddings.query_match_wrong_page_gap', 0.06);
        config()->set('seo-engine.embeddings.query_match_wrong_page_min_score', 0.84);
        config()->set('seo-engine.embeddings.query_match_refresh_position_threshold', 12.0);
        config()->set('seo-engine.embeddings.query_match_impression_threshold', 30);
        config()->set('seo-engine.embeddings.query_match_min_score', 0.6);
        config()->set('seo-engine.embeddings.query_match_min_impressions', 5);
        config()->set('seo-engine.embeddings.query_match_create_min_score', 0.78);
        config()->set('seo-engine.embeddings.query_match_create_min_impressions', 20);
        config()->set('seo-engine.embeddings.query_match_create_max_position', 25.0);
        config()->set('seo-engine.embeddings.policy.same_cluster_bonus', 0.04);
        config()->set('seo-engine.embeddings.policy.cross_cluster_penalty', 0.03);
        config()->set('seo-engine.embeddings.policy.same_intent_bonus', 0.05);
        config()->set('seo-engine.embeddings.policy.generic_target_penalty', 0.00);
        config()->set('seo-engine.embeddings.policy.pillar_target_bonus', 0.02);
        config()->set('seo-engine.embeddings.policy.strong_target_penalty', 0.00);
        config()->set('seo-engine.embeddings.policy.strong_target_inbound_threshold', 10);
        config()->set('seo-engine.embeddings.policy.intent_families', [
            'diagnostic' => ['diagnostic', 'repérage'],
            'travaux' => ['désamiantage', 'desamiantage'],
        ]);
        config()->set('seo-engine.embeddings.policy.generic_terms', ['guide']);
        config()->set('seo-engine.embeddings.policy.pillar_terms', ['diagnostic']);

        $this->app->instance(EmbeddingProvider::class, new FakeEmbeddingProvider());
        $this->app->instance(VectorStore::class, new InMemoryVectorStore());
    }

    public function test_internal_linking_analyzer_preserves_cluster_and_pillar_signal_logic(): void
    {
        [$site, $pillar, $satellite] = $this->seedDiagnosticPair();

        app(ObservedPageEmbeddingService::class)->embedSite($site->site_id, force: true);
        app(SemanticNeighborAnalyzer::class)->analyze($site->site_id, true);

        $suggestions = app(InternalLinkingAnalyzer::class)->analyze($site->site_id);

        $this->assertNotEmpty($suggestions);
        $this->assertDatabaseHas('seo_semantic_links', [
            'site_id' => $site->site_id,
            'relation_type' => 'observed_internal_link',
            'source_id' => $satellite->id,
            'target_id' => $pillar->id,
            'reason' => 'pillar_target',
        ]);
    }

    public function test_cannibalization_analyzer_preserves_overlap_and_action_decision_logic(): void
    {
        [$site, $pillar, $satellite] = $this->seedDiagnosticPair();

        SeoSearchConsoleMetric::query()->create([
            'site_id' => $site->site_id,
            'metric_date' => now()->toDateString(),
            'window_days' => 28,
            'query' => 'diagnostic amiante paris',
            'url' => $pillar->normalized_url,
            'clicks' => 12,
            'impressions' => 240,
            'ctr' => 0.05,
            'position' => 8.5,
        ]);

        SeoSearchConsoleMetric::query()->create([
            'site_id' => $site->site_id,
            'metric_date' => now()->toDateString(),
            'window_days' => 28,
            'query' => 'diagnostic amiante paris',
            'url' => $satellite->normalized_url,
            'clicks' => 6,
            'impressions' => 110,
            'ctr' => 0.03,
            'position' => 10.1,
        ]);

        app(ObservedPageEmbeddingService::class)->embedSite($site->site_id, force: true);
        app(SemanticNeighborAnalyzer::class)->analyze($site->site_id, true);

        $risks = app(CannibalizationAnalyzer::class)->analyze($site->site_id);

        $this->assertNotEmpty($risks);
        $this->assertDatabaseHas('seo_semantic_links', [
            'site_id' => $site->site_id,
            'relation_type' => 'observed_cannibalization',
            'source_id' => $pillar->id,
            'target_id' => $satellite->id,
            'reason' => 'differentiate_angle',
        ]);
    }

    public function test_query_opportunity_analyzer_preserves_wrong_page_detection_logic(): void
    {
        [$site, $pillar, $satellite] = $this->seedDiagnosticPair();

        SeoSearchConsoleMetric::query()->create([
            'site_id' => $site->site_id,
            'metric_date' => now()->toDateString(),
            'window_days' => 28,
            'query' => 'diagnostic amiante paris',
            'url' => $satellite->normalized_url,
            'clicks' => 7,
            'impressions' => 140,
            'ctr' => 0.03,
            'position' => 11.0,
        ]);

        app(ObservedPageEmbeddingService::class)->embedSite($site->site_id, force: true);
        app(ObservedQueryEmbeddingService::class)->embedSite($site->site_id, 28, 250, true);

        $opportunities = app(QueryOpportunityAnalyzer::class)->analyze($site->site_id, true);

        $this->assertNotEmpty($opportunities);
        $this->assertDatabaseHas('seo_semantic_links', [
            'site_id' => $site->site_id,
            'relation_type' => 'observed_query_match',
            'source_id' => $pillar->id,
            'reason' => 'review_wrong_ranking_page',
        ]);
    }

    /**
     * @return array{0:SeoSite,1:SeoSitePage,2:SeoSitePage}
     */
    private function seedDiagnosticPair(): array
    {
        $site = SeoSite::query()->create([
            'site_id' => 'semantic-site',
            'name' => 'Semantic Site',
            'url' => 'https://semantic.test',
            'niche' => 'amiante',
            'locale' => 'fr',
            'preset' => 'generic',
            'api_token_hash' => hash('sha256', 'token'),
            'is_active' => true,
        ]);

        $pillar = SeoSitePage::query()->create([
            'site_id' => $site->site_id,
            'normalized_url' => 'https://semantic.test/diagnostic-amiante',
            'url_hash' => sha1('https://semantic.test/diagnostic-amiante'),
            'path' => '/diagnostic-amiante',
            'title' => 'Diagnostic amiante',
            'primary_h1' => 'Diagnostic amiante',
            'indexability_state' => 'indexable',
            'latest_word_count' => 1400,
            'internal_inlinks' => 8,
            'internal_outlinks' => 1,
            'authority_score' => 0.82,
            'orphan_score' => 0.00,
            'pillar_likelihood' => 0.85,
            'cluster_label' => 'diagnostic',
            'discovered_at' => now(),
            'last_seen_at' => now(),
        ]);

        $satellite = SeoSitePage::query()->create([
            'site_id' => $site->site_id,
            'normalized_url' => 'https://semantic.test/reperage-amiante-paris',
            'url_hash' => sha1('https://semantic.test/reperage-amiante-paris'),
            'path' => '/reperage-amiante-paris',
            'title' => 'Repérage amiante Paris',
            'primary_h1' => 'Repérage amiante Paris',
            'indexability_state' => 'indexable',
            'latest_word_count' => 720,
            'internal_inlinks' => 0,
            'internal_outlinks' => 0,
            'authority_score' => 0.10,
            'orphan_score' => 1.00,
            'pillar_likelihood' => 0.22,
            'cluster_label' => 'diagnostic',
            'discovered_at' => now(),
            'last_seen_at' => now(),
        ]);

        $pillarSnapshot = SeoSitePageSnapshot::query()->create([
            'site_id' => $site->site_id,
            'site_crawl_id' => 1,
            'site_page_id' => $pillar->id,
            'url' => $pillar->normalized_url,
            'title' => $pillar->title,
            'meta_description' => 'Guide complet du diagnostic amiante',
            'canonical_url' => $pillar->normalized_url,
            'h1_json' => ['Diagnostic amiante'],
            'h2_json' => ['Obligations', 'Repérage amiante'],
            'h3_json' => ['Avant travaux'],
            'content_text' => 'Diagnostic amiante repérage avant travaux et vente.',
            'content_html' => '<h2>Obligations</h2>',
            'robots_meta' => 'index,follow',
            'status_code' => 200,
            'is_indexable' => true,
            'word_count' => 1400,
            'internal_links_count' => 1,
            'outlinks_count' => 0,
            'schema_count' => 1,
            'content_hash' => sha1('pillar'),
            'observed_at' => now(),
        ]);

        $satelliteSnapshot = SeoSitePageSnapshot::query()->create([
            'site_id' => $site->site_id,
            'site_crawl_id' => 1,
            'site_page_id' => $satellite->id,
            'url' => $satellite->normalized_url,
            'title' => $satellite->title,
            'meta_description' => 'Repérage amiante à Paris',
            'canonical_url' => $satellite->normalized_url,
            'h1_json' => ['Repérage amiante Paris'],
            'h2_json' => ['Diagnostic amiante à Paris'],
            'h3_json' => ['Coût du repérage'],
            'content_text' => 'Repérage amiante paris diagnostic amiante avant travaux.',
            'content_html' => '<h2>Diagnostic amiante à Paris</h2>',
            'robots_meta' => 'index,follow',
            'status_code' => 200,
            'is_indexable' => true,
            'word_count' => 720,
            'internal_links_count' => 0,
            'outlinks_count' => 0,
            'schema_count' => 0,
            'content_hash' => sha1('satellite'),
            'observed_at' => now(),
        ]);

        $pillar->update(['last_snapshot_id' => $pillarSnapshot->id]);
        $satellite->update(['last_snapshot_id' => $satelliteSnapshot->id]);

        return [$site, $pillar, $satellite];
    }
}
