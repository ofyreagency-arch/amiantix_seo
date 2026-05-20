<?php

declare(strict_types=1);

namespace Ofyre\SeoEngine\Contracts;

interface ContentSignalProvider
{
    /**
     * Domain-specific content markers required for quality scoring.
     * Each entry defines a string that must appear in the generated content,
     * the issue key emitted when absent, and the score penalty applied.
     *
     * @return array<int, array{marker: string, issue_key: string, score_penalty: int}>
     */
    public function requiredContentMarkers(): array;

    /**
     * Human-readable recommendation for a given issue key.
     * Return null to skip adding a recommendation for that key.
     */
    public function recommendationFor(string $issueKey): ?string;

    /**
     * Phrases whose presence in the generated content should trigger a quality warning.
     * Each entry defines the phrase to detect and the warning message to emit.
     *
     * @return array<int, array{phrase: string, warning: string}>
     */
    public function genericPhraseWarnings(): array;
}
