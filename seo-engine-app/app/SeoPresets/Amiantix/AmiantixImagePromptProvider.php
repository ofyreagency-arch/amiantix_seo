<?php

declare(strict_types=1);

namespace App\SeoPresets\Amiantix;

use Ofyre\SeoEngine\Contracts\ImagePromptProvider;
use Ofyre\SeoEngine\Examples\AmiantixPreset\AmiantixImagePromptProvider as InnerProvider;

class AmiantixImagePromptProvider implements ImagePromptProvider
{
    private InnerProvider $inner;

    public function __construct()
    {
        $this->inner = new InnerProvider();
    }

    public function promptFor(string $keyword, ?string $cluster): string
    {
        return $this->inner->promptFor($keyword, $cluster);
    }
}
