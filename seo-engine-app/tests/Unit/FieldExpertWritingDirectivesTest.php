<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\SeoPresets\Shared\FieldExpertWritingDirectives;
use App\SeoPresets\SiteAware\SiteAwareBlueprintProvider;
use App\SeoPresets\SiteAware\SiteAwarePromptProfile;
use Tests\TestCase;

class FieldExpertWritingDirectivesTest extends TestCase
{
    public function test_rejects_numbered_zoom_terrain_headings(): void
    {
        $this->expectException(\RuntimeException::class);

        FieldExpertWritingDirectives::assertFieldExpertContent(
            '<h2>Zoom terrain 1</h2><p>Un paragraphe métier crédible avec amiante et repérage.</p>'
        );
    }

    public function test_rejects_prompt_bridge_leaks_and_template_sections(): void
    {
        $this->expectException(\RuntimeException::class);

        FieldExpertWritingDirectives::assertFieldExpertContent(
            '<section><p>C est dans ce passage que les erreurs deviennent utiles.</p></section>'
            .'<section><h2>Checklist operationnelle avant intervention</h2><p>Texte template.</p></section>'
            .str_repeat('<p>Sur 48 h et 12 lots, le syndic bloque encore le chantier et documente 9 m² impactés.</p>', 40)
        );
    }

    public function test_rejects_seo_meta_packaging(): void
    {
        $this->expectException(\RuntimeException::class);

        FieldExpertWritingDirectives::assertFieldExpertPayload([
            'title' => 'Fuite d eau en copropriété',
            'meta_description' => 'Découvrez notre guide sur la fuite d eau en copropriété.',
            'h1' => 'Fuite d eau en copropriété',
            'content' => $this->sampleNarrativeContent(),
            'faq' => [],
        ]);
    }

    public function test_rejects_artificial_faq_questions(): void
    {
        $this->expectException(\RuntimeException::class);

        FieldExpertWritingDirectives::assertFieldExpertFaq([
            [
                'question' => 'Pourquoi certains articles sur le sujet manquent de profondeur ?',
                'answer' => 'Parce qu ils restent trop théoriques.',
            ],
        ]);
    }

    public function test_accepts_narrative_field_payload(): void
    {
        FieldExpertWritingDirectives::assertFieldExpertPayload([
            'title' => 'Fuite chauffe-eau en copropriété : arbitrage sous 48 h',
            'meta_description' => 'Fuite chauffe-eau en copropriété : délai, périmètre, syndic et intervention plombier.',
            'h1' => 'Fuite chauffe-eau en copropriété : ce que le syndic doit trancher vite',
            'content' => $this->sampleNarrativeContent(),
            'faq' => [
                [
                    'question' => 'Faut-il couper l eau sur les 18 lots dès l alerte ?',
                    'answer' => 'Oui si la fuite menace les parties communes ; sinon phaser sur 6 h pour limiter l arrêt.',
                ],
            ],
        ]);

        $this->assertTrue(true);
    }

    public function test_site_aware_blueprint_and_prompt_push_single_voice_rules(): void
    {
        config([
            'seo-engine.site.profile' => [
                'business' => ['industry' => 'plomberie', 'summary' => 'Dépannage plomberie Lyon'],
                'services' => [['name' => 'Dépannage urgence', 'description' => 'Intervention 24h']],
                'vocabulary' => ['core_terms' => ['fuite', 'canalisation', 'chauffe-eau']],
                'geography' => ['regions' => ['Lyon']],
                'generation_directives' => ['language' => 'fr', 'site_name' => 'Plomb Express'],
            ],
        ]);

        $blueprint = app(SiteAwareBlueprintProvider::class)->resolve('fuite chauffe-eau', 'urgence');
        $prompt = app(SiteAwarePromptProfile::class)->generationCorePrompt(
            'fuite chauffe-eau',
            'urgence',
            $blueprint,
            app(SiteAwareBlueprintProvider::class)->expectedEditorialSections($blueprint),
            app(SiteAwareBlueprintProvider::class)->expectedSignals($blueprint),
        );

        $this->assertNotEmpty($blueprint['cases']);
        $this->assertStringContainsString('cadrage', strtolower(implode(' ', $blueprint['composition'])));
        $this->assertSame('plomberie', $blueprint['niche'] ?? null);
        $this->assertStringContainsString('institutionnelle', strtolower($prompt));
        $this->assertStringContainsString('interdit je', strtolower($prompt));
        $this->assertStringContainsString('pas de tableau', strtolower($prompt));
        $this->assertStringContainsString('ne pas copier ces intitulés', strtolower($prompt));
    }

    public function test_rejects_first_person_narrator_voice(): void
    {
        $this->expectException(\RuntimeException::class);

        FieldExpertWritingDirectives::assertFieldExpertContent(
            $this->sampleNarrativeContent()
                .'<p>Je me souviens d une intervention similaire où mon équipe a subi un retard de deux semaines.</p>'
        );
    }

    private function sampleNarrativeContent(): string
    {
        return '<h2>Immeuble de 18 lots : fuite au ballon collectif</h2>'
            .'<p>Le syndic appelle un dimanche soir : l eau coule depuis le local technique et 4 caves sont déjà touchées. '
            .'En 45 minutes, le plombier doit décider entre coupure générale et isolement du groupe de 300 litres.</p>'
            .'<p>Sur ce type d intervention, l erreur classique est de lancer le démontage sans photo ni repérage des vannes : '
            .'la reprise dépasse souvent 800 € quand le collectif doit être remis en service le lendemain matin.</p>'
            .'<p>Le bon arbitrage tient en trois points : protéger les parties communes, tracer la coupure sur le registre, '
            .'et annoncer un délai crédible aux 18 occupants. Le client accepte plus facilement 6 h de coupure partielle '
            .'qu un arrêt complet de 24 h mal anticipé.</p>'
            .str_repeat(
                '<p>Le technicien documente chaque étape : pression initiale à 3,2 bars, zone impactée sur 9 m², pièces changées, et heure de remise en service. '
                .'Cette trace évite les contestations quand un voisin signale encore de l humidité 72 h plus tard, surtout dans les 18 lots concernés. '
                .'Le syndic arbitre entre coupure partielle et arrêt complet selon l état des canalisations communes et le planning des entreprises.</p>',
                42,
            );
    }
}
