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
use App\Models\SeoSuggestion;
use Illuminate\Support\Facades\Http;
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

    public function test_site_show_surfaces_indexation_backlog_from_google_and_observed_runtime(): void
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

        SeoSiteGoogleConnection::query()->create([
            'site_id' => $site->site_id,
            'connection_mode' => 'service_account',
            'property_url' => 'sc-domain:runtime-site.test',
            'google_account_email' => 'svc@runtime-site.test',
            'credentials_path' => '/var/www/runtime-site.json',
            'connection_status' => 'connected',
        ]);

        $notIndexed = SeoPage::query()->create([
            'site_id' => $site->site_id,
            'keyword' => 'page non indexee',
            'slug' => 'page-non-indexee',
            'title' => 'Page non indexee',
            'status' => 'published',
            'canonical_url' => 'https://runtime-site.test/page-non-indexee',
        ]);

        $withoutSignal = SeoPage::query()->create([
            'site_id' => $site->site_id,
            'keyword' => 'page sans signal',
            'slug' => 'page-sans-signal',
            'title' => 'Page sans signal',
            'status' => 'published',
        ]);

        $broken = SeoPage::query()->create([
            'site_id' => $site->site_id,
            'keyword' => 'page 404',
            'slug' => 'page-404',
            'title' => 'Page 404',
            'status' => 'published',
            'canonical_url' => 'https://runtime-site.test/page-404',
        ]);

        SeoSearchConsoleMetric::query()->create([
            'site_id' => $site->site_id,
            'seo_page_id' => $notIndexed->id,
            'metric_date' => now()->subDay()->toDateString(),
            'window_days' => 30,
            'query' => null,
            'url' => 'https://runtime-site.test/page-non-indexee',
            'clicks' => 0,
            'impressions' => 12,
            'ctr' => 0.0,
            'position' => 18.2,
            'is_indexed' => false,
            'coverage_json' => ['coverage_state:Detected, currently not indexed'],
            'payload_json' => [],
        ]);

        $brokenObserved = SeoSitePage::query()->create([
            'site_id' => $site->site_id,
            'normalized_url' => 'https://runtime-site.test/page-404',
            'url_hash' => sha1('https://runtime-site.test/page-404'),
            'path' => '/page-404',
            'title' => 'Page 404',
            'meta_description' => null,
            'canonical_url' => 'https://runtime-site.test/page-404',
            'indexability_state' => 'noindex',
            'last_status_code' => 404,
            'latest_word_count' => 40,
            'authority_score' => 0.10,
            'orphan_score' => 0.80,
            'overlap_score' => 0.10,
            'pillar_likelihood' => 0.15,
            'cluster_label' => 'guide',
            'last_seen_at' => now()->subDay(),
        ]);

        SeoSitePageSnapshot::query()->create([
            'site_id' => $site->site_id,
            'site_crawl_id' => 1,
            'site_page_id' => $brokenObserved->id,
            'url' => $brokenObserved->normalized_url,
            'status_code' => 404,
            'is_indexable' => false,
            'word_count' => 40,
            'observed_at' => now()->subDay(),
        ]);

        $response = $this
            ->withSession(['admin_authenticated' => true])
            ->get(route('admin.sites.show', $site->site_id));

        $response->assertOk();
        $response->assertSee('Backlog d indexation');
        $response->assertSee('Non indexee par Google');
        $response->assertSee('Sans signal Google recent');
        $response->assertSee('Observee en 404');
        $response->assertSee('Page non indexee');
        $response->assertSee('Page sans signal');
        $response->assertSee('Page 404');
        $response->assertSee('Detected, currently not indexed');
        $response->assertSee('Créer une correction moteur');
        $response->assertSee('Renforcer le maillage');
        $response->assertSee('Revue technique requise');
    }

    public function test_site_indexation_backlog_can_trigger_a_targeted_engine_action(): void
    {
        Http::fake([
            'https://api.openai.com/v1/responses' => Http::response([
                'output' => [[
                    'content' => [[
                        'text' => json_encode([
                            'title' => 'Page non indexee : fiabiliser la publication',
                            'meta_description' => 'Version renforcee pour soutenir l indexation, le maillage et les preuves utiles.',
                            'h1' => 'Page non indexee : fiabiliser la publication',
                            'content' => '<section><h2>Publication et maillage</h2><p>Renforcer le contexte d indexation, les liens internes et les signaux utiles.</p></section>',
                            'faq' => [
                                ['question' => 'Q1', 'answer' => 'A1'],
                                ['question' => 'Q2', 'answer' => 'A2'],
                                ['question' => 'Q3', 'answer' => 'A3'],
                                ['question' => 'Q4', 'answer' => 'A4'],
                                ['question' => 'Q5', 'answer' => 'A5'],
                            ],
                            'internal_links' => [],
                            'rationale' => ['Renforcer la page pour aider la reprise Google.'],
                        ], JSON_THROW_ON_ERROR),
                    ]],
                ]],
            ], 200),
        ]);

        $site = SeoSite::query()->create([
            'site_id' => 'site-runtime',
            'name' => 'Site Runtime',
            'url' => 'https://runtime-site.test',
            'niche' => 'amiante',
            'locale' => 'fr',
            'preset' => 'amiantix',
            'api_token_hash' => hash('sha256', 'token'),
            'is_active' => true,
        ]);

        SeoSiteGoogleConnection::query()->create([
            'site_id' => $site->site_id,
            'connection_mode' => 'service_account',
            'property_url' => 'sc-domain:runtime-site.test',
            'google_account_email' => 'svc@runtime-site.test',
            'credentials_path' => '/var/www/runtime-site.json',
            'connection_status' => 'connected',
        ]);

        $page = SeoPage::query()->create([
            'site_id' => $site->site_id,
            'keyword' => 'page non indexee',
            'slug' => 'page-non-indexee',
            'title' => 'Page non indexee',
            'content' => '<section><h2>Contexte</h2><p>'.str_repeat('Contexte utile pour Google et le lecteur. ', 60).'</p></section>',
            'faq_json' => array_fill(0, 5, ['question' => 'Q', 'answer' => 'R']),
            'internal_links_json' => [],
            'status' => 'published',
            'indexability_score' => 60,
            'seo_score' => 68,
            'quality_score' => 90,
        ]);

        SeoSearchConsoleMetric::query()->create([
            'site_id' => $site->site_id,
            'seo_page_id' => $page->id,
            'metric_date' => now()->subDay()->toDateString(),
            'window_days' => 30,
            'query' => null,
            'url' => 'https://runtime-site.test/page-non-indexee',
            'clicks' => 0,
            'impressions' => 12,
            'ctr' => 0.0,
            'position' => 18.2,
            'is_indexed' => false,
            'coverage_json' => ['coverage_state:Detected, currently not indexed'],
            'payload_json' => [],
        ]);

        $response = $this
            ->withSession(['admin_authenticated' => true])
            ->post(route('admin.sites.indexation-backlog.run', $site->site_id), [
                'page_id' => $page->id,
                'type' => 'google_not_indexed',
            ]);

        $response->assertRedirect(route('admin.pages.show', [$site->site_id, $page->id]));

        $this->assertDatabaseHas('seo_suggestions', [
            'seo_page_id' => $page->id,
            'source' => 'rewrite_engine:improve-indexability',
            'status' => 'pending',
        ]);

        $suggestion = SeoSuggestion::query()->where('seo_page_id', $page->id)->latest('id')->first();
        $this->assertNotNull($suggestion);
        $this->assertSame('google_not_indexed', $suggestion->signals_json['indexation_backlog_trigger']['type']);
        $this->assertSame('improve-indexability', $suggestion->signals_json['indexation_backlog_trigger']['mode']);
    }

    public function test_site_indexation_backlog_can_clear_forced_noindex_directly(): void
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

        $page = SeoPage::query()->create([
            'site_id' => $site->site_id,
            'keyword' => 'page bloquee',
            'slug' => 'page-bloquee',
            'title' => 'Page bloquee',
            'status' => 'published',
            'forced_noindex' => true,
        ]);

        SeoSearchConsoleMetric::query()->create([
            'site_id' => $site->site_id,
            'seo_page_id' => $page->id,
            'metric_date' => now()->subDay()->toDateString(),
            'window_days' => 30,
            'query' => null,
            'url' => 'https://runtime-site.test/page-bloquee',
            'clicks' => 0,
            'impressions' => 10,
            'ctr' => 0.0,
            'position' => 22.0,
            'is_indexed' => false,
            'coverage_json' => ['coverage_state:Blocked by noindex'],
            'payload_json' => [],
        ]);

        $response = $this
            ->withSession(['admin_authenticated' => true])
            ->post(route('admin.sites.indexation-backlog.run', $site->site_id), [
                'page_id' => $page->id,
                'type' => 'google_not_indexed',
            ]);

        $response->assertRedirect(route('admin.pages.show', [$site->site_id, $page->id]));
        $this->assertDatabaseHas('seo_pages', [
            'id' => $page->id,
            'forced_noindex' => false,
        ]);
        $this->assertDatabaseCount('seo_suggestions', 0);
    }

    public function test_site_show_prioritizes_actionable_gsc_opportunities(): void
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

        SeoSiteGoogleConnection::query()->create([
            'site_id' => $site->site_id,
            'connection_mode' => 'service_account',
            'property_url' => 'sc-domain:runtime-site.test',
            'google_account_email' => 'svc@runtime-site.test',
            'credentials_path' => '/var/www/runtime-site.json',
            'connection_status' => 'connected',
        ]);

        $dropPage = SeoPage::query()->create([
            'site_id' => $site->site_id,
            'keyword' => 'page en baisse',
            'slug' => 'page-en-baisse',
            'title' => 'Page en baisse',
            'status' => 'published',
        ]);

        $topTenPage = SeoPage::query()->create([
            'site_id' => $site->site_id,
            'keyword' => 'page top 10',
            'slug' => 'page-top-10',
            'title' => 'Page top 10',
            'status' => 'published',
        ]);

        $ctrPage = SeoPage::query()->create([
            'site_id' => $site->site_id,
            'keyword' => 'page ctr',
            'slug' => 'page-ctr',
            'title' => 'Page CTR',
            'status' => 'published',
        ]);

        SeoSearchConsoleMetric::query()->create([
            'seo_page_id' => $dropPage->id,
            'metric_date' => now()->subDays(10)->toDateString(),
            'window_days' => 30,
            'query' => null,
            'url' => 'https://runtime-site.test/page-en-baisse',
            'clicks' => 20,
            'impressions' => 220,
            'ctr' => 0.09,
            'position' => 9.4,
            'payload_json' => [],
        ]);

        SeoSearchConsoleMetric::query()->create([
            'seo_page_id' => $dropPage->id,
            'metric_date' => now()->subDays(40)->toDateString(),
            'window_days' => 30,
            'query' => null,
            'url' => 'https://runtime-site.test/page-en-baisse',
            'clicks' => 42,
            'impressions' => 520,
            'ctr' => 0.08,
            'position' => 8.9,
            'payload_json' => [],
        ]);

        SeoSearchConsoleMetric::query()->create([
            'seo_page_id' => $topTenPage->id,
            'metric_date' => now()->subDays(5)->toDateString(),
            'window_days' => 30,
            'query' => null,
            'url' => 'https://runtime-site.test/page-top-10',
            'clicks' => 9,
            'impressions' => 140,
            'ctr' => 0.064,
            'position' => 9.1,
            'payload_json' => [],
        ]);

        SeoSearchConsoleMetric::query()->create([
            'seo_page_id' => $ctrPage->id,
            'metric_date' => now()->subDays(4)->toDateString(),
            'window_days' => 30,
            'query' => null,
            'url' => 'https://runtime-site.test/page-ctr',
            'clicks' => 1,
            'impressions' => 180,
            'ctr' => 0.0055,
            'position' => 16.2,
            'payload_json' => [],
        ]);

        SeoSuggestion::query()->create([
            'seo_page_id' => $ctrPage->id,
            'source' => 'rewrite_engine:improve-ctr',
            'signals_json' => [
                'gsc_trigger' => [
                    'type' => 'low_ctr',
                    'mode' => 'improve-ctr',
                ],
            ],
            'suggestions_json' => [
                'mode' => 'improve-ctr',
            ],
            'status' => 'pending',
        ]);

        $response = $this
            ->withSession(['admin_authenticated' => true])
            ->get(route('admin.sites.show', $site->site_id));

        $response->assertOk();
        $response->assertSeeInOrder([
            'Page en baisse',
            'Page top 10',
            'Page CTR',
        ]);
        $response->assertSee('Priorite haute');
        $response->assertSee('Gain rapide');
        $response->assertSee('Actionnable maintenant');
        $response->assertSee('Suggestion deja en attente');
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

    public function test_site_gsc_opportunity_can_trigger_a_targeted_rewrite_suggestion(): void
    {
        Http::fake([
            'https://api.openai.com/v1/responses' => Http::response([
                'output' => [[
                    'content' => [[
                        'text' => json_encode([
                            'title' => 'Diagnostic amiante Paris : relancer les clics utiles',
                            'meta_description' => 'Une version plus engageante centrée sur les obligations, les preuves et les bons réflexes avant travaux.',
                            'h1' => 'Diagnostic amiante Paris : comment relancer l intérêt utile',
                            'content' => '<section><h2>Contexte et obligations</h2><p>Cadrez les obligations, les livrables et le point de départ du lecteur.</p></section>',
                            'faq' => [
                                ['question' => 'Q1', 'answer' => 'A1'],
                                ['question' => 'Q2', 'answer' => 'A2'],
                                ['question' => 'Q3', 'answer' => 'A3'],
                                ['question' => 'Q4', 'answer' => 'A4'],
                                ['question' => 'Q5', 'answer' => 'A5'],
                            ],
                            'internal_links' => [],
                            'rationale' => ['Relancer un angle plus cliquable sans casser la page existante.'],
                        ], JSON_THROW_ON_ERROR),
                    ]],
                ]],
            ], 200),
        ]);

        $site = SeoSite::query()->create([
            'site_id' => 'site-runtime',
            'name' => 'Site Runtime',
            'url' => 'https://runtime-site.test',
            'niche' => 'amiante',
            'locale' => 'fr',
            'preset' => 'amiantix',
            'api_token_hash' => hash('sha256', 'token'),
            'is_active' => true,
        ]);

        SeoSiteGoogleConnection::query()->create([
            'site_id' => $site->site_id,
            'connection_mode' => 'service_account',
            'property_url' => 'sc-domain:runtime-site.test',
            'google_account_email' => 'svc@runtime-site.test',
            'credentials_path' => '/var/www/runtime-site.json',
            'connection_status' => 'configured',
        ]);

        $page = SeoPage::query()->create([
            'site_id' => $site->site_id,
            'keyword' => 'diagnostic amiante paris',
            'slug' => 'diagnostic-amiante-paris',
            'title' => 'Diagnostic amiante Paris',
            'content' => implode('', [
                '<section><h2>Contexte et obligations</h2><p>'.str_repeat('Contexte documentaire et obligations terrain. ', 40).'</p></section>',
                '<section><h2>Points de vigilance</h2><p>'.str_repeat('Vigilance, coordination et verification documentaire. ', 35).'</p></section>',
                '<section><h2>Documents a conserver</h2><p>'.str_repeat('Documents, preuves et traces utiles. ', 30).'</p></section>',
            ]),
            'faq_json' => array_fill(0, 5, ['question' => 'Q', 'answer' => 'R']),
            'internal_links_json' => [],
            'status' => 'published',
            'seo_score' => 70,
            'quality_score' => 90,
            'indexability_score' => 72,
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

        $response = $this
            ->withSession(['admin_authenticated' => true])
            ->post(route('admin.sites.gsc-opportunities.run', $site->site_id), [
                'page_id' => $page->id,
                'type' => 'low_ctr',
            ]);

        $response->assertRedirect(route('admin.pages.show', [$site->site_id, $page->id]));

        $this->assertDatabaseHas('seo_suggestions', [
            'seo_page_id' => $page->id,
            'source' => 'rewrite_engine:improve-ctr',
            'status' => 'pending',
        ]);

        $suggestion = SeoSuggestion::query()->where('seo_page_id', $page->id)->latest('id')->first();
        $this->assertNotNull($suggestion);
        $this->assertSame('improve-ctr', $suggestion->suggestions_json['mode']);
        $this->assertSame('low_ctr', $suggestion->signals_json['gsc_trigger']['type']);
    }

    public function test_site_gsc_opportunity_does_not_duplicate_an_existing_pending_suggestion(): void
    {
        $site = SeoSite::query()->create([
            'site_id' => 'site-runtime',
            'name' => 'Site Runtime',
            'url' => 'https://runtime-site.test',
            'niche' => 'amiante',
            'locale' => 'fr',
            'preset' => 'amiantix',
            'api_token_hash' => hash('sha256', 'token'),
            'is_active' => true,
        ]);

        SeoSiteGoogleConnection::query()->create([
            'site_id' => $site->site_id,
            'connection_mode' => 'service_account',
            'property_url' => 'sc-domain:runtime-site.test',
            'google_account_email' => 'svc@runtime-site.test',
            'credentials_path' => '/var/www/runtime-site.json',
            'connection_status' => 'configured',
        ]);

        $page = SeoPage::query()->create([
            'site_id' => $site->site_id,
            'keyword' => 'diagnostic amiante paris',
            'slug' => 'diagnostic-amiante-paris',
            'title' => 'Diagnostic amiante Paris',
            'content' => '<section><h2>Contexte</h2><p>'.str_repeat('Contenu utile. ', 80).'</p></section>',
            'faq_json' => array_fill(0, 5, ['question' => 'Q', 'answer' => 'R']),
            'internal_links_json' => [],
            'status' => 'published',
            'seo_score' => 72,
            'quality_score' => 92,
            'indexability_score' => 72,
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

        SeoSuggestion::query()->create([
            'seo_page_id' => $page->id,
            'source' => 'rewrite_engine:improve-ctr',
            'signals_json' => [
                'gsc_trigger' => [
                    'type' => 'low_ctr',
                    'mode' => 'improve-ctr',
                ],
            ],
            'suggestions_json' => [
                'mode' => 'improve-ctr',
            ],
            'status' => 'pending',
        ]);

        $response = $this
            ->withSession(['admin_authenticated' => true])
            ->post(route('admin.sites.gsc-opportunities.run', $site->site_id), [
                'page_id' => $page->id,
                'type' => 'low_ctr',
            ]);

        $response->assertRedirect(route('admin.pages.show', [$site->site_id, $page->id]));
        $response->assertSessionHas('warning');
        $this->assertDatabaseCount('seo_suggestions', 1);
    }

    public function test_site_gsc_opportunity_respects_recent_cooldown_even_after_a_previous_non_pending_attempt(): void
    {
        $site = SeoSite::query()->create([
            'site_id' => 'site-runtime',
            'name' => 'Site Runtime',
            'url' => 'https://runtime-site.test',
            'niche' => 'amiante',
            'locale' => 'fr',
            'preset' => 'amiantix',
            'api_token_hash' => hash('sha256', 'token'),
            'is_active' => true,
        ]);

        SeoSiteGoogleConnection::query()->create([
            'site_id' => $site->site_id,
            'connection_mode' => 'service_account',
            'property_url' => 'sc-domain:runtime-site.test',
            'google_account_email' => 'svc@runtime-site.test',
            'credentials_path' => '/var/www/runtime-site.json',
            'connection_status' => 'configured',
        ]);

        $page = SeoPage::query()->create([
            'site_id' => $site->site_id,
            'keyword' => 'diagnostic amiante paris',
            'slug' => 'diagnostic-amiante-paris',
            'title' => 'Diagnostic amiante Paris',
            'content' => '<section><h2>Contexte</h2><p>'.str_repeat('Contenu utile. ', 80).'</p></section>',
            'faq_json' => array_fill(0, 5, ['question' => 'Q', 'answer' => 'R']),
            'internal_links_json' => [],
            'status' => 'published',
            'seo_score' => 72,
            'quality_score' => 92,
            'indexability_score' => 72,
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

        SeoSuggestion::query()->create([
            'seo_page_id' => $page->id,
            'source' => 'rewrite_engine:improve-ctr',
            'signals_json' => [
                'gsc_trigger' => [
                    'type' => 'low_ctr',
                    'mode' => 'improve-ctr',
                ],
            ],
            'suggestions_json' => [
                'mode' => 'improve-ctr',
            ],
            'status' => 'applied',
            'applied_at' => now()->subDays(2),
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDays(2),
        ]);

        $response = $this
            ->withSession(['admin_authenticated' => true])
            ->post(route('admin.sites.gsc-opportunities.run', $site->site_id), [
                'page_id' => $page->id,
                'type' => 'low_ctr',
            ]);

        $response->assertRedirect(route('admin.pages.show', [$site->site_id, $page->id]));
        $response->assertSessionHas('warning');
        $this->assertDatabaseCount('seo_suggestions', 1);
    }

    public function test_site_connect_page_exposes_official_installer_downloads(): void
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
            'settings_json' => [
                'publication' => [
                    'mode' => 'symfony_bridge',
                    'connect_code' => 'ABCD-EFGH-IJKL',
                    'bridge_status' => 'pending',
                ],
            ],
        ]);

        $showResponse = $this
            ->withSession(['admin_authenticated' => true])
            ->get(route('admin.sites.show', $site->site_id));

        $showResponse->assertOk();
        $showResponse->assertSee('Télécharger l’installateur');

        $connectResponse = $this
            ->withSession(['admin_authenticated' => true])
            ->get(route('admin.sites.connect', $site->site_id));

        $connectResponse->assertOk();
        $connectResponse->assertSee('Télécharger l’installateur PraeviSEO');
        $connectResponse->assertSee('ABCD-EFGH-IJKL');
        $connectResponse->assertSee('Windows');
        $connectResponse->assertSee('Linux / Mac');

        $windowsDownload = $this
            ->withSession(['admin_authenticated' => true])
            ->get(route('admin.sites.connect.installer', [$site->site_id, 'windows']));

        $windowsDownload->assertOk();
        $windowsDownload->assertDownload('praeviseo-install.ps1');

        $unixDownload = $this
            ->withSession(['admin_authenticated' => true])
            ->get(route('admin.sites.connect.installer', [$site->site_id, 'unix']));

        $unixDownload->assertOk();
        $unixDownload->assertDownload('praeviseo-install.sh');
    }
}
