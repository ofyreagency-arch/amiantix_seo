<?php

declare(strict_types=1);

namespace App\Runtime;

use App\Models\SeoPage;
use App\Models\SeoSitePage;
use App\Models\SeoSitePageSnapshot;
use App\Models\SeoSearchConsoleMetric;
use App\ObservedSite\ObservedPageHealthService;
use App\ObservedSite\ObservedRewriteBridgeService;
use App\ObservedSite\SeoPageObservedLinkService;
use Illuminate\Support\Collection;
use Ofyre\SeoEngine\Contracts\PrioritizedPageProvider;
use Ofyre\SeoEngine\Contracts\SeoFeedbackLoopDriver;
use Ofyre\SeoEngine\Services\Scoring\SeoScoreRefreshService;
use Ofyre\SeoEngine\Services\Scoring\SeoScoringService;
use Ofyre\SeoEngine\Services\Monitoring\SeoMonitoringService;
use Ofyre\SeoEngine\Services\SearchConsole\SearchConsoleService;

class RuntimeSeoMonitoringService extends SeoMonitoringService
{
    private readonly ObservedPageHealthService $observedHealth;
    private readonly ObservedRewriteBridgeService $observedRewrite;
    private readonly SeoPageObservedLinkService $observedLinks;

    public function __construct(
        SearchConsoleService $searchConsole,
        SeoScoringService $scoring,
        SeoFeedbackLoopDriver $feedbackLoop,
        PrioritizedPageProvider $prioritizedPages,
        SeoScoreRefreshService $scoreRefresh,
        ?ObservedPageHealthService $observedHealth = null,
        ?ObservedRewriteBridgeService $observedRewrite = null,
        ?SeoPageObservedLinkService $observedLinks = null,
    ) {
        parent::__construct(
            $searchConsole,
            $scoring,
            $feedbackLoop,
            $prioritizedPages,
            $scoreRefresh,
        );

        $this->observedHealth = $observedHealth ?? app(ObservedPageHealthService::class);
        $this->observedRewrite = $observedRewrite ?? app(ObservedRewriteBridgeService::class);
        $this->observedLinks = $observedLinks ?? app(SeoPageObservedLinkService::class);
    }

    /**
     * @return array{
     *   monitored:int,
     *   healthy:int,
     *   warning:int,
     *   critical:int,
     *   items:array<int,array<string,mixed>>
     * }
     */
    public function observedSummary(string $siteId, int $limit = 25): array
    {
        $pages = SeoSitePage::query()
            ->where('site_id', $siteId)
            ->orderByDesc('last_seen_at')
            ->limit($limit)
            ->get();

        if ($pages->isEmpty()) {
            return [
                'monitored' => 0,
                'healthy' => 0,
                'warning' => 0,
                'critical' => 0,
                'items' => [],
            ];
        }

        $snapshots = SeoSitePageSnapshot::query()
            ->where('site_id', $siteId)
            ->whereIn('site_page_id', $pages->pluck('id'))
            ->orderByDesc('observed_at')
            ->get()
            ->groupBy('site_page_id')
            ->map(fn (Collection $group): SeoSitePageSnapshot => $group->first());

        $items = $pages
            ->map(function (SeoSitePage $page) use ($snapshots): array {
                $health = $this->observedHealth->forPage($page);
                /** @var SeoSitePageSnapshot|null $snapshot */
                $snapshot = $snapshots->get($page->id);
                $state = $this->observedState($page, $health);
                $priority = $this->observedPriority($page, $health, $snapshot);

                return [
                    'id' => $page->id,
                    'site_id' => $page->site_id,
                    'url' => $page->normalized_url,
                    'path' => $page->path,
                    'title' => $page->title,
                    'cluster_label' => $page->cluster_label,
                    'state' => $state,
                    'priority' => $priority,
                    'health_score' => $health['health_score'],
                    'seo' => $health['seo'],
                    'quality' => $health['quality'],
                    'topical' => $health['topical'],
                    'indexability' => $health['indexability'],
                    'flags' => $health['flags'],
                    'last_status_code' => $page->last_status_code,
                    'indexability_state' => $page->indexability_state,
                    'latest_word_count' => $page->latest_word_count,
                    'authority_score' => (float) $page->authority_score,
                    'orphan_score' => (float) $page->orphan_score,
                    'overlap_score' => (float) $page->overlap_score,
                    'last_seen_at' => $page->last_seen_at,
                    'snapshot_observed_at' => $snapshot?->observed_at,
                    'snapshot_word_count' => $snapshot?->word_count,
                    'snapshot_status_code' => $snapshot?->status_code,
                    'snapshot_is_indexable' => $snapshot?->is_indexable,
                ];
            })
            ->sortBy([
                ['priority', 'desc'],
                ['health_score', 'asc'],
            ])
            ->values();

        return [
            'monitored' => $items->count(),
            'healthy' => $items->where('state', 'healthy')->count(),
            'warning' => $items->where('state', 'warning')->count(),
            'critical' => $items->where('state', 'critical')->count(),
            'items' => $items->all(),
        ];
    }

