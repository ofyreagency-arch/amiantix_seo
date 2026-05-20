<?php

declare(strict_types=1);

namespace Ofyre\SeoEngine\Services\Console;

use Ofyre\SeoEngine\Services\Embeddings\ContentEmbeddingService;

class SeoEmbedContentRunner
{
    public function __construct(
        private readonly ContentEmbeddingService $embeddings,
    ) {}

    /**
     * @return array{embedded:int,skipped:int,entities:int}
     */
    public function run(?string $slug = null, int $limit = 100, bool $force = false): array
    {
        return $this->embeddings->embedPages($slug, $limit, $force);
    }
}
