<?php

declare(strict_types=1);

namespace Ofyre\SeoEngine\Contracts;

interface SignalSuggestionFormatter
{
    /**
     * Section text recommending internal linking toward the given target pages.
     *
     * @param  array<int,string>  $targetLabels
     */
    public function internalLinkSection(array $targetLabels): ?string;

    /**
     * Section text recommending editorial differentiation to resolve cannibalization.
     */
    public function cannibalizationSection(string $targetLabel, string $action): ?string;

    /**
     * Section text recommending a direct answer to the given search query.
     */
    public function querySection(string $query, string $action): ?string;

    /**
     * A FAQ entry built from a detected query opportunity.
     *
     * @return array{question:string,answer:string}|null
     */
    public function queryFaqItem(string $question): ?array;

    /**
     * Rationale line explaining why an internal link is suggested.
     */
    public function internalLinkRationale(string $targetLabel, float $similarityScore): ?string;

    /**
     * Rationale line explaining a cannibalization risk.
     */
    public function cannibalizationRationale(string $targetLabel, string $action): ?string;

    /**
     * Rationale line for a Search Console query opportunity.
     */
    public function queryRationale(string $query, int $impressions, float $position, string $action): ?string;

    /**
     * Fallback label used when a match has no query text.
     */
    public function fallbackQueryLabel(): string;

    /**
     * Convert a raw query string into a well-formed question.
     */
    public function questionFromQuery(string $query): string;
}
