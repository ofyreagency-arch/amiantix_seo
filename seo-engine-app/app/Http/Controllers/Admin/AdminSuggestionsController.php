<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\ActionLayer\SeoSuggestionWorkflowService;
use App\Http\Controllers\Controller;
use App\Models\SeoRecommendation;
use App\Models\SeoSite;
use App\Models\SeoSuggestion;
use App\ObservedSite\SiteHealthService;
use App\Runtime\RuntimeSeoMonitoringService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
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
        $pending = $this->decoratePendingSuggestions($pending);

        $stats = [
            'pending'  => SeoSuggestion::query()->whereHas('page', fn ($q) => $q->where('site_id', $siteId))->where('status', 'pending')->count(),
            'applied'  => SeoSuggestion::query()->whereHas('page', fn ($q) => $q->where('site_id', $siteId))->where('status', 'applied')->count(),
            'rejected' => SeoSuggestion::query()->whereHas('page', fn ($q) => $q->where('site_id', $siteId))->where('status', 'rejected')->count(),
            'rewrite_targets' => $pending->getCollection()->sum(fn (SeoSuggestion $suggestion): int => count((array) ($suggestion->dashboard_rewrite_target_plan ?? []))),
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

    public function approve(string $siteId, int $id, SeoSuggestionWorkflowService $workflow): RedirectResponse
    {
        $suggestion = SeoSuggestion::query()
            ->whereHas('page', fn ($query) => $query->where('site_id', $siteId))
            ->findOrFail($id);
        $result = $workflow->apply($suggestion);

        $message = $result['body_applied']
            ? 'Suggestion approuvée et appliquée à la page.'
            : ($result['signal_notes_applied']
                ? 'Suggestion approuvée : ses recommandations ont été ramenées dans la fiche page pour revue.'
                : 'Suggestion approuvée.');

        return redirect()->route('admin.sites.autopilot', $siteId)
            ->with('success', $message);
    }

    public function reject(string $siteId, int $id): RedirectResponse
    {
        $suggestion = SeoSuggestion::query()->findOrFail($id);
        $suggestion->update(['status' => 'rejected', 'rejected_at' => now()]);

        return redirect()->route('admin.sites.autopilot', $siteId);
    }

    private function decoratePendingSuggestions(LengthAwarePaginator $pending): LengthAwarePaginator
    {
        $pending->setCollection(
            $pending->getCollection()->map(function (SeoSuggestion $suggestion): SeoSuggestion {
                $summary = is_array($suggestion->suggestions_json['signals_summary'] ?? null)
                    ? $suggestion->suggestions_json['signals_summary']
                    : [];
                $targetPlan = Arr::wrap($summary['rewrite_target_plan'] ?? []);

                $suggestion->dashboard_rewrite_target_plan = collect($targetPlan)
                    ->filter(fn (mixed $item): bool => is_array($item) && is_string($item['heading'] ?? null))
                    ->map(function (array $item): array {
                        $reasons = collect(Arr::wrap($item['reasons'] ?? []))
                            ->filter(fn (mixed $reason): bool => is_string($reason) && trim($reason) !== '')
                            ->values()
                            ->all();

                        return [
                            'heading' => (string) ($item['heading'] ?? ''),
                            'phase' => is_string($item['phase'] ?? null) ? (string) $item['phase'] : null,
                            'patch_intent' => (string) ($item['patch_intent'] ?? 'local_reinforcement'),
                            'replacement_mode' => (string) ($item['replacement_mode'] ?? 'replace_if_better'),
                            'instruction' => (string) ($item['instruction'] ?? ''),
                            'reasons' => $reasons,
                        ];
                    })
                    ->values()
                    ->all();

                return $suggestion;
            })
        );

        return $pending;
    }
}
