<?php

declare(strict_types=1);

namespace App\Services\Preset;

use Ofyre\SeoEngine\Contracts\NicheContentProvider;

class PresetContentProfile implements NicheContentProvider
{
    public function __construct(
        private readonly PresetManager $presets,
    ) {}

    public function fallbackPayload(string $keyword, string $cluster, array $blueprint, array $context = []): array
    {
        return $this->presets->resolveContentProfile()->fallbackPayload($keyword, $cluster, $blueprint, $context);
    }

    public function extraSection(string $keyword, array $blueprint, array $context = []): string
    {
        return $this->presets->resolveContentProfile()->extraSection($keyword, $blueprint, $context);
    }

    public function ensureContentDepth(string $content, array $blueprint, array $context = []): string
    {
        return $this->presets->resolveContentProfile()->ensureContentDepth($content, $blueprint, $context);
    }
}
