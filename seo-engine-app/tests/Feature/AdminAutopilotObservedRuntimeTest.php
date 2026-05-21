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
            'suggestions_json' => ['rationale' => ['low_ctr']],
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
        $response->assertSee('Strengthen weak observed page');
        $response->assertSee('/page-bloquee');
        $response->assertSee('critical');
        $response->assertSee('feedback_loop:auto');
    }
}
