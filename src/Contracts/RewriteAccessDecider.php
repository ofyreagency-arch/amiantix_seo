<?php

declare(strict_types=1);

namespace Ofyre\SeoEngine\Contracts;

interface RewriteAccessDecider
{
    public function rewriteAllowed(object $page): bool;
}
