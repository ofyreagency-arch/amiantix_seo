<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\ObservedSite\ObservedRewriteBridgeService;
use App\Models\SeoPage;
use App\Models\SeoSite;
use App\Models\SeoSuggestion;
use App\SeoBridge\Repositories\DatabaseSeoCockpitRepository;
use App\Runtime\RuntimeSeoMonitoringService;
use App\Runtime\SeoEngineContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Ofyre\SeoEngine\Services\Console\SeoGeneratePageRunner;
use Ofyre\SeoEngine\Services\Rewrite\SeoRewriteService;
use Ofyre\SeoEngine\Services\Review\SeoPageStatusService;

class AdminPagesController extends Controller
{
    public function __construct(private readonly SeoEngineContext $context) {}

    public function show(string $siteId, int $pageId, ObservedRewriteBridgeService $observedRewrite): View
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

        return view('admin.pages.show', compact('site', 'page', 'observedRewriteContext', 'latestMetric', 'pendingSuggestions'));
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
        $observedContext = $observedRewrite->syncForPage($dbPage);
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

    public function applySuggestion(string $siteId, int $pageId, int $suggestionId): RedirectResponse
    {
        $this->loadSite($siteId);
        $page = SeoPage::query()->where('site_id', $siteId)->findOrFail($pageId);
        $suggestion = SeoSuggestion::query()
            ->where('seo_page_id', $page->id)
            ->findOrFail($suggestionId);

        $payload = is_array($suggestion->suggestions_json) ? $suggestion->suggestions_json : [];
        $updates = [];

        foreach (['title', 'meta_description', 'h1'] as $field) {
            $value = trim((string) ($payload[$field] ?? ''));

            if ($value !== '') {
                $updates[$field] = $value;
            }
        }

        $content = trim((string) ($payload['content'] ?? $payload['proposed_content'] ?? ''));
        if ($content !== '') {
            $updates['content'] = $content;
        }

        if (is_array($payload['faq'] ?? null) && $payload['faq'] !== []) {
            $updates['faq_json'] = collect($payload['faq'])
                ->filter(fn (mixed $item): bool => is_array($item) && filled($item['question'] ?? null))
                ->map(fn (array $item): array => [
                    'question' => (string) ($item['question'] ?? ''),
                    'answer' => (string) ($item['answer'] ?? ''),
                ])
                ->values()
                ->all();
        }

        if (is_array($payload['internal_links'] ?? null) && $payload['internal_links'] !== []) {
            $updates['internal_links_json'] = collect($payload['internal_links'])
                ->filter(fn (mixed $item): bool => is_array($item) && filled($item['url'] ?? null))
                ->map(fn (array $item): array => [
                    'label' => (string) ($item['label'] ?? $item['text'] ?? $item['url']),
                    'url' => (string) ($item['url'] ?? ''),
                    'reason' => $item['reason'] ?? null,
                ])
                ->values()
                ->all();
        }

        if (is_array($payload['schema'] ?? null)) {
            $updates['schema_json'] = $payload['schema'];
        }

        if ($page->status === 'draft' && $updates !== []) {
            $updates['status'] = 'review';
        }

        if ($updates !== []) {
            $page->update($updates);
        }

        $suggestion->update([
            'status' => 'applied',
            'applied_at' => now(),
        ]);

        $updatedFields = array_keys($updates);
        $bodyApplied = in_array('content', $updatedFields, true);
        $message = $bodyApplied
            ? 'Suggestion appliquée à la page.'
            : 'Suggestion appliquée partiellement : métadonnées, FAQ et maillage mis à jour.';

        $redirect = redirect()
            ->route('admin.pages.show', [$siteId, $pageId])
            ->with('success', $message)
            ->with('applied_suggestion_fields', $updatedFields);

        if (! $bodyApplied && is_array($payload['sections'] ?? null) && $payload['sections'] !== []) {
            $redirect->with('warning', 'Le corps de page n a pas été remplacé automatiquement car cette suggestion contient surtout des sections et recommandations éditoriales.');
        }

        return $redirect;
    }

    private function loadSite(string $siteId): SeoSite
    {
        $site = SeoSite::query()->where('site_id', $siteId)->where('is_active', true)->firstOrFail();
        $this->context->loadFromSite($site);

        return $site;
    }
}
