<?php

declare(strict_types=1);

namespace App\SeoPresets\Amiantix;

use Ofyre\SeoEngine\Contracts\NicheContentProvider;

class AmiantixContentProfile implements NicheContentProvider
{
    private \Ofyre\SeoEngine\Examples\AmiantixPreset\AmiantixContentProfile $inner;

    public function __construct()
    {
        $this->inner = new \Ofyre\SeoEngine\Examples\AmiantixPreset\AmiantixContentProfile();
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
