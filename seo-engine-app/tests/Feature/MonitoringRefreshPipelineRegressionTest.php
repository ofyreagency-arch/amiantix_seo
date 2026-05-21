<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\SeoAudit;
use App\Models\SeoPage;
use App\Models\SeoSearchConsoleMetric;
use App\Models\SeoSite;
use App\Models\SeoSitePage;
use App\Models\SeoSitePageSnapshot;
use App\Models\SeoSuggestion;
use App\ObservedSite\ObservedPageHealthService;
use App\Runtime\DatabasePrioritizedPageProvider;
use App\Runtime\RuntimeSeoMonitoringService;
use App\SeoBridge\Feedback\DatabaseSeoFeedbackLoopDriver;
use App\SeoBridge\Persisters\DatabaseSeoAuditPersister;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Ofyre\SeoEngine\Contracts\ContentSignalProvider;
use Ofyre\SeoEngine\Contracts\NicheBlueprintProvider;
use Ofyre\SeoEngine\Services\Quality\SeoQualityGateService;
use Ofyre\SeoEngine\Services\Scoring\SeoIndexabilityScoringService;
use Ofyre\SeoEngine\Services\Scoring\SeoScoreRefreshService;
use Ofyre\SeoEngine\Services\Scoring\SeoScoringService;
use Ofyre\SeoEngine\Services\SearchConsole\SearchConsoleService;
use Tests\TestCase;

class MonitoringRefreshPipelineRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.url', 'https://runtime.test');
        config()->set('seo-engine.monitoring.auto_improve_threshold', 85);
        config()->set('seo-engine.quality.min_word_count', 1300);
        config()->set('seo-engine.quality.min_faq_count', 5);
        config()->set('seo-engine.quality.min_h2_count', 6);
        config()->set('seo-engine.quality.min_h3_count', 5);
        config()->set('seo-engine.quality.min_topical_score', 82);
        config()->set('seo-engine.quality.min_quality_score', 82);
        config()->set('seo-engine.quality.min_profession_specific_signals', 6);
    }

    public function test_prioritized_page_provider_prefers_published_pages_before_lower_scoring_drafts(): void
    {
        $publishedLow = SeoPage::query()->create([
            'site_id' => 'prio-site',
            'keyword' => 'a',
            'slug' => 'published-low',
            'status' => 'published',
            'seo_score' => 40,
        ]);

        $publishedHigh = SeoPage::query()->create([
            'site_id' => 'prio-site',
            'keyword' => 'b',
            'slug' => 'published-high',
            'status' => 'published',
            'seo_score' => 82,
        ]);

        $draftLowest = SeoPage::query()->create([
            'site_id' => 'prio-site',
            'keyword' => 'c',
            'slug' => 'draft-lowest',
            'status' => 'draft',
            'seo_score' => 5,
        ]);

        $draftHigher = SeoPage::query()->create([
            'site_id' => 'prio-site',
            'keyword' => 'd',
            'slug' => 'draft-higher',
            'status' => 'draft',
            'seo_score' => 10,
        ]);

        $ids = (new DatabasePrioritizedPageProvider())->prioritizedPageIds();

        $this->assertSame([
            $publishedLow->id,
            $publishedHigh->id,
            $draftLowest->id,
            $draftHigher->id,
        ], $ids);
    }

    public function test_monitoring_refreshes_pages_persists_audits_and_only_queues_real_weak_pages(): void
    {
        $strong = SeoPage::query()->create([
            'site_id' => 'monitor-site',
            'keyword' => 'diagnostic amiante paris',
            'slug' => 'diagnostic-amiante-paris',
            'status' => 'published',
            'title' => 'Diagnostic amiante Paris obligations et travaux',
            'meta_description' => 'Diagnostic amiante a Paris : obligations, reperage, delais, couts, DTA, avant travaux et conseils pour mieux preparer votre chantier en securite.',
            'content' => $this->strongContent(),
            'faq_json' => array_fill(0, 5, ['question' => 'Q', 'answer' => 'R']),
            'schema_json' => [['@type' => 'Article'], ['@type' => 'FAQPage']],
            'internal_links_json' => array_fill(0, 5, ['url' => '/lien']),
            'image_path' => 'images/amiante.png',
            'image_alt' => 'Diagnostic amiante avant travaux',
            'image_prompt' => 'Illustration amiante avant travaux',
            'image_status' => 'approved',
            'is_indexed' => true,
            'internal_inbound_count' => 3,
            'cluster_links_count' => 3,
            'seo_score' => 88,
        ]);

        $weak = SeoPage::query()->create([
            'site_id' => 'monitor-site',
            'keyword' => 'reperage amiante prix',
            'slug' => 'reperage-amiante-prix',
            'status' => 'published',
            'title' => 'Repérage amiante prix',
            'meta_description' => 'Courte meta.',
            'content' => '<h2>Prix</h2><p>reperage amiante prix</p>',
            'faq_json' => [],
            'schema_json' => [],
            'internal_links_json' => [],
            'seo_score' => 20,
        ]);

        $searchConsole = Mockery::mock(SearchConsoleService::class);
        $searchConsole->shouldReceive('pageMetrics')->once()->with(Mockery::on(fn ($page): bool => $page->id === $weak->id))->andReturn([
            'impressions' => 180,
            'ctr' => 0.009,
            'position' => 13.4,
            'queries' => ['reperage amiante prix'],
            'indexed' => false,
            'coverage' => ['index_verdict:FAIL'],
        ]);
        $searchConsole->shouldReceive('pageMetrics')->once()->with(Mockery::on(fn ($page): bool => $page->id === $strong->id))->andReturn([
            'impressions' => 220,
            'ctr' => 0.041,
            'position' => 6.2,
            'queries' => ['diagnostic amiante paris'],
            'indexed' => true,
            'coverage' => ['index_verdict:PASS'],
        ]);

        $monitor = new RuntimeSeoMonitoringService(
            $searchConsole,
            $this->scoring(),
            app(DatabaseSeoFeedbackLoopDriver::class),
            new DatabasePrioritizedPageProvider(),
            $this->scoreRefresh(),
        );

        $result = $monitor->monitor();

        $this->assertSame([
            'audited' => 2,
            'improved' => 1,
        ], $result);

        $weak->refresh();
        $strong->refresh();

        $this->assertFalse((bool) $weak->is_indexed);
        $this->assertTrue((bool) $strong->is_indexed);
        $this->assertNotNull($weak->last_audit_at);
        $this->assertNotNull($strong->last_audit_at);
        $this->assertLessThan(85, $weak->seo_score);
        $this->assertGreaterThanOrEqual(85, $strong->seo_score);

        $this->assertDatabaseCount('seo_audits', 2);
        $this->assertDatabaseCount('seo_search_console_metrics', 2);
        $this->assertDatabaseCount('seo_suggestions', 1);

        $suggestion = SeoSuggestion::query()->sole();
        $this->assertSame($weak->id, $suggestion->seo_page_id);
        $this->assertSame('feedback_loop:auto', $suggestion->source);
        $this->assertContains('not_indexed', $suggestion->suggestions_json['rationale']);
        $this->assertContains('low_ctr', $suggestion->suggestions_json['rationale']);
        $this->assertContains('page_two_position', $suggestion->suggestions_json['rationale']);

        $weakAudit = SeoAudit::query()->where('seo_page_id', $weak->id)->sole();
        $strongAudit = SeoAudit::query()->where('seo_page_id', $strong->id)->sole();

        $this->assertLessThan($strongAudit->score, $weakAudit->score);

        $weakMetric = SeoSearchConsoleMetric::query()->where('seo_page_id', $weak->id)->sole();
        $strongMetric = SeoSearchConsoleMetric::query()->where('seo_page_id', $strong->id)->sole();

        $this->assertSame(180.0, $weakMetric->impressions);
        $this->assertSame(0.009, $weakMetric->ctr);
        $this->assertSame(220.0, $strongMetric->impressions);
        $this->assertSame(0.041, $strongMetric->ctr);
    }

    public function test_monitor_page_can_persist_audit_and_metrics_without_creating_noise_when_auto_improve_is_disabled(): void
    {
        $page = SeoPage::query()->create([
            'site_id' => 'monitor-site',
            'keyword' => 'reperage amiante prix',
            'slug' => 'reperage-amiante-prix',
            'status' => 'published',
            'title' => 'Repérage amiante prix',
            'meta_description' => 'Courte meta.',
            'content' => '<h2>Prix</h2><p>reperage amiante prix</p>',
            'faq_json' => [],
            'schema_json' => [],
            'internal_links_json' => [],
            'seo_score' => 20,
        ]);

        $searchConsole = Mockery::mock(SearchConsoleService::class);
        $searchConsole->shouldReceive('pageMetrics')->once()->andReturn([
            'impressions' => 95,
            'ctr' => 0.011,
            'position' => 12.7,
            'queries' => ['reperage amiante prix'],
            'indexed' => false,
            'coverage' => ['index_verdict:FAIL'],
        ]);

        $monitor = new RuntimeSeoMonitoringService(
            $searchConsole,
            $this->scoring(),
            app(DatabaseSeoFeedbackLoopDriver::class),
            new DatabasePrioritizedPageProvider(),
            $this->scoreRefresh(),
        );

        $improved = $monitor->monitorPage($page, autoImprove: false);

        $this->assertFalse($improved);
        $this->assertDatabaseCount('seo_audits', 1);
        $this->assertDatabaseCount('seo_search_console_metrics', 1);
        $this->assertDatabaseCount('seo_suggestions', 0);
    }

    public function test_feedback_loop_clears_stale_pending_feedback_when_a_page_recovers(): void
    {
        $page = SeoPage::query()->create([
            'site_id' => 'monitor-site',
            'keyword' => 'diagnostic amiante paris',
            'slug' => 'diagnostic-amiante-paris',
            'status' => 'published',
            'title' => 'Diagnostic amiante Paris obligations et travaux',
            'meta_description' => 'Diagnostic amiante a Paris : obligations, reperage, delais, couts, DTA, avant travaux et conseils pour mieux preparer votre chantier en securite.',
            'content' => $this->strongContent(),
            'faq_json' => array_fill(0, 5, ['question' => 'Q', 'answer' => 'R']),
            'schema_json' => [['@type' => 'Article'], ['@type' => 'FAQPage']],
            'internal_links_json' => array_fill(0, 5, ['url' => '/lien']),
            'image_path' => 'images/amiante.png',
            'image_alt' => 'Diagnostic amiante avant travaux',
            'image_prompt' => 'Illustration amiante avant travaux',
            'image_status' => 'approved',
            'is_indexed' => true,
            'internal_inbound_count' => 3,
            'cluster_links_count' => 3,
            'seo_score' => 40,
        ]);

        SeoSuggestion::query()->create([
            'seo_page_id' => $page->id,
            'source' => 'feedback_loop:auto',
            'signals_json' => ['legacy' => true],
            'suggestions_json' => ['mode' => 'feedback_loop'],
            'status' => 'pending',
        ]);

        $metrics = [
            'impressions' => 210,
            'ctr' => 0.045,
            'position' => 5.9,
            'queries' => ['diagnostic amiante paris'],
            'indexed' => true,
            'coverage' => ['index_verdict:PASS'],
        ];

        $audit = [
            'score' => 40,
            'issues' => [],
            'recommendations' => [],
        ];

        $suggestion = app(DatabaseSeoFeedbackLoopDriver::class)->proposeForPage($page, $metrics, $audit);

        $this->assertNull($suggestion);
        $this->assertDatabaseMissing('seo_suggestions', [
            'seo_page_id' => $page->id,
            'source' => 'feedback_loop:auto',
            'status' => 'pending',
        ]);
    }

    public function test_refresh_aged_content_targets_only_old_published_pages(): void
    {
        $agedPublished = SeoPage::query()->create([
            'site_id' => 'monitor-site',
            'keyword' => 'page aged',
            'slug' => 'page-aged',
            'status' => 'published',
            'last_audit_at' => now()->subDays(60),
            'content' => '<p>court</p>',
        ]);

        $freshPublished = SeoPage::query()->create([
            'site_id' => 'monitor-site',
            'keyword' => 'page fresh',
            'slug' => 'page-fresh',
            'status' => 'published',
            'last_audit_at' => now()->subDays(10),
            'content' => '<p>court</p>',
        ]);

        $agedDraft = SeoPage::query()->create([
            'site_id' => 'monitor-site',
            'keyword' => 'page draft',
            'slug' => 'page-draft',
            'status' => 'draft',
            'last_audit_at' => now()->subDays(90),
            'content' => '<p>court</p>',
        ]);

        $searchConsole = Mockery::mock(SearchConsoleService::class);
        $searchConsole->shouldReceive('pageMetrics')->once()->with(Mockery::on(
            fn ($page): bool => $page->id === $agedPublished->id
        ))->andReturn([
            'impressions' => 80,
            'ctr' => 0.010,
            'position' => 14.4,
            'queries' => ['page aged'],
            'indexed' => false,
            'coverage' => ['index_verdict:FAIL'],
        ]);

        $monitor = new RuntimeSeoMonitoringService(
            $searchConsole,
            $this->scoring(),
            app(DatabaseSeoFeedbackLoopDriver::class),
            new DatabasePrioritizedPageProvider(),
            $this->scoreRefresh(),
        );

        $result = $monitor->refreshAgedContent(45);

        $this->assertSame(['refreshed' => 1], $result);
        $this->assertDatabaseCount('seo_suggestions', 1);
        $this->assertSame($agedPublished->id, SeoSuggestion::query()->sole()->seo_page_id);

        $this->assertDatabaseMissing('seo_suggestions', [
            'seo_page_id' => $freshPublished->id,
        ]);
        $this->assertDatabaseMissing('seo_suggestions', [
            'seo_page_id' => $agedDraft->id,
        ]);
    }

    public function test_observed_monitoring_summarizes_real_crawled_pages_and_prioritizes_critical_pages(): void
    {
        $site = SeoSite::query()->create([
            'site_id' => 'observed-monitor-site',
            'name' => 'Observed Monitor',
            'url' => 'https://observed.test',
            'locale' => 'fr',
            'preset' => 'generic',
            'api_token_hash' => hash('sha256', 'observed-monitor-token'),
        ]);

        $healthy = SeoSitePage::query()->create([
            'site_id' => $site->site_id,
            'normalized_url' => 'https://observed.test/guide-amiante',
            'url_hash' => sha1('https://observed.test/guide-amiante'),
            'path' => '/guide-amiante',
            'title' => 'Guide amiante complet',
            'meta_description' => 'Guide amiante, obligations, repérage, risques et travaux.',
            'canonical_url' => 'https://observed.test/guide-amiante',
            'indexability_state' => 'indexable',
            'last_status_code' => 200,
            'latest_word_count' => 1400,
            'authority_score' => 0.72,
            'orphan_score' => 0.08,
            'overlap_score' => 0.10,
            'pillar_likelihood' => 0.82,
            'cluster_label' => 'guide-amiante',
            'last_seen_at' => now()->subDay(),
        ]);

        $critical = SeoSitePage::query()->create([
            'site_id' => $site->site_id,
            'normalized_url' => 'https://observed.test/page-bloquee',
            'url_hash' => sha1('https://observed.test/page-bloquee'),
            'path' => '/page-bloquee',
            'title' => null,
            'meta_description' => null,
            'canonical_url' => null,
            'indexability_state' => 'noindex',
            'last_status_code' => 404,
            'latest_word_count' => 70,
            'authority_score' => 0.05,
            'orphan_score' => 0.91,
            'overlap_score' => 0.80,
            'pillar_likelihood' => 0.04,
            'cluster_label' => null,
            'last_seen_at' => now()->subDays(2),
        ]);

        SeoSitePageSnapshot::query()->create([
            'site_id' => $site->site_id,
            'site_crawl_id' => 1,
            'site_page_id' => $healthy->id,
            'url' => $healthy->normalized_url,
            'title' => $healthy->title,
            'meta_description' => $healthy->meta_description,
            'canonical_url' => $healthy->canonical_url,
            'status_code' => 200,
            'is_indexable' => true,
            'word_count' => 1450,
            'observed_at' => now()->subDay(),
        ]);

        SeoSitePageSnapshot::query()->create([
            'site_id' => $site->site_id,
            'site_crawl_id' => 1,
            'site_page_id' => $critical->id,
            'url' => $critical->normalized_url,
            'status_code' => 404,
            'is_indexable' => false,
            'word_count' => 70,
            'observed_at' => now()->subDays(20),
        ]);

        $searchConsole = Mockery::mock(SearchConsoleService::class);

        $monitor = new RuntimeSeoMonitoringService(
            $searchConsole,
            $this->scoring(),
            app(DatabaseSeoFeedbackLoopDriver::class),
            new DatabasePrioritizedPageProvider(),
            $this->scoreRefresh(),
            app(ObservedPageHealthService::class),
        );

        $summary = $monitor->observedSummary($site->site_id);

        $this->assertSame(2, $summary['monitored']);
        $this->assertSame(1, $summary['healthy']);
        $this->assertSame(0, $summary['warning']);
        $this->assertSame(1, $summary['critical']);
        $this->assertSame('/page-bloquee', $summary['items'][0]['path']);
        $this->assertSame('critical', $summary['items'][0]['state']);
        $this->assertContains('non_indexable', $summary['items'][0]['flags']);
        $this->assertContains('unhealthy_status', $summary['items'][0]['flags']);
        $this->assertSame('/guide-amiante', $summary['items'][1]['path']);
        $this->assertSame('healthy', $summary['items'][1]['state']);
        $this->assertSame(1450, $summary['items'][1]['snapshot_word_count']);
    }

    private function scoreRefresh(): SeoScoreRefreshService
    {
        return new SeoScoreRefreshService(
            $this->scoring(),
            new SeoIndexabilityScoringService($this->signals()),
            new SeoQualityGateService($this->blueprints(), $this->signals()),
            new DatabaseSeoAuditPersister(),
        );
    }

    private function scoring(): SeoScoringService
    {
        return new SeoScoringService($this->signals());
    }

    private function blueprints(): NicheBlueprintProvider
    {
        return new class implements NicheBlueprintProvider
        {
            public function resolve(string $keyword, ?string $cluster = null): array
            {
                return [
                    'sections' => ['Obligations', 'Repérage', 'DTA', 'Travaux', 'Prix', 'Délais', 'Processus', 'Risques'],
                    'signals' => ['diagnostic amiante', 'reperage', 'dta', 'travaux', 'chantier', 'obligations', 'processus', 'risques'],
                    'risk_terms' => ['amiante', 'reperage', 'dta', 'travaux', 'chantier', 'processus', 'obligations', 'risques'],
                ];
            }

            public function expectedEditorialSections(array $profile): array
            {
                return $profile['sections'];
            }

            public function expectedSignals(array $profile): array
            {
                return $profile['signals'];
            }
        };
    }

    private function signals(): ContentSignalProvider
    {
        return new class implements ContentSignalProvider
        {
            public function requiredContentMarkers(): array
            {
                return [
                    ['marker' => 'diagnostic', 'issue_key' => 'missing_diagnostic_marker', 'score_penalty' => 7],
                    ['marker' => 'reperage', 'issue_key' => 'missing_reperage_marker', 'score_penalty' => 9],
                ];
            }

            public function recommendationFor(string $issueKey): ?string
            {
                return match ($issueKey) {
                    'quality_content_too_short' => 'Allonger le contenu.',
                    'quality_missing_faq_depth' => 'Ajouter une FAQ.',
                    'quality_missing_heading_depth' => 'Ajouter des H2 et H3.',
                    'quality_missing_table' => 'Ajouter un tableau.',
                    'quality_missing_blueprint_sections' => 'Couvrir toutes les sections.',
                    'quality_low_profession_depth' => 'Ajouter plus de termes métier.',
                    'topical_score_below_threshold' => 'Renforcer les signaux de niche.',
                    'profession_specificity_below_threshold' => 'Ajouter plus de profondeur métier.',
                    'spam_risk_high' => 'Réécrire avec plus de précision.',
                    default => null,
                };
            }

            public function genericPhraseWarnings(): array
            {
                return [];
            }
        };
    }

    private function strongContent(): string
    {
        $sections = [
            'Obligations',
            'Repérage',
            'DTA',
            'Travaux',
            'Prix',
            'Délais',
            'Processus',
            'Risques',
        ];

        $html = '';
        foreach ($sections as $index => $section) {
            $html .= '<h2>'.$section.'</h2>';
            if ($index < 5) {
                $html .= '<h3>'.$section.' detail</h3>';
            }
        }

        $html .= '<table><tr><td>amiante</td></tr></table>';
        $html .= '<a href="/guide-dta">Guide DTA</a>';
        $html .= '<a href="/reperage-amiante">Repérage</a>';
        $html .= '<a href="/travaux-amiante">Travaux</a>';

        $body = 'diagnostic amiante reperage dta travaux chantier obligations processus risques ';
        $body .= str_repeat('amiante chantier obligations reperage dta travaux processus risques ', 170);

        return $html.'<p>'.$body.'</p>';
    }
}
