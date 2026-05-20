<?php

declare(strict_types=1);

namespace Ofyre\SeoEngine\Examples\AmiantixPreset;

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
            'hero_angle' => 'guide terrain et reglementaire pour la gestion du risque amiante',
            'risk_terms' => [
                'amiante',
                'desamiantage',
                'confinement',
                'repérage',
                'diagnostic amiante',
                'DTA',
                'empoussièrement',
                'ss3',
                'ss4',
                'copropriete',
                'reglementation',
                $topic,
            ],
            'editorial_sections' => [
                'Contexte et obligations',
                'Situations a risque sur le terrain',
                'Processus d intervention',
                'Documents et preuves a conserver',
                'Points de vigilance pour le donneur d ordre',
                'Couts, delais et coordination',
                'FAQ',
            ],
            'faq' => [
                [
                    'question' => 'Quand faut-il lancer un repérage amiante avant travaux ?',
                    'answer' => 'Des qu un chantier peut affecter des materiaux ou produits susceptibles de contenir de l amiante, le repérage doit etre anticipe avant la consultation et avant l intervention.',
                ],
                [
                    'question' => 'Quelle difference entre SS3 et SS4 ?',
                    'answer' => 'La SS3 concerne des operations de retrait ou d encapsulage planifiees, alors que la SS4 vise des interventions sur materiaux amiantés sans objectif premier de retrait.',
                ],
                [
                    'question' => 'Pourquoi le DTA reste central ?',
                    'answer' => 'Le dossier technique amiante aide a tracer la presence connue d amiante, organiser la surveillance et transmettre l information utile aux occupants comme aux intervenants.',
                ],
                [
                    'question' => 'Que faut-il verifier chez une entreprise ?',
                    'answer' => 'Ses certifications, ses modes operatoires, ses mesures d empoussièrement, son organisation de confinement et sa capacite a produire des preuves documentaires fiables.',
                ],
                [
                    'question' => 'Comment limiter les risques de blocage de chantier ?',
                    'answer' => 'En cadrant le repérage, les hypotheses de travaux, les zones concernees, les acces, les validations et le pilotage documentaire avant le demarrage.',
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
        return $profile['risk_terms'] ?? [];
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
