<?php

declare(strict_types=1);

namespace Ofyre\SeoEngine\Services\Composition;

final class NarrativeAssembler
{
    /**
     * @param  array<int,string>  $headings
     * @param  array<string,mixed>  $blueprint
     * @return array<int,string>
     */
    public function orderHeadings(array $headings, array $blueprint): array
    {
        if ($headings === []) {
            return [];
        }

        $flow = $blueprint['composition']['narrative_flow'] ?? [];
        $slots = $blueprint['composition']['narrative_slots'] ?? [];

        if (! is_array($flow) || ! is_array($slots) || $flow === [] || $slots === []) {
            return array_values($headings);
        }

        $phaseOrder = [];

        foreach (array_values($flow) as $index => $phase) {
            if (is_string($phase) && $phase !== '') {
                $phaseOrder[$phase] = $index;
            }
        }

        $headingPhase = [];

        foreach ($slots as $phase => $phaseHeadings) {
            if (! is_string($phase) || ! is_array($phaseHeadings)) {
                continue;
            }

            foreach ($phaseHeadings as $heading) {
                if (is_string($heading) && $heading !== '') {
                    $headingPhase[$heading] = $phase;
                }
            }
        }

        $ranked = [];

        foreach (array_values($headings) as $index => $heading) {
            $phase = $headingPhase[$heading] ?? null;
            $ranked[] = [
                'heading' => $heading,
                'phase_rank' => is_string($phase) && array_key_exists($phase, $phaseOrder)
                    ? $phaseOrder[$phase]
                    : PHP_INT_MAX,
                'original_rank' => $index,
            ];
        }

        usort($ranked, static function (array $left, array $right): int {
            $phaseComparison = $left['phase_rank'] <=> $right['phase_rank'];

            if ($phaseComparison !== 0) {
                return $phaseComparison;
            }

            return $left['original_rank'] <=> $right['original_rank'];
        });

        return array_values(array_map(
            static fn (array $row): string => (string) $row['heading'],
            $ranked
        ));
    }

    /**
     * @param  array<int,string>  $headings
     * @param  array<string,string>  $catalog
     * @param  array<string,mixed>  $blueprint
     */
    public function assembleHtml(array $headings, array $catalog, array $blueprint, string $existingContent = ''): string
    {
        $ordered = $this->orderHeadings($headings, $blueprint);

        if ($ordered === []) {
            return '';
        }

        $html = '';
        $previousPhase = $this->lastCoveredPhase($existingContent, $blueprint);

        foreach ($ordered as $heading) {
            $block = (string) ($catalog[$heading] ?? '');

            if ($block === '') {
                continue;
            }

            $phase = $this->phaseForHeading($heading, $blueprint);

            if ($previousPhase !== null) {
                $bridge = $this->bridgeForTransition($previousPhase, $phase, $heading, $blueprint, $existingContent.$html);

                if ($bridge !== '') {
                    $html .= '<section><p>'.$bridge.'</p></section>';
                }
            }

            $html .= $block;
            $previousPhase = $phase;
        }

        return $html;
    }

