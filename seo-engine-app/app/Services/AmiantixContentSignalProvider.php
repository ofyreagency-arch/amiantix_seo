<?php

declare(strict_types=1);

namespace App\Services;

use Ofyre\SeoEngine\Contracts\ContentSignalProvider;

class AmiantixContentSignalProvider implements ContentSignalProvider
{
    public function requiredContentMarkers(): array
    {
        return [
            ['marker' => 'amiante', 'issue_key' => 'missing_amiante_context', 'score_penalty' => 12],
            ['marker' => 'rep', 'issue_key' => 'missing_reperage_context', 'score_penalty' => 8],
            ['marker' => 'chantier', 'issue_key' => 'missing_field_context', 'score_penalty' => 8],
            ['marker' => 'réglement', 'issue_key' => 'missing_regulation_context', 'score_penalty' => 8],
        ];
    }

    public function recommendationFor(string $issueKey): ?string
    {
        return match ($issueKey) {
            'missing_amiante_context' => 'Ajouter un cadrage clair sur le risque amiante et le contexte d intervention.',
            'missing_reperage_context' => 'Renforcer la partie repérage, préparation documentaire et hypothèses de travaux.',
            'missing_field_context' => 'Ajouter des situations terrain concrètes pour rendre le contenu actionnable.',
            'missing_regulation_context' => 'Mieux relier le contenu aux obligations réglementaires et responsabilités des acteurs.',
            'spam_risk_medium' => 'Retirer les formules génériques et renforcer les détails métier observables.',
            'spam_risk_high' => 'Réécrire la page avec un niveau de précision métier beaucoup plus élevé.',
            default => null,
        };
    }

    public function genericPhraseWarnings(): array
    {
        return [
            ['phrase' => 'solution innovante', 'warning' => 'Expression marketing trop générique pour un contenu amiante expert.'],
            ['phrase' => 'service de qualité', 'warning' => 'Remplacer par des preuves opérationnelles concrètes.'],
            ['phrase' => 'accompagnement personnalisé', 'warning' => 'Préciser plutôt le cadre d intervention, les livrables et les contrôles.'],
        ];
    }
}
