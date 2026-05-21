<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\SeoPage;
use App\Models\SeoRecommendation;
use App\Models\SeoSite;
use App\Models\SeoSitePage;
use App\Models\SeoSitePageSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeoRuntimeObservedApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_runtime_summary_exposes_observed_health_and_alerts(): void
    {
        [$site, $token] = $this->siteWithToken();

        SeoPage::query()->create([
            'site_id' => $site->site_id,
            'keyword' => 'diagnostic amiante paris',
            'slug' => 'diagnostic-amiante-paris',
            'status' => 'published',
            'title' => 'Diagnostic amiante Paris',
        ]);

        $critical = SeoSitePage::query()->create([
            'site_id' => $site->site_id,
            'normalized_url' => 'https://runtime-api.test/page-bloquee',
            'url_hash' => sha1('https://runtime-api.test/page-bloquee'),
            'path' => '/page-bloquee',
            'title' => null,
            'meta_description' => null,
            'canonical_url' => null,
            'indexability_state' => 'noindex',
            'last_status_code' => 404,
            'latest_word_count' => 80,
            'authority_score' => 0.05,
            'orphan_score' => 0.95,
            'overlap_score' => 0.84,
            'pillar_likelihood' => 0.04,
            'cluster_label' => null,
            'last_seen_at' => now(),
        ]);

        SeoSitePageSnapshot::query()->create([
            'site_id' => $site->site_id,
            'site_crawl_id' => 1,
            'site_page_id' => $critical->id,
            'url' => $critical->normalized_url,
            'status_code' => 404,
            'is_indexable' => false,
            'word_count' => 80,
            'observed_at' => now(),
        ]);

        SeoRecommendation::query()->create([
            'site_id' => $site->site_id,
            'site_page_id' => $critical->id,
            'type' => 'differentiate_intent',
            'priority' => 30,
            'estimated_impact' => 'high',
            'difficulty' => 'medium',
            'cluster' => 'diagnostic',
            'title' => 'Resolve overlap: page-bloquee',
            'reasoning' => 'Observed overlap.',
            'suggested_action' => 'Differentiate.',
            'status' => 'pending',
            'meta_json' => [],
            'generated_at' => now(),
        ]);

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/seo/runtime-summary');

        $response->assertOk()
            ->assertJsonPath('site.site_id', $site->site_id)
            ->assertJsonPath('legacy.pages', 1)
            ->assertJsonPath('observed.pages', 1)
            ->assertJsonPath('observed.monitoring.critical', 1)
            ->assertJsonPath('observed.top_alerts.0.path', '/page-bloquee');
    }

    public function test_observed_pages_endpoint_filters_runtime_items_by_state(): void
    {
        [$site, $token] = $this->siteWithToken();

        $healthy = SeoSitePage::query()->create([
            'site_id' => $site->site_id,
            'normalized_url' => 'https://runtime-api.test/guide-amiante',
            'url_hash' => sha1('https://runtime-api.test/guide-amiante'),
            'path' => '/guide-amiante',
            'title' => 'Guide amiante',
            'meta_description' => 'Guide amiante complet.',
            'canonical_url' => 'https://runtime-api.test/guide-amiante',
            'indexability_state' => 'indexable',
            'last_status_code' => 200,
            'latest_word_count' => 1400,
            'authority_score' => 0.72,
            'orphan_score' => 0.08,
            'overlap_score' => 0.10,
            'pillar_likelihood' => 0.83,
            'cluster_label' => 'guide',
            'last_seen_at' => now(),
        ]);

        $warning = SeoSitePage::query()->create([
            'site_id' => $site->site_id,
            'normalized_url' => 'https://runtime-api.test/page-faible',
            'url_hash' => sha1('https://runtime-api.test/page-faible'),
            'path' => '/page-faible',
            'title' => 'Page faible',
            'meta_description' => null,
            'canonical_url' => 'https://runtime-api.test/page-faible',
            'indexability_state' => 'noindex',
            'last_status_code' => 200,
            'latest_word_count' => 160,
            'authority_score' => 0.10,
            'orphan_score' => 0.82,
            'overlap_score' => 0.22,
            'pillar_likelihood' => 0.20,
            'cluster_label' => 'guide',
            'last_seen_at' => now()->subMinute(),
        ]);

        foreach ([$healthy, $warning] as $page) {
            SeoSitePageSnapshot::query()->create([
                'site_id' => $site->site_id,
                'site_crawl_id' => 1,
                'site_page_id' => $page->id,
                'url' => $page->normalized_url,
                'status_code' => $page->last_status_code,
                'is_indexable' => $page->indexability_state === 'indexable',
                'word_count' => $page->latest_word_count,
                'observed_at' => now(),
            ]);
        }

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/seo/observed-pages?state=warning&limit=5');

        $response->assertOk()
            ->assertJsonPath('site_id', $site->site_id)
            ->assertJsonPath('count', 1)
            ->assertJsonPath('items.0.path', '/page-faible')
            ->assertJsonPath('items.0.state', 'warning');
    }

    public function test_pages_endpoint_can_embed_observed_page_context(): void
    {
        [$site, $token] = $this->siteWithToken();

        SeoPage::query()->create([
            'site_id' => $site->site_id,
            'keyword' => 'guide amiante',
            'slug' => 'guide-amiante',
            'status' => 'published',
            'title' => 'Guide amiante',
        ]);

        SeoSitePage::query()->create([
            'site_id' => $site->site_id,
            'normalized_url' => 'https://runtime-api.test/guide-amiante',
            'url_hash' => sha1('https://runtime-api.test/guide-amiante'),
            'path' => '/guide-amiante',
            'title' => 'Guide amiante',
            'meta_description' => 'Guide amiante complet.',
            'canonical_url' => 'https://runtime-api.test/guide-amiante',
            'indexability_state' => 'indexable',
            'last_status_code' => 200,
            'latest_word_count' => 1200,
            'authority_score' => 0.65,
            'orphan_score' => 0.10,
            'overlap_score' => 0.12,
            'pillar_likelihood' => 0.80,
            'cluster_label' => 'guide',
            'last_seen_at' => now(),
        ]);

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/seo/pages?include_observed=1');

        $response->assertOk()
            ->assertJsonPath('data.0.page.slug', 'guide-amiante')
            ->assertJsonPath('data.0.observed.path', '/guide-amiante')
            ->assertJsonPath('data.0.observed.indexability_state', 'indexable');
    }

    public function test_internal_links_endpoint_includes_observed_page_context(): void
    {
        [$site, $token] = $this->siteWithToken();

        SeoPage::query()->create([
            'site_id' => $site->site_id,
            'keyword' => 'guide amiante',
            'slug' => 'guide-amiante',
            'status' => 'published',
            'title' => 'Guide amiante',
            'canonical_url' => 'https://runtime-api.test/guide-amiante',
        ]);

        SeoSitePage::query()->create([
            'site_id' => $site->site_id,
            'normalized_url' => 'https://runtime-api.test/guide-amiante',
            'url_hash' => sha1('https://runtime-api.test/guide-amiante'),
            'path' => '/guide-amiante',
            'title' => 'Guide amiante',
            'meta_description' => 'Guide amiante complet.',
            'canonical_url' => 'https://runtime-api.test/guide-amiante',
            'indexability_state' => 'indexable',
            'last_status_code' => 200,
            'latest_word_count' => 1200,
            'authority_score' => 0.65,
            'orphan_score' => 0.10,
            'overlap_score' => 0.12,
            'pillar_likelihood' => 0.80,
            'cluster_label' => 'guide',
            'last_seen_at' => now(),
        ]);

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/seo/internal-links?slug=guide-amiante');

        $response->assertOk()
            ->assertJsonPath('page.slug', 'guide-amiante')
            ->assertJsonPath('observed.path', '/guide-amiante')
            ->assertJsonPath('observed.health.indexability', 100);
    }

    /**
     * @return array{0:SeoSite,1:string}
     */
    private function siteWithToken(): array
    {
        $token = SeoSite::generateToken();

        $site = SeoSite::query()->create([
            'site_id' => 'runtime-api-site',
            'name' => 'Runtime API Site',
            'url' => 'https://runtime-api.test',
            'niche' => 'amiante',
            'locale' => 'fr',
            'preset' => 'generic',
            'api_token_hash' => $token['hash'],
            'is_active' => true,
        ]);

        return [$site, $token['token']];
    }
}
