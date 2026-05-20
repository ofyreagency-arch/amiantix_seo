<?php

declare(strict_types=1);

namespace App\SeoPresets\Generic;

use Ofyre\SeoEngine\Contracts\ImagePromptProvider;
use Ofyre\SeoEngine\Examples\GenericBusinessPreset\GenericBusinessImagePromptProvider;

class GenericImagePromptProvider implements ImagePromptProvider
{
    private GenericBusinessImagePromptProvider $inner;

    public function __construct()
    {
        $this->inner = new GenericBusinessImagePromptProvider();
    }

    public function promptFor(string $keyword, ?string $cluster): string
    {
        return $this->inner->promptFor($keyword, $cluster);
    }
}
