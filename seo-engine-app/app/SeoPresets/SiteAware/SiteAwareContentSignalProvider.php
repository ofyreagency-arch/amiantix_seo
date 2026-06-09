<?php

declare(strict_types=1);

namespace App\SeoPresets\SiteAware;

use Ofyre\SeoEngine\Contracts\ContentSignalProvider;

final class SiteAwareContentSignalProvider implements ContentSignalProvider
{
    public function requiredContentMarkers(): array
    {
        $profile = SiteProfilePromptContext::profile() ?? [];
        $terms = data_get($profile, 'vocabulary.core_terms', []);

        if (! is_array($terms) || $terms === []) {
            return [];
        }

        return collect($terms)
            ->take(4)
            ->map(fn (string $term): array => [
                'marker' => $term,
                'issue_key' => 'missing_site_vocabulary_'.$term,
                'score_penalty' => 8,
            ])
            ->values()
            ->all();
    }

    public function recommendationFor(string $issueKey): ?string
    {
        if (str_starts_with($issueKey, 'missing_site_vocabulary_')) {
            return 'Intégrer le vocabulaire métier réel du site observé lors du crawl.';
        }

        return match ($issueKey) {
            'spam_risk_medium' => 'Supprimer les formulations génériques et citer des services réels du site.',
            'spam_risk_high' => 'Réécrire avec le vocabulaire métier et les offres observées sur le site.',
            default => null,
        };
    }

    public function genericPhraseWarnings(): array
    {
        $profile = SiteProfilePromptContext::profile() ?? [];
        $forbidden = data_get($profile, 'vocabulary.forbidden_generic', []);

        $warnings = [
            ['phrase' => 'Field example', 'warning' => 'Section fictive interdite — supprimer immédiatement.'],
            ['phrase' => 'SaaS knowledge base', 'warning' => 'Template SaaS interdit — ancrer dans le métier réel.'],
            ['phrase' => 'innovative solution', 'warning' => 'Formulation générique — remplacer par un service concret du site.'],
        ];

        foreach (is_array($forbidden) ? $forbidden : [] as $phrase) {
            if (! is_string($phrase) || trim($phrase) === '') {
                continue;
            }
            $warnings[] = ['phrase' => $phrase, 'warning' => 'Terme générique interdit pour ce site.'];
        }

        return $warnings;
    }
}
