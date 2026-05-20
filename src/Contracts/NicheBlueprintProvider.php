<?php

declare(strict_types=1);

namespace Ofyre\SeoEngine\Contracts;

interface NicheBlueprintProvider
{
    /**
     * @return array<string,mixed>
     */
    public function resolve(string $keyword, ?string $cluster = null): array;

    /**
     * @param  array<string,mixed>  $profile
     * @return array<int,string>
     */
    public function expectedEditorialSections(array $profile): array;

    /**
     * @param  array<string,mixed>  $profile
     * @return array<int,string>
     */
    public function expectedSignals(array $profile): array;
}
