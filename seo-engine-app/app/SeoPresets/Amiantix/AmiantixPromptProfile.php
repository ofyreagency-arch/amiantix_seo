<?php

declare(strict_types=1);

namespace App\SeoPresets\Amiantix;

use Illuminate\Support\Str;
use Ofyre\SeoEngine\Contracts\PromptProfileProvider;

final class AmiantixPromptProfile implements PromptProfileProvider
{
    public function generationPrompt(string $keyword, string $cluster, array $blueprint, array $editorialSections, array $expectedSignals): string
    {
        return $this->generationCorePrompt($keyword, $cluster, $blueprint, $editorialSections, $expectedSignals)."\n"
            ."Puis compléter aussi faq et schema si possible.";
    }

    public function generationCorePrompt(string $keyword, string $cluster, array $blueprint, array $editorialSections, array $expectedSignals): string
    {
        return "Tu rediges un article expert Amiantix sur le risque amiante.\n".
            'Mot-cle principal: '.$keyword."\n".
            'Cluster: '.$cluster."\n".
            'Sujet: '.$blueprint['topic']."\n".
            'Famille editoriale: '.($blueprint['family'] ?? 'default')."\n".
            'Archetype editorial: '.($blueprint['archetype'] ?? 'decision_guide')."\n".
            'Angle hero: '.$blueprint['hero_angle']."\n".
            'Plan de composition: '.json_encode($blueprint['composition'] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n".
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
            "- inclure des cas pratiques, des erreurs frequentes et des points de vigilance pour le donneur d ordre\n".
            "- conclusion orientee action, verification et preparation documentaire, pas marketing\n".
            "- 1400 mots minimum\n".
            'Retourner uniquement un JSON avec: title, meta_description, h1, content.';
    }

    public function generationFaqPrompt(string $keyword, string $cluster, array $blueprint, string $title, string $metaDescription, string $h1, string $content): string
    {
        return "Tu completes uniquement la FAQ d un article expert Amiantix sur le risque amiante.\n".
            'Mot-cle principal: '.$keyword."\n".
            'Cluster: '.$cluster."\n".
            'Sujet: '.$blueprint['topic']."\n".
            'Titre: '.$title."\n".
            'Meta description: '.$metaDescription."\n".
            'H1: '.$h1."\n".
            'FAQ blueprint a adapter: '.json_encode($blueprint['faq'] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n".
            "Contraintes FAQ:\n".
            "- 5 questions minimum\n".
            "- questions terrain et métier, pas SEO\n".
            "- réponses courtes, concrètes, orientées décision et coordination\n".
            "- rester cohérent avec le contenu principal ci-dessous\n".
            "- ne pas répéter mot pour mot le blueprint\n".
            "Contenu principal:\n".$content."\n".
            'Retourner uniquement un JSON avec: faq.';
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
        $weakSections = is_array($page->rewrite_weak_sections ?? null) ? $page->rewrite_weak_sections : [];
        $weakSectionProfiles = is_array($page->rewrite_weak_section_profiles ?? null) ? $page->rewrite_weak_section_profiles : [];
        $weakSectionInstructions = is_array($page->rewrite_weak_section_instructions ?? null) ? $page->rewrite_weak_section_instructions : [];
        $rewriteTargetPlan = is_array($page->rewrite_target_plan ?? null) ? $page->rewrite_target_plan : [];
        $modeInstruction = match ($mode) {
            'add-table-only' => 'Tu dois surtout ajouter ou renforcer un vrai tableau HTML utile, sans rewriter tout l article.',
            'add-heading-depth-only' => 'Tu dois surtout ajouter des H2/H3 manquants et mieux distribuer la structure, sans changer inutilement le fond.',
            'add-faq-only' => 'Tu dois surtout renforcer ou completer la FAQ utile, sans toucher au reste de l article si ce n est pas necessaire.',
            'add-internal-links-only' => 'Tu dois surtout proposer un meilleur maillage interne utile a l indexation et a la navigation, sans reecriture large.',
            default => 'Tu peux enrichir localement les zones faibles, mais sans compresser le contenu deja fort.',
        };

        return "Reecris un article Amiantix sur le risque amiante.\n".
            'Mode: '.$mode."\n".
            'Mot-cle: '.($page->keyword ?? '')."\n".
            'Cluster: '.($page->cluster ?? 'amiante')."\n".
            'Sections faibles a renforcer en priorite: '.json_encode(array_values($weakSections), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n".
            'Raisons de faiblesse par section: '.json_encode(array_values($weakSectionProfiles), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n".
            'Consignes de patch par section faible: '.json_encode($weakSectionInstructions, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n".
            'Plan de patch cible par section: '.json_encode(array_values($rewriteTargetPlan), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n".
            "Objectif: un contenu concret, expert, credibilise par le terrain, les obligations et les preuves documentaires.\n".
            $modeInstruction."\n".
            "Interdictions: ne pas parler de SEO, d IA, de Google, de premium content ou de structure de page.\n".
            "Exigences:\n".
            "- si le contenu de depart est deja riche, ne jamais le compresser en mini resume SEO\n".
            "- conserver les H2/H3 solides, les tableaux, les checklists, les workflows, les scenarios terrain, les preuves et les transitions utiles quand ils existent deja\n".
            "- reecrire en preservant l angle, la profondeur metier et la progression narrative avant d ajouter de nouveaux blocs\n".
            "- si une partie est faible, la renforcer localement section par section au lieu de raccourcir tout l article\n".
            "- comprendre pourquoi chaque section faible est ciblee: manque de longueur, manque de structure utile, ou les deux\n".
            "- suivre les consignes de patch associees a chaque section faible avant d ajouter des blocs hors section\n".
            "- utiliser le plan de patch cible pour comprendre la phase narrative, l intention de correction et le mode de remplacement attendu avant de proposer un patch\n".
            "- situations reelles de site occupe, maintenance, travaux ou copropriete quand c est pertinent\n".
            "- coordination entre donneur d ordre, diagnostiqueur, entreprise et occupants\n".
            "- references aux documents, validations, hypotheses de travaux et points de controle\n".
            "- ajouter de l air visuel avec listes, checklists et sections courtes quand le texte est trop compact\n".
            "- style simple, humain, professionnel, sans prose marketing\n".
            "- proposed_content doit etre un vrai contenu long et publiable, pas une passe de synthese ni une mini note de travail\n".
            "- maintenir un niveau de detail compatible avec un article expert deja bien structure\n".
            "Retourne un JSON avec title, meta_description, h1, proposed_content, sections, faq, internal_links, rationale et schema.\n".
            "Contenu actuel:\n".($page->content ?? '');
    }

    public function fallbackRewrite(object $page, string $mode): array
    {
        $topic = Str::headline((string) ($page->keyword ?? 'Risque amiante'));

        $sections = match ($mode) {
            'add-table-only' => [
                'Ajouter un tableau HTML qui relie situation reelle, risque, consequence, mesure, responsable et preuve documentaire a conserver.',
                'Nommer clairement le tableau pour qu il soit visible comme bloc de synthese exploitable.',
            ],
            'add-heading-depth-only' => [
                'Ajouter plusieurs H2/H3 couvrant obligations, mise a jour, documents a conserver, points de vigilance et cas pratiques.',
                'Decouper les parties trop compactes en sous-sections claires et respirables.',
            ],
            'add-faq-only' => [
                'Ajouter une FAQ utile qui repond aux questions terrain les plus frequentes sans rewriter tout l article.',
            ],
            'add-internal-links-only' => [
                'Ajouter un maillage interne utile vers les pages piliers, les obligations, les diagnostics et les guides complementaires.',
            ],
            default => [
                'Ajouter un cadrage clair sur les hypotheses de travaux, le perimetre du repérage et les documents attendus avant intervention.',
                'Renforcer la partie terrain avec des cas de site occupe, de copropriete, de maintenance ou de coordination entre acteurs.',
                'Ajouter un tableau qui relie risque, situation reelle, mesure, responsable et preuve documentaire a conserver.',
            ],
        };

        $faq = $mode === 'add-faq-only'
            ? [
                [
                    'question' => 'Quand faut il mettre a jour le DTA amiante en copropriete ?',
                    'answer' => 'A chaque nouvelle information utile, apres travaux ou quand un repérage modifie les donnees deja diffusees.',
                ],
                [
                    'question' => 'Qui doit pouvoir consulter le DTA ?',
                    'answer' => 'Le syndic, les entreprises intervenantes, les occupants concernes et plus largement les acteurs qui doivent connaitre le risque avant intervention.',
                ],
            ]
            : [
                [
                    'question' => 'Quel point verifier en premier avant intervention ?',
                    'answer' => 'Verifier la coherence entre le perimetre du repérage, les hypotheses de travaux, les documents transmis et la realite du site a traiter.',
                ],
            ];

        return [
            'mode' => $mode,
            'title' => $mode === 'improve-ctr' ? $topic.' : obligations, coordination chantier et points de vigilance terrain' : null,
            'meta_description' => $mode === 'improve-ctr' ? 'Guide Amiantix pour comprendre les obligations amiante, le repérage, la coordination chantier et les preuves a conserver.' : null,
            'h1' => null,
            'sections' => $sections,
            'faq' => $faq,
            'internal_links' => $page->internal_links_json ?? [],
            'rationale' => [
                'La reecriture renforce la credibilite metier, la preparation documentaire et la valeur decisionnelle du contenu.',
            ],
        ];
    }
}
