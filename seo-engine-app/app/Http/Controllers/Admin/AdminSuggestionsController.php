<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SeoRecommendation;
use App\Models\SeoSite;
use App\Models\SeoSuggestion;
use App\ObservedSite\SiteHealthService;
use App\Runtime\RuntimeSeoMonitoringService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class AdminSuggestionsController extends Controller
{
    public function show(
        string $siteId,
        SiteHealthService $siteHealth,
        RuntimeSeoMonitoringService $monitoring,
    ): View
    {
        $site = SeoSite::query()->where('site_id', $siteId)->firstOrFail();

        $pending = SeoSuggestion::query()
            ->whereHas('page', fn ($q) => $q->where('site_id', $siteId))
            ->where('status', 'pending')
            ->with('page:id,keyword,slug,status')
            ->orderByDesc('created_at')
            ->paginate(20);

        $stats = [
            'pending'  => SeoSuggestion::query()->whereHas('page', fn ($q) => $q->where('site_id', $siteId))->where('status', 'pending')->count(),
            'applied'  => SeoSuggestion::query()->whereHas('page', fn ($q) => $q->where('site_id', $siteId))->where('status', 'applied')->count(),
            'rejected' => SeoSuggestion::query()->whereHas('page', fn ($q) => $q->where('site_id', $siteId))->where('status', 'rejected')->count(),
        ];

        $observedHealth = $siteHealth->calculate($siteId);
        $observedMonitoring = $monitoring->observedSummary($siteId, 20);
        $observedAlerts = collect($observedMonitoring['items'] ?? [])
            ->filter(fn (array $item): bool => in_array($item['state'] ?? null, ['warning', 'critical'], true))
            ->take(6)
            ->values();

        $observedRecommendations = SeoRecommendation::query()
            ->where('site_id', $siteId)
            ->where('status', 'pending')
            ->orderBy('priority')
            ->orderByDesc('generated_at')
            ->limit(6)
            ->get();

        $observedStats = [
            'health_score' => (int) ($observedHealth['score'] ?? 0),
            'observed_pages' => (int) ($observedHealth['total_pages'] ?? 0),
            'healthy' => (int) ($observedMonitoring['healthy'] ?? 0),
            'warning' => (int) ($observedMonitoring['warning'] ?? 0),
            'critical' => (int) ($observedMonitoring['critical'] ?? 0),
            'recommendations' => $observedRecommendations->count(),
        ];

        return view('admin.sites.autopilot', compact(
            'site',
            'pending',
            'stats',
            'observedHealth',
            'observedMonitoring',
            'observedAlerts',
            'observedRecommendations',
            'observedStats',
        ));
    }

    public function approve(string $siteId, int $id): RedirectResponse
    {
        $suggestion = SeoSuggestion::query()->findOrFail($id);
        $suggestion->update(['status' => 'applied', 'applied_at' => now()]);

        return redirect()->route('admin.sites.autopilot', $siteId)
            ->with('success', 'Suggestion approuvée.');
    }

    public function reject(string $siteId, int $id): RedirectResponse
    {
        $suggestion = SeoSuggestion::query()->findOrFail($id);
        $suggestion->update(['status' => 'rejected', 'rejected_at' => now()]);

        return redirect()->route('admin.sites.autopilot', $siteId);
    }
}
