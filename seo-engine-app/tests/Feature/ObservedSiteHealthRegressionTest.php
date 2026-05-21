<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\SeoPage;
use App\Models\SeoSite;
use App\Models\SeoSitePage;
use App\ObservedSite\SiteHealthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ObservedSiteHealthRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_prefers_observed_pages_over_legacy_generated_pages(): void
    {
        $site = SeoSite::query()->create([
            'site_id' => 'amiantix',
            'name' => 'Amiantix',
            'url' => 'https://amiantix.com',
            'locale' => 'fr',
            'preset' => 'amiantix',
            'api_token_hash' => hash('sha256', 'token'),
        ]);

        SeoPage::query()->create([
            'site_id' => $site->site_id,
            'title' => 'Legacy Draft',
            'slug' => 'legacy-draft',
            'keyword' => 'legacy',
            'status' => 'draft',
            'seo_score' => 0,
            'quality_score' => 0,
            'topical_score' => 0,
            'indexability_score' => 0,
        ]);

        SeoSitePage::query()->create([
            'site_id' => $site->site_id,
            'normalized_url' => 'https://amiantix.com/',
            'url_hash' => sha1('https://amiantix.com/'),
            'path' => '/',
            'title' => 'Amiantix home',
            'meta_description' => 'Logiciel amiante',
            'canonical_url' => 'https://amiantix.com/',
            'indexability_state' => 'indexable',
            'last_status_code' => 200,
            'latest_word_count' => 1400,
            'authority_score' => 0.62,
            'orphan_score' => 0.08,
            'overlap_score' => 0.12,
            'pillar_likelihood' => 0.76,
            'cluster_label' => 'logiciel-amiante',
        ]);

        SeoSitePage::query()->create([
            'site_id' => $site->site_id,
            'normalized_url' => 'https://amiantix.com/blog',
            'url_hash' => sha1('https://amiantix.com/blog'),
            'path' => '/blog',
            'title' => 'Blog amiante',
            'meta_description' => 'Guides et methodes',
            'canonical_url' => 'https://amiantix.com/blog',
            'indexability_state' => 'indexable',
            'last_status_code' => 200,
            'latest_word_count' => 780,
            'authority_score' => 0.38,
            'orphan_score' => 0.18,
            'overlap_score' => 0.22,
            'pillar_likelihood' => 0.44,
            'cluster_label' => 'blog',
        ]);

        $health = app(SiteHealthService::class)->calculate($site->site_id);

        $this->assertSame(2, $health['total_pages']);
        $this->assertSame(2, $health['published']);
        $this->assertSame(0, $health['errors']);
        $this->assertGreaterThan(0, $health['score']);
        $this->assertGreaterThan(0, $health['breakdown']['seo']);
        $this->assertGreaterThan(0, $health['breakdown']['quality']);
        $this->assertGreaterThan(0, $health['breakdown']['topical']);
        $this->assertGreaterThan(0, $health['breakdown']['indexability']);
        $this->assertSame(100, $health['breakdown']['published_pct']);
        $this->assertArrayHasKey('logiciel-amiante', $health['clusters']);
    }

    public function test_health_falls_back_to_legacy_pages_when_no_observed_pages_exist(): void
    {
        $site = SeoSite::query()->create([
            'site_id' => 'legacy-site',
            'name' => 'Legacy Site',
            'url' => 'https://legacy.test',
            'locale' => 'fr',
            'preset' => 'legacy',
            'api_token_hash' => hash('sha256', 'legacy-token'),
        ]);

        SeoPage::query()->create([
            'site_id' => $site->site_id,
            'title' => 'Published page',
            'slug' => 'published-page',
            'keyword' => 'legacy keyword',
            'status' => 'published',
            'seo_score' => 72,
            'quality_score' => 68,
            'topical_score' => 60,
            'indexability_score' => 90,
            'cluster' => 'legacy-cluster',
        ]);

        $health = app(SiteHealthService::class)->calculate($site->site_id);

        $this->assertSame(1, $health['total_pages']);
        $this->assertSame(1, $health['published']);
        $this->assertGreaterThan(0, $health['score']);
        $this->assertSame('legacy-cluster', array_key_first($health['clusters']));
    }
}
