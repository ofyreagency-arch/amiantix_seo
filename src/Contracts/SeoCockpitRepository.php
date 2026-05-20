<?php

declare(strict_types=1);

namespace Ofyre\SeoEngine\Contracts;

interface SeoCockpitRepository
{
    public function dashboardPages(): mixed;

    /**
     * @return array{pending:int,published:int,rejected:int,suggestions:int}
     */
    public function dashboardStats(): array;

    /**
     * @return array<string,mixed>
     */
    public function dashboardInsights(): array;

    public function previewUrl(object $page): string;

    public function loadEditPage(object $page): object;

    /**
     * @return array<int,array<string,mixed>>
     */
    public function timelineForPage(object $page): array;

    public function inventoryForPage(object $page): mixed;

    /**
     * @return array<string,mixed>
     */
    public function semanticContextForPage(object $page): array;
}
