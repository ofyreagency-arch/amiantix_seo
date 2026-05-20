<?php

declare(strict_types=1);

namespace Ofyre\SeoEngine\Services\Console;

use Ofyre\SeoEngine\Contracts\SeoGenerationDriver;
use Ofyre\SeoEngine\Contracts\SeoPageRepository;
use Ofyre\SeoEngine\Services\Review\SeoPageStatusService;

class SeoImprovePageRunner
{
    public function __construct(
        private readonly SeoPageRepository $pages,
        private readonly SeoGenerationDriver $generation,
        private readonly SeoPageStatusService $statusService,
    ) {}

    /**
     * @return array{page:object,status:array<string,mixed>}|null
     */
    public function run(string $slug): ?array
    {
        $page = $this->pages->findBySlug($slug);

        if (! $page) {
            return null;
        }

        $page = $this->generation->improvePage($page);

        return [
            'page' => $page,
            'status' => $this->statusService->summarize($page),
        ];
    }
}
