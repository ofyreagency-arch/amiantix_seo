<?php

declare(strict_types=1);

namespace Ofyre\SeoEngine\Contracts;

interface HistoricalSeoImporter
{
    /**
     * @param  array<int,int>  $windows
     * @return array{windows:int,pages:int,queries:int}
     */
    public function import(array $windows = [7, 28, 90, 180, 365], int $limit = 250): array;
}
