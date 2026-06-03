<?php

declare(strict_types=1);

namespace App\Recommendations;

class RecommendationEligibilityService
{
    /**
     * @param  array<string,mixed>  $classification
     * @param  array<string,mixed>  $businessIntent
     * @param  array<string,mixed>  $signals
     * @return array{
     *   eligible:bool,
     *   gate:int,
     *   blocked_reasons:array<int,string>,
     *   action:string
     * }
     */
    public function evaluate(string $action, array $classification, array $businessIntent, array $signals = []): array
    {
        $blockedReasons = [];
        $pageType = (string) ($classification['page_type'] ?? 'SEO_PAGE');
        $eligibilityScore = (int) ($classification['seo_eligibility_score'] ?? 0);
        $intentScore = (int) ($classification['seo_intent_score'] ?? 0);
        $businessValue = (int) ($businessIntent['business_value_score'] ?? 0);
        $indexability = (string) ($signals['indexability_state'] ?? 'unknown');

        if (in_array($pageType, ['AUTH_PAGE', 'ACCOUNT_PAGE', 'LEGAL_PAGE', 'UTILITY_PAGE', 'SYSTEM_PAGE'], true)) {
            $blockedReasons[] = sprintf('Type de page exclu : %s', $pageType);
        }

        if ($eligibilityScore < 40) {
            $blockedReasons[] = sprintf('Éligibilité SEO trop faible (%d/100)', $eligibilityScore);
        }

        if (in_array($action, ['refresh_page', 'add_internal_links', 'differentiate_intent'], true) && $indexability !== 'indexable') {
            $blockedReasons[] = 'Page non indexable pour cette action';
        }

        if (in_array($action, ['refresh_page', 'create_page', 'differentiate_intent'], true) && $intentScore < 20) {
            $blockedReasons[] = sprintf('Intention SEO trop faible (%d/100)', $intentScore);
        }

        if (in_array($action, ['refresh_page', 'create_page'], true) && $businessValue < 20) {
            $blockedReasons[] = sprintf('Valeur business trop faible (%d/100)', $businessValue);
        }

        return [
            'eligible' => $blockedReasons === [],
            'gate' => $blockedReasons === [] ? 100 : 0,
            'blocked_reasons' => $blockedReasons,
            'action' => $action,
        ];
    }
}
