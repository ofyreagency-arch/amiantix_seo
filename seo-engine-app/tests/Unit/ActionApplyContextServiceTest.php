<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Copilot\ActionApplyContextService;
use App\Models\SeoSite;
use App\Models\SeoSitePage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActionApplyContextServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_marks_observed_page_as_advisory_only(): void
    {
        $site = SeoSite::query()->create([
            'site_id' => 'amiantix',
            'name' => 'Amiantix',
            'url' => 'https://amiantix.com',
            'niche' => 'amiante',
            'locale' => 'fr',
            'preset' => 'amiantix',
            'api_token_hash' => hash('sha256', 'token'),
            'is_active' => true,
        ]);

        SeoSitePage::query()->create([
            'site_id' => $site->site_id,
            'path' => '/faq',
            'normalized_url' => 'https://amiantix.com/faq',
            'url_hash' => hash('sha256', 'https://amiantix.com/faq'),
            'title' => 'FAQ',
            'last_status_code' => 200,
            'last_seen_at' => now(),
        ]);

        $context = app(ActionApplyContextService::class)->resolve(
            $site->site_id,
            'faq',
            null,
            'rewrite',
            false,
            'FAQ',
            'FAQ',
            $site->url,
            [
                'sections' => ['Section manquante : Questions fréquentes'],
                'faq' => ['Combien coûte un diagnostic amiante ?'],
                'topics' => [],
                'content_summary' => 'Renforcer la FAQ avec des réponses concrètes.',
                'title_change' => null,
            ],
        );

        $this->assertSame('observed', $context['page_kind']);
        $this->assertSame('/faq', $context['target_path']);
        $this->assertSame('https://amiantix.com/faq', $context['target_url']);
        $this->assertFalse($context['will_modify_live_site']);
        $this->assertSame('advisory_only', $context['live_site_impact']);
        $this->assertStringContainsString('Voir le plan pour cette page', (string) $context['button_label']);
        $this->assertStringContainsString('ne modifie pas encore automatiquement', (string) $context['button_explanation']);
    }
}
