<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SeoPage;
use App\Models\SeoRecommendation;
use App\Models\SeoSemanticLink;
use App\Models\SeoSite;
use App\Models\SeoSiteCrawl;
use App\Models\SeoSitePage;
use App\Models\SeoSuggestion;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    public function index(): View
    {
        $stats = [
            'total_sites' => SeoSite::query()->active()->count(),
            'observed_pages' => SeoSitePage::query()->count(),
            'action_queue' => SeoSuggestion::query()->where('status', 'pending')->count()
                + SeoRecommendation::query()->where('status', 'pending')->count(),
            'crawls_today' => SeoSiteCrawl::query()
                ->whereDate('created_at', now()->toDateString())
                ->count(),
        ];

        $queue = [
            'feedback' => SeoSuggestion::query()
                ->where('status', 'pending')
                ->where('source', 'feedback_loop:auto')
                ->count(),
            'signals' => SeoSuggestion::query()
                ->where('status', 'pending')
                ->where('source', 'signal_queue:auto')
                ->count(),
            'rewrites' => SeoSuggestion::query()
                ->where('status', 'pending')
                ->where('source', 'like', 'rewrite_engine:%')
                ->count(),
            'recommendations' => SeoRecommendation::query()
                ->where('status', 'pending')
                ->count(),
            'rewrite_blocked' => SeoSuggestion::query()
                ->where('status', 'rejected')
                ->where('source', 'rewrite_blocked')
                ->count(),
        ];

        $intelligence = [
            'orphan_pages' => SeoSitePage::query()->where('orphan_score', '>=', 0.75)->count(),
            'weak_pages' => SeoSitePage::query()
                ->where(function ($query): void {
                    $query->where('latest_word_count', '<', 300)
                        ->orWhere('authority_score', '<', 0.20)
                        ->orWhere('indexability_state', '!=', 'indexable');
                })
                ->count(),
            'cannibalization_risks' => SeoSemanticLink::query()
                ->where('relation_type', 'observed_cannibalization')
                ->count(),
            'pillar_candidates' => SeoSitePage::query()->where('pillar_likelihood', '>=', 0.70)->count(),
        ];

        $siteIds = SeoSite::query()->active()->pluck('site_id');

        $observedBySite = SeoSitePage::query()
            ->selectRaw('site_id, count(*) as total')
            ->whereIn('site_id', $siteIds)
            ->groupBy('site_id')
            ->pluck('total', 'site_id');

        $generatedBySite = SeoPage::query()
            ->selectRaw('site_id, count(*) as total')
            ->whereIn('site_id', $siteIds)
            ->groupBy('site_id')
            ->pluck('total', 'site_id');

        $weakBySite = SeoSitePage::query()
            ->selectRaw('site_id, count(*) as total')
            ->whereIn('site_id', $siteIds)
            ->where(function ($query): void {
                $query->where('latest_word_count', '<', 300)
                    ->orWhere('authority_score', '<', 0.20)
                    ->orWhere('indexability_state', '!=', 'indexable');
            })
            ->groupBy('site_id')
            ->pluck('total', 'site_id');

        $orphanBySite = SeoSitePage::query()
            ->selectRaw('site_id, count(*) as total')
            ->whereIn('site_id', $siteIds)
            ->where('orphan_score', '>=', 0.75)
            ->groupBy('site_id')
            ->pluck('total', 'site_id');

        $pendingBySite = SeoRecommendation::query()
            ->selectRaw('site_id, count(*) as total')
            ->whereIn('site_id', $siteIds)
            ->where('status', 'pending')
            ->groupBy('site_id')
            ->pluck('total', 'site_id');

        $avgAuthorityBySite = SeoSitePage::query()
            ->selectRaw('site_id, avg(authority_score) as total')
            ->whereIn('site_id', $siteIds)
            ->groupBy('site_id')
            ->pluck('total', 'site_id');

        $avgOrphanBySite = SeoSitePage::query()
            ->selectRaw('site_id, avg(orphan_score) as total')
            ->whereIn('site_id', $siteIds)
            ->groupBy('site_id')
            ->pluck('total', 'site_id');

        $latestCrawlBySite = SeoSiteCrawl::query()
            ->whereIn('site_id', $siteIds)
            ->orderByDesc('completed_at')
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('site_id')
            ->map(fn (Collection $crawls) => $crawls->first());

        $sites = SeoSite::query()
            ->active()
            ->orderBy('name')
            ->get()
            ->map(function (SeoSite $site) use (
                $observedBySite,
                $generatedBySite,
                $weakBySite,
                $orphanBySite,
                $pendingBySite,
                $avgAuthorityBySite,
                $avgOrphanBySite,
                $latestCrawlBySite,
            ): array {
                $observed = (int) ($observedBySite[$site->site_id] ?? 0);
                $generated = (int) ($generatedBySite[$site->site_id] ?? 0);
                $weak = (int) ($weakBySite[$site->site_id] ?? 0);
                $orphans = (int) ($orphanBySite[$site->site_id] ?? 0);
                $pending = (int) ($pendingBySite[$site->site_id] ?? 0);
                $authority = (float) ($avgAuthorityBySite[$site->site_id] ?? 0);
                $orphanAverage = (float) ($avgOrphanBySite[$site->site_id] ?? 0);
                $crawl = $latestCrawlBySite[$site->site_id] ?? null;

                $weakRatio = $observed > 0 ? min(1, $weak / $observed) : 1;
                $actionRatio = $observed > 0 ? min(1, $pending / max(1, $observed)) : 0;
                $healthScore = (int) round(
                    max(
                        0,
                        min(
                            100,
                            ($authority * 55 * 100 / 100)
                            + ((1 - $orphanAverage) * 25)
                            + ((1 - $weakRatio) * 15)
                            + ((1 - $actionRatio) * 5)
                        )
                    )
                );

                return [
                    'site' => $site,
                    'observed_pages' => $observed,
                    'generated_pages' => $generated,
                    'weak_pages' => $weak,
                    'orphan_pages' => $orphans,
                    'pending_actions' => $pending,
                    'avg_authority' => round($authority * 100),
                    'avg_orphan' => round($orphanAverage * 100),
                    'health_score' => $healthScore,
                    'latest_crawl' => $crawl,
                ];
            });

        $priorityRecommendations = SeoRecommendation::query()
            ->where('status', 'pending')
            ->orderBy('priority')
            ->orderByDesc('generated_at')
            ->limit(8)
            ->get(['id', 'site_id', 'type', 'priority', 'estimated_impact', 'difficulty', 'title', 'cluster', 'generated_at']);

        $opportunityMix = SeoRecommendation::query()
            ->where('status', 'pending')
            ->selectRaw('type, count(*) as total')
            ->groupBy('type')
            ->orderByDesc('total')
            ->limit(6)
            ->get()
            ->map(fn (SeoRecommendation $row): array => [
                'type' => str_replace('_', ' ', (string) $row->type),
                'total' => (int) ($row->total ?? 0),
            ]);

        $suggestionStatusCounts = SeoSuggestion::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $recommendationStatusCounts = SeoRecommendation::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $actionLifecycle = collect(['pending', 'applied', 'rejected', 'replaced', 'done'])
            ->merge($suggestionStatusCounts->keys())
            ->merge($recommendationStatusCounts->keys())
            ->filter()
            ->unique()
            ->map(function (string $status) use ($suggestionStatusCounts, $recommendationStatusCounts): array {
                $suggestions = (int) ($suggestionStatusCounts[$status] ?? 0);
                $recommendations = (int) ($recommendationStatusCounts[$status] ?? 0);

                return [
                    'status' => $status,
                    'suggestions' => $suggestions,
                    'recommendations' => $recommendations,
                    'total' => $suggestions + $recommendations,
                ];
            })
            ->filter(fn (array $row): bool => $row['total'] > 0)
            ->values();

        $rewriteQueue = SeoSuggestion::query()
            ->with('page:id,site_id,keyword,slug')
            ->where('status', 'pending')
            ->where('source', 'like', 'rewrite_engine:%')
            ->latest()
            ->limit(8)
            ->get();

        $feedbackQueue = SeoSuggestion::query()
            ->with('page:id,site_id,keyword,slug')
            ->where('status', 'pending')
            ->whereIn('source', ['feedback_loop:auto', 'signal_queue:auto'])
            ->latest()
            ->limit(8)
            ->get();

        $recentCrawls = SeoSiteCrawl::query()
            ->whereIn('site_id', $siteIds)
            ->orderByDesc('created_at')
            ->limit(8)
            ->get(['site_id', 'status', 'discovered_url_count', 'crawled_url_count', 'started_at', 'completed_at']);

        $graphEdges = SeoSemanticLink::query()
            ->whereIn('relation_type', ['observed_overlap', 'observed_cannibalization', 'observed_query_match'])
            ->orderByDesc('similarity_score')
            ->limit(18)
            ->get([
                'site_id',
                'relation_type',
                'source_id',
                'target_id',
                'label',
                'reason',
                'similarity_score',
                'meta_json',
            ]);

        $pageLookup = SeoSitePage::query()
            ->whereIn('id', $graphEdges->pluck('source_id')->merge($graphEdges->pluck('target_id'))->filter()->unique())
            ->get(['id', 'site_id', 'title', 'path', 'cluster_label'])
            ->keyBy('id');

        $overlapHotspots = $graphEdges
            ->whereIn('relation_type', ['observed_overlap', 'observed_cannibalization'])
            ->take(6)
            ->map(function (SeoSemanticLink $edge) use ($pageLookup): array {
                $source = $pageLookup->get($edge->source_id);
                $target = $pageLookup->get($edge->target_id);

                return [
                    'site_id' => $edge->site_id,
                    'type' => $edge->relation_type,
                    'source' => $source?->title ?: $source?->path ?: 'Page observée',
                    'target' => $target?->title ?: $target?->path ?: 'Page observée',
                    'score' => round(((float) $edge->similarity_score) * 100),
                    'reason' => $edge->reason ?: 'relation_detected',
                ];
            })
            ->values();

        $queryHotspots = $graphEdges
            ->where('relation_type', 'observed_query_match')
            ->take(6)
            ->map(function (SeoSemanticLink $edge) use ($pageLookup): array {
                $page = $pageLookup->get($edge->source_id);
                $meta = $edge->meta_json ?? [];

                return [
                    'site_id' => $edge->site_id,
                    'query' => (string) (($meta['query'] ?? null) ?: $edge->label ?: 'query'),
                    'page' => $page?->title ?: $page?->path ?: 'Page observée',
                    'action' => (string) (($meta['recommended_action'] ?? null) ?: $edge->reason ?: 'monitor_query'),
                    'impressions' => (int) ($meta['impressions'] ?? 0),
                    'position' => round((float) ($meta['position'] ?? 0.0), 1),
                    'score' => round(((float) $edge->similarity_score) * 100),
                ];
            })
            ->values();

        $weakObservedPages = SeoSitePage::query()
            ->where(function ($query): void {
                $query->where('latest_word_count', '<', 300)
                    ->orWhere('authority_score', '<', 0.20)
                    ->orWhere('orphan_score', '>=', 0.75)
                    ->orWhere('indexability_state', '!=', 'indexable');
            })
            ->orderByDesc('orphan_score')
            ->orderBy('authority_score')
            ->orderBy('latest_word_count')
            ->limit(8)
            ->get([
                'site_id',
                'title',
                'path',
                'cluster_label',
                'authority_score',
                'orphan_score',
                'latest_word_count',
                'indexability_state',
            ]);

        $recent = SeoPage::query()
            ->select(['id', 'site_id', 'keyword', 'slug', 'status', 'seo_score', 'updated_at'])
            ->orderByDesc('updated_at')
            ->limit(8)
            ->get();

        return view('admin.dashboard', compact(
            'stats',
            'queue',
            'intelligence',
            'sites',
            'priorityRecommendations',
            'opportunityMix',
            'actionLifecycle',
            'overlapHotspots',
            'queryHotspots',
            'weakObservedPages',
            'rewriteQueue',
            'feedbackQueue',
            'recentCrawls',
            'recent',
        ));
    }
}
