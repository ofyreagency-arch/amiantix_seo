<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\SeoPage;
use App\Models\SeoSearchConsoleMetric;
use App\Models\SeoSite;
use App\Models\SeoSiteGoogleConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Ofyre\SeoEngine\Services\SearchConsole\SearchConsoleService;
use Tests\TestCase;

class MultiSiteSearchConsoleImportTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_import_history_populates_metrics_for_each_active_gsc_site(): void
    {
        [$siteA, $siteB, $pageA, $pageB] = $this->seedTwoGscSites();

        $searchConsole = Mockery::mock(SearchConsoleService::class);
        $searchConsole->shouldReceive('getTopPages')->andReturnUsing(function (): array {
            return match (config('services.google_search_console.site_url')) {
                'sc-domain:alpha.test' => [[
                    'url' => 'https://alpha.test/page-alpha',
                    'clicks' => 12.0,
                    'impressions' => 180.0,
                    'ctr' => 0.066,
                    'position' => 8.3,
                ]],
                'sc-domain:beta.test' => [[
                    'url' => 'https://beta.test/page-beta',
                    'clicks' => 8.0,
                    'impressions' => 130.0,
                    'ctr' => 0.061,
                    'position' => 10.2,
                ]],
                default => [],
            };
        });
        $searchConsole->shouldReceive('getTopQueryPages')->andReturnUsing(function (): array {
            return match (config('services.google_search_console.site_url')) {
                'sc-domain:alpha.test' => [[
                    'query' => 'amiante alpha',
                    'url' => 'https://alpha.test/page-alpha',
                    'clicks' => 6.0,
                    'impressions' => 70.0,
                    'ctr' => 0.085,
                    'position' => 7.9,
                ]],
                'sc-domain:beta.test' => [[
                    'query' => 'amiante beta',
                    'url' => 'https://beta.test/page-beta',
                    'clicks' => 5.0,
                    'impressions' => 55.0,
                    'ctr' => 0.09,
                    'position' => 9.4,
                ]],
                default => [],
            };
        });
        $searchConsole->shouldReceive('analyticsDebugSnapshot')->andReturnUsing(function (): array {
            return match (config('services.google_search_console.site_url')) {
                'sc-domain:alpha.test' => [
                    'top_pages' => ['status' => 'ok', 'http_code' => 200, 'row_count' => 1],
                    'top_query_pages' => ['status' => 'ok', 'http_code' => 200, 'row_count' => 1],
                ],
                'sc-domain:beta.test' => [
                    'top_pages' => ['status' => 'ok', 'http_code' => 200, 'row_count' => 1],
                    'top_query_pages' => ['status' => 'ok', 'http_code' => 200, 'row_count' => 1],
                ],
                default => [],
            };
        });

        $this->app->instance(SearchConsoleService::class, $searchConsole);

        $this->artisan('seo:import-history', [
            '--windows' => '7',
            '--limit' => 10,
        ])->assertExitCode(0);

        $this->assertDatabaseHas('seo_search_console_metrics', [
            'site_id' => $siteA->site_id,
            'seo_page_id' => $pageA->id,
            'query' => null,
            'url' => 'https://alpha.test/page-alpha',
        ]);
        $this->assertDatabaseHas('seo_search_console_metrics', [
            'site_id' => $siteA->site_id,
            'seo_page_id' => $pageA->id,
            'query' => 'amiante alpha',
            'url' => 'https://alpha.test/page-alpha',
        ]);
        $this->assertDatabaseHas('seo_search_console_metrics', [
            'site_id' => $siteB->site_id,
            'seo_page_id' => $pageB->id,
            'query' => null,
            'url' => 'https://beta.test/page-beta',
        ]);
        $this->assertDatabaseHas('seo_search_console_metrics', [
            'site_id' => $siteB->site_id,
            'seo_page_id' => $pageB->id,
            'query' => 'amiante beta',
            'url' => 'https://beta.test/page-beta',
        ]);

        $this->assertDatabaseHas('seo_site_google_connections', [
            'site_id' => $siteA->site_id,
            'connection_status' => 'connected',
            'last_error' => null,
        ]);
        $this->assertDatabaseHas('seo_site_google_connections', [
            'site_id' => $siteB->site_id,
            'connection_status' => 'connected',
            'last_error' => null,
        ]);

        $this->assertNotNull(SeoSiteGoogleConnection::query()->where('site_id', $siteA->site_id)->value('last_sync_at'));
        $this->assertNotNull(SeoSiteGoogleConnection::query()->where('site_id', $siteB->site_id)->value('last_sync_at'));
        $this->assertSame(
            'connected_with_data',
            SeoSiteGoogleConnection::query()->where('site_id', $siteA->site_id)->value('meta_json')['last_sync']['status'] ?? null
        );
        $this->assertSame(
            1,
            SeoSiteGoogleConnection::query()->where('site_id', $siteA->site_id)->value('meta_json')['last_sync']['analytics']['top_pages']['row_count'] ?? null
        );
    }

    public function test_import_history_marks_site_error_without_blocking_other_sites(): void
    {
        [$siteA, $siteB, $pageA] = $this->seedTwoGscSites();

        $searchConsole = Mockery::mock(SearchConsoleService::class);
        $searchConsole->shouldReceive('getTopPages')->andReturnUsing(function (): array {
            return match (config('services.google_search_console.site_url')) {
                'sc-domain:alpha.test' => [[
                    'url' => 'https://alpha.test/page-alpha',
                    'clicks' => 12.0,
                    'impressions' => 180.0,
                    'ctr' => 0.066,
                    'position' => 8.3,
                ]],
                'sc-domain:beta.test' => throw new \RuntimeException('GSC access denied for beta'),
                default => [],
            };
        });
        $searchConsole->shouldReceive('getTopQueryPages')->andReturnUsing(function (): array {
            return match (config('services.google_search_console.site_url')) {
                'sc-domain:alpha.test' => [[
                    'query' => 'amiante alpha',
                    'url' => 'https://alpha.test/page-alpha',
                    'clicks' => 6.0,
                    'impressions' => 70.0,
                    'ctr' => 0.085,
                    'position' => 7.9,
                ]],
                default => [],
            };
        });
        $searchConsole->shouldReceive('analyticsDebugSnapshot')->andReturnUsing(function (): array {
            return match (config('services.google_search_console.site_url')) {
                'sc-domain:alpha.test' => [
                    'top_pages' => ['status' => 'ok', 'http_code' => 200, 'row_count' => 1],
                    'top_query_pages' => ['status' => 'ok', 'http_code' => 200, 'row_count' => 1],
                ],
                'sc-domain:beta.test' => [
                    'top_pages' => ['status' => 'http_error', 'http_code' => 403, 'row_count' => 0, 'reason' => 'forbidden'],
                ],
                default => [],
            };
        });

        $this->app->instance(SearchConsoleService::class, $searchConsole);

        $this->artisan('seo:import-history', [
            '--windows' => '7',
            '--limit' => 10,
        ])->assertExitCode(0);

        $this->assertDatabaseHas('seo_search_console_metrics', [
            'site_id' => $siteA->site_id,
            'seo_page_id' => $pageA->id,
            'query' => null,
            'url' => 'https://alpha.test/page-alpha',
        ]);

        $this->assertSame(0, SeoSearchConsoleMetric::query()->where('site_id', $siteB->site_id)->count());

        $this->assertDatabaseHas('seo_site_google_connections', [
            'site_id' => $siteA->site_id,
            'connection_status' => 'connected',
        ]);
        $this->assertDatabaseHas('seo_site_google_connections', [
            'site_id' => $siteB->site_id,
            'connection_status' => 'error',
        ]);

        $this->assertSame(
            'GSC access denied for beta',
            SeoSiteGoogleConnection::query()->where('site_id', $siteB->site_id)->value('last_error')
        );
    }

    public function test_import_history_marks_connected_but_empty_when_api_returns_no_rows(): void
    {
        [$siteA] = $this->seedTwoGscSites();

        $searchConsole = Mockery::mock(SearchConsoleService::class);
        $searchConsole->shouldReceive('getTopPages')->andReturn([]);
        $searchConsole->shouldReceive('getTopQueryPages')->andReturn([]);
        $searchConsole->shouldReceive('analyticsDebugSnapshot')->andReturn([
            'top_pages' => ['status' => 'ok_empty', 'http_code' => 200, 'row_count' => 0, 'reason' => 'empty_rows'],
            'top_query_pages' => ['status' => 'ok_empty', 'http_code' => 200, 'row_count' => 0, 'reason' => 'empty_rows'],
        ]);

        $this->app->instance(SearchConsoleService::class, $searchConsole);

        $this->artisan('seo:import-history', [
            '--windows' => '7',
            '--limit' => 10,
        ])->assertExitCode(0);

        $connection = SeoSiteGoogleConnection::query()->where('site_id', $siteA->site_id)->firstOrFail();

        $this->assertSame('connected_empty', $connection->connection_status);
        $this->assertSame('connected_but_empty', $connection->meta_json['last_sync']['status'] ?? null);
        $this->assertSame(0, $connection->meta_json['last_sync']['pages'] ?? null);
        $this->assertSame(0, $connection->meta_json['last_sync']['queries'] ?? null);
        $this->assertSame('ok_empty', $connection->meta_json['last_sync']['analytics']['top_pages']['status'] ?? null);
        $this->assertNull($connection->last_error);
    }

    /**
     * @return array{0:SeoSite,1:SeoSite,2:SeoPage,3:SeoPage}
     */
    private function seedTwoGscSites(): array
    {
        $siteA = SeoSite::query()->create([
            'site_id' => 'site-alpha',
            'name' => 'Alpha',
            'url' => 'https://alpha.test',
            'niche' => 'amiante',
            'locale' => 'fr',
            'preset' => 'amiantix',
            'api_token_hash' => hash('sha256', 'alpha-token'),
            'is_active' => true,
        ]);

        $siteB = SeoSite::query()->create([
            'site_id' => 'site-beta',
            'name' => 'Beta',
            'url' => 'https://beta.test',
            'niche' => 'amiante',
            'locale' => 'fr',
            'preset' => 'amiantix',
            'api_token_hash' => hash('sha256', 'beta-token'),
            'is_active' => true,
        ]);

        SeoSiteGoogleConnection::query()->create([
            'site_id' => $siteA->site_id,
            'connection_mode' => 'service_account',
            'property_url' => 'sc-domain:alpha.test',
            'google_account_email' => 'svc@alpha.test',
            'credentials_path' => '/var/www/alpha.json',
            'connection_status' => 'configured',
        ]);

        SeoSiteGoogleConnection::query()->create([
            'site_id' => $siteB->site_id,
            'connection_mode' => 'service_account',
            'property_url' => 'sc-domain:beta.test',
            'google_account_email' => 'svc@beta.test',
            'credentials_path' => '/var/www/beta.json',
            'connection_status' => 'configured',
        ]);

        $pageA = SeoPage::query()->create([
            'site_id' => $siteA->site_id,
            'keyword' => 'amiante alpha',
            'slug' => 'page-alpha',
            'title' => 'Page alpha',
            'status' => 'published',
        ]);

        $pageB = SeoPage::query()->create([
            'site_id' => $siteB->site_id,
            'keyword' => 'amiante beta',
            'slug' => 'page-beta',
            'title' => 'Page beta',
            'status' => 'published',
        ]);

        return [$siteA, $siteB, $pageA, $pageB];
    }
}
