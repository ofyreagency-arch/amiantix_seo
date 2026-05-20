<?php

declare(strict_types=1);

namespace Ofyre\SeoEngine\Services\Review;

use Ofyre\SeoEngine\Contracts\PageStatusLabeler;
use Ofyre\SeoEngine\Services\Quality\SeoQualityGateService;

class SeoPageStatusService
{
    public function __construct(
        protected readonly SeoQualityGateService $qualityGate,
        protected readonly ?PageStatusLabeler $labeler = null,
    ) {}

    /**
     * @return array{
     *     slug:string,
     *     status:string,
     *     editorial_status:string,
     *     editorial_label:string,
     *     quality_state:string,
     *     quality_label:string,
     *     live_message:string,
     *     scores:array<string,int|string|bool|null>,
     *     thresholds:array<string,int|string>,
     *     failed_rules:array<int,string>,
     *     blocking_reasons:array<int,string>,
     *     warnings:array<int,string>,
     *     recommendations:array<int,string>,
     *     image:array<string,mixed>
     * }
     */
    public function summarize(object $page, bool $forPublication = false): array
    {
        $review = $this->qualityGate->reviewPage($page);
        $blockingReasons = [];
        $warnings = $review['warnings'];
        $recommendations = $review['recommendations'];
        $failedRules = $review['failed_rules'];
        $faqCount = count($page->faq_json ?? []);

        $seoScoreThreshold = 70;
        $indexabilityThreshold = 65;
        $faqMinimum = 5;

        if ($forPublication && ! in_array($page->status, ['pending_review', 'published'], true)) {
            $blockingReasons[] = $this->labeler?->blockingReasonWrongStatus((string) ($page->status ?? '')) ?? 'status_not_pending_review';
            $failedRules[] = 'status_not_pending_review';
        }

        if (($page->status ?? null) === 'published') {
            $warning = $this->labeler?->warningAlreadyPublished();
            if ($warning !== null) {
                $warnings[] = $warning;
            }
        }

        if ((bool) ($page->forced_noindex ?? false)) {
            $blockingReasons[] = $this->labeler?->blockingReasonForcedNoindex() ?? 'forced_noindex';
            $failedRules[] = 'forced_noindex';
        }

        if (($page->seo_score ?? 0) < $seoScoreThreshold) {
            $blockingReasons[] = $this->labeler?->blockingReasonLowSeoScore($seoScoreThreshold) ?? 'seo_score_below_threshold';
            $failedRules[] = 'seo_score_below_threshold';
            $rec = $this->labeler?->recommendationLowSeoScore();
            if ($rec !== null) {
                $recommendations[] = $rec;
            }
        }

        if (($page->indexability_score ?? 0) < $indexabilityThreshold) {
            $blockingReasons[] = $this->labeler?->blockingReasonLowIndexability($indexabilityThreshold) ?? 'indexability_below_threshold';
            $failedRules[] = 'indexability_below_threshold';
            $rec = $this->labeler?->recommendationLowIndexability();
            if ($rec !== null) {
                $recommendations[] = $rec;
            }
        }

        if (($page->duplicate_risk_score ?? 0) >= 70) {
            $blockingReasons[] = $this->labeler?->blockingReasonHighDuplicateRisk() ?? 'duplicate_risk_high';
            $failedRules[] = 'duplicate_risk_high';
            $rec = $this->labeler?->recommendationHighDuplicateRisk();
            if ($rec !== null) {
                $recommendations[] = $rec;
            }
        } elseif (($page->duplicate_risk_score ?? 0) >= 45) {
            $warning = $this->labeler?->warningMediumDuplicateRisk();
            if ($warning !== null) {
                $warnings[] = $warning;
            }
        }

        if (($page->image_status ?? 'missing') !== 'approved') {
            $imageReviewState = $page->image_quality_json['review_state'] ?? null;
            $blockingReasons[] = $this->labeler?->blockingReasonImageNotApproved(
                (string) ($page->image_status ?? 'missing'),
                is_string($imageReviewState) ? $imageReviewState : null,
            ) ?? 'image_not_approved';
            $failedRules[] = 'image_not_approved';
            $rec = $this->labeler?->recommendationImageNotApproved();
            if ($rec !== null) {
                $recommendations[] = $rec;
            }
        }

        if ($faqCount < $faqMinimum) {
            $blockingReasons[] = $this->labeler?->blockingReasonLowFaqCount($faqMinimum) ?? 'faq_count_below_minimum';
            $failedRules[] = 'faq_count_below_minimum';
        }

        if (($page->spam_risk ?? 'low') === 'high') {
            $blockingReasons[] = $this->labeler?->blockingReasonHighSpamRisk() ?? 'spam_risk_high';
            $failedRules[] = 'spam_risk_high';
        }

        foreach ($review['issues'] as $issue) {
            $blockingReasons[] = $issue;
        }

        $editorialStatus = match ((string) ($page->status ?? '')) {
            'published' => 'published',
            'draft' => 'draft',
            default => 'review',
        };

        $qualityState = ($page->seo_score ?? 0) >= $seoScoreThreshold
            && ($page->indexability_score ?? 0) >= $indexabilityThreshold
            && ($page->spam_risk ?? 'low') !== 'high'
            && ($page->image_status ?? null) === 'approved'
            ? 'healthy'
            : ((($page->seo_score ?? 0) >= 60 && ($page->indexability_score ?? 0) >= 50 && ($page->spam_risk ?? 'low') !== 'high')
                ? 'warning'
                : 'needs_improvement');

        $editorialLabel = $this->labeler?->editorialLabel($editorialStatus) ?? $editorialStatus;
        $qualityLabel = $this->labeler?->qualityLabel($qualityState) ?? $qualityState;
        $liveMessage = $this->labeler?->liveMessage($editorialStatus, $qualityState) ?? '';

        return [
            'slug' => (string) ($page->slug ?? ''),
            'status' => (string) ($page->status ?? ''),
            'editorial_status' => $editorialStatus,
            'editorial_label' => $editorialLabel,
            'quality_state' => $qualityState,
            'quality_label' => $qualityLabel,
            'live_message' => $liveMessage,
            'scores' => [
                'seo_score' => $page->seo_score ?? null,
                'indexability_score' => $page->indexability_score ?? null,
                'topical_score' => $page->topical_score ?? null,
                'quality_score' => $page->quality_score ?? null,
                'connectivity_score' => $page->connectivity_score ?? null,
                'cluster_authority_score' => $page->cluster_authority_score ?? null,
                'duplicate_risk_score' => $page->duplicate_risk_score ?? null,
                'spam_risk' => $page->spam_risk ?? null,
                'faq_count' => $faqCount,
                'image_approved' => ($page->image_status ?? null) === 'approved',
                'image_quality_score' => $page->image_quality_score ?? null,
                'internal_inbound_count' => $page->internal_inbound_count ?? null,
                'cluster_links_count' => $page->cluster_links_count ?? null,
                'indexed' => $page->indexed ?? null,
            ],
            'thresholds' => array_merge($review['thresholds'], [
                'min_seo_score' => $seoScoreThreshold,
                'min_indexability_score' => $indexabilityThreshold,
                'max_duplicate_risk_score' => 69,
                'required_image_status' => 'approved',
            ]),
            'failed_rules' => array_values(array_unique($failedRules)),
            'blocking_reasons' => array_values(array_unique($blockingReasons)),
            'warnings' => array_values(array_unique($warnings)),
            'recommendations' => array_values(array_unique($recommendations)),
            'image' => [
                'status' => $page->image_status ?? null,
                'alt' => $page->image_alt ?? null,
                'title' => $page->image_title ?? null,
                'path' => $page->image_path ?? null,
                'quality_score' => $page->image_quality_score ?? null,
                'generation' => $page->image_quality_json['generation'] ?? null,
            ],
        ];
    }

    public function canPublish(object $page): bool
    {
        return $this->summarize($page, true)['blocking_reasons'] === [];
    }
}
