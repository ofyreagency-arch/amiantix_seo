<?php

declare(strict_types=1);

namespace Ofyre\SeoEngine\Services\Console;

use Ofyre\SeoEngine\Contracts\SeoGenerationDriver;

class SeoGeneratePageRunner
{
    public function __construct(
        private readonly SeoGenerationDriver $generation,
    ) {}

    /**
     * @return array{page:object,warning:?string}
     */
    public function run(string $keyword, string $requestedStatus, bool $publishRequested): array
    {
        $page = $this->generation->generatePage($keyword, $requestedStatus);

        return [
            'page' => $page,
            'warning' => $publishRequested && ($page->status ?? null) !== 'published'
                ? '--publish no longer publishes automatically. Page is '.$page->status.' until manual validation.'
                : null,
        ];
    }
}
