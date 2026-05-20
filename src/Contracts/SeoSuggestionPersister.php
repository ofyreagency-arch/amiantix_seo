<?php

declare(strict_types=1);

namespace Ofyre\SeoEngine\Contracts;

interface SeoSuggestionPersister
{
    /**
     * @param  array<string,mixed>  $payload
     */
    public function persist(object $page, array $payload): mixed;

    /**
     * @param  array<string,mixed>  $payload
     */
    public function replacePending(object $page, string $source, array $payload): mixed;

    public function discardPending(object $page, string $source): int;
}
