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

    public function test_health_penalizes_observed_errors_and_thin_non_indexable_pages(): void
    {
        $site = SeoSite::query()->create([
            'site_id' => 'fragile-site',
            'name' => 'Fragile Site',
            'url' => 'https://fragile.test',
            'locale' => 'fr',
            'preset' => 'fragile',
            'api_token_hash' => hash('sha256', 'fragile-token'),
        ]);

        SeoSitePage::query()->create([
            'site_id' => $site->site_id,
            'normalized_url' => 'https://fragile.test/healthy',
            'url_hash' => sha1('https://fragile.test/healthy'),
            'path' => '/healthy',
            'title' => 'Healthy page',
            'meta_description' => 'A strong observed page',
            'canonical_url' => 'https://fragile.test/healthy',
            'indexability_state' => 'indexable',
            'last_status_code' => 200,
            'latest_word_count' => 1200,
            'authority_score' => 0.71,
            'orphan_score' => 0.05,
            'overlap_score' => 0.08,
            'pillar_likelihood' => 0.72,
            'cluster_label' => 'healthy',
        ]);

        SeoSitePage::query()->create([
            'site_id' => $site->site_id,
            'normalized_url' => 'https://fragile.test/thin-noindex',
            'url_hash' => sha1('https://fragile.test/thin-noindex'),
            'path' => '/thin-noindex',
            'title' => null,
            'meta_description' => null,
            'canonical_url' => null,
            'indexability_state' => 'noindex',
            'last_status_code' => 200,
            'latest_word_count' => 45,
            'authority_score' => 0.04,
            'orphan_score' => 0.94,
            'overlap_score' => 0.51,
            'pillar_likelihood' => 0.03,
            'cluster_label' => 'weak',
        ]);

        SeoSitePage::query()->create([
            'site_id' => $site->site_id,
            'normalized_url' => 'https://fragile.test/broken',
            'url_hash' => sha1('https://fragile.test/broken'),
            'path' => '/broken',
            'title' => 'Broken page',
            'meta_description' => 'This page is broken',
            'canonical_url' => 'https://fragile.test/broken',
            'indexability_state' => 'indexable',
            'last_status_code' => 404,
            'latest_word_count' => 120,
            'authority_score' => 0.06,
            'orphan_score' => 0.88,
            'overlap_score' => 0.22,
            'pillar_likelihood' => 0.02,
            'cluster_label' => 'weak',
        ]);

        $health = app(SiteHealthService::class)->calculate($site->site_id);

        $this->assertSame(3, $health['total_pages']);
        $this->assertSame(2, $health['published']);
        $this->assertSame(1, $health['errors']);
        $this->assertLessThan(80, $health['score']);
        $this->assertLessThan(100, $health['breakdown']['published_pct']);
        $this->assertLessThan(80, $health['breakdown']['indexability']);
        $this->assertLessThan(70, $health['breakdown']['quality']);
        $this->assertSame(1, $health['score_dist']['0-20']);
    }
}
