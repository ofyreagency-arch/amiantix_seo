<?php

declare(strict_types=1);

namespace App\SeoPresets\SiteAware;

final class NicheEditorialRegistry
{
    /**
     * @param  array<int,string>  $vocabularyTerms
     * @return array<string,mixed>
     */
    public static function resolve(string $industry, string $keyword, array $vocabularyTerms = []): array
    {
        $niche = self::detectNiche($industry, $keyword, $vocabularyTerms);

        return self::profiles()[$niche] ?? self::profiles()['generic'];
    }

    /**
     * @param  array<int,string>  $vocabularyTerms
     */
    public static function detectNiche(string $industry, string $keyword, array $vocabularyTerms = []): string
    {
        $haystack = mb_strtolower($industry.' '.$keyword.' '.implode(' ', $vocabularyTerms));

        foreach ([
            'amiante' => ['amiante', 'desamiantage', 'désamiantage', 'ss3', 'ss4', 'repérage', 'reperage'],
            'plomberie' => ['plomberie', 'plombier', 'fuite', 'canalisation', 'chauffe-eau', 'degat des eaux', 'dégât'],
            'avocat' => ['avocat', 'juridique', 'droit', 'tribunal', 'contentieux', 'procedure', 'procédure', 'honoraires'],
            'immobilier' => ['immobilier', 'agence immo', 'mandat', 'vente immobili', 'achat immobili', 'diagnostic vente', 'bien immobilier'],
            'recrutement' => ['recrutement', 'rh', 'candidat', 'embauche', 'talent', 'sourcing', 'marque employeur', 'entretien'],
        ] as $niche => $needles) {
            foreach ($needles as $needle) {
                if (str_contains($haystack, $needle)) {
                    return $niche;
                }
            }
        }

        return 'generic';
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    private static function profiles(): array
    {
        return [
            'amiante' => [
                'niche' => 'amiante',
                'hero_angle' => 'Documenter les obligations amiante, les repérages et la coordination chantier',
                'voice_note' => 'Ton ingénierie / conformité chantier : DTA, repérage, MOA, SS3/SS4, copropriété en travaux.',
                'composition' => [
                    'cadrage : enjeu réglementaire amiante et périmètre de repérage',
                    'DTA, dossiers techniques et traçabilité avant travaux',
                    'acteurs : donneur d\'ordre, diagnostiqueur, entreprises, coordonnateur SPS',
                    'erreurs documentaires et conséquences sur le phasage chantier',
                    'cas copropriété, démolition ou site occupé si pertinent',
                    'arbitrages conformité vs délai de reprise d\'activité',
                ],
                'depth_topics' => [
                    'repérage amiante avant travaux et niveaux de diagnostic',
                    'DTA et dossiers techniques disponibles',
                    'responsabilités du donneur d\'ordre et du diagnostiqueur',
                    'copropriété : coordination syndic, entreprises et occupants',
                    'démolition vs rénovation : périmètre de repérage',
                    'SS3 / SS4 et ingénierie documentaire',
                ],
                'field_scenarios' => [
                    'Travaux lancés sans repérage à jour sur le périmètre réel',
                    'Copropriété : versions divergentes du DTA entre entreprises',
                    'Reprise d\'activité contrainte avec phasage désamiantage',
                ],
                'mistakes' => [
                    'Confondre diagnostic global et repérage avant travaux sur la zone d\'intervention',
                    'Oublier la mise à jour documentaire après changement d\'hypothèse de travaux',
                    'Sous-estimer la coordination copropriété / occupants avant ouverture chantier',
                ],
                'arbitrages' => [
                    'Conformité réglementaire vs délai de reprise d\'activité',
                    'Repérage complet vs phasage chantier',
                    'Coordination interne vs recours à un diagnostiqueur certifié',
                ],
                'signature_terms' => ['dta', 'repérage', 'reperage', 'ss3', 'ss4', 'desamiantage', 'désamiantage', 'diagnostiqueur', 'donneur d\'ordre', 'copropriété', 'copropriete'],
            ],
            'plomberie' => [
                'niche' => 'plomberie',
                'hero_angle' => 'Aider à trancher vite une panne, une fuite ou un dégât des eaux sans aggraver les dégâts',
                'voice_note' => 'Ton intervention terrain : coupure d\'eau, dégâts des eaux, syndic, devis, urgence plomberie.',
                'composition' => [
                    'cadrage : type de panne ou fuite et gravité immédiate',
                    'premiers gestes : coupure, protection, périmètre impacté',
                    'diagnostic technique : origine probable, accès, pièces à prévoir',
                    'coordination syndic / assurance / voisins en copropriété',
                    'devis, délai d\'intervention et critères de choix du plombier',
                    'prévention des récidives et entretien réseau',
                ],
                'depth_topics' => [
                    'coupure d\'eau générale ou partielle : quand et comment',
                    'dégâts des eaux : limiter l\'aggravation et documenter pour l\'assurance',
                    'réseaux encastrés, colonnes montantes et accès difficiles',
                    'pression, calcaire, vieillissement des installations',
                    'syndic et parties communes : qui intervient sur quoi',
                ],
                'field_scenarios' => [
                    'Fuite active un week-end en copropriété',
                    'Chauffe-eau collectif en panne avec caves déjà touchées',
                    'Canalisation encastrée : diagnostic sans casser inutilement',
                ],
                'mistakes' => [
                    'Attendre trop longtemps avant coupure alors que l\'eau progresse',
                    'Lancer une réparation sans repérer l\'étendue réelle du dégât',
                    'Oublier de tracer l\'intervention pour le syndic ou l\'assurance',
                ],
                'arbitrages' => [
                    'Coupure immédiate vs isolement partiel du réseau',
                    'Réparation provisoire vs remplacement complet',
                    'Dépannage syndic vs plombier privé du copropriétaire',
                ],
                'signature_terms' => ['fuite', 'plombier', 'coupure', 'canalisation', 'chauffe-eau', 'dégât', 'degat', 'syndic', 'colonne', 'robinet', 'pression'],
            ],
            'avocat' => [
                'niche' => 'avocat',
                'hero_angle' => 'Clarifier le cadre juridique, les délais et les preuves avant d\'engager une procédure',
                'voice_note' => 'Ton conseil juridique : procédure, preuve, délais, juridiction, honoraires — pas de vocabulaire chantier.',
                'composition' => [
                    'cadrage : nature du litige et enjeu pour le client',
                    'cadre juridique applicable et voies d\'action possibles',
                    'preuves à constituer, délais de prescription et formalisme',
                    'étapes procédurales et points de vigilance',
                    'coûts, honoraires et stratégie amiable vs contentieux',
                    'décision à prendre avant d\'assigner ou de signer',
                ],
                'depth_topics' => [
                    'compétence juridictionnelle et choix de la procédure',
                    'constitution et conservation des preuves',
                    'délais de prescription et délais procéduraux',
                    'mise en demeure, négociation et médiation',
                    'honoraires, provision et frais de justice',
                ],
                'field_scenarios' => [
                    'Client qui découvre un délai de prescription proche',
                    'Litige commercial : preuves incomplètes au moment de la mise en demeure',
                    'Arbitrage entre transaction rapide et assignation',
                ],
                'mistakes' => [
                    'Agir sans vérifier la prescription ou la compétence du tribunal',
                    'Négliger la traçabilité des échanges et pièces contractuelles',
                    'Engager une procédure sans chiffrer honoraires et issue probable',
                ],
                'arbitrages' => [
                    'Transaction amiable vs assignation',
                    'Provision d\'honoraires vs engagement contentieux long',
                    'Médiation vs procédure accélérée',
                ],
                'signature_terms' => ['juridique', 'procedure', 'procédure', 'tribunal', 'preuve', 'honoraires', 'assignation', 'mise en demeure', 'contentieux', 'prescription', 'juridiction'],
            ],
            'immobilier' => [
                'niche' => 'immobilier',
                'hero_angle' => 'Sécuriser une vente ou un achat : mandat, diagnostics, prix et délais de transaction',
                'voice_note' => 'Ton transaction immobilière : mandat, diagnostics vente, négociation, financement, copropriété vendeur.',
                'composition' => [
                    'cadrage : vendeur, acheteur ou investisseur — quel objectif de transaction',
                    'mandat, estimation et stratégie de mise en marché',
                    'diagnostics obligatoires et impact sur le prix ou le délai',
                    'négociation, offre, conditions suspensives et financement',
                    'copropriété, charges et documents à exiger avant acte',
                    'calendrier de signature et points de blocage fréquents',
                ],
                'depth_topics' => [
                    'mandat exclusif ou simple et devoir de information',
                    'diagnostics immobiliers et leur lecture pour l\'acheteur',
                    'négociation du prix et des travaux après offre',
                    'prêt, conditions suspensives et délai de rétractation',
                    'copropriété : PV d\'AG, charges et travaux votés',
                ],
                'field_scenarios' => [
                    'Vendeur qui sous-estime les diagnostics à réaliser avant mise en vente',
                    'Acheteur surpris par des travaux copropriété votés non provisionnés',
                    'Offre acceptée puis blocage financement ou dossier incomplet',
                ],
                'mistakes' => [
                    'Signer un mandat sans clarifier la stratégie de prix et de délai',
                    'Comparer des biens sans lire les diagnostics et charges réelles',
                    'Oublier les documents copropriété avant compromis',
                ],
                'arbitrages' => [
                    'Mandat exclusif vs simple : visibilité et délai de vente',
                    'Baisse de prix vs attente d\'un acheteur mieux financé',
                    'Diagnostics avant mise en vente vs signature sous condition',
                ],
                'signature_terms' => ['mandat', 'vente', 'acheteur', 'vendeur', 'diagnostic', 'compromis', 'notaire', 'charges', 'copropriété', 'copropriete', 'estimation', 'offre'],
            ],
            'recrutement' => [
                'niche' => 'recrutement',
                'hero_angle' => 'Structurer un recrutement : profil, sourcing, entretien et intégration sans perdre les bons candidats',
                'voice_note' => 'Ton RH / recrutement : fiche de poste, sourcing, entretien, onboarding, marque employeur.',
                'composition' => [
                    'cadrage : poste à pourvoir, contexte équipe et urgence réelle',
                    'profil recherché, compétences non négociables et critères d\'écart',
                    'sourcing : canaux, message employeur et tri des candidatures',
                    'processus d\'entretien et grille d\'évaluation',
                    'proposition, délai de réponse et risque de désistement',
                    'onboarding et intégration des 90 premiers jours',
                ],
                'depth_topics' => [
                    'rédaction d\'une fiche de poste orientée missions réelles',
                    'canaux de sourcing selon métier et séniorité',
                    'entretien structuré et prise de référence',
                    'marque employeur et délai de réponse candidat',
                    'onboarding et période d\'essai',
                ],
                'field_scenarios' => [
                    'Poste ouvert depuis des semaines avec profil flou',
                    'Candidat fort perdu faute de processus d\'entretien structuré',
                    'Offre tardive après plusieurs tours non cadrés',
                ],
                'mistakes' => [
                    'Publier une annonce sans critères d\'écart clairs',
                    'Multiplier les entretiens sans grille de décision commune',
                    'Laisser un candidat chaud sans retour plus de 72 h',
                ],
                'arbitrages' => [
                    'Profil strict vs élargissement du vivier candidats',
                    'Cabinet de recrutement vs recrutement interne',
                    'Offre rapide vs tours d\'entretien supplémentaires',
                ],
                'signature_terms' => ['candidat', 'recrutement', 'entretien', 'poste', 'sourcing', 'onboarding', 'rh', 'embauche', 'profil', 'marque employeur', 'période d\'essai', 'periode d\'essai'],
            ],
            'generic' => [
                'niche' => 'generic',
                'hero_angle' => 'Répondre avec profondeur métier au problème exprimé par la requête',
                'voice_note' => 'Ton professionnel neutre adapté au secteur déclaré dans le profil site.',
                'composition' => [],
                'depth_topics' => [],
                'field_scenarios' => [],
                'mistakes' => [],
                'signature_terms' => [],
            ],
        ];
    }
}
