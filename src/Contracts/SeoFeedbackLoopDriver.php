<?php

declare(strict_types=1);

namespace Ofyre\SeoEngine\Contracts;

interface SeoFeedbackLoopDriver
{
    /**
     * @param  array<string,mixed>  $metrics
     * @param  array{score:int,issues:array<int,string>,recommendations:array<int,string>}  $audit
     */
    public function proposeForPage(object $page, array $metrics, array $audit): mixed;
}
