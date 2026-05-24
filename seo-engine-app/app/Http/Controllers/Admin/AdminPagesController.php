<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\ActionLayer\SeoSuggestionWorkflowService;
use App\Http\Controllers\Controller;
use App\ObservedSite\ObservedRewriteBridgeService;
use App\Models\SeoPage;
use App\Models\SeoSite;
use App\Models\SeoSuggestion;
use App\SeoBridge\Repositories\DatabaseSeoCockpitRepository;
use App\Services\Publication\SeoLivePublicationService;
use App\Services\Media\SeoPageImageGenerator;
use App\Runtime\GscOpportunityService;
use App\Runtime\IndexationBacklogService;
use App\Runtime\PageLiveMonitoringService;
use App\Runtime\PageWorkflowLifecycleService;
use App\Runtime\RuntimeSeoMonitoringService;
use App\Runtime\SeoEngineContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Ofyre\SeoEngine\Services\Console\SeoGeneratePageRunner;
use Ofyre\SeoEngine\Services\Rewrite\SeoRewriteService;
use Ofyre\SeoEngine\Services\Review\SeoPageStatusService;
use Ofyre\SeoEngine\Services\Scoring\SeoScoreRefreshService;

class AdminPagesController extends Controller
{
    public function __construct(private readonly SeoEngineContext $context) {}

    public function show(
        string $siteId,
        int $pageId,
        ObservedRewriteBridgeService $observedRewrite,
        SeoPageStatusService $statusService,
        SeoScoreRefreshService $scoreRefresh,
        IndexationBacklogService $indexationBacklog,
        GscOpportunityService $gscOpportunities,
        PageLiveMonitoringService $liveMonitoring,
        PageWorkflowLifecycleService $pageWorkflowLifecycle,
        SeoLivePublicationService $livePublication,
    ): View
    {
        $site = $this->loadSite($siteId);
        $page = SeoPage::query()
            ->with([
                'suggestions' => fn ($query) => $query->orderByDesc('created_at'),
                'searchConsoleMetrics' => fn ($query) => $query->orderByDesc('metric_date')->orderByDesc('id'),
            ])
            ->where('site_id', $siteId)
            ->findOrFail($pageId);
        $page = $scoreRefresh->refresh($page);
        $observedRewriteContext = $observedRewrite->contextForPage($page);
        $latestMetric = $page->searchConsoleMetrics->first();
        $pendingSuggestions = $page->suggestions->where('status', 'pending')->values();
        $publicationSummary = $statusService->summarize($page, true);
        $pageIndexationBacklog = $indexationBacklog->summarizeForPage($page);
        $publicationTargetStatus = $livePublication->targetStatusForSite($site);
        $pageGscOpportunity = collect($gscOpportunities->summarize($siteId, $site->hasSearchConsoleConfigured())['items'] ?? [])
            ->where('page_id', $page->id)
            ->sortByDesc('priority_score')
            ->first();
        $pageLiveMonitoring = $liveMonitoring->summarize(
            $page,
            $site,
            is_array($pageGscOpportunity) ? $pageGscOpportunity : null,
        );
        $pageLifecycleSummary = $pageWorkflowLifecycle->summarize(
            $page,
            $publicationSummary,
            $pageIndexationBacklog,
            $publicationTargetStatus,
            is_array($pageGscOpportunity) ? $pageGscOpportunity : null,
            $pendingSuggestions->count(),
        );

        return view('admin.pages.show', compact('site', 'page', 'observedRewriteContext', 'latestMetric', 'pendingSuggestions', 'publicationSummary', 'pageIndexationBacklog', 'pageGscOpportunity', 'pageLifecycleSummary', 'publicationTargetStatus', 'pageLiveMonitoring'));
    }

    public function generate(Request $request, string $siteId, SeoGeneratePageRunner $runner): RedirectResponse
    {
        $data = $request->validate([
            'keyword' => ['required', 'string', 'max:255'],
            'status'  => ['nullable', 'string'],
        ]);

        $this->loadSite($siteId);
        $result = $runner->run($data['keyword'], $data['status'] ?? 'draft', false);

        $pageId = $result['page']->id ?? null;

        if ($pageId) {
            $page = $result['page'];
            $redirect = redirect()->route('admin.pages.show', [$siteId, $pageId]);

            if (($page->generation_source ?? null) === 'fallback') {
                return $redirect
                    ->with('success', 'Page générée en mode fallback preset.')
                    ->with('warning', $page->generation_error ?: 'La génération AI a échoué, le preset fallback a pris la main.');
            }

            if (($page->generation_source ?? null) === 'hybrid') {
                return $redirect
                    ->with('success', 'Page générée en mode hybride AI + preset.')
                    ->with('warning', 'La base vient de l’IA, mais le preset a complété certaines sections pour fermer les trous du payload.');
            }

            return $redirect->with('success', 'Page générée avec succès via AI.');
        }

        return redirect()->route('admin.sites.show', $siteId)
            ->with('success', 'Page générée.')
            ->with('warning', $result['warning'] ?? null);
    }

