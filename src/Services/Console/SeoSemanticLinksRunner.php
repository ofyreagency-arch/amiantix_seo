<?php

declare(strict_types=1);

namespace Ofyre\SeoEngine\Services\Console;

use Ofyre\SeoEngine\Services\Embeddings\InternalLinkSuggestionService;

class SeoSemanticLinksRunner
{
    public function __construct(
        private readonly InternalLinkSuggestionService $links,
    ) {}

    /**
     * @return array{pages:int,suggestions:int}
     */
    public function run(?string $slug = null, int $limit = 100): array
    {
        return $this->links->refresh($slug, $limit);
    }
}
