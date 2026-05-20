<?php

declare(strict_types=1);

namespace Ofyre\SeoEngine\Examples\AmiantixPreset;

use Illuminate\Support\Str;
use Ofyre\SeoEngine\Contracts\NicheContentProvider;

final class AmiantixContentProfile implements NicheContentProvider
{
    public function fallbackPayload(string $keyword, string $cluster, array $blueprint, array $context = []): array
    {
        $topic = Str::headline($keyword);

        return [
            'title' => $topic.' : guide Amiantix pour anticiper le risque amiante',
            'meta_description' => 'Comprendre '.$keyword.', les obligations, les points de vigilance terrain et les bonnes pratiques de pilotage avec Amiantix.',
            'h1' => $topic.' : obligations, methodes et coordination',
            'content' => implode("\n\n", [
                'Amiantix accompagne les donneurs d ordre, syndics, entreprises et gestionnaires de patrimoine sur les sujets de repérage, d intervention et de maitrise du risque amiante.',
                'Une bonne page doit expliquer le contexte reglementaire, la preparation documentaire, la sequence d intervention, les controles utiles et les erreurs qui exposent le chantier ou le patrimoine.',
                'Le contenu doit rester ancre dans des situations reelles: maintenance, travaux, copropriete, locaux occupes, logements, batiments tertiaires ou patrimoine public.',
                'La decision ne repose pas uniquement sur un diagnostic. Elle depend aussi du calendrier, des usages du site, des entreprises impliquees, du niveau d empoussièrement attendu et de la qualite de la coordination.',
                'Amiantix doit aider le lecteur a comprendre quoi verifier, quand agir, quels documents demander et comment reduire le risque sans improvisation.',
            ]),
            'faq' => $blueprint['faq'] ?? [],
            'schema' => [
                [
                    '@type' => 'FAQPage',
                    'name' => $topic,
                ],
            ],
        ];
    }

    public function extraSection(string $keyword, array $blueprint, array $context = []): string
    {
        return 'Ajouter une section terrain sur les erreurs frequentes: repérage incomplet, hypothese de travaux floue, documents non transmis, coordination insuffisante entre diagnostic, entreprise et maitrise d ouvrage.';
    }

    public function ensureContentDepth(string $content, array $blueprint, array $context = []): string
    {
        if (Str::contains($content, 'empoussi')) {
            return $content;
        }

        return rtrim($content)."\n\n".
            'Le lecteur doit aussi comprendre comment le niveau d empoussièrement, la nature des materiaux, les acces, l occupation des lieux et la tracabilite documentaire influencent la methode d intervention.';
    }
}
