<?php

declare(strict_types=1);

namespace App\Runtime;

use App\Models\SeoPage;
use App\Models\SeoSearchConsoleMetric;
use App\Models\SeoSuggestion;
use Illuminate\Support\Collection;

class GscOpportunityService
{
    public const COOLDOWN_DAYS = 7;

    /**
     * @return array{
     *   connected:bool,
     *   summary:array{low_ctr:int,near_top_10:int,emerging_queries:int,sustained_drop:int,total:int},
     *   items:array<int,array<string,mixed>>
     * }
     */
    public function summarize(string $siteId, bool $hasConnection = false): array
    {
        $pages = SeoPage::query()
            ->where('site_id', $siteId)
            ->get(['id', 'keyword', 'slug', 'title', 'status'])
            ->keyBy('id');

        if ($pages->isEmpty()) {
            return $this->summarizeSiteUrls($siteId, $hasConnection);
        }

        $recentPageMetrics = SeoSearchConsoleMetric::query()
            ->whereIn('seo_page_id', $pages->keys())
            ->whereNull('query')
            ->where('metric_date', '>=', now()->subDays(30)->toDateString())
            ->orderByDesc('metric_date')
            ->get()
            ->groupBy('seo_page_id');

        $olderPageMetrics = SeoSearchConsoleMetric::query()
            ->whereIn('seo_page_id', $pages->keys())
            ->whereNull('query')
            ->whereBetween('metric_date', [
                now()->subDays(60)->toDateString(),
                now()->subDays(31)->toDateString(),
            ])
            ->get()
            ->groupBy('seo_page_id');

        $recentQueryMetrics = SeoSearchConsoleMetric::query()
            ->whereIn('seo_page_id', $pages->keys())
            ->whereNotNull('query')
            ->where('metric_date', '>=', now()->subDays(30)->toDateString())
            ->get()
            ->groupBy('seo_page_id');

        $recentSuggestions = SeoSuggestion::query()
            ->whereIn('seo_page_id', $pages->keys())
            ->where('created_at', '>=', now()->subDays(self::COOLDOWN_DAYS))
            ->get(['id', 'seo_page_id', 'source', 'status', 'signals_json', 'created_at'])
            ->groupBy('seo_page_id');

        $items = collect();

        foreach ($pages as $pageId => $page) {
            /** @var Collection<int, SeoSearchConsoleMetric> $pageRows */
            $pageRows = $recentPageMetrics->get($pageId, collect());
            /** @var Collection<int, SeoSearchConsoleMetric> $olderRows */
            $olderRows = $olderPageMetrics->get($pageId, collect());
            /** @var Collection<int, SeoSearchConsoleMetric> $queryRows */
            $queryRows = $recentQueryMetrics->get($pageId, collect());
            /** @var Collection<int, SeoSuggestion> $suggestionRows */
            $suggestionRows = $recentSuggestions->get($pageId, collect());

            $recentImpressions = (float) $pageRows->sum('impressions');
            $recentClicks = (float) $pageRows->sum('clicks');
            $recentCtr = $recentImpressions > 0 ? $recentClicks / $recentImpressions : 0.0;
            $recentPosition = $this->weightedPosition($pageRows);
            $olderImpressions = (float) $olderRows->sum('impressions');
            $dropRatio = $olderImpressions > 0 ? $recentImpressions / $olderImpressions : null;

            $label = trim((string) ($page->title ?: $page->keyword ?: $page->slug));

            if ($recentImpressions >= 100 && $recentCtr > 0.0 && $recentCtr < 0.02) {
                $existingPending = $this->findPendingSuggestion($suggestionRows, 'improve-ctr', 'low_ctr');
                $cooldownSuggestion = $this->findRecentTriggeredSuggestion($suggestionRows, 'improve-ctr', 'low_ctr');
                $items->push($this->decorateOpportunity([
                    'type' => 'low_ctr',
                    'label' => $label,
                    'slug' => $page->slug,
                    'page_id' => $page->id,
                    'reason' => 'La page est visible dans Google mais trop peu de personnes cliquent.',
                    'action' => 'relancer le CTR',
                    'mode' => 'improve-ctr',
                    'pending_suggestion_id' => $existingPending?->id,
                    'pending_suggestion' => $existingPending !== null,
                    'cooldown_active' => $cooldownSuggestion !== null,
                    'cooldown_suggestion_id' => $cooldownSuggestion?->id,
                    'metrics' => [
                        'impressions' => (int) round($recentImpressions),
                        'ctr' => round($recentCtr * 100, 2),
                        'position' => round($recentPosition, 1),
                    ],
                ]));
            }

            if ($recentImpressions >= 50 && $recentPosition >= 8.0 && $recentPosition <= 15.0) {
                $existingPending = $this->findPendingSuggestion($suggestionRows, 'enrich', 'near_top_10');
                $cooldownSuggestion = $this->findRecentTriggeredSuggestion($suggestionRows, 'enrich', 'near_top_10');
                $items->push($this->decorateOpportunity([
                    'type' => 'near_top_10',
                    'label' => $label,
                    'slug' => $page->slug,
                    'page_id' => $page->id,
                    'reason' => 'La page est proche de la zone qui compte et peut gagner vite avec un refresh ciblé.',
                    'action' => 'rafraichir la page',
                    'mode' => 'enrich',
                    'pending_suggestion_id' => $existingPending?->id,
                    'pending_suggestion' => $existingPending !== null,
                    'cooldown_active' => $cooldownSuggestion !== null,
                    'cooldown_suggestion_id' => $cooldownSuggestion?->id,
                    'metrics' => [
                        'impressions' => (int) round($recentImpressions),
                        'ctr' => round($recentCtr * 100, 2),
                        'position' => round($recentPosition, 1),
                    ],
                ]));
            }

            if ($olderImpressions >= 80 && $dropRatio !== null && $dropRatio <= 0.7) {
                $existingPending = $this->findPendingSuggestion($suggestionRows, 'enrich', 'sustained_drop');
                $cooldownSuggestion = $this->findRecentTriggeredSuggestion($suggestionRows, 'enrich', 'sustained_drop');
                $items->push($this->decorateOpportunity([
                    'type' => 'sustained_drop',
                    'label' => $label,
                    'slug' => $page->slug,
                    'page_id' => $page->id,
                    'reason' => 'La visibilité récente baisse durablement par rapport à la fenêtre précédente.',
                    'action' => 'verifier puis relancer',
                    'mode' => 'enrich',
                    'pending_suggestion_id' => $existingPending?->id,
                    'pending_suggestion' => $existingPending !== null,
                    'cooldown_active' => $cooldownSuggestion !== null,
                    'cooldown_suggestion_id' => $cooldownSuggestion?->id,
                    'metrics' => [
                        'impressions' => (int) round($recentImpressions),
                        'previous_impressions' => (int) round($olderImpressions),
                        'position' => round($recentPosition, 1),
                    ],
                ]));
            }

            $queryRows
                ->groupBy(fn (SeoSearchConsoleMetric $metric): string => mb_strtolower(trim((string) $metric->query)))
                ->each(function (Collection $queryMetrics, string $query) use (&$items, $page, $label, $suggestionRows): void {
                    if ($query === '') {
                        return;
                    }

                    $impressions = (float) $queryMetrics->sum('impressions');
                    $clicks = (float) $queryMetrics->sum('clicks');
                    $ctr = $impressions > 0 ? $clicks / $impressions : 0.0;
                    $position = $this->weightedPosition($queryMetrics);

                    if ($impressions < 20 || $position > 20.0) {
                        return;
                    }

                    $existingPending = $this->findPendingSuggestion($suggestionRows, 'enrich', 'emerging_query', $query);
                    $cooldownSuggestion = $this->findRecentTriggeredSuggestion($suggestionRows, 'enrich', 'emerging_query', $query);

                    $items->push($this->decorateOpportunity([
                        'type' => 'emerging_query',
                        'label' => $label,
                        'slug' => $page->slug,
                        'page_id' => $page->id,
                        'query' => $query,
                        'reason' => 'Une requête émergente mérite une réponse plus explicite dans la page.',
                        'action' => 'creer une section utile',
                        'mode' => 'enrich',
                        'pending_suggestion_id' => $existingPending?->id,
                        'pending_suggestion' => $existingPending !== null,
                        'cooldown_active' => $cooldownSuggestion !== null,
                        'cooldown_suggestion_id' => $cooldownSuggestion?->id,
                        'metrics' => [
                            'impressions' => (int) round($impressions),
                            'ctr' => round($ctr * 100, 2),
                            'position' => round($position, 1),
                        ],
                    ]));
                });
        }

        $sortedItems = $items
            ->sortByDesc(fn (array $item): int => (int) ($item['priority_score'] ?? 0))
            ->values();

        return [
            'connected' => $hasConnection,
            'summary' => [
                'low_ctr' => $sortedItems->where('type', 'low_ctr')->count(),
                'near_top_10' => $sortedItems->where('type', 'near_top_10')->count(),
                'emerging_queries' => $sortedItems->where('type', 'emerging_query')->count(),
                'sustained_drop' => $sortedItems->where('type', 'sustained_drop')->count(),
                'total' => $sortedItems->count(),
            ],
            'items' => $sortedItems->take(8)->all(),
        ];
    }

