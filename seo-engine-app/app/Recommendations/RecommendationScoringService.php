<?php

declare(strict_types=1);

namespace App\Recommendations;

class RecommendationScoringService
{
    /**
     * @param  array<string,mixed>  $classification
     * @param  array<string,mixed>  $businessIntent
     * @param  array<string,mixed>  $eligibility
     * @param  array<string,mixed>  $impact
     * @param  array<string,mixed>  $signals
     * @return array{
     *   recommendation_score:int,
     *   priority:int,
     *   positive_factors:array<int,string>,
     *   negative_factors:array<int,string>
     * }
     */
    public function score(
        string $action,
        array $classification,
        array $businessIntent,
        array $eligibility,
        array $impact,
        array $signals = []
    ): array {
        if (! ($eligibility['eligible'] ?? false)) {
            return [
                'recommendation_score' => 0,
                'priority' => 99,
                'positive_factors' => [],
                'negative_factors' => (array) ($eligibility['blocked_reasons'] ?? []),
            ];
        }

        $score = 0;
        $positive = [];
        $negative = [];

        $eligibilityScore = (int) ($classification['seo_eligibility_score'] ?? 0);
        $intentScore = (int) ($classification['seo_intent_score'] ?? 0);
        $businessValue = (int) ($businessIntent['business_value_score'] ?? 0);
        $impressions = (int) ($signals['impressions'] ?? 0);
        $position = (float) ($signals['position'] ?? 0.0);
        $ctr = (float) ($signals['ctr'] ?? 0.0);
        $wordCount = (int) ($signals['word_count'] ?? 0);
        $orphanScore = (float) ($signals['orphan_score'] ?? 0.0);
        $authorityScore = (float) ($signals['authority_score'] ?? 0.0);

        $score += (int) round($eligibilityScore * 0.25);
        $score += (int) round($intentScore * 0.20);
        $score += (int) round($businessValue * 0.25);
        $score += min(20, (int) round(log($impressions + 1, 2) * 3));
        $score += min(10, (int) round(($impact['confidence'] ?? 0) / 10));

        if ($position >= 8.0 && $position <= 15.0) {
            $score += 16;
            $positive[] = sprintf('Position moyenne %.1f proche du top 10', $position);
        } elseif ($position > 15.0) {
            $negative[] = sprintf('Position moyenne encore lointaine (%.1f)', $position);
        }

        if ($impressions > 0) {
            $positive[] = sprintf('%d impressions GSC détectées', $impressions);
        }

        if ($ctr > 0 && $ctr < 2.5) {
            $score += 8;
            $positive[] = sprintf('CTR faible à %.2f%% donc potentiel de gain', $ctr);
        }

        if ($action === 'refresh_page' && $wordCount < 300) {
            $score += 10;
            $positive[] = sprintf('Contenu court (%d mots)', $wordCount);
        }

        if ($action === 'add_internal_links' && $orphanScore >= 0.5) {
            $score += 10;
            $positive[] = sprintf('Orphan score élevé à %.2f', $orphanScore);
        }

        if ($action === 'refresh_page' && $authorityScore < 0.2) {
            $negative[] = sprintf('Autorité interne faible à %.2f', $authorityScore);
        }

        if ($businessValue >= 70) {
            $positive[] = 'Forte valeur business';
        }

        if ($intentScore < 40) {
            $negative[] = sprintf('Intention SEO limitée (%d/100)', $intentScore);
        }

        $score = max(0, min(100, $score));

        return [
            'recommendation_score' => $score,
            'priority' => max(1, min(99, 100 - $score)),
            'positive_factors' => array_values(array_unique($positive)),
            'negative_factors' => array_values(array_unique($negative)),
        ];
    }
}
