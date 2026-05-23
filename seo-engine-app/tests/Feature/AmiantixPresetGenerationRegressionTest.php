<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\SeoPresets\Amiantix\AmiantixBlueprintProvider;
use App\SeoPresets\Amiantix\AmiantixContentProfile;
use App\SeoPresets\Amiantix\AmiantixPromptProfile;
use App\Services\Preset\BlockSelectionStrategy;
use Ofyre\SeoEngine\Services\Composition\NarrativeAssembler;
use Tests\TestCase;

class AmiantixPresetGenerationRegressionTest extends TestCase
{
    public function test_amiantix_blueprint_exposes_expert_generation_inputs(): void
    {
        $blueprint = app(AmiantixBlueprintProvider::class)->resolve('diagnostic amiante Paris', 'diagnostics');

        $this->assertSame('decision_guide', $blueprint['archetype']);
        $this->assertIsArray($blueprint['composition']);
        $this->assertNotEmpty($blueprint['risk_rows']);
        $this->assertNotEmpty($blueprint['obligations']);
        $this->assertNotEmpty($blueprint['cases']);
        $this->assertNotEmpty($blueprint['inspection_focus']);
        $this->assertNotEmpty($blueprint['evidence_examples']);
        $this->assertContains('Tableau de priorisation des risques', $blueprint['editorial_sections']);
        $this->assertArrayHasKey('required_blocks', $blueprint['composition']);
        $this->assertArrayHasKey('optional_blocks', $blueprint['composition']);
        $this->assertArrayHasKey('opening_block', $blueprint['composition']);
    }

    public function test_amiantix_fallback_payload_contains_structural_blocks_and_depth(): void
    {
        $provider = app(AmiantixContentProfile::class);
        $blueprint = app(AmiantixBlueprintProvider::class)->resolve('diagnostic amiante Paris', 'diagnostics');

        $payload = $provider->fallbackPayload('diagnostic amiante Paris', 'diagnostics', $blueprint, [
            'internal_links' => [
                ['label' => 'Guide repérage avant travaux', 'url' => '/reperage-avant-travaux', 'reason' => 'Renforcer le cadrage documentaire'],
            ],
        ]);

        $content = (string) $payload['content'];

        $this->assertStringContainsString('Contexte et obligations', $content);
        $this->assertStringContainsString('Tableau de priorisation des risques', $content);
        $this->assertStringContainsString('Documents et preuves a conserver', $content);
        $this->assertStringContainsString('Couts, delais et arbitrages chantier', $content);
        $this->assertStringContainsString('Questions terrain qui reviennent souvent', $content);
        $this->assertStringContainsString('Passer du constat a une intervention maitrisée', $content);
        $this->assertStringContainsString('<table>', $content);
        $this->assertStringContainsString('<ul>', $content);
        $this->assertStringContainsString('Ce socle posé, l article peut montrer où le risque se manifeste vraiment sur le terrain.', $content);
        $this->assertGreaterThanOrEqual(5, count($payload['faq']));
        $this->assertGreaterThanOrEqual(1450, str_word_count(strtolower(strip_tags($content))));
    }

    public function test_amiantix_depth_enrichment_injects_missing_structure_into_thin_content(): void
    {
        $provider = app(AmiantixContentProfile::class);
        $blueprint = app(AmiantixBlueprintProvider::class)->resolve('diagnostic amiante Paris', 'diagnostics');

        $content = $provider->ensureContentDepth('<h2>Contexte et obligations</h2><p>Texte tres court.</p>', $blueprint);

        $this->assertStringContainsString('Tableau de priorisation des risques', $content);
        $this->assertStringContainsString('Documents et preuves a conserver', $content);
        $this->assertStringContainsString('Couts, delais et arbitrages chantier', $content);
        $this->assertStringContainsString('Questions terrain qui reviennent souvent', $content);
        $this->assertStringContainsString('Passer du constat a une intervention maitrisée', $content);
        $this->assertGreaterThanOrEqual(1450, str_word_count(strtolower(strip_tags($content))));
    }

