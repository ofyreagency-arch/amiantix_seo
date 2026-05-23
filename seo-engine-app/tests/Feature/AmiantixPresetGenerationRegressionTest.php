<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\SeoPresets\Amiantix\AmiantixBlueprintProvider;
use App\SeoPresets\Amiantix\AmiantixContentProfile;
use App\SeoPresets\Amiantix\AmiantixPromptProfile;
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
}
