<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\SeoPresets\Shared\FieldExpertWritingDirectives;
use App\SeoPresets\SiteAware\SiteAwareBlueprintProvider;
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

    public function test_accepts_narrative_field_content(): void
    {
        FieldExpertWritingDirectives::assertFieldExpertContent(
            '<h2>Copropriété de 42 lots : diagnostic avant ravalement</h2>'
            .'<p>Le syndic découvre 72 h avant le chantier que le repérage date de 2019. '
            .'Sur 1 200 m² de façades, l\'équipe doit arbitrer entre phasage et arrêt complet.</p>'
        );

        $this->assertTrue(true);
    }

    public function test_site_aware_blueprint_includes_field_cases(): void
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

        $this->assertNotEmpty($blueprint['cases']);
        $this->assertNotEmpty($blueprint['mistakes']);
        $this->assertNotEmpty($blueprint['field_scenarios']);
        $this->assertStringContainsString('situation', strtolower(implode(' ', $blueprint['composition'])));
    }
}
