<?php

declare(strict_types=1);

namespace App\Runtime;

use App\Models\SeoPage;
use App\Models\SeoSearchConsoleMetric;
use App\Models\SeoSite;
use App\ObservedSite\SeoPageObservedLinkService;
use App\Services\Publication\SeoLivePublicationService;

class PageLiveMonitoringService
{
    public function __construct(
        private readonly SeoPageObservedLinkService $observedLinks,
        private readonly SeoLivePublicationService $livePublication,
    ) {}

    /**
     * @param  array<string,mixed>|null  $pageGscOpportunity
     * @return array<string,mixed>
     */
    public function summarize(SeoPage $page, SeoSite $site, ?array $pageGscOpportunity = null): array
    {
        $observed = $this->observedLinks->resolveMatch($page)['page'] ?? null;
        $pageMetric = SeoSearchConsoleMetric::query()
            ->where('seo_page_id', $page->id)
            ->whereNull('query')
            ->orderByDesc('metric_date')
            ->orderByDesc('id')
            ->first();

        $queryMetricCount = SeoSearchConsoleMetric::query()
            ->where('seo_page_id', $page->id)
            ->whereNotNull('query')
            ->count();

        $publicUrl = $page->live_url ?: $this->livePublication->liveUrlFor($page, $site);
        $observedStatusCode = (int) ($observed->last_status_code ?? 0);
        $observedIndexability = (string) ($observed->indexability_state ?? '');
        $observedNoindex = in_array($observedIndexability, ['noindex', 'non_indexable', 'blocked'], true);
        $observedTechnicalIssue = $observedStatusCode >= 300 || $observedNoindex;

        $monitoringState = match (true) {
            ! $page->isPublishedLive() => 'pre_live',
            $observed && $observedStatusCode >= 400 => 'technical_issue',
            $observed && $observedStatusCode >= 300 => 'technical_issue',
            $observedNoindex => 'technical_issue',
            is_array($pageGscOpportunity) && ($pageGscOpportunity['action_state'] ?? null) === 'ready' => 'signal_drift',
            $pageMetric && $pageMetric->is_indexed === false => 'indexation_issue',
            $observed !== null || $pageMetric !== null => 'stable',
            default => 'waiting_observation',
        };

        return [
            'public_url' => $publicUrl,
            'state' => $monitoringState,
            'state_label' => match ($monitoringState) {
                'technical_issue' => 'Revue technique humaine',
                'signal_drift' => 'Réouverture possible',
                'indexation_issue' => 'Indexation à surveiller',
                'stable' => 'Surveillance active',
                'waiting_observation' => 'En attente de signaux réels',
                default => 'Avant publication live',
            },
            'detail' => match ($monitoringState) {
                'technical_issue' => 'La vraie page publique remonte un signal technique que le moteur ne doit pas prétendre corriger seul.',
                'signal_drift' => 'Google remonte une opportunité ou une dérive assez concrète pour rouvrir une amélioration ciblée.',
                'indexation_issue' => 'La page publique existe, mais Google ne la consolide pas encore correctement.',
                'stable' => 'La page publique est observée sans dérive forte immédiate. On reste en monitoring simple.',
                'waiting_observation' => 'La page est poussée, mais le crawl observed ou Google n ont pas encore assez de recul.',
                default => 'La page doit d abord sortir du moteur vers le vrai site client.',
            },
            'manual_required' => $observedTechnicalIssue,
            'reopen_actionable' => is_array($pageGscOpportunity) && ($pageGscOpportunity['action_state'] ?? null) === 'ready',
            'observed' => [
                'matched' => $observed !== null,
                'path' => $observed->path ?? null,
                'http_status' => $observed->last_status_code ?? null,
                'canonical' => $observed->canonical_url ?? null,
                'indexability_state' => $observed->indexability_state ?? null,
                'noindex' => $observedNoindex,
                'last_seen_at' => $observed->last_seen_at ?? null,
            ],
            'google' => [
                'indexed' => $pageMetric?->is_indexed,
                'metric_date' => $pageMetric?->metric_date,
                'impressions' => $pageMetric?->impressions,
                'clicks' => $pageMetric?->clicks,
                'ctr' => $pageMetric?->ctr,
                'position' => $pageMetric?->position,
                'query_metric_count' => $queryMetricCount,
            ],
            'opportunity' => $pageGscOpportunity,
        ];
    }
}
