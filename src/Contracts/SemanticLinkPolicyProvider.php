<?php

declare(strict_types=1);

namespace Ofyre\SeoEngine\Contracts;

interface SemanticLinkPolicyProvider
{
    /**
     * @return array{
     *     accepted:bool,
     *     score:float,
     *     reasons:array<int,string>,
     *     meta:array<string,mixed>
     * }
     */
    public function evaluate(object $sourcePage, object $targetPage, float $similarityScore): array;
}
