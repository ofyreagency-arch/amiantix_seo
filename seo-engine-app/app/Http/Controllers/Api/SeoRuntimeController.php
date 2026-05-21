<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\ObservedSite\ObservedRewriteBridgeService;
use App\ObservedSite\SiteHealthService;
use App\Models\SeoPage;
use App\Models\SeoRecommendation;
use App\Models\SeoSearchConsoleMetric;
use App\Models\SeoSitePage;
use App\SeoBridge\Repositories\DatabaseSeoCockpitRepository;
use App\Runtime\RuntimeSeoMonitoringService;
use App\Runtime\SeoEngineContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Ofyre\SeoEngine\Contracts\SemanticLinkRepository;
use Ofyre\SeoEngine\Contracts\SeoPageRepository;
use Ofyre\SeoEngine\Services\Admin\SeoCockpitService;
use Ofyre\SeoEngine\Services\Console\SeoGeneratePageRunner;
use Ofyre\SeoEngine\Services\Embeddings\CannibalizationDetectionService;
use Ofyre\SeoEngine\Services\Embeddings\ContentEmbeddingService;
use Ofyre\SeoEngine\Services\Embeddings\InternalLinkSuggestionService;
use Ofyre\SeoEngine\Services\Embeddings\QueryPageMatchingService;
use Ofyre\SeoEngine\Services\Rewrite\SeoRewriteService;
use Ofyre\SeoEngine\Services\Review\SeoPageStatusService;
use Ofyre\SeoEngine\Services\SearchConsole\SearchConsoleService;

