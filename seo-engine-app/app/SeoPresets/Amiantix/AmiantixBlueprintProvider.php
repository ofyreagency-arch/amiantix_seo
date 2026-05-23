<?php

declare(strict_types=1);

namespace App\SeoPresets\Amiantix;

use Illuminate\Support\Str;
use Ofyre\SeoEngine\Contracts\NicheBlueprintProvider;

final class AmiantixBlueprintProvider implements NicheBlueprintProvider
{
    public function resolve(string $keyword, ?string $cluster = null): array
    {
        $topic = $this->topicFromKeyword($keyword);
        $resolvedCluster = $cluster ?: $this->clusterFromKeyword($topic);
        $family = $this->familyFromKeyword($topic, $resolvedCluster);
        $archetype = $this->archetypeForFamily($family);
        $composition = $this->compositionFor($family, $archetype);

        return [
            'topic' => $topic,
            'cluster' => $resolvedCluster,
            'family' => $family,
            'archetype' => $archetype,
            'composition' => $composition,
            'hero_angle' => 'coordination terrain et maitrise documentaire autour de '.$topic,
            'risk_terms' => [
                'amiante',
                'diagnostic amiante',
                'repérage avant travaux',
                'DTA',
                'ss3',
                'ss4',
                'confinement',
                'empoussièrement',
                'mesures conservatoires',
                'coordination chantier',
                'donneur d ordre',
                $topic,
            ],
            'editorial_sections' => $this->editorialSectionsForFamily($composition),
            'support_sections' => $this->supportSectionsForFamily($composition),
            'risk_rows' => [
                ['Exposition a l amiante', 'Travaux dans des zones mal caracterisees ou hypotheses de travaux incomplètes', 'Repérage adapte, cadrage des zones, lecture des diagnostics et validation du scenario d intervention'],
                ['Empoussierement et dispersion', 'Percement, decoupe, demolition ou maintenance sans confinement adapte', 'Mode operatoire, confinement, captation, verification des acces et suivi des mesures'],
                ['Defaut documentaire', 'DTA absent, repérage incomplet, transmission tardive des pieces ou plans contradictoires', 'Centraliser les pieces, dater les versions, tracer les diffusions et verrouiller les hypothèses'],
                ['Desorganisation chantier', 'Coordination insuffisante entre MOA, diagnostiqueur, entreprise et occupants', 'Planning clarifie, interlocuteurs identifies, jalons de validation et gestion des zones sensibles'],
                ['Blocage de site ou surcout', 'Decouverte tardive d amiante, zone non preparée ou arbitrage retardé', 'Anticipation, scenario de replis, budget documentaire et verification des dependances chantier'],
            ],
            'obligations' => $this->obligationsForFamily($family),
            'cases' => $this->casesForFamily($family, $topic),
            'mistakes' => $this->mistakesForFamily($family),
            'inspection_focus' => $this->inspectionFocusForFamily($family),
            'evidence_examples' => $this->evidenceExamplesForFamily($family),
            'daily_constraints' => $this->dailyConstraintsForFamily($family),
            'work_units' => $this->workUnitsForFamily($family),
            'faq' => $this->faqForFamily($family, $topic),
        ];
    }

    public function expectedEditorialSections(array $profile): array
    {
        return $profile['editorial_sections'] ?? [];
    }

    public function expectedSignals(array $profile): array
    {
        return array_values(array_unique(array_merge(
            ['amiante', 'diagnostic amiante', 'repérage', 'donneur d ordre', 'coordination chantier'],
            $profile['risk_terms'] ?? [],
            $profile['work_units'] ?? []
        )));
    }

    /**
     * @return array<int, string>
     */
    private function editorialSectionsForFamily(array $composition): array
    {
        return array_values(array_unique(array_filter([
            $composition['opening_block'] ?? null,
            ...($composition['required_blocks'] ?? []),
        ])));
    }

    /**
     * @return array<int, string>
     */
    private function supportSectionsForFamily(array $composition): array
    {
        return array_values(array_unique($composition['optional_blocks'] ?? []));
    }

    private function archetypeForFamily(string $family): string
    {
        return match ($family) {
            'appel_offre' => 'consultation_checklist',
            'copropriete' => 'terrain_casebook',
            'reperage' => 'documentary_audit',
            default => 'decision_guide',
        };
    }

