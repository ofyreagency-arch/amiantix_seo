<?php

declare(strict_types=1);

namespace Ofyre\SeoEngine\Contracts;

interface SeoAuditPersister
{
    /**
     * @param  array{score:int,issues:array<int,string>,recommendations:array<int,string>}  $audit
     * @param  array<string,mixed>  $searchConsoleData
     */
    public function persist(object $page, array $audit, array $searchConsoleData = []): void;
}
