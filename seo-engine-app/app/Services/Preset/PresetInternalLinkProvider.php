<?php

declare(strict_types=1);

namespace App\Services\Preset;

use Ofyre\SeoEngine\Contracts\InternalLinkProvider;

class PresetInternalLinkProvider implements InternalLinkProvider
{
    public function __construct(
        private readonly PresetManager $presets,
    ) {}

    public function linksFor(object $page): array
    {
        return $this->presets->resolveInternalLinkProvider()->linksFor($page);
    }

    public function clusterForKeyword(string $keyword): string
    {
        return $this->presets->resolveInternalLinkProvider()->clusterForKeyword($keyword);
    }
}
