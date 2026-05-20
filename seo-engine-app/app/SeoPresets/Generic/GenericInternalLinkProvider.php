<?php

declare(strict_types=1);

namespace App\SeoPresets\Generic;

use Ofyre\SeoEngine\Contracts\InternalLinkProvider;
use Ofyre\SeoEngine\Examples\GenericBusinessPreset\GenericBusinessInternalLinkProvider;

class GenericInternalLinkProvider implements InternalLinkProvider
{
    private GenericBusinessInternalLinkProvider $inner;

    public function __construct()
    {
        $this->inner = new GenericBusinessInternalLinkProvider();
    }

    public function linksFor(object $page): array
    {
        return $this->inner->linksFor($page);
    }

    public function clusterForKeyword(string $keyword): string
    {
        return $this->inner->clusterForKeyword($keyword);
    }
}
