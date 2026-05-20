<?php

declare(strict_types=1);

namespace Ofyre\SeoEngine\Contracts;

interface PageStatusLabeler
{
    // --- UI labels ---

    /** Human-readable label for the editorial status (published / draft / review). */
    public function editorialLabel(string $editorialStatus): string;

    /** Human-readable label for the quality state (healthy / warning / needs_improvement). */
    public function qualityLabel(string $qualityState): string;

    /** Live status message combining editorial status and quality state. */
    public function liveMessage(string $editorialStatus, string $qualityState): string;

    // --- Blocking reasons (return null to omit) ---

    public function blockingReasonWrongStatus(string $currentStatus): ?string;

    public function blockingReasonForcedNoindex(): ?string;

    public function blockingReasonLowSeoScore(int $threshold): ?string;

    public function blockingReasonLowIndexability(int $threshold): ?string;

    public function blockingReasonHighDuplicateRisk(): ?string;

    /** @param string|null $reviewState Underlying review state when image is rejected. */
    public function blockingReasonImageNotApproved(string $imageStatus, ?string $reviewState): ?string;

    public function blockingReasonLowFaqCount(int $minimum): ?string;

    public function blockingReasonHighSpamRisk(): ?string;

    // --- Warnings (return null to omit) ---

    public function warningAlreadyPublished(): ?string;

    public function warningMediumDuplicateRisk(): ?string;

    // --- Recommendations (return null to omit) ---

    public function recommendationLowSeoScore(): ?string;

    public function recommendationLowIndexability(): ?string;

    public function recommendationHighDuplicateRisk(): ?string;

    public function recommendationImageNotApproved(): ?string;
}