    public function rewrite(
        Request $request,
        string $siteId,
        int $pageId,
        SeoRewriteService $rewrite,
        ObservedRewriteBridgeService $observedRewrite,
    ): RedirectResponse {
        $data   = $request->validate(['mode' => ['nullable', 'string']]);
        $this->loadSite($siteId);
        $dbPage = SeoPage::query()->where('site_id', $siteId)->findOrFail($pageId);
        $observedContext = $observedRewrite->contextForPage($dbPage);
        $page = $dbPage->fresh(['suggestions']);

        $suggestion     = $rewrite->createSuggestion($page, $data['mode'] ?? 'enrich');
        $suggestionData = method_exists($suggestion, 'toArray') ? $suggestion->toArray() : (array) $suggestion;

        return redirect()->route('admin.pages.show', [$siteId, $pageId])
            ->with('rewrite_suggestion', $suggestionData)
            ->with('observed_rewrite_context', $observedContext);
    }

    public function analyze(
        string $siteId,
        int $pageId,
        SeoPageStatusService $statusService,
        DatabaseSeoCockpitRepository $cockpit,
        ObservedRewriteBridgeService $observedRewrite,
        SeoScoreRefreshService $scoreRefresh,
    ): RedirectResponse {
        $this->loadSite($siteId);
        $dbPage = SeoPage::query()->where('site_id', $siteId)->findOrFail($pageId);
        $dbPage = $scoreRefresh->refresh($dbPage);
        $observedContext = $observedRewrite->contextForPage($dbPage);

        $analysis = [
            'status_report' => $statusService->summarize($dbPage),
            'semantic_context' => $cockpit->semanticContextForPage($dbPage),
            'timeline' => $cockpit->timelineForPage($dbPage),
            'observed_rewrite' => $observedContext,
        ];

        return redirect()->route('admin.pages.show', [$siteId, $pageId])
            ->with('analysis', $analysis);
    }

    public function autopilot(string $siteId, RuntimeSeoMonitoringService $monitoring): RedirectResponse
    {
        $this->loadSite($siteId);
        $legacy = $monitoring->monitor();
        $observed = $monitoring->syncObservedRewriteSignals($siteId);

        return redirect()->route('admin.sites.show', $siteId)
            ->with('success', sprintf(
                'Autopilot exécuté. %d page(s) legacy auditée(s), %d signal(s) rewrite observed synchronisé(s).',
                (int) ($legacy['audited'] ?? 0),
                (int) ($observed['synced'] ?? 0)
            ));
    }

    public function applySuggestion(
        string $siteId,
        int $pageId,
        int $suggestionId,
        SeoSuggestionWorkflowService $workflow,
        SeoScoreRefreshService $scoreRefresh,
    ): RedirectResponse
    {
        $this->loadSite($siteId);
        $suggestion = SeoSuggestion::query()
            ->whereHas('page', fn ($query) => $query->where('site_id', $siteId)->whereKey($pageId))
            ->findOrFail($suggestionId);
        $result = $workflow->apply($suggestion);
        $updatedFields = $result['updated_fields'];
        $bodyApplied = $result['body_applied'];
        $contentBlockedForRegression = $result['content_blocked_for_regression'] ?? false;
        $nonContentUpdatesBlockedForRegression = $result['non_content_updates_blocked_for_regression'] ?? false;

        $page = SeoPage::query()->find($pageId);
        if ($page) {
            $page = $scoreRefresh->refresh($page->refresh());

            if ($contentBlockedForRegression) {
                $page->update([
                    'review_issues_json' => collect($page->review_issues_json ?? [])
                        ->map(fn (mixed $item): string => is_array($item) ? (string) ($item['message'] ?? json_encode($item)) : (string) $item)
                        ->push('Content patch skipped because it would degrade the current article quality.')
                        ->filter(fn (string $item): bool => trim($item) !== '')
                        ->unique()
                        ->values()
                        ->all(),
                ]);
            }
        }

        $message = $bodyApplied
            ? 'Suggestion appliquée et scores recalculés.'
            : (($contentBlockedForRegression && $nonContentUpdatesBlockedForRegression)
                ? 'Suggestion approuvée : aucun patch éditorial n a été appliqué pour protéger l article actuel.'
                : ($result['signal_notes_applied']
                    ? 'Suggestion approuvée : la page a été marquée pour revue avec ses signaux et recommandations.'
                    : 'Suggestion appliquée partiellement : métadonnées, FAQ et maillage mis à jour.'));

        $redirect = redirect()
            ->route('admin.pages.show', [$siteId, $pageId])
            ->with('success', $message)
            ->with('applied_suggestion_fields', $updatedFields);

        if (! $bodyApplied && $result['signal_notes_applied']) {
            $redirect->with('warning', 'Cette suggestion ne contenait pas de corps complet à injecter. Ses recommandations ont été ramenées dans la fiche page pour guider la prochaine passe éditoriale.');
        }

        if ($contentBlockedForRegression) {
            $redirect->with('warning', $nonContentUpdatesBlockedForRegression
                ? 'Le patch proposé a été entièrement neutralisé pour éviter de dégrader un article déjà plus fort.'
                : 'Le patch de contenu a été bloqué pour éviter de dégrader un article déjà plus fort. Les autres améliorations sûres ont été conservées.');
        }

        return $redirect;
    }

