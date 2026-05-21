<?php

declare(strict_types=1);

namespace App\SeoPresets\Amiantix;

use Illuminate\Support\Str;
use Ofyre\SeoEngine\Contracts\PromptProfileProvider;

final class AmiantixPromptProfile implements PromptProfileProvider
{
    public function generationPrompt(string $keyword, string $cluster, array $blueprint, array $editorialSections, array $expectedSignals): string
    {
        return "Tu rediges un article expert Amiantix sur le risque amiante.\n".
            'Mot-cle principal: '.$keyword."\n".
            'Cluster: '.$cluster."\n".
            'Sujet: '.$blueprint['topic']."\n".
            'Angle hero: '.$blueprint['hero_angle']."\n".
            'Sections editoriales attendues: '.json_encode($editorialSections, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n".
            'Signaux metier obligatoires: '.json_encode($expectedSignals, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n".
            'Risques terrain a couvrir: '.json_encode($blueprint['risk_rows'] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n".
            'Obligations et responsabilites a couvrir: '.json_encode($blueprint['obligations'] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n".
            'Cas pratiques a couvrir: '.json_encode($blueprint['cases'] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n".
            'Erreurs frequentes a couvrir: '.json_encode($blueprint['mistakes'] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n".
            'Points de controle a couvrir: '.json_encode($blueprint['inspection_focus'] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n".
            'Pieces et preuves a citer: '.json_encode($blueprint['evidence_examples'] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n".
            "Contraintes editoriales:\n".
            "- ton expert, net, rassurant, operationnel et non marketing\n".
            "- ecrire pour donneurs d ordre, syndics, maitres d oeuvre, entreprises, collectivites et responsables techniques\n".
            "- ouvrir sur une situation terrain ou un moment de chantier reconnaissable\n".
            "- integrer obligations, vigilance terrain, documentation, coordination chantier et reduction du risque\n".
            "- faire ressortir repérage, DTA, SS3, SS4, confinement, empoussièrement, phasage, coordination SPS, MOA/MOE et arbitrages documentaires quand c est pertinent\n".
            "- bannir les phrases vagues, les formulations premium, le meta discours, les banalites SEO et le blabla commercial\n".
            "- imposer un tableau riche avec colonnes risque / situation reelle / consequence / mesures / responsable / priorite\n".
            "- structurer avec des H2/H3 nombreux, des listes courtes, des checklists, des points de vigilance et des blocs visuellement respirables\n".
            "- couvrir aussi les contextes copropriete, ERP, site occupe, maintenance contrainte et changement d hypothese de travaux\n".
            "- inclure des cas pratiques, des erreurs frequentes, des points de vigilance pour le donneur d ordre et une FAQ terrain de 5 questions minimum\n".
            "- conclusion orientee action, verification et preparation documentaire, pas marketing\n".
            "- 1400 mots minimum\n".
            'Retourner uniquement un JSON avec: title, meta_description, h1, content, faq, schema.';
    }

    public function improvementPrompt(object $page, array $blueprint, array $audit, array $editorialSections, array $expectedSignals): string
    {
        return "Ameliore cet article Amiantix avec plus de profondeur metier, documentaire et terrain.\n".
            'Mot-cle: '.($page->keyword ?? '')."\n".
            'Sujet: '.$blueprint['topic']."\n".
            'Problemes detectes: '.json_encode($audit['issues'] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n".
            'Recommandations: '.json_encode($audit['recommendations'] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n".
            'Sections attendues: '.json_encode($editorialSections, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n".
            'Signaux obligatoires: '.json_encode($expectedSignals, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n".
            'Cas pratiques a faire sentir: '.json_encode($blueprint['cases'] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n".
            'Preuves a faire citer: '.json_encode($blueprint['evidence_examples'] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n".
            "Interdictions: ne pas parler de SEO, de Google, d IA, de structure editoriale ou du contenu lui-meme.\n".
            "Style attendu: concret, reglementaire quand il faut, oriente coordination, documents, responsabilites, arbitrages chantier et blocages evitables.\n".
            "Enrichir avec plus de H2/H3, de checklists, de points de vigilance, de cas terrain et de contextes ERP / copropriete / site occupe si le contenu reste trop compact.\n".
            "Retourne uniquement un JSON avec title, meta_description, h1, content, faq et schema.\n".
            "Contenu actuel:\n".($page->content ?? '');
    }

    public function rewritePrompt(object $page, string $mode): string
    {
        return "Reecris un article Amiantix sur le risque amiante.\n".
            'Mode: '.$mode."\n".
            'Mot-cle: '.($page->keyword ?? '')."\n".
            'Cluster: '.($page->cluster ?? 'amiante')."\n".
            "Objectif: un contenu concret, expert, credibilise par le terrain, les obligations et les preuves documentaires.\n".
            "Interdictions: ne pas parler de SEO, d IA, de Google, de premium content ou de structure de page.\n".
            "Exigences:\n".
            "- situations reelles de site occupe, maintenance, travaux ou copropriete quand c est pertinent\n".
            "- coordination entre donneur d ordre, diagnostiqueur, entreprise et occupants\n".
            "- references aux documents, validations, hypotheses de travaux et points de controle\n".
            "- ajouter de l air visuel avec listes, checklists et sections courtes quand le texte est trop compact\n".
            "- style simple, humain, professionnel, sans prose marketing\n".
            "Retourne un JSON avec title, meta_description, h1, sections, faq, internal_links et rationale.\n".
            "Contenu actuel:\n".($page->content ?? '');
    }

    public function fallbackRewrite(object $page, string $mode): array
    {
        $topic = Str::headline((string) ($page->keyword ?? 'Risque amiante'));

        return [
            'mode' => $mode,
            'title' => $mode === 'improve-ctr' ? $topic.' : obligations, coordination chantier et points de vigilance terrain' : null,
            'meta_description' => $mode === 'improve-ctr' ? 'Guide Amiantix pour comprendre les obligations amiante, le repérage, la coordination chantier et les preuves a conserver.' : null,
            'h1' => null,
            'sections' => [
                'Ajouter un cadrage clair sur les hypotheses de travaux, le perimetre du repérage et les documents attendus avant intervention.',
                'Renforcer la partie terrain avec des cas de site occupe, de copropriete, de maintenance ou de coordination entre acteurs.',
                'Ajouter un tableau qui relie risque, situation reelle, mesure, responsable et preuve documentaire a conserver.',
            ],
            'faq' => [
                [
                    'question' => 'Quel point verifier en premier avant intervention ?',
                    'answer' => 'Verifier la coherence entre le perimetre du repérage, les hypotheses de travaux, les documents transmis et la realite du site a traiter.',
                ],
            ],
            'internal_links' => $page->internal_links_json ?? [],
            'rationale' => [
                'La reecriture renforce la credibilite metier, la preparation documentaire et la valeur decisionnelle du contenu.',
            ],
        ];
    }
}
