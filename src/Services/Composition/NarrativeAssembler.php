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

        $pairBridge = $this->bridgeTextForEntry($bridges[$pairKey] ?? null, $heading, $fromPhase);

        if ($pairBridge !== '') {
            return $this->tailAlreadyCoversBridge($existingContent, $pairBridge) ? '' : $pairBridge;
        }

        $phaseBridge = $this->bridgeTextForEntry($bridges[$toPhase] ?? null, $heading, $fromPhase);

        if ($phaseBridge !== '') {
            return $this->tailAlreadyCoversBridge($existingContent, $phaseBridge) ? '' : $phaseBridge;
        }

        return '';
    }

    /**
     * @param  mixed  $entry
     */
    private function bridgeTextForEntry(mixed $entry, string $heading, ?string $fromPhase = null): string
    {
        if (is_string($entry) && $entry !== '') {
            return $entry;
        }

        if (! is_array($entry) || $entry === []) {
            return '';
        }

        $headingPhaseBridges = $entry['by_heading_and_from_phase'] ?? null;

        if (
            is_string($fromPhase)
            && is_array($headingPhaseBridges)
            && is_array($headingPhaseBridges[$heading] ?? null)
            && is_string($headingPhaseBridges[$heading][$fromPhase] ?? null)
            && $headingPhaseBridges[$heading][$fromPhase] !== ''
        ) {
            return (string) $headingPhaseBridges[$heading][$fromPhase];
        }

        $headingBridges = $entry['by_heading'] ?? null;

        if (is_array($headingBridges) && is_string($headingBridges[$heading] ?? null) && $headingBridges[$heading] !== '') {
            return (string) $headingBridges[$heading];
        }

        $phaseBridges = $entry['by_from_phase'] ?? null;

        if (
            is_string($fromPhase)
            && is_array($phaseBridges)
            && is_string($phaseBridges[$fromPhase] ?? null)
            && $phaseBridges[$fromPhase] !== ''
        ) {
            return (string) $phaseBridges[$fromPhase];
        }

        if (is_string($entry[$heading] ?? null) && $entry[$heading] !== '') {
            return (string) $entry[$heading];
        }

        if (is_string($entry['default'] ?? null) && $entry['default'] !== '') {
            return (string) $entry['default'];
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
}
