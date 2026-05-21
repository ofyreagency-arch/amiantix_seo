<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\SeoPage;
use App\Models\SeoSite;
use App\Models\SeoSitePage;
use App\ObservedSite\SeoPageObservedLinkService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeoPageObservedLinkRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_persists_an_explicit_observed_mapping_from_canonical_url(): void
    {
        $site = SeoSite::query()->create([
            'site_id' => 'mapping-site',
            'name' => 'Mapping Site',
            'url' => 'https://mapping.test',
            'niche' => 'amiante',
            'locale' => 'fr',
            'preset' => 'amiantix',
            'api_token_hash' => hash('sha256', 'token'),
            'is_active' => true,
        ]);

        $observed = SeoSitePage::query()->create([
            'site_id' => $site->site_id,
            'normalized_url' => 'https://mapping.test/guide-observe',
            'url_hash' => sha1('https://mapping.test/guide-observe'),
            'path' => '/guide-observe',
            'title' => 'Guide observé',
            'indexability_state' => 'indexable',
            'last_seen_at' => now(),
        ]);

        $page = SeoPage::query()->create([
            'site_id' => $site->site_id,
            'keyword' => 'guide workflow',
            'slug' => 'guide-workflow',
            'status' => 'review',
            'canonical_url' => 'https://mapping.test/guide-observe',
            'title' => 'Guide workflow',
        ]);

        $linked = app(SeoPageObservedLinkService::class)->syncPage($page->fresh());

        $page->refresh();

        $this->assertNotNull($linked);
        $this->assertSame($observed->id, $page->observed_site_page_id);
        $this->assertSame('canonical_url_exact', $page->observed_page_match_rule);
        $this->assertNotNull($page->observed_page_linked_at);
        $this->assertSame($observed->id, $page->observedPage?->id);
    }

    public function test_it_can_resolve_observed_context_for_api_even_when_slug_and_path_differ(): void
    {
        [$site, $token] = $this->siteWithToken();

        $observed = SeoSitePage::query()->create([
            'site_id' => $site->site_id,
            'normalized_url' => 'https://runtime-api.test/guide-observe',
            'url_hash' => sha1('https://runtime-api.test/guide-observe'),
            'path' => '/guide-observe',
            'title' => 'Guide observé',
            'meta_description' => 'Page observée réelle.',
            'canonical_url' => 'https://runtime-api.test/guide-observe',
            'indexability_state' => 'indexable',
            'last_status_code' => 200,
            'latest_word_count' => 980,
            'authority_score' => 0.65,
            'orphan_score' => 0.12,
            'overlap_score' => 0.09,
            'pillar_likelihood' => 0.75,
            'cluster_label' => 'guide',
            'last_seen_at' => now(),
        ]);

        $page = SeoPage::query()->create([
            'site_id' => $site->site_id,
            'keyword' => 'guide workflow',
            'slug' => 'guide-workflow',
            'status' => 'published',
            'title' => 'Guide workflow',
            'canonical_url' => 'https://runtime-api.test/guide-observe',
        ]);

        app(SeoPageObservedLinkService::class)->syncPage($page);

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/seo/pages?include_observed=1');

        $response->assertOk()
            ->assertJsonPath('data.0.page.slug', 'guide-workflow')
            ->assertJsonPath('data.0.observed.path', '/guide-observe')
            ->assertJsonPath('data.0.observed.url', 'https://runtime-api.test/guide-observe');
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
