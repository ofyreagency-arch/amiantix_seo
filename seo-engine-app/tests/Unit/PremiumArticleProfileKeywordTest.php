<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\SeoSite;
use App\Runtime\PremiumArticleGenerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PremiumArticleProfileKeywordTest extends TestCase
{
    use RefreshDatabase;

    public function test_falls_back_to_site_profile_keyword_when_gsc_has_no_candidate(): void
    {
        $siteId = 'profile-kw-'.Str::lower(Str::random(8));

        $site = SeoSite::query()->create([
            'site_id' => $siteId,
            'name' => 'Plomb Express',
            'url' => 'https://plomb.test',
            'niche' => 'plomberie',
            'locale' => 'fr',
            'preset' => 'generic',
            'api_token_hash' => hash('sha256', 'plomb'),
            'is_active' => true,
        ]);
        $site->saveSiteProfile([
            'status' => 'ready',
            'business' => ['industry' => 'plomberie', 'summary' => 'Dépannage plomberie'],
            'vocabulary' => ['core_terms' => ['fuite', 'chauffe-eau', 'canalisation']],
            'services' => [['name' => 'Dépannage urgence 24h', 'description' => 'Intervention rapide']],
        ]);

        $service = app(PremiumArticleGenerationService::class);
        $site = $site->fresh();

        $this->assertTrue($site->isSiteProfileReady());
        $this->assertSame('Dépannage urgence 24h', $service->resolveProfileKeyword($site));
        $this->assertSame('Dépannage urgence 24h', $service->resolveCandidateKeyword($site));
    }
}
