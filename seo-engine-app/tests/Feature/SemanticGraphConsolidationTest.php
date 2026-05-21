<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\SeoRecommendation;
use App\Models\SeoSearchConsoleMetric;
use App\Models\SeoSemanticLink;
use App\Models\SeoSite;
use App\Models\SeoSitePage;
use App\Models\SeoSitePageSnapshot;
use App\Models\SeoStrategyItem;
use App\Recommendations\RecommendationEngineService;
use App\Services\SemanticGraph\SemanticGraphEngine;
use App\Understanding\SiteUnderstandingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Ofyre\SeoEngine\Contracts\EmbeddingProvider;
use Ofyre\SeoEngine\Contracts\VectorStore;
use Tests\Support\FakeEmbeddingProvider;
use Tests\Support\InMemoryVectorStore;
use Tests\TestCase;

class SemanticGraphConsolidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('seo-engine.embeddings.internal_link_threshold', 0.80);
        config()->set('seo-engine.embeddings.cannibalization_threshold', 0.84);
        config()->set('seo-engine.embeddings.query_match_threshold', 0.78);
        config()->set('seo-engine.embeddings.query_match_min_score', 0.6);
        config()->set('seo-engine.embeddings.query_match_min_impressions', 5);
        config()->set('seo-engine.embeddings.query_match_create_min_score', 0.78);
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

    public function test_semantic_graph_reuses_core_heuristics_and_builds_structured_relations(): void
    {
        $site = SeoSite::query()->create([
            'site_id' => 'client-a',
            'name' => 'Client A',
            'url' => 'https://client-a.test',
            'niche' => 'amiante',
            'locale' => 'fr',
            'preset' => 'generic',
            'api_token_hash' => hash('sha256', 'token'),
            'is_active' => true,
        ]);

        $pillar = SeoSitePage::query()->create([
            'site_id' => $site->site_id,
            'normalized_url' => 'https://client-a.test/diagnostic-amiante',
            'url_hash' => sha1('https://client-a.test/diagnostic-amiante'),
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
            'normalized_url' => 'https://client-a.test/reperage-amiante-paris',
            'url_hash' => sha1('https://client-a.test/reperage-amiante-paris'),
            'path' => '/reperage-amiante-paris',
            'title' => 'Repérage amiante Paris',
            'primary_h1' => 'Repérage amiante Paris',
            'indexability_state' => 'indexable',
            'latest_word_count' => 700,
            'internal_inlinks' => 0,
            'internal_outlinks' => 0,
            'authority_score' => 0.10,
            'orphan_score' => 1.00,
            'pillar_likelihood' => 0.25,
            'cluster_label' => 'diagnostic',
            'discovered_at' => now(),
            'last_seen_at' => now(),
        ]);

        $other = SeoSitePage::query()->create([
            'site_id' => $site->site_id,
            'normalized_url' => 'https://client-a.test/desamiantage-batiment',
            'url_hash' => sha1('https://client-a.test/desamiantage-batiment'),
            'path' => '/desamiantage-batiment',
            'title' => 'Désamiantage bâtiment',
            'primary_h1' => 'Désamiantage bâtiment',
            'indexability_state' => 'indexable',
            'latest_word_count' => 900,
            'internal_inlinks' => 2,
            'internal_outlinks' => 1,
            'authority_score' => 0.32,
            'orphan_score' => 0.20,
            'pillar_likelihood' => 0.35,
            'cluster_label' => 'travaux',
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
            'h3_json' => ['Quand faire un diagnostic'],
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
            'meta_description' => 'Repérage amiante sur Paris',
            'canonical_url' => $satellite->normalized_url,
            'h1_json' => ['Repérage amiante Paris'],
            'h2_json' => ['Diagnostic amiante à Paris'],
            'h3_json' => ['Coût du repérage'],
            'content_text' => 'Repérage amiante paris diagnostic amiante avant travaux.',
            'content_html' => '<h2>Diagnostic amiante à Paris</h2>',
            'robots_meta' => 'index,follow',
            'status_code' => 200,
            'is_indexable' => true,
            'word_count' => 700,
            'internal_links_count' => 0,
            'outlinks_count' => 0,
            'schema_count' => 0,
            'content_hash' => sha1('satellite'),
            'observed_at' => now(),
        ]);

        $otherSnapshot = SeoSitePageSnapshot::query()->create([
            'site_id' => $site->site_id,
            'site_crawl_id' => 1,
            'site_page_id' => $other->id,
            'url' => $other->normalized_url,
            'title' => $other->title,
            'meta_description' => 'Travaux de désamiantage',
            'canonical_url' => $other->normalized_url,
            'h1_json' => ['Désamiantage bâtiment'],
            'h2_json' => ['Travaux de retrait'],
            'h3_json' => ['Confinement'],
            'content_text' => 'Désamiantage et retrait d’amiante.',
            'content_html' => '<h2>Travaux de retrait</h2>',
            'robots_meta' => 'index,follow',
            'status_code' => 200,
            'is_indexable' => true,
            'word_count' => 900,
            'internal_links_count' => 1,
            'outlinks_count' => 0,
            'schema_count' => 0,
            'content_hash' => sha1('other'),
            'observed_at' => now(),
        ]);

        $pillar->update(['last_snapshot_id' => $pillarSnapshot->id]);
        $satellite->update(['last_snapshot_id' => $satelliteSnapshot->id]);
        $other->update(['last_snapshot_id' => $otherSnapshot->id]);

        SeoSearchConsoleMetric::query()->create([
            'site_id' => $site->site_id,
            'metric_date' => now()->toDateString(),
            'window_days' => 28,
            'query' => 'diagnostic amiante paris',
            'url' => $satellite->normalized_url,
            'clicks' => 8,
            'impressions' => 120,
            'ctr' => 0.03,
            'position' => 10.2,
        ]);

        /** @var SemanticGraphEngine $graph */
        $graph = app(SemanticGraphEngine::class);
        $summary = $graph->build($site->site_id, true);

        $this->assertNotEmpty($summary['semantic_neighbors'], 'semantic_neighbors is empty');
        $this->assertNotEmpty($summary['internal_link_suggestions'], 'internal_link_suggestions is empty');
        $this->assertNotEmpty($summary['query_opportunities'], 'query_opportunities is empty');
        $this->assertNotEmpty($summary['cannibalization_risks'], 'cannibalization_risks is empty');

        $this->assertDatabaseHas('seo_semantic_links', [
            'site_id' => $site->site_id,
            'relation_type' => 'semantic_similarity_same_cluster',
            'source_id' => $pillar->id,
            'target_id' => $satellite->id,
        ]);

        $this->assertDatabaseHas('seo_semantic_links', [
            'site_id' => $site->site_id,
            'relation_type' => 'pillar_target',
            'source_id' => $satellite->id,
            'target_id' => $pillar->id,
        ]);

        $this->assertDatabaseHas('seo_semantic_links', [
            'site_id' => $site->site_id,
            'relation_type' => 'observed_internal_link',
            'source_id' => $satellite->id,
            'target_id' => $pillar->id,
        ]);

        $this->assertDatabaseHas('seo_semantic_links', [
            'site_id' => $site->site_id,
            'relation_type' => 'observed_cannibalization',
            'source_id' => $pillar->id,
            'target_id' => $satellite->id,
        ]);

        $this->assertDatabaseHas('seo_semantic_links', [
            'site_id' => $site->site_id,
            'relation_type' => 'observed_query_match',
            'source_id' => $pillar->id,
            'reason' => 'review_wrong_ranking_page',
        ]);
    }

    public function test_site_understanding_and_recommendations_preserve_semantic_outputs(): void
    {
        $site = SeoSite::query()->create([
            'site_id' => 'client-b',
            'name' => 'Client B',
            'url' => 'https://client-b.test',
            'niche' => 'amiante',
            'locale' => 'fr',
            'preset' => 'generic',
            'api_token_hash' => hash('sha256', 'token'),
            'is_active' => true,
        ]);

        $page = SeoSitePage::query()->create([
            'site_id' => $site->site_id,
            'normalized_url' => 'https://client-b.test/reperage-amiante',
            'url_hash' => sha1('https://client-b.test/reperage-amiante'),
            'path' => '/reperage-amiante',
            'title' => 'Repérage amiante',
            'primary_h1' => 'Repérage amiante',
            'indexability_state' => 'indexable',
            'latest_word_count' => 180,
            'internal_inlinks' => 0,
            'internal_outlinks' => 0,
            'authority_score' => 0.10,
            'orphan_score' => 1.00,
            'pillar_likelihood' => 0.20,
            'cluster_label' => 'diagnostic',
            'discovered_at' => now(),
            'last_seen_at' => now(),
        ]);

        $snapshot = SeoSitePageSnapshot::query()->create([
            'site_id' => $site->site_id,
            'site_crawl_id' => 1,
            'site_page_id' => $page->id,
            'url' => $page->normalized_url,
            'title' => $page->title,
            'meta_description' => '',
            'canonical_url' => $page->normalized_url,
            'h1_json' => [],
            'h2_json' => ['Repérage amiante'],
            'h3_json' => [],
            'content_text' => 'Repérage amiante.',
            'content_html' => '<h2>Repérage amiante</h2>',
            'robots_meta' => 'index,follow',
            'status_code' => 200,
            'is_indexable' => true,
            'word_count' => 180,
            'internal_links_count' => 0,
            'outlinks_count' => 0,
            'schema_count' => 0,
            'content_hash' => sha1('page'),
            'observed_at' => now(),
        ]);

        $page->update(['last_snapshot_id' => $snapshot->id]);

        /** @var SiteUnderstandingService $understanding */
        $understanding = app(SiteUnderstandingService::class);
        $summary = $understanding->analyze($site, true);

        $this->assertArrayHasKey('semantic_neighbors', $summary);
        $this->assertArrayHasKey('orphan_pages', $summary);
        $this->assertArrayHasKey('weak_pages', $summary);
        $this->assertSame(1, $summary['opportunities']['orphan_pages']);
        $this->assertSame(1, $summary['opportunities']['weak_pages']);

        /** @var RecommendationEngineService $engine */
        $engine = app(RecommendationEngineService::class);
        $generated = $engine->generate($site, true);

        $this->assertNotEmpty($generated);
        $this->assertDatabaseCount('seo_recommendations', SeoRecommendation::query()->where('site_id', $site->site_id)->count());
        $this->assertGreaterThan(0, SeoStrategyItem::query()->where('site_id', $site->site_id)->count());
    }
}