class SeoRuntimeController extends Controller
{
    public function generate(Request $request, SeoGeneratePageRunner $runner): JsonResponse
    {
        $payload = $request->validate([
            'keyword' => ['required', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:40'],
        ]);

        $result = $runner->run($payload['keyword'], $payload['status'] ?? 'draft', false);

        return response()->json([
            'page' => $result['page'],
            'warning' => $result['warning'],
        ], 201);
    }

    public function rewrite(
        Request $request,
        SeoPageRepository $pages,
        SeoRewriteService $rewrite,
        SeoEngineContext $context,
        ObservedRewriteBridgeService $observedRewrite,
    ): JsonResponse
    {
        $payload = $request->validate([
            'slug' => ['required', 'string'],
            'mode' => ['nullable', 'string'],
        ]);

        $page = $pages->findBySlug($payload['slug']);
        abort_if(! $page, 404, 'SEO page not found.');
        $dbPage = SeoPage::query()
            ->where('site_id', $context->siteId())
            ->where('slug', ltrim($payload['slug'], '/'))
            ->first();
        $observedContext = $dbPage ? $observedRewrite->syncForPage($dbPage) : null;
        $rewritePage = $dbPage ? $dbPage->fresh(['suggestions']) : $page;

        $suggestion = $rewrite->createSuggestion($rewritePage, $payload['mode'] ?? 'enrich');

        return response()->json([
            'page' => $rewritePage,
            'suggestion' => $suggestion,
            'observed_rewrite' => $observedContext,
        ]);
    }

    public function analyze(
        Request $request,
        SeoPageRepository $pages,
        SeoPageStatusService $statusService,
        SearchConsoleService $searchConsole,
        DatabaseSeoCockpitRepository $cockpitRepository,
        ObservedRewriteBridgeService $observedRewrite,
    ): JsonResponse {
        $payload = $request->validate([
            'slug' => ['required', 'string'],
        ]);

        $page = $pages->findBySlug($payload['slug']);
        abort_if(! $page, 404, 'SEO page not found.');
        $dbPage = $page instanceof SeoPage
            ? $page
            : SeoPage::query()->where('site_id', $page->site_id ?? null)->where('slug', $payload['slug'])->first();

        return response()->json([
            'page' => $page,
            'status_report' => $statusService->summarize($page),
            'metrics' => $searchConsole->pageMetrics($page),
            'semantic_context' => $cockpitRepository->semanticContextForPage($page),
            'timeline' => $cockpitRepository->timelineForPage($page),
            'observed_rewrite' => $dbPage ? $observedRewrite->contextForPage($dbPage) : null,
        ]);
    }

    public function opportunities(SeoCockpitService $cockpit): JsonResponse
    {
        return response()->json($cockpit->dashboardPayload());
    }

    public function autopilot(
        Request $request,
        RuntimeSeoMonitoringService $monitoring,
        ContentEmbeddingService $embeddings,
        InternalLinkSuggestionService $internalLinks,
        CannibalizationDetectionService $cannibalization,
        QueryPageMatchingService $matching,
        SeoPageRepository $pages,
        SeoEngineContext $context,
    ): JsonResponse {
        $payload = $request->validate([
            'slug' => ['nullable', 'string'],
            'sync_embeddings' => ['nullable', 'boolean'],
        ]);

        $summary = [];

        if (! empty($payload['slug'])) {
            $page = $pages->findBySlug($payload['slug']);
            abort_if(! $page, 404, 'SEO page not found.');

            $summary['monitored'] = $monitoring->monitorPage($page);

            if ($page instanceof SeoPage) {
                $summary['observed_rewrite'] = $monitoring->syncObservedRewriteSignals($page->site_id, 1);
            }
        } else {
            $summary['monitoring'] = $monitoring->monitor();
            $summary['observed_rewrite'] = $monitoring->syncObservedRewriteSignals($context->siteId());
        }

        if ((bool) ($payload['sync_embeddings'] ?? true)) {
            $summary['embeddings'] = $embeddings->embedPages($payload['slug'] ?? null, force: true);
            $summary['internal_links'] = $internalLinks->refresh($payload['slug'] ?? null);
            $summary['cannibalization'] = $cannibalization->refresh($payload['slug'] ?? null);
            $summary['query_matching'] = $matching->refresh($payload['slug'] ?? null, force: true);
        }

        return response()->json($summary);
    }

    public function searchConsole(Request $request, SearchConsoleService $searchConsole): JsonResponse
    {
        $days = (int) $request->integer('days', 28);
        $limit = (int) $request->integer('limit', 25);

        return response()->json([
            'top_queries' => $searchConsole->getTopQueries($days, $limit),
            'top_pages' => $searchConsole->getTopPages($days, min($limit, 250)),
            'stored_metrics' => SeoSearchConsoleMetric::query()->latest('metric_date')->limit(50)->get(),
        ]);
    }

    public function runtimeSummary(
        SeoEngineContext $context,
        SiteHealthService $siteHealth,
        RuntimeSeoMonitoringService $monitoring,
    ): JsonResponse {
        $siteId = $context->siteId();
        $health = $siteHealth->calculate($siteId);
        $observedMonitoring = $monitoring->observedSummary($siteId, 20);

        $legacyPages = SeoPage::query()->where('site_id', $siteId);
        $observedPages = SeoSitePage::query()->where('site_id', $siteId);

        return response()->json([
            'site' => [
                'site_id' => $siteId,
                'name' => $context->name(),
                'url' => $context->url(),
                'niche' => $context->niche(),
                'locale' => $context->locale(),
                'preset' => $context->preset(),
            ],
            'legacy' => [
                'pages' => $legacyPages->count(),
                'published' => (clone $legacyPages)->where('status', 'published')->count(),
                'pending_suggestions' => SeoRecommendation::query()->where('site_id', $siteId)->where('status', 'pending')->count(),
            ],
            'observed' => [
                'health' => $health,
                'monitoring' => [
                    'monitored' => $observedMonitoring['monitored'],
                    'healthy' => $observedMonitoring['healthy'],
                    'warning' => $observedMonitoring['warning'],
                    'critical' => $observedMonitoring['critical'],
                ],
                'pages' => $observedPages->count(),
                'top_alerts' => collect($observedMonitoring['items'] ?? [])
                    ->filter(fn (array $item): bool => in_array($item['state'] ?? null, ['warning', 'critical'], true))
                    ->take(5)
                    ->values()
                    ->all(),
            ],
        ]);
    }

    public function observedPages(
        Request $request,
        SeoEngineContext $context,
        RuntimeSeoMonitoringService $monitoring,
    ): JsonResponse {
        $limit = max(1, min(100, (int) $request->integer('limit', 25)));
        $state = $request->query('state');
        $path = trim((string) $request->query('path', ''));

        $items = collect($monitoring->observedSummary($context->siteId(), max($limit * 3, $limit))['items'] ?? []);

        if (is_string($state) && $state !== '') {
            $items = $items->where('state', $state);
        }

        if ($path !== '') {
            $items = $items->filter(fn (array $item): bool => str_contains((string) ($item['path'] ?? ''), $path));
        }

        $items = $items->take($limit)->values();

        return response()->json([
            'site_id' => $context->siteId(),
            'count' => $items->count(),
            'items' => $items->all(),
        ]);
    }

    public function internalLinks(Request $request, SeoPageRepository $pages, SemanticLinkRepository $semanticLinks): JsonResponse
    {
        $slug = (string) $request->query('slug', '');
        $page = $pages->findBySlug($slug);
        abort_if(! $page, 404, 'SEO page not found.');
        $observedPage = $this->observedPageForSlug($slug);
        $observed = $observedPage ? $this->observedPagePayload($observedPage) : null;

        return response()->json([
            'page' => $page,
            'internal_links' => $semanticLinks->internalLinkSuggestions($slug),
            'cannibalization_risks' => $semanticLinks->cannibalizationRisks($slug),
            'query_matches' => $semanticLinks->queryPageMatches($slug),
            'observed' => $observed,
        ]);
    }

    public function indexation(Request $request, SeoPageRepository $pages, SearchConsoleService $searchConsole): JsonResponse
    {
        $slug = (string) $request->query('slug', '');
        $page = $pages->findBySlug($slug);
        abort_if(! $page, 404, 'SEO page not found.');

        $pageUrl = rtrim((string) config('app.url'), '/').$page->canonicalPath();

        return response()->json([
            'page' => $page,
            'inspection' => $searchConsole->inspectPageUrl($pageUrl),
        ]);
    }

    public function pages(Request $request, SeoEngineContext $context): JsonResponse
    {
        $siteId = $context->siteId();
        $includeObserved = $request->boolean('include_observed', false);

        if ($request->filled('slug')) {
            $page = SeoPage::query()
                ->where('site_id', $siteId)
                ->where('slug', ltrim((string) $request->query('slug'), '/'))
                ->first();
            abort_if(! $page, 404, 'SEO page not found.');

            if (! $includeObserved) {
                return response()->json($page);
            }

            $observedPage = $this->observedPageForLegacy($page);

            return response()->json([
                'page' => $page,
                'observed' => $observedPage ? $this->observedPagePayload($observedPage) : null,
            ]);
        }

        $pages = SeoPage::query()
            ->where('site_id', $siteId)
            ->orderByDesc('updated_at')
            ->paginate((int) $request->integer('per_page', 25));

        if (! $includeObserved) {
            return response()->json($pages);
        }

        $observedPages = SeoSitePage::query()
            ->where('site_id', $siteId)
            ->whereIn('path', $pages->getCollection()->map(fn (SeoPage $page): string => $page->canonicalPath())->all())
            ->get()
            ->keyBy('path');

        $items = $pages->getCollection()->map(function (SeoPage $page) use ($observedPages): array {
            $observedPage = $observedPages->get($page->canonicalPath());

            return [
                'page' => $page,
                'observed' => $observedPage ? $this->observedPagePayload($observedPage) : null,
            ];
        });

        return response()->json([
            'current_page' => $pages->currentPage(),
            'data' => $items->values()->all(),
            'from' => $pages->firstItem(),
            'last_page' => $pages->lastPage(),
            'per_page' => $pages->perPage(),
            'to' => $pages->lastItem(),
            'total' => $pages->total(),
        ]);
    }

    public function sitemap(SeoEngineContext $context, SeoPageRepository $pages): JsonResponse
    {
        $siteUrl = rtrim($context->url(), '/');

        $entries = collect($pages->publishedPages())
            ->map(fn (object $page): array => [
                'loc'        => $siteUrl.$page->canonicalPath(),
                'lastmod'    => $page->updated_at?->toDateString(),
                'changefreq' => 'weekly',
                'priority'   => '0.8',
            ])
            ->values();

        return response()->json([
            'site_id' => $context->siteId(),
            'count'   => $entries->count(),
            'entries' => $entries,
        ]);
    }

    private function observedPageForSlug(string $slug): ?SeoSitePage
    {
        $siteId = app(SeoEngineContext::class)->siteId();
        $path = '/'.ltrim($slug, '/');

        return SeoSitePage::query()
            ->where('site_id', $siteId)
            ->where('path', $path)
            ->first();
    }

    private function observedPageForLegacy(SeoPage $page): ?SeoSitePage
    {
        return SeoSitePage::query()
            ->where('site_id', $page->site_id)
            ->where('path', $page->canonicalPath())
            ->first();
    }

    /**
     * @return array<string,mixed>
     */
    private function observedPagePayload(SeoSitePage $page): array
    {
        $pageHealth = app(\App\ObservedSite\ObservedPageHealthService::class)->forPage($page);

        return [
            'id' => $page->id,
            'path' => $page->path,
            'url' => $page->normalized_url,
            'title' => $page->title,
            'cluster_label' => $page->cluster_label,
            'indexability_state' => $page->indexability_state,
            'last_status_code' => $page->last_status_code,
            'latest_word_count' => $page->latest_word_count,
            'authority_score' => (float) $page->authority_score,
            'orphan_score' => (float) $page->orphan_score,
            'overlap_score' => (float) $page->overlap_score,
            'health' => $pageHealth,
        ];
    }
}
