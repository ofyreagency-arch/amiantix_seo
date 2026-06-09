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
        $this->assertSame('preview_then_confirm', $context['live_site_impact']);
        $this->assertStringContainsString('Voir la prévisualisation', (string) $context['button_label']);
        $this->assertStringContainsString('Aucune modification n’est envoyée sur votre site', (string) $context['button_explanation']);
    }

    public function test_linked_studio_mirror_stays_observed_for_native_url(): void
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
            'settings_json' => [
                'publication' => [
                    'path_prefix' => 'ressources',
                ],
            ],
        ]);

        $observed = SeoSitePage::query()->create([
            'site_id' => $site->site_id,
            'path' => '/faq',
            'normalized_url' => 'https://amiantix.com/faq',
            'url_hash' => hash('sha256', 'https://amiantix.com/faq'),
            'title' => 'FAQ',
        ]);

        $page = \App\Models\SeoPage::query()->create([
            'site_id' => $site->site_id,
            'observed_site_page_id' => $observed->id,
            'keyword' => 'FAQ',
            'slug' => 'faq',
            'status' => 'published',
            'published_at' => now(),
            'title' => 'FAQ',
            'content' => '<p>FAQ</p>',
            'published_live' => true,
            'published_live_at' => now(),
            'live_url' => 'https://amiantix.com/faq',
        ]);

        $context = app(ActionApplyContextService::class)->resolve(
            $site->site_id,
            'faq',
            $page->id,
            'rewrite',
            false,
            'FAQ',
            'FAQ',
            $site->url,
            ['sections' => ['Section test'], 'faq' => [], 'topics' => [], 'content_summary' => 'Plan', 'title_change' => null],
        );

        $this->assertSame('observed', $context['page_kind']);
        $this->assertFalse($context['will_modify_live_site']);
        $this->assertSame('preview_then_confirm', $context['live_site_impact']);
        $this->assertFalse(app(ActionApplyContextService::class)->canAutoApply('rewrite', $site->site_id, $page->id, 'faq'));
    }
}
