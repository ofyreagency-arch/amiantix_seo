<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\SeoPage;
use App\Models\SeoSearchConsoleMetric;
use App\Models\SeoSite;
use App\Models\SeoSiteCrawl;
use App\Models\SeoSiteGoogleConnection;
use App\Models\SeoSitePage;
use App\Models\SeoSitePageSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSiteObservedRuntimeTest extends TestCase
{
    use RefreshDatabase;

    public function test_site_show_surfaces_observed_runtime_health_and_alerts(): void
    {
        $this->withoutVite();

        $site = SeoSite::query()->create([
            'site_id' => 'site-runtime',
            'name' => 'Site Runtime',
            'url' => 'https://runtime-site.test',
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
            'title' => 'Diagnostic amiante Paris',
            'status' => 'published',
            'seo_score' => 60,
            'quality_score' => 55,
        ]);

        SeoSearchConsoleMetric::query()->create([
            'seo_page_id' => $page->id,
            'metric_date' => now()->subDays(3)->toDateString(),
            'window_days' => 30,
            'query' => null,
            'url' => 'https://runtime-site.test/diagnostic-amiante-paris',
            'clicks' => 1,
            'impressions' => 160,
            'ctr' => 0.00625,
            'position' => 12.4,
            'payload_json' => [],
        ]);

        SeoSearchConsoleMetric::query()->create([
            'seo_page_id' => $page->id,
            'metric_date' => now()->subDays(2)->toDateString(),
            'window_days' => 30,
            'query' => 'diagnostic amiante paris',
            'url' => 'https://runtime-site.test/diagnostic-amiante-paris',
            'clicks' => 0,
            'impressions' => 42,
            'ctr' => 0.0,
            'position' => 11.8,
            'payload_json' => [],
        ]);

        SeoSiteGoogleConnection::query()->create([
            'site_id' => $site->site_id,
            'connection_mode' => 'service_account',
            'property_url' => 'sc-domain:runtime-site.test',
            'google_account_email' => 'svc@runtime-site.test',
            'credentials_path' => '/var/www/runtime-site.json',
            'connection_status' => 'configured',
        ]);

        $healthy = SeoSitePage::query()->create([
            'site_id' => $site->site_id,
            'normalized_url' => 'https://runtime-site.test/guide-amiante',
            'url_hash' => sha1('https://runtime-site.test/guide-amiante'),
            'path' => '/guide-amiante',
            'title' => 'Guide amiante',
            'meta_description' => 'Guide amiante complet',
            'canonical_url' => 'https://runtime-site.test/guide-amiante',
            'indexability_state' => 'indexable',
            'last_status_code' => 200,
            'latest_word_count' => 1400,
            'authority_score' => 0.70,
            'orphan_score' => 0.10,
            'overlap_score' => 0.05,
            'pillar_likelihood' => 0.80,
            'cluster_label' => 'guide',
            'last_seen_at' => now()->subDay(),
        ]);

        $critical = SeoSitePage::query()->create([
            'site_id' => $site->site_id,
            'normalized_url' => 'https://runtime-site.test/page-bloquee',
            'url_hash' => sha1('https://runtime-site.test/page-bloquee'),
            'path' => '/page-bloquee',
            'title' => null,
            'meta_description' => null,
            'canonical_url' => null,
            'indexability_state' => 'noindex',
            'last_status_code' => 404,
            'latest_word_count' => 70,
            'authority_score' => 0.05,
            'orphan_score' => 0.90,
            'overlap_score' => 0.80,
            'pillar_likelihood' => 0.02,
            'cluster_label' => null,
            'last_seen_at' => now()->subDays(2),
        ]);

        SeoSitePageSnapshot::query()->create([
            'site_id' => $site->site_id,
            'site_crawl_id' => 1,
            'site_page_id' => $healthy->id,
            'url' => $healthy->normalized_url,
            'title' => $healthy->title,
            'meta_description' => $healthy->meta_description,
            'canonical_url' => $healthy->canonical_url,
            'status_code' => 200,
            'is_indexable' => true,
            'word_count' => 1450,
            'observed_at' => now()->subDay(),
        ]);

        SeoSitePageSnapshot::query()->create([
            'site_id' => $site->site_id,
            'site_crawl_id' => 1,
            'site_page_id' => $critical->id,
            'url' => $critical->normalized_url,
            'status_code' => 404,
            'is_indexable' => false,
            'word_count' => 70,
            'observed_at' => now()->subDays(20),
        ]);

        SeoSiteCrawl::query()->create([
            'site_id' => $site->site_id,
            'base_url' => $site->url,
            'status' => 'completed',
            'max_pages' => 80,
            'discovered_url_count' => 12,
            'crawled_url_count' => 10,
            'started_at' => now()->subMinutes(10),
            'completed_at' => now()->subMinutes(2),
        ]);

        $response = $this
            ->withSession(['admin_authenticated' => true])
            ->get(route('admin.sites.show', $site->site_id));

        $response->assertOk();
        $response->assertSee('Couche observée');
        $response->assertSee('Alertes observed');
        $response->assertSee('Articles');
        $response->assertSee('Google Search Console');
        $response->assertSee('CTR à relancer');
        $response->assertSee('Proches du top 10');
        $response->assertSee('Requêtes émergentes');
        $response->assertSee('Connexion Google');
        $response->assertSee('sc-domain:runtime-site.test');
        $response->assertSee('Site Runtime');
        $response->assertSee('/page-bloquee');
        $response->assertSee('critical');
        $response->assertSee('health score');
    }

    public function test_site_google_connection_can_be_updated_per_site_from_the_admin_page(): void
    {
        $site = SeoSite::query()->create([
            'site_id' => 'site-runtime',
            'name' => 'Site Runtime',
            'url' => 'https://runtime-site.test',
            'niche' => 'amiante',
            'locale' => 'fr',
            'preset' => 'generic',
            'api_token_hash' => hash('sha256', 'token'),
            'is_active' => true,
        ]);

        $response = $this
            ->withSession(['admin_authenticated' => true])
            ->post(route('admin.sites.google-connection.update', $site->site_id), [
                'gsc_connection_mode' => 'service_account',
                'gsc_property_url' => 'sc-domain:runtime-site.test',
                'gsc_credentials_path' => '/var/www/runtime-site.json',
                'gsc_account_email' => 'svc@runtime-site.test',
            ]);

        $response->assertRedirect(route('admin.sites.show', $site->site_id));

        $this->assertDatabaseHas('seo_sites', [
            'site_id' => $site->site_id,
            'gsc_site_url' => 'sc-domain:runtime-site.test',
            'gsc_credentials_path' => '/var/www/runtime-site.json',
        ]);

        $this->assertDatabaseHas('seo_site_google_connections', [
            'site_id' => $site->site_id,
            'connection_mode' => 'service_account',
            'property_url' => 'sc-domain:runtime-site.test',
            'google_account_email' => 'svc@runtime-site.test',
            'credentials_path' => '/var/www/runtime-site.json',
            'connection_status' => 'configured',
        ]);
    }
}
