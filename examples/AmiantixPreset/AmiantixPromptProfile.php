<?php

declare(strict_types=1);

namespace Ofyre\SeoEngine\Examples\AmiantixPreset;

use Illuminate\Support\Str;
use Ofyre\SeoEngine\Contracts\PromptProfileProvider;

final class AmiantixPromptProfile implements PromptProfileProvider
{
    public function generationPrompt(string $keyword, string $cluster, array $blueprint, array $editorialSections, array $expectedSignals): string
    {
        return "Rédige un contenu SEO expert pour Amiantix, specialiste du risque amiante.\n".
            'Mot-cle principal: '.$keyword."\n".
            'Cluster: '.$cluster."\n".
            'Sujet: '.$blueprint['topic']."\n".
            'Sections editoriales attendues: '.json_encode($editorialSections, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n".
            'Signaux metier obligatoires: '.json_encode($expectedSignals, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n".
            "Contraintes:\n".
            "- ton expert, clair, rassurant et operationnel\n".
            "- ecrire pour maitres d ouvrage, syndics, entreprises, collectivites et responsables techniques\n".
            "- integrer obligations, vigilance terrain, documentation, coordination chantier et reduction du risque\n".
            "- eviter le blabla marketing, le jargon IA et les banalites SEO\n".
            "- faire ressortir les contextes SS3, SS4, repérage, confinement, DTA et empoussièrement quand c est pertinent\n".
            "- minimum 1400 mots\n".
            'Retourner du JSON uniquement avec: title, meta_description, h1, content, faq, schema.';
    }

    public function improvementPrompt(object $page, array $blueprint, array $audit, array $editorialSections, array $expectedSignals): string
    {
        return "Ameliore cet article Amiantix avec plus de profondeur metier et reglementaire.\n".
            'Mot-cle: '.($page->keyword ?? '')."\n".
            'Sujet: '.$blueprint['topic']."\n".
            'Problemes detectes: '.json_encode($audit['issues'] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n".
            'Recommandations: '.json_encode($audit['recommendations'] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n".
            'Sections attendues: '.json_encode($editorialSections, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n".
            'Signaux obligatoires: '.json_encode($expectedSignals, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n".
            "Ne parle ni de SEO ni d IA. Retourne du JSON uniquement avec title, meta_description, h1, content, faq et schema.\n".
            "Contenu actuel:\n".($page->content ?? '');
    }

    public function rewritePrompt(object $page, string $mode): string
    {
        return "Reecris un contenu Amiantix sur le risque amiante.\n".
            'Mode: '.$mode."\n".
            'Mot-cle: '.($page->keyword ?? '')."\n".
            'Cluster: '.($page->cluster ?? 'amiante')."\n".
            "Utilise un ton expert, concret et pedagogique. Integre coordination de chantier, obligations documentaires, decision du donneur d ordre et prevention du risque.\n".
            "Ne mentionne ni Google, ni SEO, ni IA.\n".
            "Retourne du JSON avec title, meta_description, h1, sections, faq, internal_links et rationale.\n".
            "Contenu actuel:\n".($page->content ?? '');
    }

    public function fallbackRewrite(object $page, string $mode): array
    {
        $topic = Str::headline((string) ($page->keyword ?? 'Risque amiante'));

        return [
            'mode' => $mode,
            'title' => $mode === 'improve-ctr' ? $topic.' : obligations, points de vigilance et deroule terrain' : null,
            'meta_description' => $mode === 'improve-ctr' ? 'Un guide pratique Amiantix pour comprendre les obligations amiante, preparer l intervention et limiter les risques terrain.' : null,
            'h1' => null,
            'sections' => [
                'Ajouter un passage qui explique le contexte reglementaire et les responsabilites de chaque acteur.',
                'Preciser le deroule terrain: repérage, mode operatoire, confinement, tracabilite et verification finale.',
                'Renforcer les exemples concrets pour copropriete, locaux occupes, maintenance et travaux planifies.',
            ],
            'faq' => [
                [
                    'question' => 'Quel est le premier point a verifier avant intervention ?',
                    'answer' => 'Verifier le contexte du site, les informations disponibles sur l amiante et le bon niveau de preparation documentaire avant toute action.',
                ],
            ],
            'internal_links' => $page->internal_links_json ?? [],
            'rationale' => [
                'La reecriture renforce la credibilite metier, la clarte terrain et la valeur decisionnelle du contenu.',
            ],
        ];
    }
}
