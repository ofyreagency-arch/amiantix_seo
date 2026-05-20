<?php

declare(strict_types=1);

namespace App\SeoPresets\Amiantix;

use Ofyre\SeoEngine\Contracts\NicheBlueprintProvider;

class AmiantixBlueprintProvider implements NicheBlueprintProvider
{
    private \Ofyre\SeoEngine\Examples\AmiantixPreset\AmiantixBlueprintProvider $inner;

    public function __construct()
    {
        $this->inner = new \Ofyre\SeoEngine\Examples\AmiantixPreset\AmiantixBlueprintProvider();
    }

    public function resolve(string $keyword, ?string $cluster = null): array
    {
        return $this->inner->resolve($keyword, $cluster);
    }

    public function expectedEditorialSections(array $profile): array
    {
        return $this->inner->expectedEditorialSections($profile);
    }

    public function expectedSignals(array $profile): array
    {
        return $this->inner->expectedSignals($profile);
    }
}