    /**
     * @return array{
     *   connected:bool,
     *   summary:array{low_ctr:int,near_top_10:int,emerging_queries:int,sustained_drop:int,total:int},
     *   items:array<int,array<string,mixed>>
     * }
     */
    private function summarizeSiteUrls(string $siteId, bool $hasConnection): array
    {
        $recentUrlMetrics = SeoSearchConsoleMetric::query()
            ->where('site_id', $siteId)
            ->whereNull('query')
            ->whereNotNull('url')
            ->where('metric_date', '>=', now()->subDays(30)->toDateString())
            ->orderByDesc('metric_date')
            ->get()
            ->groupBy(fn (SeoSearchConsoleMetric $metric): string => $this->normalizeMetricUrl($metric));

        if ($recentUrlMetrics->isEmpty()) {
            return [
                'connected' => $hasConnection,
                'summary' => [
                    'low_ctr' => 0,
                    'near_top_10' => 0,
                    'emerging_queries' => 0,
                    'sustained_drop' => 0,
                    'total' => 0,
                ],
                'items' => [],
            ];
        }

        $olderUrlMetrics = SeoSearchConsoleMetric::query()
            ->where('site_id', $siteId)
            ->whereNull('query')
            ->whereNotNull('url')
            ->whereBetween('metric_date', [
                now()->subDays(60)->toDateString(),
                now()->subDays(31)->toDateString(),
            ])
            ->get()
            ->groupBy(fn (SeoSearchConsoleMetric $metric): string => $this->normalizeMetricUrl($metric));

        $recentQueryMetrics = SeoSearchConsoleMetric::query()
            ->where('site_id', $siteId)
            ->whereNotNull('query')
            ->whereNotNull('url')
            ->where('metric_date', '>=', now()->subDays(30)->toDateString())
            ->get()
            ->groupBy(fn (SeoSearchConsoleMetric $metric): string => $this->normalizeMetricUrl($metric));

        $items = collect();

        foreach ($recentUrlMetrics as $urlKey => $pageRows) {
            /** @var Collection<int, SeoSearchConsoleMetric> $pageRows */
            $pageRows = $pageRows;
            /** @var Collection<int, SeoSearchConsoleMetric> $olderRows */
            $olderRows = $olderUrlMetrics->get($urlKey, collect());
            /** @var Collection<int, SeoSearchConsoleMetric> $queryRows */
            $queryRows = $recentQueryMetrics->get($urlKey, collect());

            /** @var SeoSearchConsoleMetric|null $referenceMetric */
            $referenceMetric = $pageRows->first();
            $referenceUrl = trim((string) ($referenceMetric?->url ?? ''));

            if ($referenceUrl === '') {
                continue;
            }

            $recentImpressions = (float) $pageRows->sum('impressions');
            $recentClicks = (float) $pageRows->sum('clicks');
            $recentCtr = $recentImpressions > 0 ? $recentClicks / $recentImpressions : 0.0;
            $recentPosition = $this->weightedPosition($pageRows);
            $olderImpressions = (float) $olderRows->sum('impressions');
            $dropRatio = $olderImpressions > 0 ? $recentImpressions / $olderImpressions : null;

            $label = $this->labelFromUrl($referenceUrl);
            $slug = $this->slugFromUrl($referenceUrl);

            if ($recentImpressions >= 100 && $recentCtr > 0.0 && $recentCtr < 0.02) {
                $items->push($this->decorateOpportunity([
                    'type' => 'low_ctr',
                    'label' => $label,
                    'slug' => $slug,
                    'page_id' => null,
                    'reason' => 'La page est visible dans Google mais trop peu de personnes cliquent.',
                    'action' => 'relancer le CTR',
                    'mode' => 'improve-ctr',
                    'pending_suggestion_id' => null,
                    'pending_suggestion' => false,
                    'cooldown_active' => false,
                    'cooldown_suggestion_id' => null,
                    'metrics' => [
                        'impressions' => (int) round($recentImpressions),
                        'ctr' => round($recentCtr * 100, 2),
                        'position' => round($recentPosition, 1),
                    ],
                ]));
            }

            if ($recentImpressions >= 50 && $recentPosition >= 8.0 && $recentPosition <= 15.0) {
                $items->push($this->decorateOpportunity([
                    'type' => 'near_top_10',
                    'label' => $label,
                    'slug' => $slug,
                    'page_id' => null,
                    'reason' => 'La page est proche de la zone qui compte et peut gagner vite avec un refresh ciblé.',
                    'action' => 'rafraichir la page',
                    'mode' => 'enrich',
                    'pending_suggestion_id' => null,
                    'pending_suggestion' => false,
                    'cooldown_active' => false,
                    'cooldown_suggestion_id' => null,
                    'metrics' => [
                        'impressions' => (int) round($recentImpressions),
                        'ctr' => round($recentCtr * 100, 2),
                        'position' => round($recentPosition, 1),
                    ],
                ]));
            }

            if ($olderImpressions >= 80 && $dropRatio !== null && $dropRatio <= 0.7) {
                $items->push($this->decorateOpportunity([
                    'type' => 'sustained_drop',
                    'label' => $label,
                    'slug' => $slug,
                    'page_id' => null,
                    'reason' => 'La visibilité récente baisse durablement par rapport à la fenêtre précédente.',
                    'action' => 'verifier puis relancer',
                    'mode' => 'enrich',
                    'pending_suggestion_id' => null,
                    'pending_suggestion' => false,
                    'cooldown_active' => false,
                    'cooldown_suggestion_id' => null,
                    'metrics' => [
                        'impressions' => (int) round($recentImpressions),
                        'previous_impressions' => (int) round($olderImpressions),
                        'position' => round($recentPosition, 1),
                    ],
                ]));
            }

            $queryRows
                ->groupBy(fn (SeoSearchConsoleMetric $metric): string => mb_strtolower(trim((string) $metric->query)))
                ->each(function (Collection $queryMetrics, string $query) use (&$items, $label, $slug): void {
                    if ($query === '') {
                        return;
                    }

                    $impressions = (float) $queryMetrics->sum('impressions');
                    $clicks = (float) $queryMetrics->sum('clicks');
                    $ctr = $impressions > 0 ? $clicks / $impressions : 0.0;
                    $position = $this->weightedPosition($queryMetrics);

                    if ($impressions < 20 || $position > 20.0) {
                        return;
                    }

                    $items->push($this->decorateOpportunity([
                        'type' => 'emerging_query',
                        'label' => $label,
                        'slug' => $slug,
                        'page_id' => null,
                        'query' => $query,
                        'reason' => 'Une requête émergente mérite une réponse plus explicite dans la page.',
                        'action' => 'creer une section utile',
                        'mode' => 'enrich',
                        'pending_suggestion_id' => null,
                        'pending_suggestion' => false,
                        'cooldown_active' => false,
                        'cooldown_suggestion_id' => null,
                        'metrics' => [
                            'impressions' => (int) round($impressions),
                            'ctr' => round($ctr * 100, 2),
                            'position' => round($position, 1),
                        ],
                    ]));
                });
        }

        $sortedItems = $items
            ->sortByDesc(fn (array $item): int => (int) ($item['priority_score'] ?? 0))
            ->values();

        return [
            'connected' => $hasConnection,
            'summary' => [
                'low_ctr' => $sortedItems->where('type', 'low_ctr')->count(),
                'near_top_10' => $sortedItems->where('type', 'near_top_10')->count(),
                'emerging_queries' => $sortedItems->where('type', 'emerging_query')->count(),
                'sustained_drop' => $sortedItems->where('type', 'sustained_drop')->count(),
                'total' => $sortedItems->count(),
            ],
            'items' => $sortedItems->take(8)->all(),
        ];
    }

