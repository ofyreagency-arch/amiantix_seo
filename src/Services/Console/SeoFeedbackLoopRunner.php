<?php

declare(strict_types=1);

namespace Ofyre\SeoEngine\Services\Console;

use Ofyre\SeoEngine\Contracts\SeoFeedbackLoopDriver;
use Ofyre\SeoEngine\Contracts\SeoPageRepository;
use Ofyre\SeoEngine\Services\Scoring\SeoScoringService;
use Ofyre\SeoEngine\Services\SearchConsole\SearchConsoleService;

class SeoFeedbackLoopRunner
{
    public function __construct(
        private readonly SeoPageRepository $pages,
        private readonly SearchConsoleService $searchConsole,
        private readonly SeoScoringService $scoring,
        private readonly SeoFeedbackLoopDriver $feedback,
    ) {}

    public function run(): int
    {
        $created = 0;

        foreach ($this->pages->publishedPages() as $page) {
            $metrics = $this->searchConsole->pageMetrics($page);
            $audit = $this->scoring->audit($page, $metrics);

            if ($this->feedback->proposeForPage($page, $metrics, $audit)) {
                $created++;
            }
        }

        return $created;
    }
}
