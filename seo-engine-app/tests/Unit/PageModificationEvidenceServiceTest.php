<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Copilot\PageModificationEvidenceService;
use App\Models\SeoPage;
use App\Models\SeoSearchConsoleMetric;
use App\Models\SeoSite;
use App\Models\SeoSiteCrawl;
use App\Models\SeoSitePage;
use App\Models\SeoSitePageSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PageModificationEvidenceServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_gather_uses_gsc_queries_and_niche_faq_for_faq_page(): void
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

        $page = SeoPage::query()->create([
            'site_id' => $site->site_id,
            'keyword' => 'faq amiante',
            'slug' => 'faq',
            'title' => 'FAQ amiante',
            'status' => 'published',
        ]);

        $observed = SeoSitePage::query()->create([
            'site_id' => $site->site_id,
            'normalized_url' => 'https://amiantix.com/faq',
            'url_hash' => hash('sha256', 'https://amiantix.com/faq'),
            'path' => '/faq',
            'title' => 'FAQ amiante',
            'latest_word_count' => 320,
        ]);

        $page->update(['observed_site_page_id' => $observed->id]);

        $crawl = SeoSiteCrawl::query()->create([
            'site_id' => $site->site_id,
            'base_url' => 'https://amiantix.com',
            'status' => 'completed',
            'max_pages' => 10,
            'meta_json' => [],
        ]);

        SeoSitePageSnapshot::query()->create([
            'site_id' => $site->site_id,
            'site_crawl_id' => $crawl->id,
            'site_page_id' => $observed->id,
            'url' => 'https://amiantix.com/faq',
            'h2_json' => ['Questions fréquentes'],
            'content_text' => 'FAQ générale sur le repérage amiante.',
            'word_count' => 320,
            'observed_at' => now(),
        ]);

        SeoSearchConsoleMetric::query()->create([
            'site_id' => $site->site_id,
            'seo_page_id' => $page->id,
            'metric_date' => now()->subDay()->toDateString(),
            'window_days' => 28,
            'query' => 'délai repérage amiante copropriété',
            'url' => 'https://amiantix.com/faq',
            'clicks' => 1,
            'impressions' => 44,
            'ctr' => 0.02,
            'position' => 12.4,
            'payload_json' => [],
        ]);

        $evidence = app(PageModificationEvidenceService::class)->gather(
            $site->site_id,
            $page->id,
            'faq',
            'délai repérage amiante copropriété',
            'FAQ',
        );

        $this->assertNotEmpty($evidence['gsc_queries']);
        $this->assertStringContainsString('repérage', implode(' ', $evidence['faq_candidates']));
        $this->assertStringNotContainsString('combien de temps pour traiter', mb_strtolower(implode(' ', $evidence['faq_candidates'])));
        $this->assertNotEmpty($evidence['missing_topics']);
    }

    public function test_gather_filters_irrelevant_low_volume_gsc_queries_for_faq_page(): void
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

        $page = SeoPage::query()->create([
            'site_id' => $site->site_id,
            'keyword' => 'faq amiante',
            'slug' => 'faq',
            'title' => 'FAQ amiante',
            'status' => 'published',
        ]);

        SeoSearchConsoleMetric::query()->create([
            'site_id' => $site->site_id,
            'seo_page_id' => $page->id,
            'metric_date' => now()->subDay()->toDateString(),
            'window_days' => 28,
            'query' => 'logiciel spécifique amiante',
            'url' => 'https://amiantix.com/faq',
            'clicks' => 0,
            'impressions' => 1,
            'ctr' => 0,
            'position' => 14.0,
            'payload_json' => [],
        ]);

        $evidence = app(PageModificationEvidenceService::class)->gather(
            $site->site_id,
            $page->id,
            'faq',
            null,
            'FAQ',
        );

        $queries = collect($evidence['gsc_queries'])->pluck('query')->all();
        $faqBlob = mb_strtolower(implode(' ', $evidence['faq_candidates']));

        $this->assertNotContains('logiciel spécifique amiante', $queries);
        $this->assertStringNotContainsString('logiciel spécifique amiante', $faqBlob);
    }
}
