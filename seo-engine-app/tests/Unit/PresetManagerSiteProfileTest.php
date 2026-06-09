<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\SeoSite;
use App\Runtime\SeoEngineContext;
use App\SeoPresets\SiteAware\SiteAwareBlueprintProvider;
use App\SeoPresets\SiteAware\SiteAwareContentProfile;
use App\SeoPresets\SiteAware\SiteAwarePromptProfile;
use App\Services\Preset\PresetContentProfile;
use App\Services\Preset\PresetManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SiteProfileTestSupport;
use Tests\TestCase;

class PresetManagerSiteProfileTest extends TestCase
{
    use RefreshDatabase;
    use SiteProfileTestSupport;

    public function test_amiantix_preset_uses_site_aware_generation_when_profile_is_ready(): void
    {
        $site = SeoSite::query()->create([
            'site_id' => 'amiantix',
            'name' => 'Amiantix',
            'url' => 'https://amiantix.com',
            'niche' => 'amiante',
            'locale' => 'fr',
            'preset' => 'amiantix',
            'api_token_hash' => hash('sha256', 'amiantix'),
            'is_active' => true,
        ]);

        $this->seedReadySiteProfile($site, [
            'business' => ['industry' => 'amiante', 'summary' => 'Logiciel amiante SS3/SS4'],
            'vocabulary' => ['core_terms' => ['amiante', 'repérage', 'DTA']],
        ]);

        app(SeoEngineContext::class)->loadFromSite($site->fresh());

        $manager = app(PresetManager::class);

        $this->assertTrue($manager->siteProfileDrivesGeneration());
        $this->assertInstanceOf(SiteAwareBlueprintProvider::class, $manager->resolveBlueprintProvider());
        $this->assertInstanceOf(SiteAwarePromptProfile::class, $manager->resolvePromptProfile());
        $this->assertInstanceOf(SiteAwareContentProfile::class, $manager->resolveContentProfile());
    }

    public function test_preset_content_profile_never_appends_blocks_when_profile_drives_generation(): void
    {
        config()->set('seo-engine.require_site_profile', true);
        config()->set('seo-engine.site.profile', ['status' => 'ready']);

        $wrapper = app(PresetContentProfile::class);
        $content = '<p>Article IA unique avec 18 lots et 48 h de délai.</p>';

        $this->assertSame(
            $content,
            $wrapper->ensureContentDepth($content, ['topic' => 'test'], ['preserve_ai_narrative' => true]),
        );
    }
}
