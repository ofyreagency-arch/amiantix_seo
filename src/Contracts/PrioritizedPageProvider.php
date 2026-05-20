<?php

declare(strict_types=1);

namespace Ofyre\SeoEngine\Contracts;

interface PrioritizedPageProvider
{
    /**
     * @return array<int,int>
     */
    public function prioritizedPageIds(): array;
}
