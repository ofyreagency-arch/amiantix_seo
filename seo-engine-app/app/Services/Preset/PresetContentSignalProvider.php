<?php

declare(strict_types=1);

namespace App\Services\Preset;

use Ofyre\SeoEngine\Contracts\ContentSignalProvider;

class PresetContentSignalProvider implements ContentSignalProvider
{
    public function __construct(
        private readonly PresetManager $presets,
    ) {}

    public function requiredContentMarkers(): array
    {
        return $this->presets->resolveContentSignalProvider()->requiredContentMarkers();
    }

    public function recommendationFor(string $issueKey): ?string
    {
        return $this->presets->resolveContentSignalProvider()->recommendationFor($issueKey);
    }

    public function genericPhraseWarnings(): array
    {
        return $this->presets->resolveContentSignalProvider()->genericPhraseWarnings();
    }
}
