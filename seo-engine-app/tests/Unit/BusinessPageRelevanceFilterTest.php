<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\SeoPage;
use App\Models\SeoSite;
use App\Models\SeoSiteCrawl;
use App\Models\SeoSitePage;
use App\Models\SeoSitePageSnapshot;
use App\ObservedSite\BusinessPageRelevanceFilter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessPageRelevanceFilterTest extends TestCase
{
    use RefreshDatabase;

    private BusinessPageRelevanceFilter $filter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->filter = app(BusinessPageRelevanceFilter::class);
    }

    public function test_excludes_bridge_and_slug_test_urls(): void
    {
        $this->assertFalse($this->filter->isRelevantUrl('https://amiantix.com/ressources/slug-test'));
        $this->assertFalse($this->filter->isRelevantUrl('https://example.com/bridge-lab-test'));
        $this->assertFalse($this->filter->isRelevantUrl('https://example.com/e2e/publication'));
        $this->assertFalse($this->filter->isRelevantUrl('https://example.com/symfony-bridge/health'));
    }

    public function test_keeps_legitimate_business_urls(): void
    {
        $this->assertTrue($this->filter->isRelevantUrl('https://amiantix.com/'));
        $this->assertTrue($this->filter->isRelevantUrl('https://amiantix.com/ressources/diagnostic-amiante-avant-travaux'));
        $this->assertTrue($this->filter->isRelevantUrl('https://amiantix.com/services/diagnostic-amiante'));
    }

    public function test_excludes_observed_page_with_technical_title(): void
    {
        $crawl = SeoSiteCrawl::query()->create([
            'site_id' => 'amiantix',
            'base_url' => 'https://amiantix.com',
            'status' => 'completed',
            'max_pages' => 10,
            'meta_json' => [],
        ]);

        $page = SeoSitePage::query()->create([
            'site_id' => 'amiantix',
            'normalized_url' => 'https://amiantix.com/ressources/slug-test',
            'url_hash' => hash('sha256', '/ressources/slug-test'),
            'path' => '/ressources/slug-test',
            'title' => 'Guide to Reviewing the Test Bridge Praeviseo Process',
            'indexability_state' => 'indexable',
        ]);

        $snapshot = SeoSitePageSnapshot::query()->create([
            'site_id' => 'amiantix',
            'site_crawl_id' => $crawl->id,
            'site_page_id' => $page->id,
            'url' => $page->normalized_url,
            'h1_json' => ['Guide to Reviewing the Test Bridge Praeviseo Process'],
            'content_text' => 'Field example for bridge validation workflow.',
            'word_count' => 120,
            'is_indexable' => true,
            'status_code' => 200,
            'observed_at' => now(),
        ]);

        $page->forceFill(['last_snapshot_id' => $snapshot->id])->save();

        $this->assertFalse($this->filter->isRelevantObservedPage($page->fresh(), $snapshot));
    }

    public function test_keeps_observed_business_page(): void
    {
        $crawl = SeoSiteCrawl::query()->create([
            'site_id' => 'amiantix',
            'base_url' => 'https://amiantix.com',
            'status' => 'completed',
            'max_pages' => 10,
            'meta_json' => [],
        ]);

        $page = SeoSitePage::query()->create([
            'site_id' => 'amiantix',
            'normalized_url' => 'https://amiantix.com/ressources/diagnostic-amiante',
            'url_hash' => hash('sha256', '/ressources/diagnostic-amiante'),
            'path' => '/ressources/diagnostic-amiante',
            'title' => 'Diagnostic amiante avant travaux en copropriété',
            'primary_h1' => 'Diagnostic amiante',
            'indexability_state' => 'indexable',
        ]);

        $snapshot = SeoSitePageSnapshot::query()->create([
            'site_id' => 'amiantix',
            'site_crawl_id' => $crawl->id,
            'site_page_id' => $page->id,
            'url' => $page->normalized_url,
            'h1_json' => ['Diagnostic amiante'],
            'content_text' => 'Repérage amiante et DTA pour copropriété à Paris.',
            'word_count' => 420,
            'is_indexable' => true,
            'status_code' => 200,
            'observed_at' => now(),
        ]);

        $page->forceFill(['last_snapshot_id' => $snapshot->id])->save();

        $this->assertTrue($this->filter->isRelevantObservedPage($page->fresh(), $snapshot));
    }

    public function test_excludes_forgot_password_pages_by_title(): void
    {
        $this->assertFalse($this->filter->isRelevantUrl('https://amiantix.com/login'));
        $this->assertFalse($this->filter->isRelevantUrl('https://amiantix.com/mot-de-passe-oublie'));

        $page = SeoSitePage::query()->create([
            'site_id' => 'amiantix',
            'normalized_url' => 'https://amiantix.com/mot-de-passe-oublie',
            'url_hash' => hash('sha256', '/mot-de-passe-oublie'),
            'path' => '/mot-de-passe-oublie',
            'title' => 'Mot de passe oublié - Amiantix',
            'primary_h1' => 'Mot de passe oublié',
            'indexability_state' => 'indexable',
        ]);

        $this->assertFalse($this->filter->isRelevantObservedPage($page));
        $this->assertFalse($this->filter->isRelevantSeoPage(SeoPage::query()->create([
            'site_id' => 'amiantix',
            'keyword' => 'mot de passe oublié',
            'slug' => 'mot-de-passe-oublie',
            'title' => 'Mot de passe oublié - Amiantix',
            'status' => 'published',
        ])));
    }

    public function test_mark_excluded_technical_pages_persists_state(): void
    {
        $site = SeoSite::query()->create([
            'site_id' => 'amiantix',
            'name' => 'Amiantix',
            'url' => 'https://amiantix.com',
            'preset' => 'amiantix',
            'api_token_hash' => hash('sha256', 'amiantix'),
            'is_active' => true,
        ]);

        $page = SeoSitePage::query()->create([
            'site_id' => 'amiantix',
            'normalized_url' => 'https://amiantix.com/ressources/slug-test',
            'url_hash' => hash('sha256', '/ressources/slug-test'),
            'path' => '/ressources/slug-test',
            'title' => 'Guide to Reviewing the Test Bridge Praeviseo Process',
            'indexability_state' => 'indexable',
        ]);

        $marked = $this->filter->markExcludedTechnicalPages($site);

        $this->assertSame(1, $marked);
        $this->assertSame('excluded_technical', $page->fresh()->indexability_state);
        $this->assertSame('technical_excluded', $page->fresh()->cluster_label);
    }
}