    public function test_amiantix_depth_enrichment_does_not_duplicate_existing_ai_headings_with_different_casing(): void
    {
        $provider = app(AmiantixContentProfile::class);
        $blueprint = app(AmiantixBlueprintProvider::class)->resolve('risque amiante appel offre', 'reglementation');

        $content = $provider->ensureContentDepth(
            '<h2>Contexte et Obligations</h2><p>Texte AI.</p>'
            .'<h2>Tableau de Priorisation des Risques</h2><p>Bloc AI.</p>'
            .'<h2>Documents et Preuves à Conserver</h2><p>Bloc AI.</p>',
            $blueprint
        );

        $this->assertSame(1, substr_count($content, 'Contexte et Obligations'));
        $this->assertSame(1, substr_count($content, 'Tableau de Priorisation des Risques'));
        $this->assertSame(1, substr_count($content, 'Documents et Preuves à Conserver'));
        $this->assertStringContainsString('Matrice de controle documentaire et terrain', $content);
    }

    public function test_amiantix_depth_enrichment_recognizes_headings_despite_apostrophes_and_punctuation(): void
    {
        $provider = app(AmiantixContentProfile::class);
        $blueprint = app(AmiantixBlueprintProvider::class)->resolve('appel offre amiante', 'reglementation');

        $content = $provider->ensureContentDepth(
            '<h2>Processus d\'Intervention et Coordination</h2><p>Bloc AI.</p>'
            .'<h2>Points de Vigilance pour le Donneur d\'Ordre</h2><p>Bloc AI.</p>'
            .'<h2>Coûts, Délais et Arbitrages Chantier</h2><p>Bloc AI.</p>',
            $blueprint
        );

        $this->assertSame(1, substr_count($content, "Processus d'Intervention et Coordination"));
        $this->assertSame(1, substr_count($content, "Points de Vigilance pour le Donneur d'Ordre"));
        $this->assertSame(1, substr_count($content, 'Coûts, Délais et Arbitrages Chantier'));
    }

    public function test_amiantix_generation_prompt_demands_expert_structure(): void
    {
        $blueprint = app(AmiantixBlueprintProvider::class)->resolve('diagnostic amiante Paris', 'diagnostics');
        $prompt = app(AmiantixPromptProfile::class)->generationPrompt(
            'diagnostic amiante Paris',
            'diagnostics',
            $blueprint,
            $blueprint['editorial_sections'],
            app(AmiantixBlueprintProvider::class)->expectedSignals($blueprint),
        );

        $this->assertStringContainsString('tableau riche', $prompt);
        $this->assertStringContainsString('Cas pratiques a couvrir', $prompt);
        $this->assertStringContainsString('Pieces et preuves a citer', $prompt);
        $this->assertStringContainsString('coordination SPS, MOA/MOE', $prompt);
        $this->assertStringContainsString('checklists, des points de vigilance', $prompt);
        $this->assertStringContainsString('1400 mots minimum', $prompt);
        $this->assertStringContainsString('Archetype editorial', $prompt);
        $this->assertStringContainsString('Plan de composition', $prompt);
    }

