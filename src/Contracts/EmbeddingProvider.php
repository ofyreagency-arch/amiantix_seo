<?php

declare(strict_types=1);

namespace Ofyre\SeoEngine\Contracts;

interface EmbeddingProvider
{
    /**
     * @return array<int,float>
     */
    public function embed(string $text): array;

    public function model(): string;

    public function dimensions(): int;
}
