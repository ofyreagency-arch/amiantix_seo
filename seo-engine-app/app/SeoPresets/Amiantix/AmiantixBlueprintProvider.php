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

        return [
            'topic' => $topic,
            'cluster' => $resolvedCluster,
            'hero_angle' => 'coordination terrain et maitrise documentaire du risque amiante',
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
            'editorial_sections' => [
                'Contexte et obligations',
                'Tableau de priorisation des risques',
                'Situations a risque sur le terrain',
                'Processus d intervention et coordination',
                'Documents et preuves a conserver',
                'Points de vigilance pour le donneur d ordre',
                'Couts, delais et arbitrages chantier',
                'Erreurs frequentes et blocages evitables',
                'FAQ',
            ],
            'risk_rows' => [
                ['Exposition a l amiante', 'Travaux dans des zones mal caracterisees ou hypotheses de travaux incomplètes', 'Repérage adapte, cadrage des zones, lecture des diagnostics et validation du scenario d intervention'],
                ['Empoussierement et dispersion', 'Percement, decoupe, demolition ou maintenance sans confinement adapte', 'Mode operatoire, confinement, captation, verification des acces et suivi des mesures'],
                ['Defaut documentaire', 'DTA absent, repérage incomplet, transmission tardive des pieces ou plans contradictoires', 'Centraliser les pieces, dater les versions, tracer les diffusions et verrouiller les hypothèses'],
                ['Desorganisation chantier', 'Coordination insuffisante entre MOA, diagnostiqueur, entreprise et occupants', 'Planning clarifie, interlocuteurs identifies, jalons de validation et gestion des zones sensibles'],
                ['Blocage de site ou surcout', 'Decouverte tardive d amiante, zone non preparée ou arbitrage retardé', 'Anticipation, scenario de replis, budget documentaire et verification des dependances chantier'],
            ],
            'obligations' => [
                'Identifier qui commande le repérage, sur quel perimetre et avec quelles hypotheses de travaux ou de maintenance.',
                'Tracer les pieces utiles: DTA, repérage avant travaux, plans, comptes rendus, restrictions d acces et validations de diffusion.',
                'Verifier la coherence entre diagnostic, mode operatoire, entreprise engagee et contexte d occupation du site.',
                'Rendre visible ce qui releve de la preparation documentaire, de la coordination terrain et de la preuve finale conservee.',
            ],
            'cases' => [
                'Une renovation en copropriete demarre avec un repérage ancien et un descriptif travaux trop flou : le vrai risque est autant documentaire que technique.',
                'Une intervention de maintenance en site occupe combine acces contraints, circulation des tiers et incertitude sur les materiaux : la coordination decide une grande partie du niveau de risque.',
                'Une demolition partielle revele des materiaux amiantés non anticipes : sans scenario de repli, le chantier se bloque et la chaine documentaire se fissure.',
            ],
            'mistakes' => [
                'Parler du diagnostic comme d une formalite sans traiter les hypotheses de travaux, les zones grises et la qualite des pieces remises.',
                'Confondre repérage, retrait, SS3 et SS4 alors que ces cadres changent la methode, la coordination et la preuve attendue.',
                'Oublier les contraintes de site occupe, de copropriete, de maintenance ou de phasage alors qu elles structurent les vrais arbitrages.',
                'Rediger un contenu trop theorique sans montrer ce qui fait perdre du temps, de l argent ou de la maitrise documentaire sur le terrain.',
            ],
            'inspection_focus' => [
                'coherence entre les hypotheses de travaux et le perimetre reel du repérage amiante',
                'presence, version et diffusion des documents techniques utiles avant intervention',
                'organisation des acces, des confinements, des protections et de la circulation sur site occupe',
                'preuves de coordination entre donneur d ordre, diagnostiqueur, entreprise et exploitation du site',
            ],
            'evidence_examples' => [
                'repérage avant travaux date, avec hypothèses clairement formulees et plans joints',
                'DTA ou documents techniques transmis avec trace de diffusion et version de reference',
                'compte rendu de visite ou de coordination precissant zones, acces, occupation et points de vigilance',
                'mode operatoire, protocole d intervention ou validation de scenario SS3/SS4 selon le cas',
                'preuve de levee de doute, arbitrage chantier ou adaptation documentaire apres decouverte terrain',
            ],
            'daily_constraints' => [
                'site occupe ou partiellement exploite',
                'acces sensibles et circulation de tiers',
                'phasage travaux et dependances entre lots',
                'pression planning avant intervention',
                'pieces techniques eparses ou versions contradictoires',
            ],
            'work_units' => [
                'preparation documentaire',
                'visite et cadrage terrain',
                'coordination avant intervention',
                'intervention en zone sensible',
                'suivi de preuve et cloture documentaire',
            ],
            'faq' => [
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
