<?php

declare(strict_types=1);

namespace App\Runtime;

use Ofyre\SeoEngine\Contracts\PageStatusLabeler;

class RuntimePageStatusLabeler implements PageStatusLabeler
{
    public function editorialLabel(string $editorialStatus): string
    {
        return match ($editorialStatus) {
            'published' => 'Published',
            'review' => 'In review',
            default => 'Draft',
        };
    }

    public function qualityLabel(string $qualityState): string
    {
        return match ($qualityState) {
            'healthy' => 'Healthy',
            'warning' => 'Warning',
            default => 'Needs improvement',
        };
    }

    public function liveMessage(string $editorialStatus, string $qualityState): string
    {
        return $this->editorialLabel($editorialStatus).' / '.$this->qualityLabel($qualityState);
    }

    public function blockingReasonWrongStatus(string $currentStatus): ?string { return 'Editorial status blocks publication: '.$currentStatus; }
    public function blockingReasonForcedNoindex(): ?string { return 'Human override has forced noindex.'; }
    public function blockingReasonLowSeoScore(int $threshold): ?string { return 'SEO score is below '.$threshold.'.'; }
    public function blockingReasonLowIndexability(int $threshold): ?string { return 'Indexability score is below '.$threshold.'.'; }
    public function blockingReasonHighDuplicateRisk(): ?string { return 'Duplicate risk is too high.'; }
    public function blockingReasonImageNotApproved(string $imageStatus, ?string $reviewState): ?string { return 'Image is not approved yet ('.$imageStatus.($reviewState ? ', '.$reviewState : '').').'; }
    public function blockingReasonLowFaqCount(int $minimum): ?string { return 'FAQ count is below '.$minimum.'.'; }
    public function blockingReasonHighSpamRisk(): ?string { return 'Spam risk is high.'; }
    public function warningAlreadyPublished(): ?string { return 'Page is already published.'; }
    public function warningMediumDuplicateRisk(): ?string { return 'Duplicate risk is elevated.'; }
    public function recommendationLowSeoScore(): ?string { return 'Improve topical coverage and page depth.'; }
    public function recommendationLowIndexability(): ?string { return 'Improve crawlability, media approval and internal linking.'; }
    public function recommendationHighDuplicateRisk(): ?string { return 'Differentiate the page angle or consolidate overlap.'; }
    public function recommendationImageNotApproved(): ?string { return 'Approve or replace the main image before publication.'; }
}
