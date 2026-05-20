<?php

declare(strict_types=1);

namespace Ofyre\SeoEngine\Contracts;

interface SearchConsoleTokenProvider
{
    public function accessToken(): ?string;
}
