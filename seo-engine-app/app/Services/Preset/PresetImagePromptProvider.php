<?php

declare(strict_types=1);

namespace App\Services\Preset;

use Ofyre\SeoEngine\Contracts\ImagePromptProvider;

class PresetImagePromptProvider implements ImagePromptProvider
{
    public function __construct(
        private readonly PresetManager $presets,
    ) {}

    public function promptFor(string $keyword, ?string $cluster): string
    {
        return $this->presets->resolveImagePromptProvider()->promptFor($keyword, $cluster);
    }
}
