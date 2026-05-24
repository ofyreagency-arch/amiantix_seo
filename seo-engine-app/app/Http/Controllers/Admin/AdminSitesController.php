<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SeoPage;
use App\Models\SeoSite;
use App\Models\SeoSiteCrawl;
use App\Models\SeoSiteGoogleConnection;
use App\Models\SeoSuggestion;
use App\ObservedSite\SiteHealthService;
use App\Runtime\GscOpportunityService;
use App\Runtime\IndexationBacklogService;
use App\Runtime\RuntimeSeoMonitoringService;
use App\Services\Preset\PresetManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Ofyre\SeoEngine\Services\Rewrite\SeoRewriteService;

class AdminSitesController extends Controller
{
    public function __construct(
        private readonly PresetManager $presets,
    ) {}

    public function index(): View
    {
        $sites = SeoSite::query()
            ->with('googleConnection')
            ->orderByDesc('created_at')
            ->get();

        return view('admin.sites.index', [
            'sites' => $sites,
            'availablePresets' => $this->presets->availablePresets(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'site_id'     => ['required', 'string', 'max:60', 'unique:seo_sites,site_id'],
            'name'        => ['required', 'string', 'max:120'],
            'url'         => ['required', 'url', 'max:255'],
            'niche'       => ['required', 'string', 'max:80'],
            'locale'      => ['required', 'string', 'max:10'],
            'preset'      => ['required', 'string', 'in:generic,amiantix'],
            'webhook_url' => ['nullable', 'url', 'max:255'],
            'gsc_connection_mode' => ['nullable', 'string', 'in:service_account,oauth_google'],
            'gsc_property_url' => ['nullable', 'string', 'max:500'],
            'gsc_credentials_path' => ['nullable', 'string', 'max:500'],
            'gsc_account_email' => ['nullable', 'email', 'max:255'],
        ]);

        $token = SeoSite::generateToken();

        $site = SeoSite::query()->create([
            ...$data,
            'api_token_hash' => $token['hash'],
            'is_active'      => true,
            'gsc_site_url' => $data['gsc_property_url'] ?? null,
            'gsc_credentials_path' => $data['gsc_credentials_path'] ?? null,
        ]);

        $this->syncGoogleConnection($site, $data);

        return redirect()->route('admin.sites.index')
            ->with('new_token', $token['token'])
            ->with('new_token_site', $data['name']);
    }

    public function show(
        string $siteId,
        SiteHealthService $siteHealth,
        RuntimeSeoMonitoringService $monitoring,
        GscOpportunityService $gscOpportunities,
        IndexationBacklogService $indexationBacklog,
    ): View
    {
        $site  = SeoSite::query()->with('googleConnection')->where('site_id', $siteId)->firstOrFail();
        $pages = SeoPage::query()
            ->where('site_id', $siteId)
            ->select(['id', 'keyword', 'slug', 'status', 'seo_score', 'quality_score', 'updated_at'])
            ->orderByDesc('updated_at')
            ->paginate(25);

        $observedHealth = $siteHealth->calculate($siteId);
        $observedMonitoring = $monitoring->observedSummary($siteId, 12);
        $latestCrawl = SeoSiteCrawl::query()
            ->where('site_id', $siteId)
            ->orderByDesc('completed_at')
            ->orderByDesc('created_at')
            ->first();

        $observedMetrics = [
            'observed_pages' => $observedHealth['total_pages'] ?? 0,
            'published_pages' => $observedHealth['published'] ?? 0,
            'draft_pages' => $observedHealth['draft'] ?? 0,
            'error_pages' => $observedHealth['errors'] ?? 0,
            'generated_pages' => $pages->total(),
            'healthy_pages' => $observedMonitoring['healthy'] ?? 0,
            'warning_pages' => $observedMonitoring['warning'] ?? 0,
            'critical_pages' => $observedMonitoring['critical'] ?? 0,
        ];

        $observedAlerts = collect($observedMonitoring['items'] ?? [])
            ->filter(fn (array $item): bool => in_array($item['state'] ?? null, ['warning', 'critical'], true))
            ->take(4)
            ->values();

        $gscOpportunitySummary = $gscOpportunities->summarize(
            $siteId,
            $site->hasSearchConsoleConfigured()
        );
        $gscConnection = $site->resolvedGoogleConnection();
        $gscSyncDetails = is_array($gscConnection?->meta_json['last_sync'] ?? null)
            ? $gscConnection->meta_json['last_sync']
            : [];
        $indexationBacklogSummary = $indexationBacklog->summarize($siteId);

        return view('admin.sites.show', compact(
            'site',
            'pages',
            'observedHealth',
            'observedMonitoring',
            'observedMetrics',
            'observedAlerts',
            'latestCrawl',
            'gscOpportunitySummary',
            'gscSyncDetails',
            'indexationBacklogSummary',
        ));
    }

    public function updateGoogleConnection(string $siteId, Request $request): RedirectResponse
    {
        $site = SeoSite::query()->with('googleConnection')->where('site_id', $siteId)->firstOrFail();

        $data = $request->validate([
            'gsc_connection_mode' => ['nullable', 'string', 'in:service_account,oauth_google'],
            'gsc_property_url' => ['nullable', 'string', 'max:500'],
            'gsc_credentials_path' => ['nullable', 'string', 'max:500'],
            'gsc_account_email' => ['nullable', 'email', 'max:255'],
        ]);

        $site->forceFill([
            'gsc_site_url' => $data['gsc_property_url'] ?: null,
            'gsc_credentials_path' => $data['gsc_credentials_path'] ?: null,
        ])->save();

        $this->syncGoogleConnection($site, $data);

        return redirect()
            ->route('admin.sites.show', $siteId)
            ->with('success', 'Connexion Google mise à jour.');
    }

    public function runGscOpportunity(
        string $siteId,
        Request $request,
        GscOpportunityService $gscOpportunities,
        SeoRewriteService $rewrite,
    ): RedirectResponse {
        $site = $this->loadSite($siteId);

        $data = $request->validate([
            'page_id' => ['required', 'integer'],
            'type' => ['required', 'string'],
        ]);

        $page = SeoPage::query()
            ->where('site_id', $siteId)
            ->findOrFail((int) $data['page_id']);

        $opportunities = collect($gscOpportunities->summarize($siteId, $site->hasSearchConsoleConfigured())['items'] ?? []);
        $opportunity = $opportunities->first(fn (array $item): bool => (int) ($item['page_id'] ?? 0) === (int) $page->id && (string) ($item['type'] ?? '') === (string) $data['type']);

        if (! is_array($opportunity)) {
            return redirect()
                ->route('admin.sites.show', $siteId)
                ->with('warning', 'Cette opportunité GSC n est plus disponible ou a déjà changé.');
        }

        if (! empty($opportunity['cooldown_active'])) {
            return redirect()
                ->route('admin.pages.show', [$siteId, $page->id])
                ->with('warning', 'Cette page est encore en cooldown pour ce signal GSC.');
        }

        $mode = (string) ($opportunity['mode'] ?? 'enrich');
        $existingPending = $this->findExistingPendingGscSuggestion(
            $page->id,
            $mode,
            (string) $data['type'],
            isset($opportunity['query']) ? (string) $opportunity['query'] : null,
        );

        if ($existingPending !== null) {
            return redirect()
                ->route('admin.pages.show', [$siteId, $page->id])
                ->with('warning', 'Une suggestion GSC de ce type existe déjà pour cette page.');
        }

        $suggestion = $rewrite->createSuggestion($page->fresh(['suggestions']), $mode);
        $suggestion->forceFill([
            'signals_json' => array_merge($suggestion->signals_json ?? [], [
                'gsc_trigger' => array_filter([
                    'type' => (string) $data['type'],
                    'mode' => $mode,
                    'action' => (string) ($opportunity['action'] ?? ''),
                    'reason' => (string) ($opportunity['reason'] ?? ''),
                    'query' => isset($opportunity['query']) ? (string) $opportunity['query'] : null,
                ], static fn (mixed $value): bool => $value !== null && $value !== ''),
            ]),
        ])->save();

        return redirect()
            ->route('admin.pages.show', [$siteId, $page->id])
            ->with('rewrite_suggestion', method_exists($suggestion, 'toArray') ? $suggestion->toArray() : (array) $suggestion)
            ->with('success', 'Suggestion créée depuis Google Search Console : '.$opportunity['action'].'.');
    }

    public function rotateToken(string $siteId): RedirectResponse
    {
        $site  = SeoSite::query()->where('site_id', $siteId)->firstOrFail();
        $token = SeoSite::generateToken();
        $site->update(['api_token_hash' => $token['hash']]);

        return redirect()->route('admin.sites.show', $siteId)
            ->with('new_token', $token['token'])
            ->with('new_token_site', $site->name);
    }

    public function destroy(string $siteId): RedirectResponse
    {
        SeoSite::query()->where('site_id', $siteId)->update(['is_active' => false]);

        return redirect()->route('admin.sites.index')->with('success', 'Site désactivé.');
    }

    /**
     * @param  array<string,mixed>  $data
     */
    private function syncGoogleConnection(SeoSite $site, array $data): void
    {
        $propertyUrl = trim((string) ($data['gsc_property_url'] ?? ''));
        $credentialsPath = trim((string) ($data['gsc_credentials_path'] ?? ''));
        $accountEmail = trim((string) ($data['gsc_account_email'] ?? ''));
        $mode = trim((string) ($data['gsc_connection_mode'] ?? ''));

        $hasConnectionData = $propertyUrl !== '' || $credentialsPath !== '' || $accountEmail !== '' || $mode !== '';

        if (! $hasConnectionData) {
            return;
        }

        SeoSiteGoogleConnection::query()->updateOrCreate(
            ['site_id' => $site->site_id],
            [
                'connection_mode' => $mode !== '' ? $mode : 'service_account',
                'property_url' => $propertyUrl !== '' ? $propertyUrl : null,
                'google_account_email' => $accountEmail !== '' ? $accountEmail : null,
                'credentials_path' => $credentialsPath !== '' ? $credentialsPath : null,
                'connection_status' => ($propertyUrl !== '' || $credentialsPath !== '') ? 'configured' : 'not_connected',
                'last_error' => null,
            ],
        );
    }

    private function loadSite(string $siteId): SeoSite
    {
        return SeoSite::query()
            ->with('googleConnection')
            ->where('site_id', $siteId)
            ->firstOrFail();
    }

    private function findExistingPendingGscSuggestion(int $pageId, string $mode, string $type, ?string $query = null): ?SeoSuggestion
    {
        return SeoSuggestion::query()
            ->where('seo_page_id', $pageId)
            ->where('status', 'pending')
            ->where('source', 'rewrite_engine:'.$mode)
            ->get()
            ->first(function (SeoSuggestion $suggestion) use ($type, $query): bool {
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
            });
    }
}
