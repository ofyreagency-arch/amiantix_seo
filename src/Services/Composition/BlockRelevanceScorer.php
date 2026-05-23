<?php

declare(strict_types=1);

namespace Ofyre\SeoEngine\Services\Composition;

final class BlockRelevanceScorer
{
    public function __construct(
        private readonly CoverageInspector $coverage,
    ) {}

    /**
     * @param  array<string,mixed>  $blueprint
     */
    public function score(string $heading, array $blueprint, string $content, bool $required = false): int
    {
        if ($this->coverage->coversHeading($content, $heading, $blueprint)) {
            return PHP_INT_MIN;
        }

        $score = $required ? 100 : 50;
        $headingTokens = $this->coverage->headingTokens($heading);
        $contextTokens = $this->coverage->blueprintContextTokens($blueprint);
        $overlap = count(array_intersect($headingTokens, $contextTokens));

        $score += $overlap * 8;

        if (! $required && $overlap === 0) {
            $score -= 40;
        }

        if (! $required && $this->isTransverseHeading($heading) && $overlap <= 1) {
            $score -= 18;
        }

        if (! $required && $this->containsDisallowedOptionalTerm($heading, $blueprint)) {
            $score -= 80;
        }

        $normalizedContent = $this->coverage->normalize($content);
        $duplicatedSignals = 0;

        foreach ($headingTokens as $token) {
            if ($token !== '' && str_contains($normalizedContent, $token)) {
                $duplicatedSignals++;
            }
        }

        if (! $required && $duplicatedSignals >= max(2, intdiv(max(1, count($headingTokens)), 2))) {
            $score -= 10;
        }

        return $score;
    }

    private function isTransverseHeading(string $heading): bool
    {
        $tokens = $this->coverage->headingTokens($heading);
        $transverse = [
            'checklist',
            'questions',
            'question',
            'ressources',
            'resources',
            'site',
            'routine',
            'matrix',
            'matrice',
            'mistakes',
            'erreurs',
            'faq',
            'occupation',
        ];

        return count(array_intersect($tokens, $transverse)) > 0;
    }

    /**
     * @param  array<string,mixed>  $blueprint
     */
    private function containsDisallowedOptionalTerm(string $heading, array $blueprint): bool
    {
        $terms = $blueprint['composition']['disallowed_optional_terms'] ?? [];

        if (! is_array($terms) || $terms === []) {
            return false;
        }

        $headingTokens = $this->coverage->headingTokens($heading);
        $disallowed = array_values(array_unique(array_filter(
            collect($terms)
                ->flatMap(fn (mixed $term): array => is_string($term) ? $this->coverage->tokens($term) : [])
                ->all(),
            static fn (string $token): bool => $token !== ''
        )));

        return count(array_intersect($headingTokens, $disallowed)) > 0;
    }
}