    /**
     * @return array<string,mixed>
     */
    private function compositionFor(string $family, string $archetype): array
    {
        return match ($family) {
            'appel_offre' => [
                'archetype' => $archetype,
                'opening_block' => 'Contexte et obligations',
                'required_blocks' => [
                    'Documents et preuves a conserver',
                    'Points de vigilance pour le donneur d ordre',
                    'Couts, delais et arbitrages chantier',
                    'Matrice de controle documentaire et terrain',
                    'Ressources et pages utiles a croiser',
                    'Passer du constat a une intervention maitrisée',
                ],
                'optional_blocks' => [
                    'Repérage, SS3, SS4 et responsabilites de coordination',
                    'Processus d intervention et coordination',
                    'Checklist operationnelle avant intervention',
                    'Cas pratiques terrain a cadrer',
                    'Erreurs frequentes et blocages evitables',
                    'Copropriete, ERP et site occupe : ce qui change vraiment',
                    'Questions terrain qui reviennent souvent',
                    'Routine documentaire et trace utile',
                ],
                'max_optional_blocks' => 4,
                'disallowed_optional_terms' => ['copropriete', 'erp', 'site occupe', 'occupation'],
                'coverage_markers' => [
                    'Routine documentaire et trace utile' => [
                        'traces de diffusion',
                        'clarifications',
                        'hypotheses de travaux',
                    ],
                ],
                'narrative_flow' => ['opening', 'legal', 'workflow', 'checklist', 'cases', 'friction', 'arbitrage', 'proof', 'control', 'faq', 'resources', 'close'],
                'narrative_slots' => [
                    'opening' => ['Contexte et obligations'],
                    'legal' => ['Repérage, SS3, SS4 et responsabilites de coordination'],
                    'workflow' => ['Processus d intervention et coordination'],
                    'checklist' => ['Checklist operationnelle avant intervention'],
                    'cases' => ['Cas pratiques terrain a cadrer'],
                    'friction' => ['Erreurs frequentes et blocages evitables'],
                    'arbitrage' => ['Points de vigilance pour le donneur d ordre', 'Couts, delais et arbitrages chantier'],
                    'proof' => ['Documents et preuves a conserver', 'Routine documentaire et trace utile'],
                    'control' => ['Matrice de controle documentaire et terrain'],
                    'faq' => ['Questions terrain qui reviennent souvent'],
                    'resources' => ['Ressources et pages utiles a croiser'],
                    'close' => ['Passer du constat a une intervention maitrisée'],
                ],
                'narrative_phase_bridges' => [
                    'legal' => 'Avant de parler exécution, il faut clarifier le cadre réglementaire et les responsabilités de coordination qui tiennent l ensemble du dossier.',
                    'workflow' => 'Une fois ce cadre posé, la page doit montrer comment la préparation documentaire se transforme en déroulé d intervention lisible.',
                    'checklist' => 'À partir de là, le lecteur a besoin d un passage opérationnel très concret pour vérifier ce qui doit être figé avant lancement.',
                    'cases' => 'Ce cadrage devient beaucoup plus parlant lorsqu on le relie à des situations de consultation ou de chantier qui dérapent réellement.',
                    'friction' => 'C est aussi là qu apparaissent les erreurs récurrentes qui font glisser un bon dossier vers un blocage évitable.',
                    'arbitrage' => [
                        'default' => 'Après ces points de friction, l article doit revenir sur les arbitrages qui incombent au donneur d ordre et aux décideurs du dossier.',
                        'by_heading' => [
                            'Couts, delais et arbitrages chantier' => 'Avant de parler budget et calendrier, il faut remettre les décideurs face aux arbitrages concrets que le dossier documentaire leur impose.',
                        ],
                    ],
                    'proof' => [
                        'default' => 'Ces arbitrages ne tiennent que s ils sont reliés à des pièces, versions et traces réellement conservées.',
                        'by_heading' => [
                            'Routine documentaire et trace utile' => 'Avant de multiplier les annexes, la page peut montrer comment la routine documentaire maintient le dossier exploitable quand la consultation évolue.',
                        ],
                    ],
                    'control' => 'Une fois les preuves posées, il devient plus simple de montrer comment les contrôles documentaires et terrain s enchaînent.',
                    'faq' => [
                        'default' => 'À ce stade, la FAQ peut traiter les hésitations qui restent sans casser le fil principal de l article.',
                        'by_from_phase' => [
                            'control' => 'Après ce passage de contrôle, quelques questions terrain suffisent souvent à lever les derniers doutes sans relancer toute la consultation.',
                        ],
                        'by_context_signal' => [
                            [
                                'from_phase' => 'control',
                                'heading' => 'Questions terrain qui reviennent souvent',
                                'terms' => ['dce', 'consultation'],
                                'match' => 'any',
                                'text' => 'Après ce contrôle du DCE et de la consultation, quelques questions terrain suffisent souvent à verrouiller les derniers doutes sans réouvrir tout le dossier.',
                            ],
                        ],
                    ],
                    'resources' => [
                        'default' => 'Le lecteur peut ensuite croiser ce cadre avec les ressources les plus proches de son besoin immédiat.',
                        'by_from_phase' => [
                            'faq' => 'Une fois les dernières hésitations absorbées, quelques ressources bien ciblées permettent d approfondir sans disperser la décision.',
                        ],
                    ],
                    'close' => 'La clôture doit alors ramener l ensemble vers une décision ou une action maîtrisée, sans rouvrir de digressions inutiles.',
                ],
                'table_mode' => 'control_matrix',
                'faq_mode' => 'consultation_decision',
            ],
            'copropriete' => [
                'archetype' => $archetype,
                'opening_block' => 'Contexte et obligations',
                'required_blocks' => [
                    'Situations a risque sur le terrain',
                    'Checklist operationnelle avant intervention',
                    'Cas pratiques terrain a cadrer',
                    'Documents et preuves a conserver',
                    'Copropriete, ERP et site occupe : ce qui change vraiment',
                    'Questions terrain qui reviennent souvent',
                    'Passer du constat a une intervention maitrisée',
                ],
                'optional_blocks' => [
                    'Repérage, SS3, SS4 et responsabilites de coordination',
                    'Tableau de priorisation des risques',
                    'Matrice de controle documentaire et terrain',
                    'Points de vigilance pour le donneur d ordre',
                    'Couts, delais et arbitrages chantier',
                    'Ressources et pages utiles a croiser',
                ],
                'max_optional_blocks' => 4,
                'narrative_flow' => ['opening', 'legal', 'terrain', 'checklist', 'cases', 'proof', 'arbitrage', 'control', 'faq', 'resources', 'close'],
                'narrative_slots' => [
                    'opening' => ['Contexte et obligations'],
                    'legal' => ['Repérage, SS3, SS4 et responsabilites de coordination'],
                    'terrain' => ['Situations a risque sur le terrain', 'Tableau de priorisation des risques'],
                    'checklist' => ['Checklist operationnelle avant intervention'],
                    'cases' => ['Cas pratiques terrain a cadrer', 'Copropriete, ERP et site occupe : ce qui change vraiment'],
                    'proof' => ['Documents et preuves a conserver'],
                    'arbitrage' => ['Points de vigilance pour le donneur d ordre', 'Couts, delais et arbitrages chantier'],
                    'control' => ['Matrice de controle documentaire et terrain'],
                    'faq' => ['Questions terrain qui reviennent souvent'],
                    'resources' => ['Ressources et pages utiles a croiser'],
                    'close' => ['Passer du constat a une intervention maitrisée'],
                ],
                'narrative_phase_bridges' => [
                    'legal' => 'Avant de descendre sur le terrain, il faut recadrer les obligations et les limites documentaires qui conditionnent la suite.',
                    'terrain' => 'Ce cadre posé, la page peut alors montrer où le risque se matérialise concrètement dans les zones et usages du site.',
                    'checklist' => 'Une fois ces situations identifiées, le lecteur attend naturellement une vérification simple de ce qui doit être sécurisé avant intervention.',
                    'cases' => 'Ces vérifications prennent encore plus de sens lorsqu on les confronte à des cas de copropriété ou de site occupé vécus sur le terrain.',
                    'proof' => 'Pour que ces cas restent défendables, il faut ensuite revenir aux pièces et preuves à conserver.',
                    'arbitrage' => [
                        'default' => 'Ces preuves servent justement à étayer les arbitrages pris lorsque le contexte d occupation complique le dossier.',
                        'by_heading' => [
                            'Couts, delais et arbitrages chantier' => 'À partir de là, le lecteur peut mesurer comment l occupation du site rejaillit sur les délais, les interfaces et les arbitrages de chantier.',
                        ],
                    ],
                    'control' => 'On peut alors refermer la boucle par une logique de contrôle, à la fois documentaire et terrain.',
                    'faq' => 'La FAQ vient ensuite absorber les hésitations qui restent sans réouvrir le cœur du raisonnement.',
                    'resources' => 'Enfin, quelques ressources connexes aident à prolonger la lecture sans la disperser.',
                    'close' => 'La conclusion peut alors ramener le lecteur vers une intervention maîtrisée et défendable.',
                ],
                'table_mode' => 'risk_priority',
                'faq_mode' => 'terrain_cases',
            ],
            'reperage' => [
                'archetype' => $archetype,
                'opening_block' => 'Repérage, SS3, SS4 et responsabilites de coordination',
                'required_blocks' => [
                    'Documents et preuves a conserver',
                    'Points de vigilance pour le donneur d ordre',
                    'Tableau de priorisation des risques',
                    'Questions terrain qui reviennent souvent',
                    'Ressources et pages utiles a croiser',
                    'Passer du constat a une intervention maitrisée',
                ],
                'optional_blocks' => [
                    'Contexte et obligations',
                    'Processus d intervention et coordination',
                    'Erreurs frequentes et blocages evitables',
                    'Checklist operationnelle avant intervention',
                    'Matrice de controle documentaire et terrain',
                    'Couts, delais et arbitrages chantier',
                ],
                'max_optional_blocks' => 4,
                'narrative_flow' => ['opening', 'legal', 'workflow', 'friction', 'proof', 'arbitrage', 'control', 'faq', 'resources', 'close'],
                'narrative_slots' => [
                    'opening' => ['Contexte et obligations'],
                    'legal' => ['Repérage, SS3, SS4 et responsabilites de coordination'],
                    'workflow' => ['Processus d intervention et coordination', 'Checklist operationnelle avant intervention'],
                    'friction' => ['Erreurs frequentes et blocages evitables', 'Tableau de priorisation des risques'],
                    'proof' => ['Documents et preuves a conserver'],
                    'arbitrage' => ['Points de vigilance pour le donneur d ordre', 'Couts, delais et arbitrages chantier'],
                    'control' => ['Matrice de controle documentaire et terrain'],
                    'faq' => ['Questions terrain qui reviennent souvent'],
                    'resources' => ['Ressources et pages utiles a croiser'],
                    'close' => ['Passer du constat a une intervention maitrisée'],
                ],
                'narrative_phase_bridges' => [
                    'legal' => 'Le repérage n a de valeur que si le cadre réglementaire et les hypothèses de travaux sont clarifiés dès le départ.',
                    'workflow' => 'À partir de là, le lecteur doit voir comment ce cadre se traduit en séquence opérationnelle et en vérifications concrètes.',
                    'friction' => 'C est justement dans cette séquence que se nichent les erreurs documentaires ou de périmètre les plus coûteuses.',
                    'proof' => [
                        'default' => 'Pour éviter ces glissements, la page doit ensuite revenir sur les pièces et preuves qui verrouillent réellement le dossier.',
                        'by_heading' => [
                            'Documents et preuves a conserver' => 'Pour éviter ces glissements, la page doit revenir d abord aux pièces qui verrouillent réellement le périmètre et les hypothèses de travaux.',
                        ],
                    ],
                    'arbitrage' => [
                        'default' => 'Une fois les preuves posées, les arbitrages du donneur d ordre et des acteurs techniques deviennent plus lisibles.',
                        'by_heading' => [
                            'Couts, delais et arbitrages chantier' => 'Une fois le dossier verrouillé, l article peut ouvrir plus directement sur les arbitrages de délai, de coût et de coordination qui en découlent.',
                        ],
                    ],
                    'control' => 'La logique de contrôle peut alors s exprimer sans casser le fil principal du repérage.',
                    'faq' => 'La FAQ vient ensuite absorber les questions pratiques qui restent en suspens.',
                    'resources' => 'Quelques ressources bien choisies prolongent enfin la lecture sans diluer l angle.',
                    'close' => 'La conclusion doit alors ramener l article vers une décision documentée et un périmètre d intervention maîtrisé.',
                ],
                'table_mode' => 'document_controls',
                'faq_mode' => 'documentary_precision',
            ],
            default => [
                'archetype' => $archetype,
                'opening_block' => 'Contexte et obligations',
                'required_blocks' => [
                    'Tableau de priorisation des risques',
                    'Situations a risque sur le terrain',
                    'Processus d intervention et coordination',
                    'Documents et preuves a conserver',
                    'Points de vigilance pour le donneur d ordre',
                    'Couts, delais et arbitrages chantier',
                    'Questions terrain qui reviennent souvent',
                    'Passer du constat a une intervention maitrisée',
                ],
                'optional_blocks' => [
                    'Repérage, SS3, SS4 et responsabilites de coordination',
                    'Checklist operationnelle avant intervention',
                    'Erreurs frequentes et blocages evitables',
                    'Ressources et pages utiles a croiser',
                    'Matrice de controle documentaire et terrain',
                    'Site occupe, acces sensibles et zones grises',
                    'Routine documentaire et trace utile',
                ],
                'max_optional_blocks' => 4,
                'narrative_flow' => ['opening', 'legal', 'terrain', 'workflow', 'friction', 'proof', 'arbitrage', 'control', 'faq', 'resources', 'close'],
                'narrative_slots' => [
                    'opening' => ['Contexte et obligations'],
                    'legal' => ['Repérage, SS3, SS4 et responsabilites de coordination'],
                    'terrain' => ['Tableau de priorisation des risques', 'Situations a risque sur le terrain'],
                    'workflow' => ['Processus d intervention et coordination', 'Checklist operationnelle avant intervention'],
                    'friction' => ['Erreurs frequentes et blocages evitables', 'Site occupe, acces sensibles et zones grises'],
                    'proof' => ['Documents et preuves a conserver', 'Routine documentaire et trace utile'],
                    'arbitrage' => ['Points de vigilance pour le donneur d ordre', 'Couts, delais et arbitrages chantier'],
                    'control' => ['Matrice de controle documentaire et terrain'],
                    'faq' => ['Questions terrain qui reviennent souvent'],
                    'resources' => ['Ressources et pages utiles a croiser'],
                    'close' => ['Passer du constat a une intervention maitrisée'],
                ],
                'narrative_phase_bridges' => [
                    'legal' => 'Avant d empiler les points techniques, il faut recadrer les responsabilités et le socle réglementaire du sujet.',
                    'terrain' => 'Ce socle posé, l article peut montrer où le risque se manifeste vraiment sur le terrain.',
                    'workflow' => 'Le lecteur attend ensuite un passage vers la manière concrète de préparer et conduire l intervention.',
                    'friction' => 'C est dans ce passage que les erreurs, blocages et zones grises deviennent les plus utiles à nommer.',
                    'proof' => [
                        'default' => 'Une fois ces points de friction visibles, il faut revenir à ce qui rend l ensemble défendable dans les pièces et les traces.',
                        'by_heading' => [
                            'Routine documentaire et trace utile' => 'Une fois les points de friction nommés, la page peut montrer comment une routine documentaire simple évite que le dossier se dégrade entre deux arbitrages.',
                        ],
                    ],
                    'arbitrage' => [
                        'default' => 'Ces preuves soutiennent ensuite les arbitrages économiques, techniques et organisationnels.',
                        'by_heading' => [
                            'Couts, delais et arbitrages chantier' => 'Ces preuves servent ensuite de base pour parler plus franchement des délais, des coûts et des arbitrages qui suivent sur le chantier.',
                        ],
                    ],
                    'control' => 'La logique de contrôle peut alors fermer le raisonnement sans repartir dans un simple catalogue de points.',
                    'faq' => [
                        'default' => 'La FAQ vient ensuite absorber les hésitations restantes sans casser la progression.',
                        'by_from_phase' => [
                            'control' => 'Après la boucle de contrôle, la FAQ peut traiter les dernières hésitations sans faire retomber l article dans une simple liste de points.',
                        ],
                        'by_context_signal' => [
                            [
                                'from_phase' => 'control',
                                'heading' => 'Questions terrain qui reviennent souvent',
                                'terms' => ['controle documentaire', 'terrain'],
                                'match' => 'all',
                                'text' => 'Après ce contrôle documentaire et terrain, la FAQ peut absorber les derniers doutes sans casser la progression principale.',
                            ],
                        ],
                    ],
                    'resources' => [
                        'default' => [
                            'default' => 'Quelques ressources connexes permettent enfin d élargir la lecture avec méthode.',
                            'by_length_signal' => [
                                'expanded' => 'Le passage précédent allant à l essentiel, quelques ressources bien choisies permettent d ouvrir la suite sans casser le rythme.',
                                'compact' => 'Après cette séquence déjà dense, quelques ressources ciblées valent mieux qu une nouvelle couche de détails.',
                            ],
                        ],
                    ],
                    'close' => 'La conclusion doit ramener l ensemble vers une action maîtrisée plutôt que vers une nouvelle pile d informations.',
                ],
                'table_mode' => 'risk_priority',
                'faq_mode' => 'decision_support',
            ],
        };
    }