    public function test_amiantix_blueprint_varies_structure_for_appel_offre_keywords(): void
    {
        $provider = app(AmiantixContentProfile::class);
        $blueprint = app(AmiantixBlueprintProvider::class)->resolve("gestion du risque amiante appel d offre", 'reglementation');

        $payload = $provider->fallbackPayload("gestion du risque amiante appel d offre", 'reglementation', $blueprint);
        $content = (string) $payload['content'];

        $this->assertSame('appel_offre', $blueprint['family']);
        $this->assertSame('consultation_checklist', $blueprint['archetype']);
        $this->assertSame('control_matrix', $blueprint['composition']['table_mode'] ?? null);
        $this->assertContains('Documents et preuves a conserver', $blueprint['editorial_sections']);
        $this->assertContains('Points de vigilance pour le donneur d ordre', $blueprint['editorial_sections']);
        $this->assertNotContains('Tableau de priorisation des risques', $blueprint['editorial_sections']);
        $this->assertStringContainsString('appel d offre', $content);
        $this->assertStringContainsString('DCE', $content);
        $this->assertStringContainsString('Matrice de controle documentaire et terrain', $content);
        $this->assertStringNotContainsString('Tableau de priorisation des risques', $content);
        $this->assertStringNotContainsString('Une renovation en copropriete demarre', $content);
        $this->assertStringNotContainsString('Scenario copropriete ou site occupe', $content);
    }

    public function test_appel_offre_enrichment_prioritizes_relevant_optional_blocks_over_transverse_noise(): void
    {
        $strategy = app(BlockSelectionStrategy::class);
        $blueprint = app(AmiantixBlueprintProvider::class)->resolve("gestion du risque amiante appel d offre", 'reglementation');

        $catalog = [];

        foreach (array_merge(
            [$blueprint['composition']['opening_block'] ?? null],
            $blueprint['composition']['required_blocks'] ?? [],
            $blueprint['composition']['optional_blocks'] ?? [],
        ) as $heading) {
            if (is_string($heading) && $heading !== '') {
                $catalog[$heading] = '<section><h2>'.$heading.'</h2><p>'.$heading.' content.</p></section>';
            }
        }

        $content = implode('', [
            '<section><h2>Contexte et obligations</h2><p>Appel d offre amiante, DCE, consultation, variantes, lots, perimetre documentaire et hypothese de travaux.</p></section>',
            '<section><h2>Documents et preuves a conserver</h2><p>DCE, consultation, clarifications, plans, lotissement, hypotheses de travaux, variantes et traces de diffusion.</p></section>',
            '<section><h2>Points de vigilance pour le donneur d ordre</h2><p>Le donneur d ordre arbitre le DCE, la consultation, les hypotheses de travaux et le cadrage documentaire.</p></section>',
            '<section><h2>Couts, delais et arbitrages chantier</h2><p>Le DCE, les lots, les variantes et la consultation structurent les delais, les arbitrages et le budget.</p></section>',
            '<section><h2>Matrice de controle documentaire et terrain</h2><p>Controle du DCE, des lots, des clarifications, de la consultation et des hypotheses de travaux.</p></section>',
            '<section><h2>Ressources et pages utiles a croiser</h2><p>Liens vers consultation, DCE, lotissement, coordination et cadrage documentaire.</p></section>',
            '<section><h2>Passer du constat a une intervention maitrisée</h2><p>Le parcours editorial reste centre sur l appel d offre, le DCE, la consultation et le perimetre documentaire.</p></section>',
            '<section><p>'.str_repeat('appel d offre dce consultation lots variantes perimetre documentaire hypotheses de travaux coordination ', 140).'</p></section>',
        ]);

        $headings = $strategy->enrichmentHeadings($blueprint, $catalog, $content);

        $this->assertLessThanOrEqual(2, count($headings));
        $this->assertNotContains('Copropriete, ERP et site occupe : ce qui change vraiment', $headings);
        $this->assertNotContains('Routine documentaire et trace utile', $headings);
    }

