<?php

declare(strict_types=1);

namespace Ofyre\SeoEngine\Services\Admin;

use Ofyre\SeoEngine\Contracts\SeoCockpitRepository;
use Ofyre\SeoEngine\Services\Review\SeoPageStatusService;

class SeoCockpitService
{
    public function __construct(
        protected readonly SeoCockpitRepository $repository,
        protected readonly SeoPageStatusService $statusService,
    ) {}

    /**
     * @return array{pages:mixed,stats:array<string,int>,historicalInsights:array<string,mixed>}
     */
    public function dashboardPayload(): array
    {
        $pages = $this->repository->dashboardPages();

        if (is_object($pages) && method_exists($pages, 'getCollection') && method_exists($pages, 'setCollection')) {
            $collection = $pages->getCollection()->map(function (object $page): object {
                $page->setAttribute('status_report', $this->statusService->summarize($page));
                $page->setAttribute('preview_url', $this->repository->previewUrl($page));

                return $page;
            });

            $pages->setCollection($collection);
        }

        return [
            'pages' => $pages,
            'stats' => $this->repository->dashboardStats(),
            'historicalInsights' => $this->repository->dashboardInsights(),
        ];
    }

    /**
     * @return array{page:object,statusReport:array<string,mixed>,previewUrl:string,historicalTimeline:array<int,array<string,mixed>>,historicalInventory:mixed,semanticContext:array<string,mixed>}
     */
    public function editPayload(object $page): array
    {
        $page = $this->repository->loadEditPage($page);

        return [
            'page' => $page,
            'statusReport' => $this->statusService->summarize($page),
            'previewUrl' => $this->repository->previewUrl($page),
            'historicalTimeline' => $this->repository->timelineForPage($page),
            'historicalInventory' => $this->repository->inventoryForPage($page),
            'semanticContext' => $this->repository->semanticContextForPage($page),
        ];
    }
}
