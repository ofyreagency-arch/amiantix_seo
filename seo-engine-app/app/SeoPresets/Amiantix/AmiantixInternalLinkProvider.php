<?php

declare(strict_types=1);

namespace App\SeoPresets\Amiantix;

use Ofyre\SeoEngine\Contracts\InternalLinkProvider;
use Ofyre\SeoEngine\Examples\AmiantixPreset\AmiantixInternalLinkProvider as InnerProvider;

class AmiantixInternalLinkProvider implements InternalLinkProvider
{
    private InnerProvider $inner;

    public function __construct()
    {
        $this->inner = new InnerProvider();
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
