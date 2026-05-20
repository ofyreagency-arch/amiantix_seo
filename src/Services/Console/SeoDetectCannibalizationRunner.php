<?php

declare(strict_types=1);

namespace Ofyre\SeoEngine\Services\Console;

use Ofyre\SeoEngine\Services\Embeddings\CannibalizationDetectionService;

class SeoDetectCannibalizationRunner
{
    public function __construct(
        private readonly CannibalizationDetectionService $detection,
    ) {}

    /**
     * @return array{pages:int,risks:int}
     */
    public function run(?string $slug = null, int $limit = 100): array
    {
        return $this->detection->refresh($slug, $limit);
    }
}
