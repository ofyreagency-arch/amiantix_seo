<?php

declare(strict_types=1);

namespace Ofyre\SeoEngine\Services\Embeddings;

use Ofyre\SeoEngine\Contracts\CannibalizationActionDecider;

final class DefaultCannibalizationActionDecider implements CannibalizationActionDecider
{
    public function decide(
        bool $clusterMatch,
        bool $intentMatch,
        string $sourceIntent,
        string $targetIntent,
        int $sourceImpressions,
        int $targetImpressions,
        float $rawScore,
    ): string {
        if ($clusterMatch && $intentMatch) {
            $maxImpressions = max($sourceImpressions, $targetImpressions);
            $minImpressions = max(1, min($sourceImpressions, $targetImpressions));

            if ($maxImpressions >= 150 && ($maxImpressions / $minImpressions) >= 2.5 && $rawScore >= 0.97) {
                return 'consolidate_weaker_page';
            }

            return 'differentiate_angle';
        }

        if ($intentMatch) {
            return 'clarify_search_intent';
        }

        if ($clusterMatch) {
            return 'review_cluster_overlap';
        }

        return 'monitor_overlap';
    }
}
