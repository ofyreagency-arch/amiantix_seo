<?php

declare(strict_types=1);

namespace App\SeoPresets\Amiantix;

use Ofyre\SeoEngine\Contracts\NicheContentProvider;
use Ofyre\SeoEngine\Examples\AmiantixPreset\AmiantixContentProfile as InnerProvider;

class AmiantixContentProfile implements NicheContentProvider
{
    private InnerProvider $inner;

    public function __construct()
    {
        $this->inner = new InnerProvider();
    }

    public function fallbackPayload(string $keyword, string $cluster, array $blueprint, array $context = []): array
    {
        return $this->inner->fallbackPayload($keyword, $cluster, $blueprint, $context);
    }

    public function extraSection(string $keyword, array $blueprint, array $context = []): string
    {
        return $this->inner->extraSection($keyword, $blueprint, $context);
    }

    public function ensureContentDepth(string $content, array $blueprint, array $context = []): string
    {
        return $this->inner->ensureContentDepth($content, $blueprint, $context);
    }
}