    public function test_appel_offre_enrichment_detects_documentary_coverage_without_exact_heading_match(): void
    {
        $strategy = app(BlockSelectionStrategy::class);
        $blueprint = app(AmiantixBlueprintProvider::class)->resolve("gestion du risque amiante appel d offre", 'reglementation');

        $catalog = [];

        foreach (array_merge(
            [$blueprint['composition']['opening_block'] ?? null],
            $blueprint['composition']['required_blocks'] ?? [],
            $blueprint['composition']['optional_blocks'] ?? [],
        ) as $heading) {
            if (is_string($heading) && $heading !== '') {
                $catalog[$heading] = '<section><h2>'.$heading.'</h2><p>'.$heading.' content.</p></section>';
            }
        }

        $content = implode('', [
            '<section><h2>Contexte et obligations</h2><p>Appel d offre amiante, DCE, consultation et hypotheses de travaux.</p></section>',
            '<section><h2>Documents et preuves a conserver</h2><p>Le contenu suit deja les versions documentaires, les traces de diffusion, les clarifications et les hypotheses de travaux avant attribution.</p></section>',
            '<section><h2>Points de vigilance pour le donneur d ordre</h2><p>Le donneur d ordre arbitre les clarifications de consultation et la diffusion des pieces.</p></section>',
            '<section><p>'.str_repeat('dce consultation clarifications traces de diffusion hypotheses de travaux documents versions attribution ', 120).'</p></section>',
        ]);

        $headings = $strategy->enrichmentHeadings($blueprint, $catalog, $content);

        $this->assertNotContains('Routine documentaire et trace utile', $headings);
    }

    public function test_appel_offre_enrichment_respects_narrative_flow_order(): void
    {
        $strategy = app(BlockSelectionStrategy::class);
        $blueprint = app(AmiantixBlueprintProvider::class)->resolve("gestion du risque amiante appel d offre", 'reglementation');

        $catalog = [];

        foreach (array_merge(
            [$blueprint['composition']['opening_block'] ?? null],
            $blueprint['composition']['required_blocks'] ?? [],
            $blueprint['composition']['optional_blocks'] ?? [],
        ) as $heading) {
            if (is_string($heading) && $heading !== '') {
                $catalog[$heading] = '<section><h2>'.$heading.'</h2><p>'.$heading.' content.</p></section>';
            }
        }

        $content = implode('', [
            '<section><h2>Contexte et obligations</h2><p>Appel d offre amiante, DCE et consultation.</p></section>',
            '<section><h2>Documents et preuves a conserver</h2><p>Documents et consultation.</p></section>',
            '<section><h2>Points de vigilance pour le donneur d ordre</h2><p>Arbitrages et DCE.</p></section>',
            '<section><h2>Couts, delais et arbitrages chantier</h2><p>Delais et arbitrages.</p></section>',
            '<section><h2>Matrice de controle documentaire et terrain</h2><p>Controle documentaire.</p></section>',
            '<section><h2>Ressources et pages utiles a croiser</h2><p>Ressources utiles.</p></section>',
            '<section><h2>Passer du constat a une intervention maitrisée</h2><p>Conclusion.</p></section>',
            '<section><p>'.str_repeat('appel d offre dce consultation lots variantes coordination entreprise chantier scenario reserve attribution ', 90).'</p></section>',
        ]);

        $headings = $strategy->enrichmentHeadings($blueprint, $catalog, $content);

        $this->assertSame([
            'Repérage, SS3, SS4 et responsabilites de coordination',
            'Questions terrain qui reviennent souvent',
        ], $headings);
    }

    public function test_narrative_assembler_inserts_a_contextual_bridge_before_the_first_enrichment_block(): void
    {
        $assembler = app(NarrativeAssembler::class);
        $blueprint = app(AmiantixBlueprintProvider::class)->resolve("gestion du risque amiante appel d offre", 'reglementation');
        $catalog = [
            'Questions terrain qui reviennent souvent' => '<section><h2>Questions terrain qui reviennent souvent</h2><p>FAQ.</p></section>',
        ];

        $html = $assembler->assembleHtml(
            ['Questions terrain qui reviennent souvent'],
            $catalog,
            $blueprint,
            '<section><h2>Documents et preuves a conserver</h2><p>Versions, traces et diffusion.</p></section>'
        );

        $this->assertStringStartsWith(
            '<section><p>À ce stade, la FAQ peut traiter les hésitations qui restent sans casser le fil principal de l article.</p></section>',
            $html
        );
        $this->assertStringContainsString('<h2>Questions terrain qui reviennent souvent</h2>', $html);
    }