    /**
     * @return array<int, string>
     */
    private function obligationsForFamily(string $family): array
    {
        return match ($family) {
            'appel_offre' => [
                'Verifier que l appel d offre decrit bien les zones, hypotheses de travaux et contraintes d acces reellement visees.',
                'Exiger des pieces de consultation coherentes entre DCE, diagnostics, plans, variantes et hypotheses de phasage.',
                'Rendre explicites les jalons de clarification documentaire avant remise, attribution et ordre de service.',
                'Tracer ce qui releve du donneur d ordre, de la MOE, du coordonnateur SPS et des entreprises consultées.',
            ],
            default => [
                'Identifier qui commande le repérage, sur quel perimetre et avec quelles hypotheses de travaux ou de maintenance.',
                'Tracer les pieces utiles: DTA, repérage avant travaux, plans, comptes rendus, restrictions d acces et validations de diffusion.',
                'Verifier la coherence entre diagnostic, mode operatoire, entreprise engagee et contexte d occupation du site.',
                'Rendre visible ce qui releve de la preparation documentaire, de la coordination terrain et de la preuve finale conservee.',
            ],
        };
    }

    /**
     * @return array<int, string>
     */
    private function casesForFamily(string $family, string $topic): array
    {
        return match ($family) {
            'appel_offre' => [
                'Sur le sujet '.$topic.', un appel d offre part avec un DCE incomplet, des diagnostics mal relies aux lots et des hypotheses de phasage trop vagues : le risque est autant contractuel que technique.',
                'Dans un contexte '.$topic.', la consultation des entreprises est lancee alors que les zones sensibles, les restrictions d acces et les preuves documentaires ne sont pas encore alignees.',
                'Autour de '.$topic.', une variante proposee en cours de consultation revele que les pieces techniques ne racontent pas toutes la meme histoire : sans arbitrage, l attribution se fait sur une base fragile.',
            ],
            'copropriete' => [
                'Sur le sujet '.$topic.', une renovation en copropriete demarre avec un repérage ancien et un descriptif travaux trop flou : le vrai risque est autant documentaire que technique.',
                'Dans un contexte '.$topic.', la circulation des occupants, l acces aux parties communes et les contraintes de planning rendent chaque arbitrage plus sensible qu en site vide.',
                'Autour de '.$topic.', des travaux par tranches font apparaitre des dependances entre lots, accès, autorisations et validations documentaires qui ne peuvent pas etre traitées au dernier moment.',
            ],
            'reperage' => [
                'Sur le sujet '.$topic.', un repérage est utilise hors de son perimetre reel et cree une illusion de couverture qui fragilise toute la suite.',
                'Dans un contexte '.$topic.', les hypotheses de travaux evoluent plus vite que les pieces remises aux intervenants, ce qui ouvre des zones grises documentaires.',
                'Autour de '.$topic.', une mise a jour partielle des diagnostics suffit a creer des contradictions entre plans, zones et methode d intervention.',
            ],
            default => [
                'Sur le sujet '.$topic.', une renovation en copropriete demarre avec un repérage ancien et un descriptif travaux trop flou : le vrai risque est autant documentaire que technique.',
                'Dans un contexte '.$topic.', une intervention de maintenance en site occupe combine acces contraints, circulation des tiers et incertitude sur les materiaux : la coordination decide une grande partie du niveau de risque.',
                'Autour de '.$topic.', une demolition partielle revele des materiaux amiantés non anticipes : sans scenario de repli, le chantier se bloque et la chaine documentaire se fissure.',
            ],
        };
    }

