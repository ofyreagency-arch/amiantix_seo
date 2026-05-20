<?php

declare(strict_types=1);

namespace App\SeoPresets\Amiantix;

use Ofyre\SeoEngine\Contracts\ImagePromptProvider;

class AmiantixImagePromptProvider implements ImagePromptProvider
{
    private \Ofyre\SeoEngine\Examples\AmiantixPreset\AmiantixImagePromptProvider $inner;

    public function __construct()
    {
        $this->inner = new \Ofyre\SeoEngine\Examples\AmiantixPreset\AmiantixImagePromptProvider();
    }

    public function promptFor(string $keyword, ?string $cluster): string
    {
        return $this->inner->promptFor($keyword, $cluster);
    }
}
