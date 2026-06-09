<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\SeoPresets\SiteAware\NicheDistinguishabilityAnalyzer;
use App\SeoPresets\SiteAware\NicheEditorialRegistry;
use App\SeoPresets\SiteAware\SiteAwareBlueprintProvider;
use Tests\TestCase;

class NicheEditorialRegistryTest extends TestCase
{
    public function test_detects_distinct_niches(): void
    {
        $this->assertSame('amiante', NicheEditorialRegistry::detectNiche('amiante', 'diagnostic amiante avant travaux', ['ss3']));
        $this->assertSame('plomberie', NicheEditorialRegistry::detectNiche('plomberie', 'fuite chauffe-eau', ['canalisation']));
        $this->assertSame('avocat', NicheEditorialRegistry::detectNiche('droit', 'mise en demeure impayé', ['honoraires']));
        $this->assertSame('immobilier', NicheEditorialRegistry::detectNiche('immobilier', 'mandat vente maison', ['notaire']));
        $this->assertSame('recrutement', NicheEditorialRegistry::detectNiche('rh', 'processus entretien recrutement', ['candidat']));
    }

    public function test_blueprint_composition_differs_by_niche(): void
    {
        config([
            'seo-engine.site.profile' => [
                'business' => ['industry' => 'plomberie', 'summary' => 'Plombier Lyon'],
                'services' => [['name' => 'Dépannage', 'description' => 'Urgence']],
                'vocabulary' => ['core_terms' => ['fuite', 'canalisation']],
            ],
        ]);

        $plomberie = strtolower(implode(' ', app(SiteAwareBlueprintProvider::class)->resolve('fuite chauffe-eau', 'urgence')['composition'] ?? []));

        config([
            'seo-engine.site.profile' => [
                'business' => ['industry' => 'droit', 'summary' => 'Cabinet avocat'],
                'services' => [['name' => 'Contentieux', 'description' => 'Litiges']],
                'vocabulary' => ['core_terms' => ['tribunal', 'honoraires']],
            ],
        ]);

        $avocat = strtolower(implode(' ', app(SiteAwareBlueprintProvider::class)->resolve('mise en demeure', 'contentieux')['composition'] ?? []));

        $this->assertStringContainsString('fuite', $plomberie);
        $this->assertStringContainsString('juridique', $avocat);
        $this->assertNotSame($plomberie, $avocat);
    }

    public function test_distinguishability_flags_similar_fingerprints(): void
    {
        $left = NicheDistinguishabilityAnalyzer::fingerprint('plomberie', '<h2>Fuite et coupure eau</h2><p>canalisation chauffe-eau syndic plombier dégât coupure pression colonne robinet</p>', ['fuite']);
        $right = NicheDistinguishabilityAnalyzer::fingerprint('avocat', '<h2>Preuve et tribunal</h2><p>honoraires assignation prescription juridiction mise en demeure contentieux avocat</p>', ['litige']);

        $comparison = NicheDistinguishabilityAnalyzer::compare([
            'plomberie' => $left,
            'avocat' => $right,
        ]);

        $this->assertTrue($comparison['distinct_enough']);
    }
}