    /**
     * @param  array<string,mixed>  $item
     * @return array<string,mixed>
     */
    private function decorateOpportunity(array $item): array
    {
        $metrics = is_array($item['metrics'] ?? null) ? $item['metrics'] : [];
        $baseScore = $this->basePriorityScore((string) ($item['type'] ?? ''), $metrics);
        $actionState = ! empty($item['pending_suggestion'])
            ? 'pending'
            : (! empty($item['cooldown_active']) ? 'cooldown' : 'ready');

        $score = $baseScore + match ($actionState) {
            'ready' => 120,
            'cooldown' => -40,
            'pending' => -120,
            default => 0,
        };

        [$level, $label] = match (true) {
            $score >= 620 => ['high', 'Priorite haute'],
            $score >= 420 => ['medium', 'Gain rapide'],
            default => ['watch', 'A surveiller'],
        };

        $actionStateLabel = match ($actionState) {
            'pending' => 'Suggestion deja en attente',
            'cooldown' => 'Cooldown actif',
            default => 'Actionnable maintenant',
        };

        $item['priority_score'] = $score;
        $item['priority_level'] = $level;
        $item['priority_label'] = $label;
        $item['action_state'] = $actionState;
        $item['action_state_label'] = $actionStateLabel;

        return $item;
    }

