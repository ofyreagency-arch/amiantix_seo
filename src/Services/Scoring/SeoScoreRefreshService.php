<?php

declare(strict_types=1);

namespace Ofyre\SeoEngine\Services\Scoring;

use Ofyre\SeoEngine\Contracts\SeoAuditPersister;
use Ofyre\SeoEngine\Services\Quality\SeoQualityGateService;

class SeoScoreRefreshService
{
    public function __construct(
        private readonly SeoScoringService $scoring,
        private readonly SeoIndexabilityScoringService $indexability,
        private readonly SeoQualityGateService $qualityGate,
        private readonly SeoAuditPersister $auditPersister,
    ) {}

    /**
     * @param  array<string,mixed>  $searchConsoleData
     */
    public function refresh(object $page, array $searchConsoleData = [], bool $createAudit = false): object
    {
        $review = $this->qualityGate->reviewPage($page);

        if (method_exists($page, 'forceFill')) {
            $page->forceFill([
                'topical_score' => $review['topical_score'],
                'quality_score' => $review['quality_score'],
                'spam_risk' => $review['spam_risk'],
                'review_issues_json' => $review['issues'],
            ]);
        } else {
            $page->topical_score = $review['topical_score'];
            $page->quality_score = $review['quality_score'];
            $page->spam_risk = $review['spam_risk'];
            $page->review_issues_json = $review['issues'];
        }

        $audit = $this->scoring->audit($page, $searchConsoleData);
        $payload = [
            'seo_score' => $audit['score'],
            'indexability_score' => $this->indexability->score($page),
            'image_quality_score' => $this->indexability->imageQualityScore($page),
            'last_audit_at' => now(),
        ];

        if (method_exists($page, 'forceFill')) {
            $page->forceFill($payload)->save();
        } else {
            foreach ($payload as $key => $value) {
                $page->{$key} = $value;
            }
        }

        if ($createAudit) {
            $this->auditPersister->persist($page, $audit, $searchConsoleData);
        }

        return method_exists($page, 'refresh') ? $page->refresh() : $page;
    }
}
