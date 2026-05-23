<?php

declare(strict_types=1);

namespace App\Services\Preset;

final class BlockSelectionStrategy
{
    /**
     * @param  array<string,mixed>  $blueprint
     * @param  array<string,string>  $catalog
     * @return array<int,string>
     */
    public function primaryHeadings(array $blueprint, array $catalog): array
    {
        $plan = $this->compositionPlan($blueprint);

        return $this->filterKnownHeadings(array_values(array_unique(array_filter([
            $plan['opening_block'] ?? null,
            ...($plan['required_blocks'] ?? []),
        ]))), $catalog);
    }

    /**
     * @param  array<string,mixed>  $blueprint
     * @param  array<string,string>  $catalog
     * @return array<int,string>
     */
    public function requiredHeadings(array $blueprint, array $catalog): array
    {
        $plan = $this->compositionPlan($blueprint);

        return $this->filterKnownHeadings(array_values(array_unique(array_filter([
            $plan['opening_block'] ?? null,
            ...($plan['required_blocks'] ?? []),
        ]))), $catalog);
    }

    /**
     * @param  array<string,mixed>  $blueprint
     * @param  array<string,string>  $catalog
     * @return array<int,string>
     */
    public function enrichmentHeadings(array $blueprint, array $catalog, string $content = ''): array
    {
        $plan = $this->compositionPlan($blueprint);
        $optional = $this->filterKnownHeadings($this->rotateOptionals($blueprint, $plan['optional_blocks'] ?? []), $catalog);
        $limit = max(0, (int) ($plan['max_optional_blocks'] ?? count($optional)));

        if ($content !== '') {
            $optional = array_values(array_filter($optional, function (string $heading) use ($content): bool {
                return ! str_contains($content, $heading);
            }));
        }

        return array_slice($optional, 0, $limit);
    }

    /**
     * @param  array<string,mixed>  $blueprint
     * @return array<string,mixed>
     */
    private function compositionPlan(array $blueprint): array
    {
        return is_array($blueprint['composition'] ?? null) ? $blueprint['composition'] : [];
    }

    /**
     * @param  array<int,string>  $headings
     * @param  array<string,string>  $catalog
     * @return array<int,string>
     */
    private function filterKnownHeadings(array $headings, array $catalog): array
    {
        return array_values(array_filter($headings, static fn (string $heading): bool => array_key_exists($heading, $catalog)));
    }

    /**
     * @param  array<string,mixed>  $blueprint
     * @param  array<int,string>  $optional
     * @return array<int,string>
     */
    private function rotateOptionals(array $blueprint, array $optional): array
    {
        if (count($optional) <= 1) {
            return array_values($optional);
        }

        $seed = implode('|', [
            (string) ($blueprint['topic'] ?? ''),
            (string) ($blueprint['family'] ?? ''),
            (string) ($blueprint['archetype'] ?? ''),
        ]);

        $offset = abs(crc32($seed)) % count($optional);

        return array_values(array_merge(
            array_slice($optional, $offset),
            array_slice($optional, 0, $offset),
        ));
    }
}
