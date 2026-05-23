<?php

declare(strict_types=1);

namespace Ofyre\SeoEngine\Services\Composition;

use Illuminate\Support\Str;

final class CoverageInspector
{
    /**
     * @param  array<int,string>  $headings
     * @param  array<string,mixed>  $blueprint
     * @return array<string,bool>
     */
    public function headingCoverageMap(string $content, array $headings, array $blueprint = []): array
    {
        $normalizedContent = $this->normalize($content);
        $coverage = [];

        foreach ($headings as $heading) {
            $coverage[$heading] = $this->coversHeading($content, $heading, $blueprint, $normalizedContent);
        }

        return $coverage;
    }

    /**
     * @param  array<int,string>  $headings
     * @param  array<string,mixed>  $blueprint
     */
    public function headingCoverageRatio(string $content, array $headings, array $blueprint = []): float
    {
        if ($headings === []) {
            return 1.0;
        }

        $coverage = $this->headingCoverageMap($content, $headings, $blueprint);
        $covered = count(array_filter($coverage, static fn (bool $state): bool => $state));

        return $covered / max(1, count($headings));
    }

    /**
     * @param  array<string,mixed>  $blueprint
     */
    public function coversHeading(string $content, string $heading, array $blueprint = [], ?string $normalizedContent = null): bool
    {
        $normalizedContent ??= $this->normalize($content);

        if ($this->containsNormalized($normalizedContent, $heading)) {
            return true;
        }

        if ($this->matchesCoverageMarkers($normalizedContent, $heading, $blueprint)) {
            return true;
        }

        return $this->tokenCoverageRatio($normalizedContent, $heading) >= 0.75;
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

    /**
     * @param  array<string,mixed>  $blueprint
     */
    private function matchesCoverageMarkers(string $normalizedContent, string $heading, array $blueprint): bool
    {
        $markerMap = $blueprint['composition']['coverage_markers'] ?? [];

        if (! is_array($markerMap)) {
            return false;
        }

        $markers = $markerMap[$heading] ?? null;

        if (! is_array($markers) || $markers === []) {
            return false;
        }

        $matched = 0;

        foreach ($markers as $marker) {
            if (! is_string($marker) || $marker === '') {
                continue;
            }

            if ($this->containsNormalized($normalizedContent, $marker)) {
                $matched++;
            }
        }

        return $matched >= max(1, min(2, count($markers)));
    }

    private function tokenCoverageRatio(string $normalizedContent, string $heading): float
    {
        $headingTokens = $this->tokens($heading);

        if (count($headingTokens) < 2) {
            return 0.0;
        }

        $matched = 0;

        foreach ($headingTokens as $token) {
            if ($token !== '' && str_contains($normalizedContent, $token)) {
                $matched++;
            }
        }

        return $matched / max(1, count($headingTokens));
    }
}
