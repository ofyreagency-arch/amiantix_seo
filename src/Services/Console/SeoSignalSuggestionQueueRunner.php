<?php

declare(strict_types=1);

namespace Ofyre\SeoEngine\Services\Console;

use Ofyre\SeoEngine\Services\Suggestions\SignalSuggestionQueueService;

final class SeoSignalSuggestionQueueRunner
{
    public function __construct(
        private readonly SignalSuggestionQueueService $queue,
    ) {}

    /**
     * @return array{pages:int,queued:int,cleared:int}
     */
    public function run(?string $slug = null, int $limit = 100): array
    {
        return $this->queue->queue($slug, $limit);
    }
}
