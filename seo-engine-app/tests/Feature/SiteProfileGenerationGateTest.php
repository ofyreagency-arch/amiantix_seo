<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Exceptions\SiteProfileNotReadyException;
use App\Models\SeoSite;
use App\Runtime\SeoEngineContext;
use App\SeoBridge\Drivers\OpenAiSeoGenerationDriver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Ofyre\SeoEngine\Services\Generation\SeoGenerationService;
use Tests\Support\SiteProfileTestSupport;
use Tests\TestCase;

class SiteProfileGenerationGateTest extends TestCase
{
    use RefreshDatabase;
    use SiteProfileTestSupport;

    public function test_generation_is_blocked_without_ready_profile(): void
    {
        $site = SeoSite::query()->create([
            'site_id' => 'blocked-site',
            'name' => 'Blocked Site',
            'url' => 'https://blocked-site.test',
            'niche' => 'general',
            'locale' => 'fr',
            'preset' => 'generic',
            'api_token_hash' => hash('sha256', 'blocked'),
            'is_active' => true,
            'settings_json' => [
                'site_profile' => ['status' => 'pending'],
            ],
        ]);

        app(SeoEngineContext::class)->loadFromSite($site);

        $this->expectException(SiteProfileNotReadyException::class);

        app(OpenAiSeoGenerationDriver::class)->generatePage('test keyword', 'draft');
    }

    public function test_seo_generation_service_blocks_when_profile_not_ready(): void
    {
        config([
            'seo-engine.require_site_profile' => true,
            'seo-engine.site.profile' => ['status' => 'analyzing'],
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('profil métier');

        app(SeoGenerationService::class)->generatePayload('mot cle test');
    }

    public function test_site_aware_prompt_contains_business_vocabulary(): void
    {
        $site = SeoSite::query()->create([
            'site_id' => 'prompt-site',
            'name' => 'Amiantix',
            'url' => 'https://amiantix.test',
            'niche' => 'amiante',
            'locale' => 'fr',
            'preset' => 'amiantix',
            'api_token_hash' => hash('sha256', 'prompt'),
            'is_active' => true,
        ]);

        $this->seedReadySiteProfile($site, [
            'vocabulary' => [
                'core_terms' => ['amiante', 'repérage', 'DTA'],
                'forbidden_generic' => ['Field example'],
                'tone' => 'expert réglementaire',
            ],
        ]);

        app(SeoEngineContext::class)->loadFromSite($site->fresh());

        $prompt = app(\App\Services\Preset\PresetPromptProfile::class)
            ->generationCorePrompt('diagnostic amiante', 'amiante', [
                'topic' => 'diagnostic amiante',
                'hero_angle' => 'Obligations avant travaux',
                'family' => 'amiante',
                'archetype' => 'decision_guide',
                'composition' => [],
                'risk_rows' => [],
                'obligations' => [],
                'cases' => [],
                'mistakes' => [],
                'inspection_focus' => [],
                'evidence_examples' => [],
            ], [], []);

        $this->assertStringContainsString('amiante', strtolower($prompt));
        $this->assertStringContainsString('Field example', $prompt);
        $this->assertStringContainsString('langue obligatoire : fr', strtolower($prompt));
        $this->assertStringNotContainsString('Write a business article for a professional SaaS', $prompt);
    }
}
