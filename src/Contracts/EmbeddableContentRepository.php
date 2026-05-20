<?php

declare(strict_types=1);

namespace Ofyre\SeoEngine\Contracts;

interface EmbeddableContentRepository
{
    /**
     * @return iterable<int,object>
     */
    public function pagesForEmbedding(?string $slug = null, int $limit = 100): iterable;

    /**
     * @return iterable<int,object>
     */
    public function publishedPagesForSemanticLinks(?string $slug = null, int $limit = 250): iterable;

    /**
     * @return iterable<int,object>
     */
    public function queriesForMatching(?string $slug = null, int $window = 28, int $limit = 250): iterable;
}
