<?php

declare(strict_types=1);

namespace Ofyre\SeoEngine\Services\Console;

use Ofyre\SeoEngine\Contracts\HistoricalSeoImporter;

class SeoImportHistoryRunner
{
    public function __construct(
        private readonly HistoricalSeoImporter $history,
    ) {}

    /**
     * @return array{windows:array<int,int>,summary:array{windows:int,pages:int,queries:int}}
     */
    public function run(string $windowsOption, int $limit): array
    {
        $windows = collect(explode(',', $windowsOption))
            ->map(fn (string $window): int => max(7, (int) trim($window)))
            ->filter()
            ->unique()
            ->values()
            ->all();

        return [
            'windows' => $windows,
            'summary' => $this->history->import($windows, $limit),
        ];
    }
}
