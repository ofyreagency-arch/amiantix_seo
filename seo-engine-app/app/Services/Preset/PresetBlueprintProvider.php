<?php

declare(strict_types=1);

namespace App\Services\Preset;

use Ofyre\SeoEngine\Contracts\NicheBlueprintProvider;

class PresetBlueprintProvider implements NicheBlueprintProvider
{
    public function __construct(
        private readonly PresetManager $presets,
    ) {}

    public function resolve(string $keyword, ?string $cluster = null): array
    {
        return $this->presets->resolveBlueprintProvider()->resolve($keyword, $cluster);
    }

    public function expectedEditorialSections(array $profile): array
    {
        return $this->presets->resolveBlueprintProvider()->expectedEditorialSections($profile);
    }

    public function expectedSignals(array $profile): array
    {
        return $this->presets->resolveBlueprintProvider()->expectedSignals($profile);
    }
}