    /**
     * @param  array<string,mixed>  $blueprint
     */
    private function phaseForHeading(string $heading, array $blueprint): ?string
    {
        $slots = $blueprint['composition']['narrative_slots'] ?? [];

        if (! is_array($slots)) {
            return null;
        }

        foreach ($slots as $phase => $phaseHeadings) {
            if (! is_string($phase) || ! is_array($phaseHeadings)) {
                continue;
            }

            foreach ($phaseHeadings as $candidate) {
                if (is_string($candidate) && $candidate === $heading) {
                    return $phase;
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string,mixed>  $blueprint
     */
    private function lastCoveredPhase(string $content, array $blueprint): ?string
    {
        if ($content === '') {
            return null;
        }

        $slots = $blueprint['composition']['narrative_slots'] ?? [];
        $flow = $blueprint['composition']['narrative_flow'] ?? [];

        if (! is_array($slots) || ! is_array($flow) || $slots === [] || $flow === []) {
            return null;
        }

        $normalizedContent = $this->normalize($content);
        $covered = [];

        foreach ($slots as $phase => $phaseHeadings) {
            if (! is_string($phase) || ! is_array($phaseHeadings)) {
                continue;
            }

            foreach ($phaseHeadings as $heading) {
                if (! is_string($heading) || $heading === '') {
                    continue;
                }

                if (str_contains($normalizedContent, $this->normalize($heading))) {
                    $covered[$phase] = true;
                    break;
                }
            }
        }

        if ($covered === []) {
            return null;
        }

        $lastPhase = null;

        foreach ($flow as $phase) {
            if (is_string($phase) && isset($covered[$phase])) {
                $lastPhase = $phase;
            }
        }

        return $lastPhase;
    }

    /**
     * @param  array<string,mixed>  $blueprint
     */
    private function bridgeForTransition(?string $fromPhase, ?string $toPhase, string $heading, array $blueprint, string $existingContent = ''): string
    {
        if ($toPhase === null) {
            return '';
        }

        if ($fromPhase !== null && $fromPhase === $toPhase) {
            return '';
        }

        $bridges = $blueprint['composition']['narrative_phase_bridges'] ?? [];

        if (! is_array($bridges) || $bridges === []) {
            return '';
        }

        $pairKey = ($fromPhase ?? 'start').':'.$toPhase;

        $pairBridge = $this->bridgeSelectionForEntry($bridges[$pairKey] ?? null, $heading, $fromPhase, $existingContent);

        if ($pairBridge['text'] !== '') {
            if ($pairBridge['skip_tail_dedupe'] === false && $this->tailAlreadyCoversBridge($existingContent, $pairBridge['text'])) {
                return '';
            }

            return $pairBridge['text'];
        }

        $phaseBridge = $this->bridgeSelectionForEntry($bridges[$toPhase] ?? null, $heading, $fromPhase, $existingContent);

        if ($phaseBridge['text'] !== '') {
            if ($phaseBridge['skip_tail_dedupe'] === false && $this->tailAlreadyCoversBridge($existingContent, $phaseBridge['text'])) {
                return '';
            }

            return $phaseBridge['text'];
        }

        return '';
    }

    /**
     * @param  mixed  $entry
     * @return array{text:string,skip_tail_dedupe:bool}
     */
    private function bridgeSelectionForEntry(mixed $entry, string $heading, ?string $fromPhase = null, string $existingContent = ''): array
    {
        if (is_string($entry) && $entry !== '') {
            return ['text' => $entry, 'skip_tail_dedupe' => false];
        }

        if (! is_array($entry) || $entry === []) {
            return ['text' => '', 'skip_tail_dedupe' => false];
        }

        $signalBridge = $this->bridgeTextForSignalEntry($entry['by_context_signal'] ?? null, $heading, $fromPhase, $existingContent);

        if ($signalBridge !== '') {
            return ['text' => $signalBridge, 'skip_tail_dedupe' => true];
        }

        $headingPhaseBridges = $entry['by_heading_and_from_phase'] ?? null;

        if (
            is_string($fromPhase)
            && is_array($headingPhaseBridges)
            && is_array($headingPhaseBridges[$heading] ?? null)
            && is_string($headingPhaseBridges[$heading][$fromPhase] ?? null)
            && $headingPhaseBridges[$heading][$fromPhase] !== ''
        ) {
            return ['text' => (string) $headingPhaseBridges[$heading][$fromPhase], 'skip_tail_dedupe' => false];
        }

        $headingBridges = $entry['by_heading'] ?? null;

        if (is_array($headingBridges) && is_string($headingBridges[$heading] ?? null) && $headingBridges[$heading] !== '') {
            return ['text' => (string) $headingBridges[$heading], 'skip_tail_dedupe' => false];
        }

        $phaseBridges = $entry['by_from_phase'] ?? null;

        if (
            is_string($fromPhase)
            && is_array($phaseBridges)
            && is_string($phaseBridges[$fromPhase] ?? null)
            && $phaseBridges[$fromPhase] !== ''
        ) {
            return ['text' => (string) $phaseBridges[$fromPhase], 'skip_tail_dedupe' => false];
        }

        $densityBridges = $entry['by_density_signal'] ?? null;
        $densitySignal = $this->densitySignalForContent($existingContent);

        if (
            $densitySignal !== null
            && is_array($densityBridges)
            && is_string($densityBridges[$densitySignal] ?? null)
            && $densityBridges[$densitySignal] !== ''
        ) {
            return ['text' => (string) $densityBridges[$densitySignal], 'skip_tail_dedupe' => false];
        }

        if (is_string($entry[$heading] ?? null) && $entry[$heading] !== '') {
            return ['text' => (string) $entry[$heading], 'skip_tail_dedupe' => false];
        }

        if (is_string($entry['default'] ?? null) && $entry['default'] !== '') {
            return ['text' => (string) $entry['default'], 'skip_tail_dedupe' => false];
        }

        return ['text' => '', 'skip_tail_dedupe' => false];
    }

    /**
     * @param  mixed  $entry
     */
    private function bridgeTextForSignalEntry(mixed $entry, string $heading, ?string $fromPhase, string $existingContent): string
    {
        if (! is_array($entry) || $entry === [] || $existingContent === '') {
            return '';
        }

        $tail = $this->tailWindow($existingContent, 55);
        $tailTokens = $this->tokens($tail);

        if ($tailTokens === []) {
            return '';
        }

        foreach ($entry as $rule) {
            if (! is_array($rule)) {
                continue;
            }

            if (is_string($rule['heading'] ?? null) && $rule['heading'] !== $heading) {
                continue;
            }

            if (is_string($rule['from_phase'] ?? null) && $rule['from_phase'] !== $fromPhase) {
                continue;
            }

            $terms = is_array($rule['terms'] ?? null) ? $rule['terms'] : [];

            if ($terms === []) {
                continue;
            }

            $normalizedTerms = array_values(array_filter(array_map(
                fn (mixed $term): string => is_string($term) ? $this->normalize($term) : '',
                $terms
            ), static fn (string $term): bool => $term !== ''));

            if ($normalizedTerms === []) {
                continue;
            }

            $matchedTerms = array_values(array_filter(
                $normalizedTerms,
                fn (string $term): bool => $this->tailContainsTerm($tailTokens, $term)
            ));

            if ($matchedTerms === []) {
                continue;
            }

            $matchMode = is_string($rule['match'] ?? null) ? strtolower((string) $rule['match']) : 'any';

            if ($matchMode === 'all' && count($matchedTerms) !== count($normalizedTerms)) {
                continue;
            }

            if (is_string($rule['text'] ?? null) && $rule['text'] !== '') {
                return (string) $rule['text'];
            }
        }

        return '';
    }

    private function normalize(string $value): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', strip_tags($value));

        if ($ascii === false) {
            $ascii = strip_tags($value);
        }

        $ascii = strtolower($ascii);
        $ascii = preg_replace('/[^a-z0-9]+/u', ' ', $ascii) ?? '';

        return trim(preg_replace('/\s+/u', ' ', $ascii) ?? '');
    }

    private function tailAlreadyCoversBridge(string $content, string $bridge): bool
    {
        $normalizedBridge = $this->normalize($bridge);

        if ($normalizedBridge === '') {
            return false;
        }

        $tailTokens = $this->tokens($this->tailWindow($content));
        $bridgeTokens = $this->tokens($normalizedBridge);

        if ($tailTokens === [] || $bridgeTokens === []) {
            return false;
        }

        $shared = array_intersect($bridgeTokens, $tailTokens);
        $sharedCount = count($shared);

        if ($sharedCount === 0) {
            return false;
        }

        return $sharedCount >= 5 || ($sharedCount / max(1, count($bridgeTokens))) >= 0.6;
    }

    /**
     * @return array<int,string>
     */
    private function tokens(string $value): array
    {
        $normalized = $this->normalize($value);

        if ($normalized === '') {
            return [];
        }

        return array_values(array_filter(explode(' ', $normalized), static fn (string $token): bool => $token !== ''));
    }

    private function tailWindow(string $content, int $words = 45): string
    {
        $tokens = $this->tokens($content);

        if ($tokens === []) {
            return '';
        }

        return implode(' ', array_slice($tokens, -$words));
    }

    private function densitySignalForContent(string $content): ?string
    {
        if ($content === '') {
            return null;
        }

        $recentPassage = $this->recentPassageText($content);
        $wordCount = count($this->tokens($recentPassage));

        if ($wordCount === 0) {
            return null;
        }

        if ($wordCount <= 18) {
            return 'concise';
        }

        if ($wordCount >= 42) {
            return 'dense';
        }

        return 'balanced';
    }

    private function recentPassageText(string $content): string
    {
        preg_match_all('/<section\b[^>]*>.*?<\/section>/is', $content, $matches);
        $sections = $matches[0] ?? [];

        if ($sections !== []) {
            return (string) end($sections);
        }

        return $content;
    }

    /**
     * @param  array<int,string>  $tailTokens
     */
    private function tailContainsTerm(array $tailTokens, string $term): bool
    {
        $termTokens = $this->tokens($term);

        if ($termTokens === []) {
            return false;
        }

        if (count($termTokens) === 1) {
            return in_array($termTokens[0], $tailTokens, true);
        }

        $tail = implode(' ', $tailTokens);

        return str_contains($tail, implode(' ', $termTokens));
    }
}
