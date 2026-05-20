<?php

declare(strict_types=1);

namespace Ofyre\SeoEngine\Contracts;

interface SeoPageRepository
{
    public function findBySlug(string $slug): ?object;

    /**
     * @return iterable<int,object>
     */
    public function publishedPages(): iterable;

    /**
     * @return iterable<int,object>
     */
    public function pagesForScoreRefresh(?string $slug = null): iterable;
}
