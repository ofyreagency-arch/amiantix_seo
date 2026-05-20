<?php

declare(strict_types=1);

namespace Ofyre\SeoEngine\Services\Console;

use Ofyre\SeoEngine\Contracts\SeoPageRepository;
use Ofyre\SeoEngine\Services\Scoring\SeoScoreRefreshService;

class SeoRecalculateScoresRunner
{
    public function __construct(
        private readonly SeoPageRepository $pages,
        private readonly SeoScoreRefreshService $scoreRefresh,
    ) {}

    /**
     * @param  callable(object):void|null  $progress
     */
    public function run(?string $slug, bool $createAudit, ?callable $progress = null): int
    {
        $count = 0;

        foreach ($this->pages->pagesForScoreRefresh($slug) as $page) {
            $this->scoreRefresh->refresh($page, createAudit: $createAudit);
            $count++;

            if ($progress) {
                $progress($page);
            }
        }

        return $count;
    }
}
