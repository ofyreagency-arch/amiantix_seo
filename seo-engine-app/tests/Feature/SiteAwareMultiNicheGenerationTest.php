<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\SeoSite;
use App\Runtime\SeoEngineContext;
use App\SeoPresets\SiteAware\SiteAwarePromptProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SiteProfileTestSupport;
use Tests\TestCase;

class SiteAwareMultiNicheGenerationTest extends TestCase
{
    use RefreshDatabase;
    use SiteProfileTestSupport;

    public function test_symfony_lab_site_uses_site_aware_prompt_in_french(): void
    {
        $site = SeoSite::query()->create([
            'site_id' => 'symfony-lab',
            'name' => 'Symfony Bridge Lab',
            'url' => 'http://217.160.70.252',
            'niche' => 'lab',
            'locale' => 'fr',
            'preset' => 'generic',
            'api_token_hash' => hash('sha256', 'lab'),
            'is_active' => true,
        ]);

        $this->seedReadySiteProfile($site, [
            'business' => [
                'summary' => 'Site de test bridge Symfony pour publication PraeviSEO.',
                'industry' => 'laboratoire technique',
                'positioning' => 'Symfony Bridge Lab',
            ],
            'vocabulary' => [
                'core_terms' => ['symfony', 'bridge', 'publication', 'ressources'],
                'forbidden_generic' => ['Field example'],
                'tone' => 'technique',
            ],
        ]);

        app(SeoEngineContext::class)->loadFromSite($site->fresh());

        $prompt = app(SiteAwarePromptProfile::class)->generationCorePrompt(
            'publication ressources symfony',
            'lab',
            app(\App\SeoPresets\SiteAware\SiteAwareBlueprintProvider::class)->resolve('publication ressources symfony', 'lab'),
            [],
            [],
        );

        $this->assertStringContainsString('symfony', strtolower($prompt));
        $this->assertStringContainsString('langue obligatoire : fr', strtolower($prompt));
        $this->assertStringNotContainsString('Write a business article', $prompt);
    }
}