    /**
     * @return array{
     *   scanned:int,
     *   matched:int,
     *   synced:int,
     *   cleared:int,
     *   missing:int
     * }
     */
    public function syncObservedRewriteSignals(string $siteId, int $limit = 10): array
    {
        $items = collect($this->observedSummary($siteId, max($limit * 3, $limit))['items'] ?? [])
            ->filter(fn (array $item): bool => in_array($item['state'] ?? null, ['warning', 'critical'], true))
            ->take($limit)
            ->values();

        $summary = [
            'scanned' => $items->count(),
            'matched' => 0,
            'synced' => 0,
            'cleared' => 0,
            'missing' => 0,
        ];

        foreach ($items as $item) {
            $page = $this->legacyPageForObservedItem($siteId, $item);

            if (! $page) {
                $summary['missing']++;

                continue;
            }

            $summary['matched']++;
            $context = $this->observedRewrite->syncForPage($page);

            if (($context['queued'] ?? false) === true) {
                $summary['synced']++;
            } else {
                $summary['cleared']++;
            }
        }

        return $summary;
    }

    protected function candidatePages(array $prioritizedIds): iterable
    {
        $query = SeoPage::query();

        if ($prioritizedIds !== []) {
            $query->whereIn('id', $prioritizedIds);
        }

        return $query->orderBy('seo_score')->get();
    }

    protected function agedPublishedPages(int $days): iterable
    {
        return SeoPage::query()
            ->published()
            ->where(function ($query) use ($days): void {
                $query->whereNull('last_audit_at')
                    ->orWhere('last_audit_at', '<', now()->subDays($days));
            })
            ->get();
    }

    protected function markIndexed(object $page, bool $indexed): void
    {
        if ($page instanceof SeoPage) {
            $page->forceFill([
                'is_indexed' => $indexed,
            ])->save();
        }
    }

    protected function persistSearchConsoleHistory(object $page, array $metrics, array $audit): void
    {
        if (! $page instanceof SeoPage) {
            return;
        }

        SeoSearchConsoleMetric::query()->create([
            'seo_page_id' => $page->id,
            'metric_date' => now()->toDateString(),
            'window_days' => 30,
            'query' => null,
            'url' => rtrim((string) config('app.url'), '/').$page->canonicalPath(),
            'clicks' => (float) ($metrics['ctr'] ?? 0) * (float) ($metrics['impressions'] ?? 0),
            'impressions' => (float) ($metrics['impressions'] ?? 0),
            'ctr' => (float) ($metrics['ctr'] ?? 0),
            'position' => (float) ($metrics['position'] ?? 0),
            'is_indexed' => $metrics['indexed'] ?? null,
            'coverage_json' => $metrics['coverage'] ?? [],
            'payload_json' => [
                'queries' => $metrics['queries'] ?? [],
                'audit_score' => $audit['score'] ?? null,
            ],
        ]);
    }

    protected function persistMonitoringState(object $page, array $metrics, array $audit): void
    {
        if (! $page instanceof SeoPage) {
            return;
        }

        $page->forceFill([
            'duplicate_risk_score' => in_array('duplicate_risk_high', $audit['issues'] ?? [], true) ? 80 : (in_array('duplicate_risk_medium', $audit['issues'] ?? [], true) ? 50 : 0),
            'last_audit_at' => now(),
            'is_indexed' => $metrics['indexed'] ?? $page->is_indexed,
        ])->save();
    }

    /**
     * @param  array{health_score:int,flags:array<int,string>}  $health
     */
    private function observedState(SeoSitePage $page, array $health): string
    {
        if ((int) ($page->last_status_code ?? 0) >= 400 || $health['health_score'] < 45) {
            return 'critical';
        }

        if ($health['flags'] !== [] || $health['health_score'] < 75) {
            return 'warning';
        }

        return 'healthy';
    }

    /**
     * @param  array{health_score:int,flags:array<int,string>}  $health
     */
    private function observedPriority(SeoSitePage $page, array $health, ?SeoSitePageSnapshot $snapshot): int
    {
        $priority = 0;
        $priority += max(0, 100 - $health['health_score']);
        $priority += min(30, count($health['flags']) * 6);
        $priority += (int) round(((float) $page->orphan_score) * 20);
        $priority += (int) round(((float) $page->overlap_score) * 15);

        if ((int) ($page->last_status_code ?? 0) >= 400) {
            $priority += 30;
        }

        if ($snapshot && $snapshot->observed_at?->lt(now()->subDays(14))) {
            $priority += 10;
        }

        return min(100, $priority);
    }

    /**
     * @param  array<string,mixed>  $item
     */
    private function legacyPageForObservedItem(string $siteId, array $item): ?SeoPage
    {
        $observedSitePageId = (int) ($item['id'] ?? 0);

        if ($observedSitePageId > 0) {
            $linkedPage = SeoPage::query()
                ->where('site_id', $siteId)
                ->where('observed_site_page_id', $observedSitePageId)
                ->orderByDesc('updated_at')
                ->first();

            if ($linkedPage) {
                return $linkedPage;
            }
        }

        $path = trim((string) ($item['path'] ?? ''));
        $slug = ltrim($path, '/');

        if ($slug === '') {
            return null;
        }

        $page = SeoPage::query()
            ->where('site_id', $siteId)
            ->where(function ($query) use ($slug, $path): void {
                $query->where('slug', $slug)
                    ->orWhere('canonical_url', 'like', '%'.$path);
            })
            ->orderByDesc('updated_at')
            ->first();

        if ($page) {
            $this->observedLinks->syncPage($page);
        }

        return $page;
    }
}