    /**
     * @return array<int, string>
     */
    private function mistakesForFamily(string $family): array
    {
        return match ($family) {
            'appel_offre' => [
                'Lancer la consultation avec un DCE qui melange hypotheses de travaux, diagnostics incomplets et variantes non bornées.',
                'Croire qu un repérage suffit sans relier les pieces aux lots, options, contraintes d acces et sequence chantier.',
                'Sous-estimer les effets d un flou documentaire sur les prix, les exclusions, les reserves et les demandes de clarification.',
                'Traiter la coordination comme un sujet d execution alors qu une grande partie du risque se joue dès la consultation.',
            ],
            default => [
                'Parler du diagnostic comme d une formalite sans traiter les hypotheses de travaux, les zones grises et la qualite des pieces remises.',
                'Confondre repérage, retrait, SS3 et SS4 alors que ces cadres changent la methode, la coordination et la preuve attendue.',
                'Oublier les contraintes de site occupe, de copropriete, de maintenance ou de phasage alors qu elles structurent les vrais arbitrages.',
                'Rediger un contenu trop theorique sans montrer ce qui fait perdre du temps, de l argent ou de la maitrise documentaire sur le terrain.',
            ],
        };
    }

    /**
     * @return array<int, string>
     */
    private function inspectionFocusForFamily(string $family): array
    {
        return match ($family) {
            'appel_offre' => [
                'coherence entre le DCE, les hypotheses de travaux et le perimetre reel des diagnostics',
                'presence des pieces de consultation utiles avant remise et attribution',
                'alignement entre contraintes d acces, phasage et obligations documentaires',
                'preuves de clarification, reserves et arbitrages avant ordre de service',
            ],
            default => [
                'coherence entre les hypotheses de travaux et le perimetre reel du repérage amiante',
                'presence, version et diffusion des documents techniques utiles avant intervention',
                'organisation des acces, des confinements, des protections et de la circulation sur site occupe',
                'preuves de coordination entre donneur d ordre, diagnostiqueur, entreprise et exploitation du site',
            ],
        };
    }