    public function test_narrative_assembler_skips_bridge_when_enrichment_stays_in_the_same_phase(): void
    {
        $assembler = app(NarrativeAssembler::class);
        $blueprint = app(AmiantixBlueprintProvider::class)->resolve('diagnostic amiante Paris', 'diagnostics');
        $catalog = [
            'Routine documentaire et trace utile' => '<section><h2>Routine documentaire et trace utile</h2><p>Routine.</p></section>',
        ];

        $html = $assembler->assembleHtml(
            ['Routine documentaire et trace utile'],
            $catalog,
            $blueprint,
            '<section><h2>Documents et preuves a conserver</h2><p>Pieces et preuves.</p></section>'
        );

        $this->assertStringNotContainsString('<section><p>', $html);
        $this->assertStringStartsWith('<section><h2>Routine documentaire et trace utile</h2>', $html);
    }

    public function test_narrative_assembler_skips_a_bridge_that_is_already_covered_by_the_recent_tail(): void
    {
        $assembler = app(NarrativeAssembler::class);
        $blueprint = app(AmiantixBlueprintProvider::class)->resolve("gestion du risque amiante appel d offre", 'reglementation');
        $blueprint['composition']['narrative_phase_bridges']['faq'] = 'À ce stade, la FAQ peut traiter les hésitations qui restent sans casser le fil principal de l article.';
        $catalog = [
            'Questions terrain qui reviennent souvent' => '<section><h2>Questions terrain qui reviennent souvent</h2><p>FAQ.</p></section>',
        ];

        $html = $assembler->assembleHtml(
            ['Questions terrain qui reviennent souvent'],
            $catalog,
            $blueprint,
            '<section><h2>Matrice de controle documentaire et terrain</h2><p>À ce stade, la FAQ peut traiter les hésitations qui restent sans casser le fil principal de l article, tout en gardant le raisonnement centré sur la consultation.</p></section>'
        );

        $this->assertStringNotContainsString('À ce stade, la FAQ peut traiter les hésitations qui restent sans casser le fil principal de l article.', $html);
        $this->assertStringStartsWith('<section><h2>Questions terrain qui reviennent souvent</h2>', $html);
    }

    public function test_narrative_assembler_prefers_a_heading_specific_bridge_variant_when_available(): void
    {
        $assembler = app(NarrativeAssembler::class);
        $blueprint = app(AmiantixBlueprintProvider::class)->resolve("gestion du risque amiante appel d offre", 'reglementation');
        $blueprint['composition']['narrative_phase_bridges']['faq'] = [
            'default' => 'La FAQ vient ensuite absorber les hésitations restantes sans casser la progression.',
            'by_heading' => [
                'Questions terrain qui reviennent souvent' => 'Avant de conclure, quelques questions terrain permettent de verrouiller les derniers arbitrages sans casser le fil du dossier.',
            ],
        ];
        $catalog = [
            'Questions terrain qui reviennent souvent' => '<section><h2>Questions terrain qui reviennent souvent</h2><p>FAQ.</p></section>',
        ];

        $html = $assembler->assembleHtml(
            ['Questions terrain qui reviennent souvent'],
            $catalog,
            $blueprint,
            '<section><h2>Matrice de controle documentaire et terrain</h2><p>Controle documentaire.</p></section>'
        );

        $this->assertStringStartsWith(
            '<section><p>Avant de conclure, quelques questions terrain permettent de verrouiller les derniers arbitrages sans casser le fil du dossier.</p></section>',
            $html
        );
        $this->assertStringNotContainsString('La FAQ vient ensuite absorber les hésitations restantes sans casser la progression.', $html);
    }

