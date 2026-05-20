<?php

declare(strict_types=1);

namespace Ofyre\SeoEngine\Services\Embeddings;

class SemanticSimilarityService
{
    /**
     * @param  array<int,float>  $left
     * @param  array<int,float>  $right
     */
    public function cosine(array $left, array $right): float
    {
        $size = min(count($left), count($right));

        if ($size === 0) {
            return 0.0;
        }

        $dot = 0.0;
        $leftNorm = 0.0;
        $rightNorm = 0.0;

        for ($i = 0; $i < $size; $i++) {
            $dot += $left[$i] * $right[$i];
            $leftNorm += $left[$i] ** 2;
            $rightNorm += $right[$i] ** 2;
        }

        if ($leftNorm <= 0.0 || $rightNorm <= 0.0) {
            return 0.0;
        }

        return $dot / (sqrt($leftNorm) * sqrt($rightNorm));
    }
}