    /**
     * @return array<int, string>
     */
    private function evidenceExamplesForFamily(string $family): array
    {
        return match ($family) {
            'appel_offre' => [
                'DCE date avec hypotheses de travaux, lots, options et perimetres documentes',
                'questions/reponses de consultation tracees avec version de reference partagee',
                'synthese des diagnostics, plans et contraintes d acces annexee aux pieces de consultation',
                'compte rendu d arbitrage avant attribution sur zones sensibles, variantes et reserves',
                'ordre de service ou cadrage d execution aligne sur les clarifications retenues',
            ],
            default => [
                'repérage avant travaux date, avec hypothèses clairement formulees et plans joints',
                'DTA ou documents techniques transmis avec trace de diffusion et version de reference',
                'compte rendu de visite ou de coordination precissant zones, acces, occupation et points de vigilance',
                'mode operatoire, protocole d intervention ou validation de scenario SS3/SS4 selon le cas',
                'preuve de levee de doute, arbitrage chantier ou adaptation documentaire apres decouverte terrain',
            ],
        };
    }

    /**
     * @return array<int, string>
     */
    private function dailyConstraintsForFamily(string $family): array
    {
        return match ($family) {
            'appel_offre' => [
                'DCE incomplet ou versions contradictoires',
                'clarifications entreprises trop tardives',
                'lots dependants et sequence chantier mal bordee',
                'contraintes d acces ou occupation insuffisamment decrites',
                'risque de reserves, variantes ou exclusions mal encadrées',
            ],
            default => [
                'site occupe ou partiellement exploite',
                'acces sensibles et circulation de tiers',
                'phasage travaux et dependances entre lots',
                'pression planning avant intervention',
                'pieces techniques eparses ou versions contradictoires',
            ],
        };
    }

