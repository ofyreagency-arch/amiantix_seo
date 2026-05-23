<?php

declare(strict_types=1);

namespace Ofyre\SeoEngine\Services\Composition;

use Illuminate\Support\Str;

final class CoverageInspector
{
    /**
     * @param  array<int,string>  $headings
     * @return array<string,bool>
     */
    public function headingCoverageMap(string $content, array $headings): array
    {
        $normalizedContent = $this->normalize($content);
        $coverage = [];

        foreach ($headings as $heading) {
            $coverage[$heading] = $this->containsNormalized($normalizedContent, $heading);
        }

        return $coverage;
    }

    /**
     * @param  array<int,string>  $headings
     */
    public function headingCoverageRatio(string $content, array $headings): float
    {
        if ($headings === []) {
            return 1.0;
        }

        $coverage = $this->headingCoverageMap($content, $headings);
        $covered = count(array_filter($coverage, static fn (bool $state): bool => $state));

        return $covered / max(1, count($headings));
    }

    public function coversHeading(string $content, string $heading): bool
    {
        return $this->containsNormalized($this->normalize($content), $heading);
    }

    /**
     * @return array<int,string>
     */
    public function headingTokens(string $heading): array
    {
        return $this->tokens($heading);
    }

    /**
     * @param  array<string,mixed>  $blueprint
     * @return array<int,string>
     */
    public function blueprintContextTokens(array $blueprint): array
    {
        $fragments = [
            (string) ($blueprint['topic'] ?? ''),
            (string) ($blueprint['cluster'] ?? ''),
            (string) ($blueprint['family'] ?? ''),
            (string) ($blueprint['archetype'] ?? ''),
            (string) ($blueprint['hero_angle'] ?? ''),
        ];

        foreach (['risk_terms', 'editorial_sections', 'support_sections', 'daily_constraints', 'work_units'] as $key) {
            foreach (($blueprint[$key] ?? []) as $value) {
                if (is_string($value)) {
                    $fragments[] = $value;
                }
            }
        }

        foreach (array_slice($blueprint['cases'] ?? [], 0, 3) as $value) {
            if (is_string($value)) {
                $fragments[] = $value;
            }
        }

        return array_values(array_unique(array_filter(
            collect($fragments)
                ->flatMap(fn (string $value): array => $this->tokens($value))
                ->all(),
            static fn (string $token): bool => $token !== ''
        )));
    }

    public function normalize(string $value): string
    {
        return Str::of(strip_tags($value))
            ->lower()
            ->ascii()
            ->replaceMatches('/[^a-z0-9]+/u', ' ')
            ->replace(['&nbsp;', "\r", "\n", "\t"], ' ')
            ->squish()
            ->value();
    }

    /**
     * @return array<int,string>
     */
    public function tokens(string $value): array
    {
        $normalized = $this->normalize($value);

        if ($normalized === '') {
            return [];
        }

        return array_values(array_filter(
            explode(' ', $normalized),
            static fn (string $token): bool => $token !== '' && strlen($token) >= 3
        ));
    }

    private function containsNormalized(string $normalizedContent, string $needle): bool
    {
        $normalizedNeedle = $this->normalize($needle);

        if ($normalizedNeedle === '') {
            return false;
        }

        return str_contains($normalizedContent, $normalizedNeedle);
    }
}
