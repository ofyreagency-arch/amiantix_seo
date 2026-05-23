<?php

declare(strict_types=1);

namespace Ofyre\SeoEngine\Services\Composition;

final class EnrichmentBudget
{
    /**
     * @param  array<string,mixed>  $blueprint
     */
    public function allowedOptionalBlocks(array $blueprint, int $wordCount, float $requiredCoverageRatio): int
    {
        $base = max(0, (int) (($blueprint['composition']['max_optional_blocks'] ?? 3)));

        if ($wordCount >= 900) {
            $base--;
        }

        if ($wordCount >= 1200) {
            $base--;
        }

        if ($requiredCoverageRatio < 0.5) {
            $base = min($base, 1);
        } elseif ($requiredCoverageRatio < 0.8) {
            $base = min($base, 2);
        }

        return max(0, $base);
    }
}