    /**
     * @param  array<string,mixed>  $metrics
     */
    private function basePriorityScore(string $type, array $metrics): int
    {
        $impressions = (int) ($metrics['impressions'] ?? 0);
        $position = (float) ($metrics['position'] ?? 0.0);
        $ctr = (float) ($metrics['ctr'] ?? 0.0);
        $previousImpressions = (int) ($metrics['previous_impressions'] ?? 0);
        $loss = max(0, $previousImpressions - $impressions);

        return match ($type) {
            'sustained_drop' => 520 + min(220, $loss) + max(0, (int) round(18 - $position) * 8),
            'near_top_10' => 440 + min(180, $impressions) + max(0, (int) round(16 - $position) * 18),
            'low_ctr' => 320 + min(200, $impressions) + max(0, (int) round((2.5 - $ctr) * 40)),
            'emerging_query' => 180 + min(160, $impressions) + max(0, (int) round(20 - $position) * 8),
            default => 0,
        };
    }

    /**
     * @param  Collection<int,SeoSearchConsoleMetric>  $metrics
     */
    private function weightedPosition(Collection $metrics): float
    {
        $impressions = (float) $metrics->sum('impressions');

        if ($impressions <= 0.0) {
            return 0.0;
        }

        $weighted = $metrics->reduce(
            fn (float $carry, SeoSearchConsoleMetric $metric): float => $carry + (((float) $metric->position) * ((float) $metric->impressions)),
            0.0
        );

        return $weighted / $impressions;
    }

