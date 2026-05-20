<?php

declare(strict_types=1);

namespace Ofyre\SeoEngine\Examples\GenericBusinessPreset;

use Illuminate\Support\Str;
use Ofyre\SeoEngine\Contracts\ImagePromptProvider;

final class GenericBusinessImagePromptProvider implements ImagePromptProvider
{
    public function promptFor(string $keyword, ?string $cluster): string
    {
        $topic = Str::of($keyword)->lower()->replace('-', ' ')->value();

        return trim('Editorial business scene about '.$topic.'. Show a realistic team environment, practical tools, visible workflow artifacts and a calm professional tone. Cluster: '.($cluster ?: 'generic-business').'.');
    }
}
