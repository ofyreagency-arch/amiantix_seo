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
}
