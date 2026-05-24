<?php

declare(strict_types=1);

namespace Ofyre\SeoEngine\Services\SearchConsole;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Ofyre\SeoEngine\Contracts\SearchConsoleTokenProvider;

class SearchConsoleService
{
    /** @var array<string,array<string,mixed>> */
    private array $analyticsDebug = [];

    public function __construct(private readonly SearchConsoleTokenProvider $tokenProvider) {}

    /**
     * @return array<int, array{query:string,clicks:float,impressions:float,ctr:float,position:float}>
     */
    public function getTopQueries(int $days = 30, int $limit = 25): array
    {
        return collect($this->queryAnalytics(['query'], $days, $limit))
            ->map(static fn (array $row): array => [
                'query' => (string) ($row['keys'][0] ?? ''),
                'clicks' => (float) ($row['clicks'] ?? 0),
                'impressions' => (float) ($row['impressions'] ?? 0),
                'ctr' => (float) ($row['ctr'] ?? 0),
                'position' => (float) ($row['position'] ?? 0),
            ])
            ->filter(static fn (array $row): bool => $row['query'] !== '')
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{url:string,clicks:float,impressions:float,ctr:float,position:float}>
     */
    public function getTopPages(int $days = 28, int $limit = 250, int $endOffsetDays = 2): array
    {
        return collect($this->queryAnalytics(['page'], $days, $limit, endOffsetDays: $endOffsetDays))
            ->map(static fn (array $row): array => [
                'url' => (string) ($row['keys'][0] ?? ''),
                'clicks' => (float) ($row['clicks'] ?? 0),
                'impressions' => (float) ($row['impressions'] ?? 0),
                'ctr' => (float) ($row['ctr'] ?? 0),
                'position' => (float) ($row['position'] ?? 0),
            ])
            ->filter(static fn (array $row): bool => $row['url'] !== '')
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{query:string,url:string,clicks:float,impressions:float,ctr:float,position:float}>
     */
    public function getTopQueryPages(int $days = 28, int $limit = 250, int $endOffsetDays = 2): array
    {
        return collect($this->queryAnalytics(['query', 'page'], $days, $limit, endOffsetDays: $endOffsetDays))
            ->map(static fn (array $row): array => [
                'query' => (string) ($row['keys'][0] ?? ''),
                'url' => (string) ($row['keys'][1] ?? ''),
                'clicks' => (float) ($row['clicks'] ?? 0),
                'impressions' => (float) ($row['impressions'] ?? 0),
                'ctr' => (float) ($row['ctr'] ?? 0),
                'position' => (float) ($row['position'] ?? 0),
            ])
            ->filter(static fn (array $row): bool => $row['query'] !== '' && $row['url'] !== '')
            ->values()
            ->all();
    }

    /**
     * @return array{impressions:int,ctr:float,position:float,queries:array<int,string>,indexed:bool|null,coverage:array<int,string>}
     */
    public function pageMetrics(object $page): array
    {
        $empty = [
            'impressions' => 0,
            'ctr' => 0.0,
            'position' => 0.0,
            'queries' => [],
            'indexed' => null,
            'coverage' => [],
        ];

        $token = $this->accessToken();
        $siteUrl = config('services.google_search_console.site_url', config('seo-engine.search_console.site_url'));

        if (! $token || ! $siteUrl) {
            return $empty;
        }

        $pageUrl = rtrim((string) config('app.url', config('seo-engine.site.url')), '/').$this->canonicalPath($page);
        $inspection = $this->inspectPageUrl($pageUrl);

        $rows = $this->queryAnalytics(
            ['query'],
            30,
            25,
            [[
                'filters' => [[
                    'dimension' => 'page',
                    'operator' => 'equals',
                    'expression' => $pageUrl,
                ]],
            ]]
        );

        if ($rows === []) {
            return array_merge($empty, [
                'indexed' => $inspection['indexed'],
                'coverage' => $inspection['coverage'],
            ]);
        }

        $clicks = 0.0;
        $impressions = 0.0;
        $weightedPosition = 0.0;
        $queries = [];

        foreach ($rows as $row) {
            $rowImpressions = (float) ($row['impressions'] ?? 0);
            $clicks += (float) ($row['clicks'] ?? 0);
            $impressions += $rowImpressions;
            $weightedPosition += ((float) ($row['position'] ?? 0)) * $rowImpressions;
            $queries[] = (string) ($row['keys'][0] ?? '');
        }

        return [
            'impressions' => (int) $impressions,
            'ctr' => $impressions > 0 ? $clicks / $impressions : 0.0,
            'position' => $impressions > 0 ? $weightedPosition / $impressions : 0.0,
            'queries' => array_values(array_filter($queries)),
            'indexed' => $inspection['indexed'],
            'coverage' => $inspection['coverage'],
        ];
    }

    /**
     * @return array{indexed:bool|null,coverage:array<int,string>,coverage_state:?string,canonical:?string,last_crawl_at:?string,robots:?string,indexing_state:?string,page_fetch_state:?string,raw:array<string,mixed>}
     */
    public function inspectPageUrl(string $pageUrl): array
    {
        $token = $this->accessToken();
        $siteUrl = config('services.google_search_console.site_url', config('seo-engine.search_console.site_url'));

        if (! $token || ! $siteUrl) {
            return $this->emptyInspection();
        }

        $response = Http::withToken($token)
            ->acceptJson()
            ->post('https://searchconsole.googleapis.com/v1/urlInspection/index:inspect', [
                'inspectionUrl' => $pageUrl,
                'siteUrl' => (string) $siteUrl,
                'languageCode' => $this->languageCode(),
            ]);

        if (! $response->successful()) {
            return $this->emptyInspection();
        }

        $indexStatus = $response->json('inspectionResult.indexStatusResult', []);
        $verdict = (string) ($indexStatus['verdict'] ?? '');
        $coverageState = (string) ($indexStatus['coverageState'] ?? '');
        $robotsTxtState = (string) ($indexStatus['robotsTxtState'] ?? '');
        $indexingState = (string) ($indexStatus['indexingState'] ?? '');
        $pageFetchState = (string) ($indexStatus['pageFetchState'] ?? '');
        $googleCanonical = (string) ($indexStatus['googleCanonical'] ?? '');
        $userCanonical = (string) ($indexStatus['userCanonical'] ?? '');
        $lastCrawlTime = (string) ($indexStatus['lastCrawlTime'] ?? '');
        $coverage = array_values(array_filter([
            $verdict !== '' ? 'index_verdict:'.$verdict : null,
            $coverageState !== '' ? 'coverage_state:'.$coverageState : null,
            $robotsTxtState !== '' ? 'robots_txt:'.$robotsTxtState : null,
            $indexingState !== '' ? 'indexing_state:'.$indexingState : null,
            $pageFetchState !== '' ? 'page_fetch:'.$pageFetchState : null,
        ]));

        return [
            'indexed' => $verdict === 'PASS',
            'coverage' => $coverage,
            'coverage_state' => $coverageState !== '' ? $coverageState : null,
            'canonical' => $googleCanonical !== '' ? $googleCanonical : ($userCanonical !== '' ? $userCanonical : null),
            'last_crawl_at' => $lastCrawlTime !== '' ? $lastCrawlTime : null,
            'robots' => $robotsTxtState !== '' ? $robotsTxtState : null,
            'indexing_state' => $indexingState !== '' ? $indexingState : null,
            'page_fetch_state' => $pageFetchState !== '' ? $pageFetchState : null,
            'raw' => $response->json(),
        ];
    }

    /**
     * @return array{indexed:bool|null,coverage:array<int,string>,coverage_state:?string,canonical:?string,last_crawl_at:?string,robots:?string,indexing_state:?string,page_fetch_state:?string,raw:array<string,mixed>}
     */
    protected function emptyInspection(): array
    {
        return [
            'indexed' => null,
            'coverage' => [],
            'coverage_state' => null,
            'canonical' => null,
            'last_crawl_at' => null,
            'robots' => null,
            'indexing_state' => null,
            'page_fetch_state' => null,
            'raw' => [],
        ];
    }

    protected function accessToken(): ?string
    {
        if (! config('services.google_search_console.enabled', config('seo-engine.search_console.enabled', false))) {
            return null;
        }

        $token = config('services.google_search_console.access_token', config('seo-engine.search_console.access_token'));

        if (is_string($token) && $token !== '') {
            return $token;
        }

        return $this->tokenProvider->accessToken();
    }

    protected function canonicalPath(object $page): string
    {
        if (method_exists($page, 'canonicalPath')) {
            return (string) $page->canonicalPath();
        }

        return '/'.ltrim((string) ($page->slug ?? ''), '/');
    }

    protected function languageCode(): string
    {
        $configured = (string) config('seo-engine.search_console.language_code', config('seo-engine.site.locale', config('app.locale', 'en')));
        $normalized = str_replace('_', '-', trim($configured));

        return $normalized !== '' ? $normalized : 'en';
    }

    /**
     * @param  array<int,string>  $dimensions
     * @param  array<int,array<string,mixed>>  $filterGroups
     * @return array<int, array<string,mixed>>
     */
    protected function queryAnalytics(
        array $dimensions,
        int $days,
        int $limit,
        array $filterGroups = [],
        int $endOffsetDays = 2,
    ): array {
        $token = $this->accessToken();
        $siteUrl = config('services.google_search_console.site_url', config('seo-engine.search_console.site_url'));
        $label = $this->analyticsLabel($dimensions);
        $startDate = now()->subDays($days + $endOffsetDays)->toDateString();
        $endDate = now()->subDays($endOffsetDays)->toDateString();

        if (! $token || ! $siteUrl) {
            $this->analyticsDebug[$label] = [
                'status' => 'skipped',
                'http_code' => null,
                'row_count' => 0,
                'site_url' => $siteUrl,
                'dimensions' => $dimensions,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'reason' => ! $token ? 'missing_token' : 'missing_site_url',
                'body_preview' => null,
            ];
            return [];
        }

        $payload = [
            'startDate' => $startDate,
            'endDate' => $endDate,
            'dimensions' => $dimensions,
            'rowLimit' => $limit,
        ];

        if ($filterGroups !== []) {
            $payload['dimensionFilterGroups'] = $filterGroups;
        }

        $response = Http::withToken($token)
            ->acceptJson()
            ->post('https://www.googleapis.com/webmasters/v3/sites/'.rawurlencode((string) $siteUrl).'/searchAnalytics/query', $payload);

        if (! $response->successful()) {
            $this->analyticsDebug[$label] = [
                'status' => 'http_error',
                'http_code' => $response->status(),
                'row_count' => 0,
                'site_url' => $siteUrl,
                'dimensions' => $dimensions,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'reason' => 'http_error',
                'body_preview' => Str::limit($response->body(), 500),
            ];
            return [];
        }

        $rows = Arr::wrap($response->json('rows'));
        $this->analyticsDebug[$label] = [
            'status' => $rows === [] ? 'ok_empty' : 'ok',
            'http_code' => $response->status(),
            'row_count' => count($rows),
            'site_url' => $siteUrl,
            'dimensions' => $dimensions,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'reason' => $rows === [] ? 'empty_rows' : null,
            'body_preview' => Str::limit($response->body(), 500),
        ];

        return $rows;
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    public function analyticsDebugSnapshot(): array
    {
        return $this->analyticsDebug;
    }

    /**
     * @param  array<int,string>  $dimensions
     */
    private function analyticsLabel(array $dimensions): string
    {
        return match (implode('|', $dimensions)) {
            'page' => 'top_pages',
            'query|page' => 'top_query_pages',
            'query' => 'top_queries',
            default => 'analytics',
        };
    }
}