    /**
     * @return array<int, string>
     */
    private function workUnitsForFamily(string $family): array
    {
        return match ($family) {
            'appel_offre' => [
                'cadrage du dce',
                'clarification documentaire',
                'analyse des variantes',
                'attribution et reserves',
                'ordre de service et transfert vers execution',
            ],
            default => [
                'preparation documentaire',
                'visite et cadrage terrain',
                'coordination avant intervention',
                'intervention en zone sensible',
                'suivi de preuve et cloture documentaire',
            ],
        };
    }

    /**
     * @return array<int, array{question:string,answer:string}>
     */
    private function faqForFamily(string $family, string $topic): array
    {
        return match ($family) {
            'appel_offre' => [
                [
                    'question' => 'Pourquoi un appel d offre amiante se bloque-t-il souvent avant meme l attribution ?',
                    'answer' => 'Parce que les pieces de consultation ne cadrent pas assez les zones, hypotheses de travaux, diagnostics utiles et contraintes d execution reelles.',
                ],
                [
                    'question' => 'Quelles pieces doivent etre stabilisees avant consultation ?',
                    'answer' => 'Le DCE, les diagnostics relies aux lots, les plans, les contraintes d acces, le phasage et les clarifications qui changent la methode ou le prix.',
                ],
                [
                    'question' => 'Comment limiter les variantes et reserves non maitrisees ?',
                    'answer' => 'En bornant mieux les hypotheses de travaux, les zones sensibles, les attentes documentaires et les questions/reponses pendant la consultation.',
                ],
                [
                    'question' => 'Pourquoi le sujet '.$topic.' ne releve-t-il pas seulement du juridique ?',
                    'answer' => 'Parce que les erreurs d appel d offre finissent tres vite en problemes terrain, blocages de coordination et surcouts d execution.',
                ],
                [
                    'question' => 'Quel est le bon niveau de detail pour rester exploitable ?',
                    'answer' => 'Assez de detail pour cadrer les zones, les pieces et les arbitrages, sans diluer l information critique dans un dossier trop abstrait.',
                ],
            ],
            default => [
                [
                    'question' => 'Quand un diagnostic amiante devient-il insuffisant pour un chantier ?',
                    'answer' => 'Quand les hypotheses de travaux sont floues, que le perimetre reel n est pas clair ou que les zones a ouvrir n ont pas ete correctement documentees avant intervention.',
                ],
                [
                    'question' => 'Pourquoi la coordination documentaire compte autant que le repérage ?',
                    'answer' => 'Parce qu un bon repérage perd une grande partie de sa valeur si les bonnes versions, les bons plans et les bonnes consignes ne sont pas transmis aux bons acteurs au bon moment.',
                ],
                [
                    'question' => 'Comment distinguer un sujet SS3 d un sujet SS4 dans un contenu utile ?',
                    'answer' => 'Il faut expliquer le type d intervention vise, l objectif premier des travaux, le niveau de preparation et les implications concretes sur la methode et la preuve attendue.',
                ],
                [
                    'question' => 'Quels documents rendent une page amiante vraiment credible ?',
                    'answer' => 'Des references claires au repérage, au DTA, aux validations de diffusion, aux comptes rendus de coordination et aux pieces qui cadrent la methode d intervention.',
                ],
                [
                    'question' => 'Pourquoi un article amiante devient vite trop generique ?',
                    'answer' => 'Quand il parle seulement du risque en theorie sans montrer les arbitrages de terrain, les blocages documentaires, les situations de site occupe et les vraies decisions du donneur d ordre.',
                ],
            ],
        };
    }

