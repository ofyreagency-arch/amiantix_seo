<?php

declare(strict_types=1);

namespace Ofyre\SeoEngine\Contracts;

interface ImagePromptProvider
{
    public function promptFor(string $keyword, ?string $cluster): string;
}
