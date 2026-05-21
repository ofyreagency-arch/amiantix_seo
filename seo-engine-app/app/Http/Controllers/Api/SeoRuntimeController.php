<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\ObservedSite\ObservedRewriteBridgeService;
use App\Models\SeoPage;
use App\Models\SeoSearchConsoleMetric;
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
use Throwable;

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

    public function internalLinks(Request $request, SeoPageRepository $pages, SemanticLinkRepository $semanticLinks): JsonResponse
    {
        $slug = (string) $request->query('slug', '');
        $page = $pages->findBySlug($slug);
        abort_if(! $page, 404, 'SEO page not found.');

        return response()->json([
            'page' => $page,
            'internal_links' => $semanticLinks->internalLinkSuggestions($slug),
            'cannibalization_risks' => $semanticLinks->cannibalizationRisks($slug),
            'query_matches' => $semanticLinks->queryPageMatches($slug),
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

        if ($request->filled('slug')) {
            $page = SeoPage::query()
                ->where('site_id', $siteId)
                ->where('slug', ltrim((string) $request->query('slug'), '/'))
                ->first();
            abort_if(! $page, 404, 'SEO page not found.');

            return response()->json($page);
        }

        return response()->json(
            SeoPage::query()
                ->where('site_id', $siteId)
                ->orderByDesc('updated_at')
                ->paginate((int) $request->integer('per_page', 25))
        );
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
}
