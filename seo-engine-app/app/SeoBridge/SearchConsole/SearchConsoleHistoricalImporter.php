<?php

declare(strict_types=1);

namespace App\SeoBridge\SearchConsole;

use App\Models\SeoPage;
use App\Models\SeoSearchConsoleMetric;
use Illuminate\Support\Str;
use Ofyre\SeoEngine\Contracts\HistoricalSeoImporter;
use Ofyre\SeoEngine\Services\SearchConsole\SearchConsoleService;

class SearchConsoleHistoricalImporter implements HistoricalSeoImporter
{
    public function __construct(
        private readonly SearchConsoleService $searchConsole,
    ) {}

    public function import(array $windows = [7, 28, 90, 180, 365], int $limit = 250): array
    {
        $pageRows = 0;
        $queryRows = 0;

        foreach ($windows as $window) {
            $date = now()->toDateString();
            $topPages = $this->searchConsole->getTopPages($window, $limit);
            $topQueryPages = $this->searchConsole->getTopQueryPages($window, $limit);

            foreach ($topPages as $row) {
                $page = $this->pageForUrl($row['url']);

                SeoSearchConsoleMetric::query()->updateOrCreate(
                    [
                        'metric_date' => $date,
                        'window_days' => $window,
                        'query' => null,
                        'url' => $row['url'],
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

                if ($page) {
                    $page->forceFill([
                        'published_at' => $page->published_at ?? now(),
                    ])->save();
                }

                $pageRows++;
            }

            foreach ($topQueryPages as $row) {
                $page = $this->pageForUrl($row['url']);

                SeoSearchConsoleMetric::query()->updateOrCreate(
                    [
                        'metric_date' => $date,
                        'window_days' => $window,
                        'query' => $row['query'],
                        'url' => $row['url'],
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

    private function pageForUrl(string $url): ?SeoPage
    {
        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');
        $slug = Str::of($path)->trim('/')->value();

        if ($slug === '') {
            return null;
        }

        return SeoPage::query()->where('slug', $slug)->first();
    }
}
