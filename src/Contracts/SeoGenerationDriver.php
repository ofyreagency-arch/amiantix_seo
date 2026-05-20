<?php

declare(strict_types=1);

namespace Ofyre\SeoEngine\Contracts;

interface SeoGenerationDriver
{
    public function generatePage(string $keyword, string $status): object;

    /**
     * @param  array<string,mixed>  $audit
     */
    public function improvePage(object $page, array $audit = []): object;
}
