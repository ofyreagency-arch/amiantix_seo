<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\SeoPage;
use App\Models\SeoSearchConsoleMetric;
use App\Models\SeoSemanticLink;
use App\Models\SeoSite;
use App\Models\SeoSiteCrawl;
use App\Models\SeoSiteGoogleConnection;
use App\Models\SeoSitePage;
use App\Models\SeoSiteSitemap;
use App\Models\SeoSuggestion;
use App\Models\SeoVector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSystemRuntimeStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_system_page_surfaces_runtime_modules_with_real_statuses(): void
    {
        $this->withoutVite();

        $site = SeoSite::query()->create([
            'site_id' => 'system-runtime',
            'name' => 'System Runtime',
            'url' => 'https://system-runtime.test',
            'niche' => 'amiante',
            'locale' => 'fr',
            'preset' => 'amiantix',
            'api_token_hash' => hash('sha256', 'token'),
            'is_active' => true,
        ]);

        $page = SeoPage::query()->create([
            'site_id' => $site->site_id,
            'keyword' => 'diagnostic amiante systeme',
            'slug' => 'diagnostic-amiante-systeme',
            'title' => 'Diagnostic amiante systeme',
            'status' => 'published',
            'seo_score' => 78,
            'quality_score' => 92,
            'indexability_score' => 74,
        ]);

        SeoSiteGoogleConnection::query()->create([
            'site_id' => $site->site_id,
            'connection_mode' => 'service_account',
            'property_url' => 'sc-domain:system-runtime.test',
            'google_account_email' => 'svc@system-runtime.test',
            'credentials_path' => '/var/www/system-runtime.json',
            'connection_status' => 'connected',
        ]);

        SeoSearchConsoleMetric::query()->create([
            'seo_page_id' => $page->id,
            'metric_date' => now()->subDay()->toDateString(),
            'window_days' => 30,
            'query' => 'diagnostic amiante systeme',
            'url' => 'https://system-runtime.test/diagnostic-amiante-systeme',
            'clicks' => 4,
            'impressions' => 120,
            'ctr' => 0.033,
            'position' => 9.8,
            'payload_json' => [],
        ]);

        SeoSiteCrawl::query()->create([
            'site_id' => $site->site_id,
            'base_url' => $site->url,
            'status' => 'completed',
            'max_pages' => 100,
            'discovered_url_count' => 10,
            'crawled_url_count' => 10,
            'started_at' => now()->subMinutes(15),
            'completed_at' => now()->subMinutes(5),
        ]);

        SeoSitePage::query()->create([
            'site_id' => $site->site_id,
            'normalized_url' => 'https://system-runtime.test/diagnostic-amiante-systeme',
            'url_hash' => sha1('https://system-runtime.test/diagnostic-amiante-systeme'),
            'path' => '/diagnostic-amiante-systeme',
            'title' => 'Diagnostic amiante systeme',
            'meta_description' => 'Page suivie par le runtime',
            'canonical_url' => 'https://system-runtime.test/diagnostic-amiante-systeme',
            'indexability_state' => 'indexable',
            'last_status_code' => 200,
            'latest_word_count' => 1600,
            'authority_score' => 0.7,
            'orphan_score' => 0.1,
            'overlap_score' => 0.05,
            'pillar_likelihood' => 0.81,
            'cluster_label' => 'amiante',
            'last_seen_at' => now()->subHour(),
        ]);

        SeoSiteSitemap::query()->create([
            'site_id' => $site->site_id,
            'url' => 'https://system-runtime.test/sitemap.xml',
            'normalized_url' => 'https://system-runtime.test/sitemap.xml',
            'url_hash' => sha1('https://system-runtime.test/sitemap.xml'),
            'sitemap_type' => 'root',
            'discovered_at' => now()->subHour(),
        ]);

        SeoVector::query()->create([
            'site_id' => $site->site_id,
            'entity_type' => 'seo_page',
            'entity_key' => 'seo_page:'.$page->id.':body',
            'entity_id' => $page->id,
            'source_text' => 'Contenu source du runtime system health',
            'source_hash' => sha1('diagnostic-amiante-systeme-body'),
            'embedding_model' => 'text-embedding-3-small',
            'embedding_version' => 'page_v1',
            'embedding_json' => array_fill(0, 8, 0.01),
            'meta_json' => ['dimensions' => 8],
        ]);

        SeoSemanticLink::query()->create([
            'site_id' => $site->site_id,
            'relation_type' => 'internal_link',
            'source_key' => 'seo_page:'.$page->id,
            'source_id' => $page->id,
            'target_key' => 'seo_page:'.$page->id,
            'target_id' => $page->id,
            'label' => 'Diagnostic amiante systeme',
            'url' => 'https://system-runtime.test/diagnostic-amiante-systeme',
            'reason' => 'system health coverage',
            'similarity_score' => 0.88,
            'meta_json' => ['status' => 'suggested'],
        ]);

        SeoSuggestion::query()->create([
            'seo_page_id' => $page->id,
            'source' => 'signal_queue:low_ctr',
            'status' => 'pending',
            'signals_json' => ['gsc_trigger' => ['type' => 'low_ctr']],
            'suggestions_json' => ['mode' => 'improve-ctr'],
        ]);

        $response = $this
            ->withSession(['admin_authenticated' => true])
            ->get(route('admin.system'));

        $response->assertOk();
        $response->assertSee('System Health / Runtime Status');
        $response->assertSee('OpenAI');
        $response->assertSee('Search Console');
        $response->assertSee('Monitoring / Crawl');
        $response->assertSee('Sitemap');
        $response->assertSee('Indexation');
        $response->assertSee('Queue');
        $response->assertSee('Cron / Scheduler');
        $response->assertSee('Embeddings');
        $response->assertSee('Autopilot');
        $response->assertSee('Publication live');
        $response->assertSee('Rollback');
        $response->assertSee('Sites connectés');
        $response->assertSee('Métriques reçues');
        $response->assertSee('Pages observées');
        $response->assertSee('Sitemaps détectés');
        $response->assertSee('Aucun rollback explicite n est branché pour le moment, mais cela ne bloque pas le moteur SEO.');
        $response->assertSee('Search Console');
        $response->assertSee('Activé');
    }
}
