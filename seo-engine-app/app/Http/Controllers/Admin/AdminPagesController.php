<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SeoPage;
use App\Models\SeoSite;
use App\SeoBridge\Repositories\DatabaseSeoCockpitRepository;
use App\Services\RuntimeSeoMonitoringService;
use App\Services\SeoEngineContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Ofyre\SeoEngine\Contracts\SeoPageRepository;
use Ofyre\SeoEngine\Services\Console\SeoGeneratePageRunner;
use Ofyre\SeoEngine\Services\Rewrite\SeoRewriteService;
use Ofyre\SeoEngine\Services\Review\SeoPageStatusService;

class AdminPagesController extends Controller
{
    public function __construct(private readonly SeoEngineContext $context) {}

    public function show(string $siteId, int $pageId): View
    {
        $site = SeoSite::query()->where('site_id', $siteId)->firstOrFail();
        $page = SeoPage::query()->where('site_id', $siteId)->findOrFail($pageId);

        return view('admin.pages.show', compact('site', 'page'));
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
        SeoPageRepository $pages,
        SeoRewriteService $rewrite,
    ): RedirectResponse {
        $data   = $request->validate(['mode' => ['nullable', 'string']]);
        $this->loadSite($siteId);
        $dbPage = SeoPage::query()->where('site_id', $siteId)->findOrFail($pageId);
        $page   = $pages->findBySlug($dbPage->slug);
        abort_if(! $page, 404);

        $suggestion     = $rewrite->createSuggestion($page, $data['mode'] ?? 'enrich');
        $suggestionData = method_exists($suggestion, 'toArray') ? $suggestion->toArray() : (array) $suggestion;

        return redirect()->route('admin.pages.show', [$siteId, $pageId])
            ->with('rewrite_suggestion', $suggestionData);
    }

    public function analyze(
        string $siteId,
        int $pageId,
        SeoPageRepository $pages,
        SeoPageStatusService $statusService,
        DatabaseSeoCockpitRepository $cockpit,
    ): RedirectResponse {
        $this->loadSite($siteId);
        $dbPage = SeoPage::query()->where('site_id', $siteId)->findOrFail($pageId);
        $page   = $pages->findBySlug($dbPage->slug);
        abort_if(! $page, 404);

        $analysis = [
            'status_report'    => $statusService->summarize($page),
            'semantic_context' => $cockpit->semanticContextForPage($page),
            'timeline'         => $cockpit->timelineForPage($page),
        ];

        return redirect()->route('admin.pages.show', [$siteId, $pageId])
            ->with('analysis', $analysis);
    }

    public function autopilot(string $siteId, RuntimeSeoMonitoringService $monitoring): RedirectResponse
    {
        $this->loadSite($siteId);
        $monitoring->monitor();

        return redirect()->route('admin.sites.show', $siteId)
            ->with('success', 'Autopilot exécuté.');
    }

    private function loadSite(string $siteId): SeoSite
    {
        $site = SeoSite::query()->where('site_id', $siteId)->where('is_active', true)->firstOrFail();
        $this->context->loadFromSite($site);

        return $site;
    }
}
