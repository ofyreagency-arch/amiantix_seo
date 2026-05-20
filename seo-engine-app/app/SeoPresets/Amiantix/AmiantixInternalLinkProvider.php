<?php

declare(strict_types=1);

namespace App\SeoPresets\Amiantix;

use Ofyre\SeoEngine\Contracts\InternalLinkProvider;

class AmiantixInternalLinkProvider implements InternalLinkProvider
{
    private \Ofyre\SeoEngine\Examples\AmiantixPreset\AmiantixInternalLinkProvider $inner;

    public function __construct()
    {
        $this->inner = new \Ofyre\SeoEngine\Examples\AmiantixPreset\AmiantixInternalLinkProvider();
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