    private function familyFromKeyword(string $topic, string $cluster): string
    {
        return match (true) {
            str_contains($topic, 'appel d offre'), str_contains($topic, 'appel offre'), str_contains($topic, 'consultation'), str_contains($topic, 'dce') => 'appel_offre',
            $cluster === 'copropriete' => 'copropriete',
            in_array($cluster, ['reperage', 'dta'], true) => 'reperage',
            default => 'default',
        };
    }

    private function topicFromKeyword(string $keyword): string
    {
        $normalized = Str::of(Str::ascii(Str::lower($keyword)))
            ->replace('-', ' ')
            ->squish()
            ->value();

        return $normalized !== '' ? $normalized : 'gestion du risque amiante';
    }

    private function clusterFromKeyword(string $topic): string
    {
        return match (true) {
            str_contains($topic, 'ss3') => 'ss3',
            str_contains($topic, 'ss4') => 'ss4',
            str_contains($topic, 'desamiantage') => 'desamiantage',
            str_contains($topic, 'confinement') => 'confinement',
            str_contains($topic, 'dta') => 'dta',
            str_contains($topic, 'empoussierement') => 'empoussierement',
            str_contains($topic, 'reperage') => 'reperage',
            str_contains($topic, 'diagnostic') => 'diagnostics',
            str_contains($topic, 'copropriete') => 'copropriete',
            default => 'reglementation',
        };
    }
}
