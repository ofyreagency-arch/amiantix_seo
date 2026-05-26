<?php

declare(strict_types=1);

namespace App\SeoBridge\SearchConsole;

use App\Models\SeoPage;
use App\Models\SeoSearchConsoleMetric;
use App\Models\SeoSitePage;
use App\Runtime\SeoEngineContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Ofyre\SeoEngine\Contracts\HistoricalSeoImporter;
use Ofyre\SeoEngine\Services\SearchConsole\SearchConsoleService;

class SearchConsoleHistoricalImporter implements HistoricalSeoImporter
{
    public function __construct(
        private readonly SearchConsoleService $searchConsole,
        private readonly SeoEngineContext $context,
    ) {}

    public function import(array $windows = [7, 28, 90, 180, 365], int $limit = 250): array
    {
        $pageRows = 0;
        $queryRows = 0;
        $inspectionCache = [];

        foreach ($windows as $window) {
            $date = now()->toDateString();
            $siteTotals = $this->searchConsole->getSiteTotals($window);
            $topPages = $this->searchConsole->getTopPages($window, $limit);
            $topQueryPages = $this->searchConsole->getTopQueryPages($window, $limit);
            $topPagesByUrl = collect($topPages)
                ->filter(fn (array $row): bool => trim((string) ($row['url'] ?? '')) !== '')
                ->keyBy(fn (array $row): string => rtrim((string) $row['url'], '/'));

            SeoSearchConsoleMetric::query()->updateOrCreate(
                [
                    'site_id' => $this->context->siteId(),
                    'metric_date' => $date,
                    'window_days' => $window,
                    'query' => null,
                    'url' => null,
                ],
                [
                    'seo_page_id' => null,
                    'clicks' => $siteTotals['clicks'],
                    'impressions' => $siteTotals['impressions'],
                    'ctr' => $siteTotals['ctr'],
                    'position' => $siteTotals['position'],
                    'payload_json' => [
                        'scope' => 'site_totals',
                        'window_days' => $window,
                    ],
                ]
            );

            foreach ($this->inspectionUrls($topPagesByUrl) as $url) {
                $inspection = $inspectionCache[$url] ??= $this->searchConsole->inspectPageUrl($url);
                $page = $this->pageForUrl($url);
                $analyticsRow = $topPagesByUrl->get(rtrim($url, '/'), []);

                SeoSearchConsoleMetric::query()->updateOrCreate(
                    [
                        'site_id'     => $this->context->siteId(),
                        'metric_date' => $date,
                        'window_days' => $window,
                        'query'       => null,
                        'url'         => $url,
                    ],
                    [
                        'seo_page_id' => $page?->id,
                        'clicks' => (float) ($analyticsRow['clicks'] ?? 0),
                        'impressions' => (float) ($analyticsRow['impressions'] ?? 0),
                        'ctr' => (float) ($analyticsRow['ctr'] ?? 0),
                        'position' => (float) ($analyticsRow['position'] ?? 0),
                        'is_indexed' => $inspection['indexed'],
                        'coverage_json' => $inspection['coverage'],
                        'payload_json' => array_filter([
                            'analytics' => $analyticsRow !== [] ? $analyticsRow : null,
                            'inspection' => $inspection['raw'] !== [] ? $inspection['raw'] : null,
                        ]),
                    ]
                );

                if ($page) {
                    $page->forceFill([
                        'published_at' => $page->published_at ?? now(),
                        'is_indexed' => $inspection['indexed'] ?? $page->is_indexed,
                    ])->save();
                }

                if ($analyticsRow !== []) {
                    $pageRows++;
                }
            }

            foreach ($topQueryPages as $row) {
                $page = $this->pageForUrl($row['url']);

                SeoSearchConsoleMetric::query()->updateOrCreate(
                    [
                        'site_id'     => $this->context->siteId(),
                        'metric_date' => $date,
                        'window_days' => $window,
                        'query'       => $row['query'],
                        'url'         => $row['url'],
                    ],
                    [
                        'seo_page_id' => $page?->id,
                        'clicks' => $row['clicks'],
                        'impressions' => $row['impressions'],
                        'ctr' => $row['ctr'],
                        'position' => $row['position'],
                        'payload_json' => $row,
                    ]
                );

                $queryRows++;
            }
        }

        return [
            'windows' => count($windows),
            'pages' => $pageRows,
            'queries' => $queryRows,
        ];
    }

    /**
     * @param  Collection<string,array<string,mixed>>  $topPagesByUrl
     * @return array<int,string>
     */
    private function inspectionUrls(Collection $topPagesByUrl): array
    {
        $observedUrls = SeoSitePage::query()
            ->where('site_id', $this->context->siteId())
            ->whereNotNull('normalized_url')
            ->pluck('normalized_url')
            ->map(fn (mixed $url): string => rtrim((string) $url, '/'))
            ->filter()
            ->values()
            ->all();

        $engineUrls = SeoPage::query()
            ->where('site_id', $this->context->siteId())
            ->whereNotNull('slug')
            ->get(['slug'])
            ->map(function (SeoPage $page): string {
                return rtrim(rtrim((string) config('app.url'), '/').$page->canonicalPath(), '/');
            })
            ->filter()
            ->values()
            ->all();

        return collect(array_merge($observedUrls, $engineUrls, $topPagesByUrl->keys()->all()))
            ->map(fn (string $url): string => rtrim($url, '/'))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function pageForUrl(string $url): ?SeoPage
    {
        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');
        $slug = Str::of($path)->trim('/')->value();

        if ($slug === '') {
            return null;
        }

        return SeoPage::query()
            ->where('site_id', $this->context->siteId())
            ->where('slug', $slug)
            ->first();
    }
}
