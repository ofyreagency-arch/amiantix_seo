<?php

declare(strict_types=1);

namespace Ofyre\SeoEngine\Services\Console;

use Ofyre\SeoEngine\Services\Embeddings\QueryPageMatchingService;

class SeoMatchQueryPagesRunner
{
    public function __construct(
        private readonly QueryPageMatchingService $matching,
    ) {}

    /**
     * @return array{queries:int,opportunities:int,embedded:int,skipped:int}
     */
    public function run(?string $slug = null, int $window = 28, int $limit = 250, bool $force = false): array
    {
        return $this->matching->refresh($slug, $window, $limit, $force);
    }
}
