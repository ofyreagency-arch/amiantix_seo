<?php

declare(strict_types=1);

namespace App\Recommendations;

class ImpactEstimatorService
{
    /**
     * @param  array<string,mixed>  $classification
     * @param  array<string,mixed>  $businessIntent
     * @param  array<string,mixed>  $signals
     * @return array{
     *   estimated_impact:string,
     *   monthly_gain_min:int,
     *   monthly_gain_max:int,
     *   confidence:int
     * }
     */
    public function estimate(string $action, array $classification, array $businessIntent, array $signals = []): array
    {
        $impressions = (int) ($signals['impressions'] ?? 0);
        $position = (float) ($signals['position'] ?? 0.0);
        $ctr = (float) ($signals['ctr'] ?? 0.0);
        $businessValue = (int) ($businessIntent['business_value_score'] ?? 0);
        $intent = (int) ($classification['seo_intent_score'] ?? 0);

        $baseGain = match ($action) {
            'create_page' => max(15, (int) round(($businessValue + $intent) / 2)),
            'refresh_page' => max(10, (int) round($impressions * 0.05)),
            'add_internal_links' => max(5, (int) round(($impressions * 0.02) + 8)),
            'differentiate_intent' => max(5, (int) round(($impressions * 0.03) + 6)),
            default => 0,
        };

        if ($position >= 8.0 && $position <= 15.0) {
            $baseGain += 18;
        }

        if ($ctr > 0 && $ctr < 2.5) {
            $baseGain += 12;
        }

        $confidence = max(
            35,
            min(
                90,
                (int) round(
                    35
                    + min(20, log(max($impressions, 1), 2) * 3)
                    + min(20, $businessValue / 5)
                    + min(15, $intent / 7)
                )
            )
        );

        $tier = match (true) {
            $baseGain >= 80 => 'high',
            $baseGain >= 30 => 'medium',
            default => 'low',
        };

        return [
            'estimated_impact' => $tier,
            'monthly_gain_min' => max(0, (int) floor($baseGain * 0.8)),
            'monthly_gain_max' => max(0, (int) ceil($baseGain * 1.5)),
            'confidence' => $confidence,
        ];
    }
}
