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
use App\Services\Media\SeoPageImageGenerator;
use App\Runtime\RuntimeSeoMonitoringService;
use App\Runtime\SeoEngineContext;
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
    ): View
    {
        $site = SeoSite::query()->where('site_id', $siteId)->firstOrFail();
        $page = SeoPage::query()
            ->with([
                'suggestions' => fn ($query) => $query->orderByDesc('created_at'),
                'searchConsoleMetrics' => fn ($query) => $query->orderByDesc('metric_date')->orderByDesc('id'),
            ])
            ->where('site_id', $siteId)
            ->findOrFail($pageId);
        $observedRewriteContext = $observedRewrite->contextForPage($page);
        $latestMetric = $page->searchConsoleMetrics->first();
        $pendingSuggestions = $page->suggestions->where('status', 'pending')->values();
        $publicationSummary = $statusService->summarize($page, true);

        return view('admin.pages.show', compact('site', 'page', 'observedRewriteContext', 'latestMetric', 'pendingSuggestions', 'publicationSummary'));
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
            return redirect()->route('admin.pages.show', [$siteId, $pageId])
                ->with('success', 'Page générée avec succès.');
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
    ): RedirectResponse {
        $this->loadSite($siteId);
        $dbPage = SeoPage::query()->where('site_id', $siteId)->findOrFail($pageId);
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
    ): RedirectResponse
    {
        $this->loadSite($siteId);
        $suggestion = SeoSuggestion::query()
            ->whereHas('page', fn ($query) => $query->where('site_id', $siteId)->whereKey($pageId))
            ->findOrFail($suggestionId);
        $result = $workflow->apply($suggestion);
        $updatedFields = $result['updated_fields'];
        $bodyApplied = $result['body_applied'];
        $message = $bodyApplied
            ? 'Suggestion appliquée à la page.'
            : ($result['signal_notes_applied']
                ? 'Suggestion approuvée : la page a été marquée pour revue avec ses signaux et recommandations.'
                : 'Suggestion appliquée partiellement : métadonnées, FAQ et maillage mis à jour.');

        $redirect = redirect()
            ->route('admin.pages.show', [$siteId, $pageId])
            ->with('success', $message)
            ->with('applied_suggestion_fields', $updatedFields);

        if (! $bodyApplied && $result['signal_notes_applied']) {
            $redirect->with('warning', 'Cette suggestion ne contenait pas de corps complet à injecter. Ses recommandations ont été ramenées dans la fiche page pour guider la prochaine passe éditoriale.');
        }

        return $redirect;
    }

    public function publish(
        string $siteId,
        int $pageId,
        SeoPageStatusService $statusService,
    ): RedirectResponse {
        $this->loadSite($siteId);
        $page = SeoPage::query()->where('site_id', $siteId)->findOrFail($pageId);
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
            ->with('success', 'Page publiée.');
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

        if (in_array($action, ['clear_noindex', 'set_review'], true)) {
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

    private function loadSite(string $siteId): SeoSite
    {
        $site = SeoSite::query()->where('site_id', $siteId)->where('is_active', true)->firstOrFail();
        $this->context->loadFromSite($site);

        return $site;
    }
}
