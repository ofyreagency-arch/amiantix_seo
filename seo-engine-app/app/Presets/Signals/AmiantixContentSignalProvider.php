<?php

declare(strict_types=1);

namespace App\Presets\Signals;

use Ofyre\SeoEngine\Contracts\ContentSignalProvider;

class AmiantixContentSignalProvider implements ContentSignalProvider
{
    public function requiredContentMarkers(): array
    {
        return [
            ['marker' => 'amiante', 'issue_key' => 'missing_amiante_context', 'score_penalty' => 12],
            ['marker' => 'repérage', 'issue_key' => 'missing_reperage_context', 'score_penalty' => 8],
            ['marker' => 'dta', 'issue_key' => 'missing_dta_context', 'score_penalty' => 6],
            ['marker' => 'ss3', 'issue_key' => 'missing_ss3_ss4_context', 'score_penalty' => 6],
        ];
    }

    public function recommendationFor(string $issueKey): ?string
    {
        return match ($issueKey) {
            'missing_amiante_context' => 'Renforcer le cadrage métier autour du risque amiante et des obligations du donneur d’ordre.',
            'missing_reperage_context' => 'Ajouter un passage concret sur le repérage avant travaux et la préparation documentaire.',
            'missing_dta_context' => 'Préciser le rôle du DTA, sa transmission et son usage opérationnel.',
            'missing_ss3_ss4_context' => 'Clarifier les cas SS3 et SS4 quand le sujet s’y prête.',
            'spam_risk_medium' => 'Supprimer les formules trop génériques et ajouter des détails terrain vérifiables.',
            'spam_risk_high' => 'Réécrire le contenu avec beaucoup plus de précision réglementaire et opérationnelle.',
            default => null,
        };
    }

    public function genericPhraseWarnings(): array
    {
        return [
            ['phrase' => 'service de qualité', 'warning' => 'Formulation trop vague pour un contenu métier amiante.'],
            ['phrase' => 'solution innovante', 'warning' => 'Remplacer par une promesse concrète de méthode, preuve ou conformité.'],
            ['phrase' => 'accompagnement personnalisé', 'warning' => 'Préciser les livrables, les contrôles et la coordination réelle.'],
        ];
    }
}
