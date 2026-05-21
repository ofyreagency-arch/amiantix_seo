<?php

declare(strict_types=1);

namespace Ofyre\SeoEngine\Services\Monitoring;

use Ofyre\SeoEngine\Contracts\PrioritizedPageProvider;
use Ofyre\SeoEngine\Contracts\SeoFeedbackLoopDriver;
use Ofyre\SeoEngine\Services\Scoring\SeoScoreRefreshService;
use Ofyre\SeoEngine\Services\Scoring\SeoScoringService;
use Ofyre\SeoEngine\Services\SearchConsole\SearchConsoleService;

abstract class SeoMonitoringService
{
    public function __construct(
        private readonly SearchConsoleService $searchConsole,
        private readonly SeoScoringService $scoring,
        private readonly SeoFeedbackLoopDriver $feedbackLoop,
        private readonly PrioritizedPageProvider $prioritizedPages,
        private readonly SeoScoreRefreshService $scoreRefresh,
    ) {}

    /**
     * @return array{audited:int,improved:int}
     */
    public function monitor(bool $autoImprove = true): array
    {
        $audited = 0;
        $improved = 0;
        $prioritizedIds = $this->prioritizedPages->prioritizedPageIds();

        foreach ($this->candidatePages($prioritizedIds) as $page) {
            $pageWasImproved = $this->monitorPage($page, $autoImprove);
            $audited++;

            if ($pageWasImproved) {
                $improved++;
            }
        }

        return ['audited' => $audited, 'improved' => $improved];
    }

    public function monitorPage(object $page, bool $autoImprove = true): bool
    {
        $metrics = $this->searchConsole->pageMetrics($page);

        if ($metrics['indexed'] !== null) {
            $this->markIndexed($page, (bool) $metrics['indexed']);
        }

        $page = $this->scoreRefresh->refresh($page, $metrics, createAudit: true);
        $audit = $this->scoring->audit($page, $metrics);

        $this->persistSearchConsoleHistory($page, $metrics, $audit);
        $this->persistMonitoringState($page, $metrics, $audit);

        if ($autoImprove) {
            $this->feedbackLoop->proposeForPage($page, $metrics, $audit);

            return $audit['score'] < (int) config('seo-engine.monitoring.auto_improve_threshold', 85);
        }

        return false;
    }

    /**
     * Whether a published page's quality has degraded enough to warrant re-review.
     * Generic rule — threshold is configurable per project via SEO_PUBLISHED_DEGRADATION_THRESHOLD.
     *
     * @param  array{score:int,issues:array<int,string>,recommendations:array<int,string>}  $audit
     */
    protected function qualityHasDegraded(array $audit): bool
    {
        $threshold = (int) config('seo-engine.monitoring.published_degradation_threshold', 60);

        return $audit['score'] < $threshold;
    }

    /**
     * @return array{refreshed:int}
     */
    public function refreshAgedContent(int $days = 45): array
    {
        $refreshed = 0;

        foreach ($this->agedPublishedPages($days) as $page) {
            $metrics = $this->searchConsole->pageMetrics($page);
            $audit = $this->scoring->audit($page, $metrics);
            $this->feedbackLoop->proposeForPage($page, $metrics, $audit);
            $refreshed++;
        }

        return ['refreshed' => $refreshed];
    }

    /**
     * @param  array<int,int>  $prioritizedIds
     * @return iterable<int,object>
     */
    abstract protected function candidatePages(array $prioritizedIds): iterable;

    /**
     * @return iterable<int,object>
     */
    abstract protected function agedPublishedPages(int $days): iterable;

    abstract protected function markIndexed(object $page, bool $indexed): void;

    /**
     * @param  array<string,mixed>  $metrics
     * @param  array{score:int,issues:array<int,string>,recommendations:array<int,string>}  $audit
     */
    abstract protected function persistSearchConsoleHistory(object $page, array $metrics, array $audit): void;

    /**
     * @param  array<string,mixed>  $metrics
     * @param  array{score:int,issues:array<int,string>,recommendations:array<int,string>}  $audit
     */
    abstract protected function persistMonitoringState(object $page, array $metrics, array $audit): void;
}
