<?php

declare(strict_types=1);

namespace Ofyre\SeoEngine\Contracts;

interface CannibalizationActionDecider
{
    /**
     * Return the recommended action key for a detected cannibalization pair.
     *
     * Typical return values: 'clarify_search_intent', 'differentiate_angle',
     * 'consolidate_weaker_page', 'review_cluster_overlap', 'monitor_overlap'.
     * Projects may define additional domain-specific action keys.
     */
    public function decide(
        bool $clusterMatch,
        bool $intentMatch,
        string $sourceIntent,
        string $targetIntent,
        int $sourceImpressions,
        int $targetImpressions,
        float $rawScore,
    ): string;
}
