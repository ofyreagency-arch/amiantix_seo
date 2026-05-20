<?php

declare(strict_types=1);

namespace App\SeoPresets\Generic;

use Ofyre\SeoEngine\Contracts\NicheBlueprintProvider;
use Ofyre\SeoEngine\Examples\GenericBusinessPreset\GenericBusinessBlueprintProvider;

class GenericBlueprintProvider implements NicheBlueprintProvider
{
    private GenericBusinessBlueprintProvider $inner;

    public function __construct()
    {
        $this->inner = new GenericBusinessBlueprintProvider();
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
