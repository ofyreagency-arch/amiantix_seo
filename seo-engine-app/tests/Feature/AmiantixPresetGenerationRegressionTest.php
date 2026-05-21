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

        $this->assertNotEmpty($blueprint['risk_rows']);
        $this->assertNotEmpty($blueprint['obligations']);
        $this->assertNotEmpty($blueprint['cases']);
        $this->assertNotEmpty($blueprint['inspection_focus']);
        $this->assertNotEmpty($blueprint['evidence_examples']);
        $this->assertContains('Tableau de priorisation des risques', $blueprint['editorial_sections']);
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

        $this->assertStringContainsString('Tableau de priorisation des risques', $content);
        $this->assertStringContainsString('Documents et preuves a conserver', $content);
        $this->assertStringContainsString('Matrice de controle documentaire et terrain', $content);
        $this->assertStringContainsString('<table>', $content);
        $this->assertGreaterThanOrEqual(5, count($payload['faq']));
        $this->assertGreaterThanOrEqual(1350, str_word_count(strtolower(strip_tags($content))));
    }

    public function test_amiantix_depth_enrichment_injects_missing_structure_into_thin_content(): void
    {
        $provider = app(AmiantixContentProfile::class);
        $blueprint = app(AmiantixBlueprintProvider::class)->resolve('diagnostic amiante Paris', 'diagnostics');

        $content = $provider->ensureContentDepth('<h2>Contexte et obligations</h2><p>Texte tres court.</p>', $blueprint);

        $this->assertStringContainsString('Tableau de priorisation des risques', $content);
        $this->assertStringContainsString('Questions terrain qui reviennent souvent', $content);
        $this->assertStringContainsString('Passer du constat a une intervention maitrisée', $content);
        $this->assertGreaterThanOrEqual(1350, str_word_count(strtolower(strip_tags($content))));
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
        $this->assertStringContainsString('1400 mots minimum', $prompt);
    }
}
