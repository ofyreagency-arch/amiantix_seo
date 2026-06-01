<?php

declare(strict_types=1);

namespace App\Runtime;

use App\Models\SeoPage;
use App\Models\SeoSearchConsoleMetric;
use App\Models\SeoSite;
use Illuminate\Support\Collection;

class PremiumArticleGenerationService
{
    /**
     * @return array{
     *   min_hours_between_articles:int,
     *   max_articles_per_28_days:int,
     *   minimum_query_impressions:int,
     *   maximum_query_position:float
     * }
     */
    public function policyFor(SeoSite $site): array
    {
        $settings = data_get($site->settings_json, 'automation.autoblog', []);
        $settings = is_array($settings) ? $settings : [];

        return [
            'min_hours_between_articles' => max(12, (int) ($settings['min_hours_between_articles'] ?? 72)),
            'max_articles_per_28_days' => max(1, (int) ($settings['max_articles_per_28_days'] ?? 3)),
            'minimum_query_impressions' => max(1, (int) ($settings['minimum_query_impressions'] ?? 3)),
            'maximum_query_position' => max(5.0, (float) ($settings['maximum_query_position'] ?? 35.0)),
        ];
    }

    public function canGenerate(SeoSite $site): bool
    {
        $policy = $this->policyFor($site);

        return $this->limitReason($site, $policy) === null;
    }

    public function limitReason(SeoSite $site, ?array $policy = null): ?string
    {
        $policy ??= $this->policyFor($site);
        $generatedQuery = SeoPage::query()
            ->where('site_id', $site->site_id)
            ->whereNotNull('generation_source');

        $recentGeneratedCount = (clone $generatedQuery)
            ->where('created_at', '>=', now()->subDays(28))
            ->count();

        if ($recentGeneratedCount >= $policy['max_articles_per_28_days']) {
            return 'PraeviSEO a déjà ouvert assez de nouveaux articles sur les 28 derniers jours pour ce site.';
        }

        /** @var SeoPage|null $lastGenerated */
        $lastGenerated = (clone $generatedQuery)
            ->orderByDesc('published_at')
            ->orderByDesc('created_at')
            ->first(['published_at', 'created_at']);

        if ($lastGenerated) {
            $referenceDate = $lastGenerated->published_at ?? $lastGenerated->created_at;

            if ($referenceDate && $referenceDate->gt(now()->subHours($policy['min_hours_between_articles']))) {
                return 'PraeviSEO attend encore avant d ouvrir un nouveau sujet pour éviter de publier trop vite.';
            }
        }

        return null;
    }

    public function resolveCandidateKeyword(SeoSite $site): ?string
    {
        $policy = $this->policyFor($site);

        if ($this->limitReason($site, $policy) !== null) {
            return null;
        }

        $existingTokens = SeoPage::query()
            ->where('site_id', $site->site_id)
            ->get(['keyword', 'slug', 'title'])
            ->flatMap(function (SeoPage $page): array {
                return array_filter([
                    mb_strtolower(trim((string) $page->keyword)),
                    mb_strtolower(trim((string) $page->slug)),
                    mb_strtolower(trim((string) $page->title)),
                ]);
            })
            ->values();

        $rows = SeoSearchConsoleMetric::query()
            ->where('site_id', $site->site_id)
            ->whereNotNull('query')
            ->where('window_days', 28)
            ->orderByDesc('metric_date')
            ->orderByDesc('id')
            ->get(['metric_date', 'query', 'clicks', 'impressions', 'position']);

        if ($rows->isEmpty()) {
            return null;
        }

        $latestDate = optional($rows->first()?->metric_date)?->toDateString();
        if (! $latestDate) {
            return null;
        }

        $currentRows = $rows->filter(fn (SeoSearchConsoleMetric $metric): bool => $metric->metric_date?->toDateString() === $latestDate);
        $previousRows = $rows->filter(fn (SeoSearchConsoleMetric $metric): bool => $metric->metric_date?->toDateString() !== $latestDate);

        $previousByQuery = $previousRows
            ->groupBy(fn (SeoSearchConsoleMetric $metric): string => mb_strtolower(trim((string) $metric->query)))
            ->map(fn (Collection $items): int => (int) round($items->sum('impressions')));

        $candidates = $currentRows
            ->groupBy(fn (SeoSearchConsoleMetric $metric): string => trim((string) $metric->query))
            ->map(function (Collection $items, string $query) use ($previousByQuery): array {
                $impressions = (int) round($items->sum('impressions'));
                $position = $impressions > 0
                    ? $items->reduce(
                        fn (float $carry, SeoSearchConsoleMetric $metric): float => $carry + (((float) $metric->position) * ((float) $metric->impressions)),
                        0.0
                    ) / $impressions
                    : 0.0;

                return [
                    'query' => $query,
                    'impressions' => $impressions,
                    'previous_impressions' => (int) ($previousByQuery->get(mb_strtolower($query)) ?? 0),
                    'position' => round($position, 1),
                ];
            })
            ->filter(function (array $item) use ($policy): bool {
                return trim($item['query']) !== ''
                    && $item['impressions'] >= $policy['minimum_query_impressions']
                    && ((float) $item['position']) > 0
                    && ((float) $item['position']) <= $policy['maximum_query_position'];
            })
            ->sortByDesc(function (array $item): int {
                $newQueryBonus = $item['previous_impressions'] === 0 ? 300 : 0;

                return $newQueryBonus + ((int) $item['impressions'] * 100) - (int) round(((float) $item['position']) * 5);
            })
            ->values();

        foreach ($candidates as $candidate) {
            $query = mb_strtolower(trim((string) $candidate['query']));

            if ($query === '' || mb_strlen($query) < 4) {
                continue;
            }

            $alreadyCovered = $existingTokens->contains(function (string $token) use ($query): bool {
                return $token === $query || str_contains($token, $query) || str_contains($query, $token);
            });

            if (! $alreadyCovered) {
                return (string) $candidate['query'];
            }
        }

        return null;
    }
}
