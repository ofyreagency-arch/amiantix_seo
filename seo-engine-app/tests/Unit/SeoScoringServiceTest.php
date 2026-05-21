<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Scoring\RuntimeSeoScoringService;
use Ofyre\SeoEngine\Contracts\ContentSignalProvider;
use PHPUnit\Framework\TestCase;

class SeoScoringServiceTest extends TestCase
{
    public function test_it_preserves_historical_editorial_penalties_and_marker_recommendations(): void
    {
        $service = new RuntimeSeoScoringService(new class implements ContentSignalProvider
        {
            public function requiredContentMarkers(): array
            {
                return [
                    [
                        'marker' => 'diagnostic',
                        'issue_key' => 'missing_diagnostic_marker',
                        'score_penalty' => 7,
                    ],
                    [
                        'marker' => 'reperage',
                        'issue_key' => 'missing_reperage_marker',
                        'score_penalty' => 9,
                    ],
                ];
            }

            public function recommendationFor(string $issueKey): ?string
            {
                return match ($issueKey) {
                    'content_too_short' => 'Allonger le contenu avec des sections métier plus profondes.',
                    'missing_or_short_faq' => 'Ajouter une FAQ couvrant les intentions secondaires.',
                    'weak_heading_structure' => 'Ajouter plus de H2/H3 avec une logique de cluster.',
                    'missing_reperage_marker' => 'Ajouter une vraie couverture du repérage amiante.',
                    default => null,
                };
            }

            public function genericPhraseWarnings(): array
            {
                return [];
            }
        });

        $page = (object) [
            'title' => 'Diagnostic amiante avant travaux a Paris',
            'meta_description' => str_repeat('Valeur SEO claire. ', 8),
            'content' => $this->buildContent(
                words: 600,
                headings: 5,
                links: 2,
                markers: ['diagnostic']
            ),
            'faq_json' => array_fill(0, 4, ['question' => 'Q', 'answer' => 'R']),
            'schema_json' => [],
            'image_prompt' => 'Image chantier amiante',
            'topical_score' => 85,
            'spam_risk' => 'low',
            'indexed' => true,
        ];

        $audit = $service->audit($page);

        $this->assertSame(27, $audit['score']);
        $this->assertSame([
            'content_too_short',
            'missing_or_short_faq',
            'weak_internal_linking',
            'weak_heading_structure',
            'missing_schema',
            'missing_reperage_marker',
        ], $audit['issues']);
        $this->assertContains('Allonger le contenu avec des sections métier plus profondes.', $audit['recommendations']);
        $this->assertContains('Ajouter une FAQ couvrant les intentions secondaires.', $audit['recommendations']);
        $this->assertContains('Ajouter plus de H2/H3 avec une logique de cluster.', $audit['recommendations']);
        $this->assertContains('Ajouter une vraie couverture du repérage amiante.', $audit['recommendations']);
    }

    public function test_it_preserves_search_console_and_risk_signal_weighting(): void
    {
        $service = new RuntimeSeoScoringService();

        $page = (object) [
            'title' => 'Diagnostic amiante avant travaux Paris',
            'meta_description' => 'Diagnostic amiante avant travaux, obligations, prix, delais et bonnes pratiques pour mieux convertir sur une requete deja visible en Search Console.',
            'content' => $this->buildContent(
                words: 1500,
                headings: 6,
                links: 3,
                markers: ['diagnostic', 'reperage', 'travaux']
            ),
            'faq_json' => array_fill(0, 5, ['question' => 'Q', 'answer' => 'R']),
            'schema_json' => ['@type' => 'Article'],
            'image_path' => 'images/page.png',
            'topical_score' => 70,
            'spam_risk' => 'high',
            'indexed' => true,
        ];

        $audit = $service->audit($page, [
            'ctr' => 0.011,
            'position' => 13.2,
        ]);

        $this->assertSame(40, $audit['score']);
        $this->assertSame([
            'topic_outside_expected_scope',
            'high_spam_risk',
            'low_ctr',
            'page_two_position',
        ], $audit['issues']);
        $this->assertContains('Reject or rewrite the page to stay within the expected editorial scope.', $audit['recommendations']);
        $this->assertContains('Block publication and review the editorial brief.', $audit['recommendations']);
        $this->assertContains('Test a clearer title and a more result-oriented meta description.', $audit['recommendations']);
        $this->assertContains('Strengthen semantic depth and add content matching Search Console queries.', $audit['recommendations']);
    }

    public function test_it_does_not_penalize_unpublished_pages_for_missing_indexation_state(): void
    {
        $service = new RuntimeSeoScoringService();

        $page = (object) [
            'status' => 'review',
            'published_at' => null,
            'title' => 'Danger Sante Amiante : obligations, preuves et coordination',
            'meta_description' => 'Contenu Amiantix sur le risque amiante avec situations terrain, preuves documentaires, coordination et arbitrages utiles avant intervention.',
            'content' => $this->buildContent(
                words: 1500,
                headings: 6,
                links: 3,
                markers: ['amiante', 'diagnostic', 'reperage', 'travaux']
            ),
            'faq_json' => array_fill(0, 5, ['question' => 'Q', 'answer' => 'R']),
            'schema_json' => ['@type' => 'Article'],
            'image_path' => 'images/page.png',
            'topical_score' => 100,
            'spam_risk' => 'low',
            'is_indexed' => null,
        ];

        $audit = $service->audit($page);

        $this->assertNotContains('not_indexed', $audit['issues']);
    }

    public function test_it_still_penalizes_published_pages_when_indexation_is_missing(): void
    {
        $service = new RuntimeSeoScoringService();

        $page = (object) [
            'status' => 'published',
            'published_at' => '2026-05-21 00:00:00',
            'title' => 'Danger Sante Amiante : obligations, preuves et coordination',
            'meta_description' => 'Contenu Amiantix sur le risque amiante avec situations terrain, preuves documentaires, coordination et arbitrages utiles avant intervention.',
            'content' => $this->buildContent(
                words: 1500,
                headings: 6,
                links: 3,
                markers: ['amiante', 'diagnostic', 'reperage', 'travaux']
            ),
            'faq_json' => array_fill(0, 5, ['question' => 'Q', 'answer' => 'R']),
            'schema_json' => ['@type' => 'Article'],
            'image_path' => 'images/page.png',
            'topical_score' => 100,
            'spam_risk' => 'low',
            'is_indexed' => null,
        ];

        $audit = $service->audit($page);

        $this->assertContains('not_indexed', $audit['issues']);
    }

    /**
     * @param  array<int,string>  $markers
     */
    private function buildContent(int $words, int $headings, int $links, array $markers = []): string
    {
        $headingMarkup = '';
        for ($i = 1; $i <= $headings; $i++) {
            $headingMarkup .= sprintf('<h2>Section %d</h2>', $i);
        }

        $linkMarkup = '';
        for ($i = 1; $i <= $links; $i++) {
            $linkMarkup .= sprintf('<a href="/page-%d">Lien %d</a>', $i, $i);
        }

        $body = trim(implode(' ', $markers).' '.str_repeat('chantier securite conformite ', (int) ceil($words / 3)));

        return $headingMarkup.$linkMarkup.'<p>'.$body.'</p>';
    }
}