    public function test_narrative_assembler_uses_real_preset_bridge_variants_for_specific_optional_blocks(): void
    {
        $assembler = app(NarrativeAssembler::class);
        $blueprint = app(AmiantixBlueprintProvider::class)->resolve('diagnostic amiante Paris', 'diagnostics');
        $catalog = [
            'Routine documentaire et trace utile' => '<section><h2>Routine documentaire et trace utile</h2><p>Routine.</p></section>',
            'Couts, delais et arbitrages chantier' => '<section><h2>Couts, delais et arbitrages chantier</h2><p>Arbitrages.</p></section>',
        ];

        $proofHtml = $assembler->assembleHtml(
            ['Routine documentaire et trace utile'],
            $catalog,
            $blueprint,
            '<section><h2>Erreurs frequentes et blocages evitables</h2><p>Blocages et zones grises.</p></section>'
        );

        $arbitrageHtml = $assembler->assembleHtml(
            ['Couts, delais et arbitrages chantier'],
            $catalog,
            $blueprint,
            '<section><h2>Documents et preuves a conserver</h2><p>Pieces et traces.</p></section>'
        );

        $this->assertStringStartsWith(
            '<section><p>Une fois les points de friction nommés, la page peut montrer comment une routine documentaire simple évite que le dossier se dégrade entre deux arbitrages.</p></section>',
            $proofHtml
        );
        $this->assertStringStartsWith(
            '<section><p>Ces preuves servent ensuite de base pour parler plus franchement des délais, des coûts et des arbitrages qui suivent sur le chantier.</p></section>',
            $arbitrageHtml
        );
    }

    public function test_narrative_assembler_prefers_a_previous_phase_variant_when_the_preset_provides_one(): void
    {
        $assembler = app(NarrativeAssembler::class);
        $blueprint = app(AmiantixBlueprintProvider::class)->resolve('diagnostic amiante Paris', 'diagnostics');
        $blueprint['composition']['narrative_phase_bridges']['faq'] = [
            'default' => 'La FAQ vient ensuite absorber les hésitations restantes sans casser la progression.',
            'by_from_phase' => [
                'control' => 'Après la boucle de contrôle, la FAQ peut traiter les dernières hésitations sans faire retomber l article dans une simple liste de points.',
            ],
        ];
        $catalog = [
            'Questions terrain qui reviennent souvent' => '<section><h2>Questions terrain qui reviennent souvent</h2><p>FAQ.</p></section>',
        ];

        $html = $assembler->assembleHtml(
            ['Questions terrain qui reviennent souvent'],
            $catalog,
            $blueprint,
            '<section><h2>Matrice de controle documentaire et terrain</h2><p>Controle documentaire.</p></section>'
        );

        $this->assertStringStartsWith(
            '<section><p>Après la boucle de contrôle, la FAQ peut traiter les dernières hésitations sans faire retomber l article dans une simple liste de points.</p></section>',
            $html
        );
        $this->assertStringNotContainsString('La FAQ vient ensuite absorber les hésitations restantes sans casser la progression.', $html);
    }

    public function test_narrative_assembler_uses_real_preset_previous_phase_variants(): void
    {
        $assembler = app(NarrativeAssembler::class);
        $blueprint = app(AmiantixBlueprintProvider::class)->resolve("gestion du risque amiante appel d offre", 'reglementation');
        $catalog = [
            'Questions terrain qui reviennent souvent' => '<section><h2>Questions terrain qui reviennent souvent</h2><p>FAQ.</p></section>',
            'Ressources et pages utiles a croiser' => '<section><h2>Ressources et pages utiles a croiser</h2><p>Ressources.</p></section>',
        ];

        $faqHtml = $assembler->assembleHtml(
            ['Questions terrain qui reviennent souvent'],
            $catalog,
            $blueprint,
            '<section><h2>Matrice de controle documentaire et terrain</h2><p>Controle documentaire.</p></section>'
        );

        $resourcesHtml = $assembler->assembleHtml(
            ['Ressources et pages utiles a croiser'],
            $catalog,
            $blueprint,
            '<section><h2>Questions terrain qui reviennent souvent</h2><p>FAQ.</p></section>'
        );

        $this->assertStringStartsWith(
            '<section><p>Après ce passage de contrôle, quelques questions terrain suffisent souvent à lever les derniers doutes sans relancer toute la consultation.</p></section>',
            $faqHtml
        );
        $this->assertStringStartsWith(
            '<section><p>Une fois les dernières hésitations absorbées, quelques ressources bien ciblées permettent d approfondir sans disperser la décision.</p></section>',
            $resourcesHtml
        );
    }

