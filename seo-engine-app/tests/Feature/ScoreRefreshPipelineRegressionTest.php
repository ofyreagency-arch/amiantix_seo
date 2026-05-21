<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\SeoAudit;
use App\Models\SeoPage;
use App\SeoBridge\Persisters\DatabaseSeoAuditPersister;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Ofyre\SeoEngine\Contracts\ContentSignalProvider;
use Ofyre\SeoEngine\Contracts\NicheBlueprintProvider;
use Ofyre\SeoEngine\Services\Quality\SeoQualityGateService;
use Ofyre\SeoEngine\Services\Scoring\SeoIndexabilityScoringService;
use Ofyre\SeoEngine\Services\Scoring\SeoScoreRefreshService;
use Ofyre\SeoEngine\Services\Scoring\SeoScoringService;
use Tests\TestCase;

class ScoreRefreshPipelineRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('seo-engine.quality.min_word_count', 1300);
        config()->set('seo-engine.quality.min_faq_count', 5);
        config()->set('seo-engine.quality.min_h2_count', 6);
        config()->set('seo-engine.quality.min_h3_count', 5);
        config()->set('seo-engine.quality.min_topical_score', 82);
        config()->set('seo-engine.quality.min_quality_score', 82);
        config()->set('seo-engine.quality.min_profession_specific_signals', 6);
        config()->set('seo-engine.quality.image_alt_required_terms', ['amiante']);
        config()->set('seo-engine.quality.image_prompt_required_terms', ['amiante']);
    }

    public function test_score_refresh_updates_page_fields_and_persists_the_audit_with_search_console_signals(): void
    {
        $page = SeoPage::query()->create([
            'site_id' => 'refresh-site',
            'keyword' => 'diagnostic amiante paris',
            'slug' => 'diagnostic-amiante-paris',
            'cluster' => 'diagnostic',
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
        ]);
        $metrics = [
            'impressions' => 160,
            'ctr' => 0.031,
            'position' => 8.4,
            'queries' => ['diagnostic amiante paris', 'repérage amiante paris'],
            'indexed' => true,
            'coverage' => ['index_verdict:PASS'],
        ];

        $refresh = $this->makeScoreRefreshService();
        $refreshed = $refresh->refresh($page, $metrics, createAudit: true);

        $this->assertGreaterThanOrEqual(82, $refreshed->topical_score);
        $this->assertGreaterThanOrEqual(82, $refreshed->quality_score);
        $this->assertSame('low', $refreshed->spam_risk);
        $this->assertGreaterThanOrEqual(85, $refreshed->seo_score);
        $this->assertGreaterThanOrEqual(80, $refreshed->indexability_score);
        $this->assertGreaterThanOrEqual(80, $refreshed->image_quality_score);
        $this->assertNotNull($refreshed->last_audit_at);

        $audit = SeoAudit::query()->sole();

        $this->assertSame($refreshed->id, $audit->seo_page_id);
        $this->assertSame($refreshed->seo_score, $audit->score);
        $this->assertSame($metrics['position'], $audit->search_console_json['position']);
        $this->assertSame($metrics['queries'], $audit->search_console_json['queries']);
        $this->assertSame([], $audit->issues_json);
    }

    private function makeScoreRefreshService(): SeoScoreRefreshService
    {
        $signals = $this->signalProvider();
        $scoring = new SeoScoringService($signals);
        $indexability = new SeoIndexabilityScoringService($signals);
        $qualityGate = new SeoQualityGateService($this->blueprints(), $signals);

        return new SeoScoreRefreshService(
            $scoring,
            $indexability,
            $qualityGate,
            new DatabaseSeoAuditPersister(),
        );
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

    private function signalProvider(): ContentSignalProvider
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