    private function normalizeMetricUrl(SeoSearchConsoleMetric $metric): string
    {
        $url = trim((string) $metric->url);

        if ($url === '') {
            return '';
        }

        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? 'https'));
        $host = strtolower((string) ($parts['host'] ?? ''));
        $path = trim((string) ($parts['path'] ?? '/'));
        $path = $path === '' ? '/' : rtrim($path, '/');

        if ($path === '') {
            $path = '/';
        }

        return $host !== '' ? $scheme.'://'.$host.$path : rtrim($url, '/');
    }

    private function slugFromUrl(string $url): string
    {
        $path = trim((string) parse_url($url, PHP_URL_PATH));

        return trim($path, '/');
    }

    private function labelFromUrl(string $url): string
    {
        $path = $this->slugFromUrl($url);

        if ($path === '') {
            return (string) parse_url($url, PHP_URL_HOST);
        }

        $lastSegment = (string) last(array_filter(explode('/', $path)));
        $normalized = str_replace(['-', '_'], ' ', $lastSegment);

        return mb_convert_case($normalized, MB_CASE_TITLE, 'UTF-8');
    }

    /**
     * @param  Collection<int,SeoSuggestion>  $suggestions
     */
    private function findPendingSuggestion(Collection $suggestions, string $mode, string $type, ?string $query = null): ?SeoSuggestion
    {
        return $suggestions->first(function (SeoSuggestion $suggestion) use ($mode, $type, $query): bool {
            if ($suggestion->status !== 'pending') {
                return false;
            }

            return $this->matchesTrigger($suggestion, $mode, $type, $query);
        });
    }

    /**
     * @param  Collection<int,SeoSuggestion>  $suggestions
     */
    private function findRecentTriggeredSuggestion(Collection $suggestions, string $mode, string $type, ?string $query = null): ?SeoSuggestion
    {
        return $suggestions->first(function (SeoSuggestion $suggestion) use ($mode, $type, $query): bool {
            return $this->matchesTrigger($suggestion, $mode, $type, $query);
        });
    }

    private function matchesTrigger(SeoSuggestion $suggestion, string $mode, string $type, ?string $query = null): bool
    {
            if ($suggestion->source !== 'rewrite_engine:'.$mode) {
                return false;
            }

            $trigger = is_array($suggestion->signals_json['gsc_trigger'] ?? null)
                ? $suggestion->signals_json['gsc_trigger']
                : [];

            if (($trigger['type'] ?? null) !== $type) {
                return false;
            }

            if ($query !== null && mb_strtolower((string) ($trigger['query'] ?? '')) !== mb_strtolower($query)) {
                return false;
            }

            return true;
    }
}
