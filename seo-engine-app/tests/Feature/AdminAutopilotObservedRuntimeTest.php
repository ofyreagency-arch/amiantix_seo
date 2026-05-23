<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\SeoPage;
use App\Models\SeoRecommendation;
use App\Models\SeoSite;
use App\Models\SeoSitePage;
use App\Models\SeoSitePageSnapshot;
use App\Models\SeoSuggestion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAutopilotObservedRuntimeTest extends TestCase
{
    use RefreshDatabase;

    public function test_autopilot_page_surfaces_observed_runtime_backlog_before_legacy_suggestions(): void
    {
        $this->withoutVite();

        $site = SeoSite::query()->create([
            'site_id' => 'autopilot-site',
            'name' => 'Autopilot Site',
            'url' => 'https://autopilot.test',
            'niche' => 'amiante',
            'locale' => 'fr',
            'preset' => 'generic',
            'api_token_hash' => hash('sha256', 'token'),
            'is_active' => true,
        ]);

        $legacyPage = SeoPage::query()->create([
            'site_id' => $site->site_id,
            'keyword' => 'diagnostic amiante paris',
            'slug' => 'diagnostic-amiante-paris',
            'status' => 'published',
            'seo_score' => 55,
        ]);

        SeoSuggestion::query()->create([
            'seo_page_id' => $legacyPage->id,
            'source' => 'feedback_loop:auto',
            'signals_json' => ['low_ctr'],
            'suggestions_json' => [
                'sections' => [
                    'Ajouter un comparatif opérationnel entre repérage et diagnostic.',
                    'Détailler les points de contrôle avant diffusion des pièces.',
                ],
                'rationale' => [
                    'low_ctr',
                    'page too generic',
                ],
                'faq' => [
                    ['question' => 'Quand faut-il relancer le repérage ?', 'answer' => 'Quand le périmètre de travaux change.'],
                ],
                'signals_summary' => [
                    'rewrite_target_plan' => [
                        [
                            'heading' => 'Documents et preuves a conserver',
                            'phase' => 'proof',
                            'patch_intent' => 'expand_and_structure',
                            'replacement_mode' => 'replace_only_if_patch_adds_structure',
                            'instruction' => 'developper et structurer cette section avec des listes, sous-parties ou tableaux utiles',
                            'reasons' => ['too_short', 'missing_structure'],
                        ],
                    ],
                ],
            ],
            'status' => 'pending',
        ]);

        SeoRecommendation::query()->create([
            'site_id' => $site->site_id,
            'site_page_id' => 1,
            'type' => 'refresh_page',
            'priority' => 20,
            'estimated_impact' => 'high',
            'difficulty' => 'medium',
            'cluster' => 'diagnostic',
            'title' => 'Strengthen weak observed page',
            'reasoning' => 'Observed page is thin and partially isolated.',
            'suggested_action' => 'Refresh page and reconnect links.',
            'status' => 'pending',
            'generated_at' => now(),
        ]);

        $critical = SeoSitePage::query()->create([
            'site_id' => $site->site_id,
            'normalized_url' => 'https://autopilot.test/page-bloquee',
            'url_hash' => sha1('https://autopilot.test/page-bloquee'),
            'path' => '/page-bloquee',
            'title' => null,
            'meta_description' => null,
            'canonical_url' => null,
            'indexability_state' => 'noindex',
            'last_status_code' => 404,
            'latest_word_count' => 70,
            'authority_score' => 0.05,
            'orphan_score' => 0.90,
            'overlap_score' => 0.82,
            'pillar_likelihood' => 0.05,
            'cluster_label' => null,
            'last_seen_at' => now()->subDay(),
        ]);

        SeoSitePageSnapshot::query()->create([
            'site_id' => $site->site_id,
            'site_crawl_id' => 1,
            'site_page_id' => $critical->id,
            'url' => $critical->normalized_url,
            'status_code' => 404,
            'is_indexable' => false,
            'word_count' => 70,
            'observed_at' => now()->subDays(10),
        ]);

        $response = $this
            ->withSession(['admin_authenticated' => true])
            ->get(route('admin.sites.autopilot', $site->site_id));

        $response->assertOk();
        $response->assertSee('Autopilot observed');
        $response->assertSee('Backlog observed');
        $response->assertSee('Suggestions legacy en attente');
        $response->assertSee('Sections ciblées');
        $response->assertSee('Strengthen weak observed page');
        $response->assertSee('/page-bloquee');
        $response->assertSee('critical');
        $response->assertSee('feedback_loop:auto');
        $response->assertSee('Ajouter un comparatif opérationnel entre repérage et diagnostic.');
        $response->assertSee('page too generic');
        $response->assertSee('Quand faut-il relancer le repérage ?');
        $response->assertSee('Plan de patch ciblé');
        $response->assertSee('Documents et preuves a conserver');
        $response->assertSee('phase proof');
        $response->assertSee('expand_and_structure');
        $response->assertSee('replace_only_if_patch_adds_structure');
        $response->assertSee('too_short');
        $response->assertSee('missing_structure');
    }

    public function test_approving_legacy_autopilot_suggestion_applies_review_signal_to_page(): void
    {
        $site = SeoSite::query()->create([
            'site_id' => 'autopilot-site',
            'name' => 'Autopilot Site',
            'url' => 'https://autopilot.test',
            'niche' => 'amiante',
            'locale' => 'fr',
            'preset' => 'generic',
            'api_token_hash' => hash('sha256', 'token'),
            'is_active' => true,
        ]);

        $page = SeoPage::query()->create([
            'site_id' => $site->site_id,
            'keyword' => 'diagnostic amiante paris',
            'slug' => 'diagnostic-amiante-paris',
            'status' => 'draft',
            'seo_score' => 58,
            'review_issues_json' => ['issue-existing'],
        ]);

        $suggestion = SeoSuggestion::query()->create([
            'seo_page_id' => $page->id,
            'source' => 'feedback_loop:auto',
            'signals_json' => ['seo_score' => 58],
            'suggestions_json' => [
                'recommendations' => [
                    'Add more strategic internal links to semantically close pages.',
                    'Check indexation status, sitemap, canonical tags and content quality before requesting re-indexation.',
                ],
                'rationale' => [
                    'low_ctr',
                    'not_indexed',
                ],
            ],
            'status' => 'pending',
        ]);

        $response = $this
            ->withSession(['admin_authenticated' => true])
            ->post(route('admin.sites.suggestions.approve', [$site->site_id, $suggestion->id]));

        $response->assertRedirect(route('admin.sites.autopilot', $site->site_id));

        $page->refresh();
        $suggestion->refresh();

        $this->assertSame('review', $page->status);
        $this->assertSame('applied', $suggestion->status);
        $this->assertNotNull($suggestion->applied_at);
        $this->assertContains('issue-existing', $page->review_issues_json);
        $this->assertContains('Add more strategic internal links to semantically close pages.', $page->review_issues_json);
        $this->assertContains('low_ctr', $page->review_issues_json);
    }
}