    public function test_narrative_assembler_prefers_a_context_signal_variant_when_tail_matches(): void
    {
        $assembler = app(NarrativeAssembler::class);
        $blueprint = app(AmiantixBlueprintProvider::class)->resolve("gestion du risque amiante appel d offre", 'reglementation');
        $blueprint['composition']['narrative_phase_bridges']['faq'] = [
            'default' => 'Bridge générique faq.',
            'by_from_phase' => [
                'control' => 'Bridge phase control.',
            ],
            'by_context_signal' => [
                [
                    'from_phase' => 'control',
                    'heading' => 'Questions terrain qui reviennent souvent',
                    'terms' => ['dce', 'consultation'],
                    'match' => 'any',
                    'text' => 'Bridge signal consultation.',
                ],
            ],
        ];
        $catalog = [
            'Questions terrain qui reviennent souvent' => '<section><h2>Questions terrain qui reviennent souvent</h2><p>FAQ.</p></section>',
        ];

        $html = $assembler->assembleHtml(
            ['Questions terrain qui reviennent souvent'],
            $catalog,
            $blueprint,
            '<section><h2>Matrice de controle documentaire et terrain</h2><p>Controle DCE, consultation et variantes.</p></section>'
        );

        $this->assertStringStartsWith('<section><p>Bridge signal consultation.</p></section>', $html);
        $this->assertStringNotContainsString('Bridge phase control.', $html);
    }

    public function test_narrative_assembler_uses_real_preset_context_signal_variants(): void
    {
        $assembler = app(NarrativeAssembler::class);
        $appelOffreBlueprint = app(AmiantixBlueprintProvider::class)->resolve("gestion du risque amiante appel d offre", 'reglementation');
        $defaultBlueprint = app(AmiantixBlueprintProvider::class)->resolve('diagnostic amiante Paris', 'diagnostics');
        $catalog = [
            'Questions terrain qui reviennent souvent' => '<section><h2>Questions terrain qui reviennent souvent</h2><p>FAQ.</p></section>',
        ];

        $appelOffreHtml = $assembler->assembleHtml(
            ['Questions terrain qui reviennent souvent'],
            $catalog,
            $appelOffreBlueprint,
            '<section><h2>Matrice de controle documentaire et terrain</h2><p>Controle du DCE, consultation, variantes et diffusion.</p></section>'
        );

        $defaultHtml = $assembler->assembleHtml(
            ['Questions terrain qui reviennent souvent'],
            $catalog,
            $defaultBlueprint,
            '<section><h2>Matrice de controle documentaire et terrain</h2><p>Controle documentaire terrain et verification croisee.</p></section>'
        );

        $this->assertStringStartsWith(
            '<section><p>Après ce contrôle du DCE et de la consultation, quelques questions terrain suffisent souvent à verrouiller les derniers doutes sans réouvrir tout le dossier.</p></section>',
            $appelOffreHtml
        );
        $this->assertStringStartsWith(
            '<section><p>Après ce contrôle documentaire et terrain, la FAQ peut absorber les derniers doutes sans casser la progression principale.</p></section>',
            $defaultHtml
        );
    }
}
