<?php

declare(strict_types=1);

namespace Ofyre\SeoEngine\Contracts;

interface InternalLinkProvider
{
    /**
     * @return array<int, array{label:string,url:string,reason:string}>
     */
    public function linksFor(object $page): array;

    public function clusterForKeyword(string $keyword): string;
}
