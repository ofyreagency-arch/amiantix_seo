<?php

declare(strict_types=1);

namespace App\Runtime;

use Ofyre\SeoEngine\Contracts\SignalSuggestionFormatter;

class RuntimeSignalSuggestionFormatter implements SignalSuggestionFormatter
{
    public function internalLinkSection(array $labels): ?string
    {
        return 'Ajouter un bloc de renvois contextuels vers '.implode(', ', $labels).'.';
    }

    public function cannibalizationSection(string $label, string $action): ?string
    {
        return 'Clarifier l angle éditorial par rapport à '.$label.' et préparer une action de type '.$action.'.';
    }

    public function querySection(string $query, string $action): ?string
    {
        return 'Ajouter une section qui répond explicitement à la requête "'.$query.'" avec une logique '.$action.'.';
    }

    public function questionFromQuery(string $query): string
    {
        return ucfirst($query);
    }

    public function queryFaqItem(string $question): ?array
    {
        return [
            'question' => $question,
            'answer' => 'Cette réponse doit être enrichie avec un angle éditorial précis avant publication.',
        ];
    }

    public function internalLinkRationale(string $label, float $similarityScore): ?string
    {
        return 'Lien interne recommandé vers '.$label.' (similarité '.round($similarityScore, 2).').';
    }

    public function cannibalizationRationale(string $label, string $action): ?string
    {
        return 'Risque de cannibalisation détecté avec '.$label.' ; action suggérée : '.$action.'.';
    }

    public function queryRationale(string $query, int $impressions, float $position, string $reason): ?string
    {
        return 'La requête "'.$query.'" génère '.$impressions.' impressions à la position moyenne '.$position.' ; raison : '.$reason.'.';
    }

    public function fallbackQueryLabel(): string
    {
        return 'requête SEO';
    }
}