    public function publish(
        string $siteId,
        int $pageId,
        SeoPageStatusService $statusService,
        SeoScoreRefreshService $scoreRefresh,
    ): RedirectResponse {
        $this->loadSite($siteId);
        $page = SeoPage::query()->where('site_id', $siteId)->findOrFail($pageId);
        $page = $scoreRefresh->refresh($page);
        $summary = $statusService->summarize($page, true);

        if ($summary['blocking_reasons'] !== []) {
            return redirect()
                ->route('admin.pages.show', [$siteId, $pageId])
                ->with('warning', 'Publication bloquée tant que certains points ne sont pas validés.')
                ->with('publication_summary', $summary);
        }

        $page->update([
            'status' => 'published',
            'published_at' => $page->published_at ?? now(),
        ]);

        return redirect()
            ->route('admin.pages.show', [$siteId, $pageId])
            ->with('success', 'Page publiée côté moteur.');
    }

    public function publishLive(
        string $siteId,
        int $pageId,
        SeoLivePublicationService $livePublication,
    ): RedirectResponse {
        $site = $this->loadSite($siteId);
        $page = SeoPage::query()->where('site_id', $siteId)->findOrFail($pageId);

        if (! $page->isPublishedInEngine()) {
            return redirect()
                ->route('admin.pages.show', [$siteId, $pageId])
                ->with('warning', 'La page doit d’abord être publiée côté moteur avant de pouvoir être poussée en live.');
        }

        try {
            $page = $livePublication->publish($page, $site);
        } catch (\RuntimeException $exception) {
            return redirect()
                ->route('admin.pages.show', [$siteId, $pageId])
                ->with('warning', $exception->getMessage());
        }

        return redirect()
            ->route('admin.pages.show', [$siteId, $pageId])
            ->with('success', 'Page publiée en live sur le site public.');
    }

    public function quickFix(
        Request $request,
        string $siteId,
        int $pageId,
        SeoPageImageGenerator $images,
        SeoScoreRefreshService $scoreRefresh,
    ): RedirectResponse
    {
        $action = $request->input('action');
        $this->loadSite($siteId);
        $page = SeoPage::query()->where('site_id', $siteId)->findOrFail($pageId);

        try {
            match ($action) {
                'generate_image' => $images->generate($page),
                'approve_image'  => $images->approve($page),
                'clear_noindex'  => $page->forceFill(['forced_noindex' => false])->save(),
                'set_review'     => $page->forceFill(['status' => 'review'])->save(),
                default          => null,
            };
        } catch (\RuntimeException $exception) {
            return redirect()
                ->route('admin.pages.show', [$siteId, $pageId])
                ->with('warning', $exception->getMessage());
        }

        if (in_array($action, ['clear_noindex', 'set_review', 'approve_image'], true)) {
            $scoreRefresh->refresh($page->refresh());
        }

        return redirect()
            ->route('admin.pages.show', [$siteId, $pageId])
            ->with('success', match ($action) {
                'generate_image' => 'Image IA générée pour la page.',
                'approve_image' => 'Image approuvée pour publication.',
                'clear_noindex' => 'Forced noindex désactivé.',
                'set_review'    => 'Page passée en statut review.',
                default         => 'Action appliquée.',
            });
    }

    public function preview(string $siteId, int $pageId): View
    {
        $site = SeoSite::query()->where('site_id', $siteId)->firstOrFail();
        $page = SeoPage::query()
            ->where('site_id', $siteId)
            ->findOrFail($pageId);

        return view('admin.pages.preview', compact('site', 'page'));
    }

    public function destroy(string $siteId, int $pageId): RedirectResponse
    {
        $this->loadSite($siteId);
        $page = SeoPage::query()->where('site_id', $siteId)->findOrFail($pageId);

        DB::transaction(function () use ($page): void {
            $page->searchConsoleMetrics()->delete();
            $page->audits()->delete();
            $page->suggestions()->delete();
            $page->overrides()->delete();
            $page->delete();
        });

        return redirect()
            ->route('admin.sites.show', $siteId)
            ->with('success', 'Article supprimé.');
    }

    private function loadSite(string $siteId): SeoSite
    {
        $site = SeoSite::query()->where('site_id', $siteId)->where('is_active', true)->firstOrFail();
        $this->context->loadFromSite($site);

        return $site;
    }
}
