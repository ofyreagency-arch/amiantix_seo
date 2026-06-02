<?php

declare(strict_types=1);

namespace App\SeoPresets\Amiantix;

use Illuminate\Support\Str;
use Ofyre\SeoEngine\Contracts\InternalLinkProvider;

class AmiantixInternalLinkProvider implements InternalLinkProvider
{
    /**
     * @return array<int, array{label:string,url:string,reason:string}>
     */
    public function linksFor(object $page): array
    {
        $keyword = (string) ($page->keyword ?? '');
        $cluster = (string) ($page->cluster ?? $this->clusterForKeyword($keyword));
        $currentSlug = trim((string) ($page->slug ?? ''), '/');

        $links = collect($this->linkCatalog()[$cluster] ?? $this->linkCatalog()['reglementation'])
            ->reject(fn (array $link): bool => trim($link['slug'], '/') === $currentSlug)
            ->take(3)
            ->map(fn (array $link): array => [
                'label' => $link['label'],
                'url' => '/'.$link['slug'],
                'reason' => $link['reason'],
            ])
            ->values()
            ->all();

        if ($links !== []) {
            return $links;
        }

        return [
            [
                'label' => 'Guide repérage avant travaux',
                'url' => '/guide-reperage-avant-travaux',
                'reason' => 'Renforcer le cadrage documentaire et la lecture terrain autour de la page.',
            ],
            [
                'label' => 'FAQ amiante',
                'url' => '/faq-amiante',
                'reason' => 'Absorber les objections fréquentes avec une page de soutien plus pédagogique.',
            ],
        ];
    }

    public function clusterForKeyword(string $keyword): string
    {
        $topic = Str::of(Str::ascii(Str::lower($keyword)))
            ->replace('-', ' ')
            ->squish()
            ->value();

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

    /**
     * @return array<string, array<int, array{label:string,slug:string,reason:string}>>
     */
    private function linkCatalog(): array
    {
        return [
            'diagnostics' => [
                [
                    'label' => 'Diagnostic amiante en copropriete',
                    'slug' => 'diagnostic-amiante-copropriete',
                    'reason' => 'Relier le sujet à un cas d usage concret qui convertit mieux la demande de diagnostic.',
                ],
                [
                    'label' => 'Guide repérage avant travaux',
                    'slug' => 'guide-reperage-avant-travaux',
                    'reason' => 'Ajouter le cadrage documentaire qui transforme un simple diagnostic en décision chantier.',
                ],
                [
                    'label' => 'FAQ amiante',
                    'slug' => 'faq-amiante',
                    'reason' => 'Couvrir les objections récurrentes avant prise de contact.',
                ],
            ],
            'copropriete' => [
                [
                    'label' => 'Diagnostic amiante en copropriete',
                    'slug' => 'diagnostic-amiante-copropriete',
                    'reason' => 'Renforcer le maillage vers la page la plus proche du contexte syndic, parties communes et occupants.',
                ],
                [
                    'label' => 'Qui sommes nous',
                    'slug' => 'qui-sommes-nous',
                    'reason' => 'Rassurer sur la capacité d intervention et la coordination terrain dans des sites occupés.',
                ],
                [
                    'label' => 'FAQ amiante',
                    'slug' => 'faq-amiante',
                    'reason' => 'Répondre aux questions fréquentes qui ralentissent la prise de décision en copropriété.',
                ],
            ],
            'reperage' => [
                [
                    'label' => 'Guide repérage avant travaux',
                    'slug' => 'guide-reperage-avant-travaux',
                    'reason' => 'Faire remonter la page de référence documentaire la plus proche de l intention repérage.',
                ],
                [
                    'label' => 'Diagnostic amiante en copropriete',
                    'slug' => 'diagnostic-amiante-copropriete',
                    'reason' => 'Montrer comment le repérage se traduit ensuite dans un contexte terrain réel.',
                ],
                [
                    'label' => 'FAQ amiante',
                    'slug' => 'faq-amiante',
                    'reason' => 'Compléter le sujet avec les cas limites et hésitations fréquentes.',
                ],
            ],
            'dta' => [
                [
                    'label' => 'Guide repérage avant travaux',
                    'slug' => 'guide-reperage-avant-travaux',
                    'reason' => 'Relier la gestion documentaire longue au déclenchement d une intervention concrète.',
                ],
                [
                    'label' => 'FAQ amiante',
                    'slug' => 'faq-amiante',
                    'reason' => 'Aider à traiter les demandes récurrentes autour des obligations et des pièces à conserver.',
                ],
                [
                    'label' => 'Qui sommes nous',
                    'slug' => 'qui-sommes-nous',
                    'reason' => 'Donner un ancrage confiance quand la page parle de suivi documentaire sensible.',
                ],
            ],
            'ss3' => [
                [
                    'label' => 'Guide repérage avant travaux',
                    'slug' => 'guide-reperage-avant-travaux',
                    'reason' => 'Revenir au périmètre documentaire qui encadre la préparation avant travaux.',
                ],
                [
                    'label' => 'FAQ amiante',
                    'slug' => 'faq-amiante',
                    'reason' => 'Répondre aux différences pratiques entre préparation, retrait et maintenance.',
                ],
                [
                    'label' => 'Diagnostic amiante en copropriete',
                    'slug' => 'diagnostic-amiante-copropriete',
                    'reason' => 'Illustrer un cas occupé où la coordination change le niveau de risque.',
                ],
            ],
            'ss4' => [
                [
                    'label' => 'Guide repérage avant travaux',
                    'slug' => 'guide-reperage-avant-travaux',
                    'reason' => 'Ancrer le sujet dans le bon niveau de préparation documentaire avant intervention.',
                ],
                [
                    'label' => 'FAQ amiante',
                    'slug' => 'faq-amiante',
                    'reason' => 'Clarifier les interventions de maintenance et les zones grises les plus fréquentes.',
                ],
                [
                    'label' => 'Qui sommes nous',
                    'slug' => 'qui-sommes-nous',
                    'reason' => 'Renforcer la confiance sur la coordination et l accompagnement terrain.',
                ],
            ],
            'desamiantage' => [
                [
                    'label' => 'Guide repérage avant travaux',
                    'slug' => 'guide-reperage-avant-travaux',
                    'reason' => 'Replacer le retrait dans la séquence documentaire qui le rend défendable.',
                ],
                [
                    'label' => 'Diagnostic amiante en copropriete',
                    'slug' => 'diagnostic-amiante-copropriete',
                    'reason' => 'Montrer un cas où l occupation du site change les arbitrages d intervention.',
                ],
                [
                    'label' => 'FAQ amiante',
                    'slug' => 'faq-amiante',
                    'reason' => 'Éclairer les questions récurrentes sur le déroulé et les contraintes chantier.',
                ],
            ],
            'confinement' => [
                [
                    'label' => 'Guide repérage avant travaux',
                    'slug' => 'guide-reperage-avant-travaux',
                    'reason' => 'Relier le confinement à la qualité des hypothèses de travaux et du repérage.',
                ],
                [
                    'label' => 'FAQ amiante',
                    'slug' => 'faq-amiante',
                    'reason' => 'Apporter les réponses pratiques que le lecteur attend sur les zones et accès sensibles.',
                ],
                [
                    'label' => 'Diagnostic amiante en copropriete',
                    'slug' => 'diagnostic-amiante-copropriete',
                    'reason' => 'Illustrer les contraintes de circulation et d occupation les plus concrètes.',
                ],
            ],
            'empoussierement' => [
                [
                    'label' => 'Guide repérage avant travaux',
                    'slug' => 'guide-reperage-avant-travaux',
                    'reason' => 'Ramener le sujet vers le cadrage technique et documentaire qui évite la dispersion.',
                ],
                [
                    'label' => 'FAQ amiante',
                    'slug' => 'faq-amiante',
                    'reason' => 'Rendre plus pédagogique un sujet anxiogène pour le lecteur non expert.',
                ],
                [
                    'label' => 'Diagnostic amiante en copropriete',
                    'slug' => 'diagnostic-amiante-copropriete',
                    'reason' => 'Montrer comment le risque se gère dans un environnement occupé.',
                ],
            ],
            'reglementation' => [
                [
                    'label' => 'Guide repérage avant travaux',
                    'slug' => 'guide-reperage-avant-travaux',
                    'reason' => 'Transformer la règle en séquence opérationnelle compréhensible pour le client.',
                ],
                [
                    'label' => 'FAQ amiante',
                    'slug' => 'faq-amiante',
                    'reason' => 'Réduire la part de jargon et répondre aux questions réglementaires les plus courantes.',
                ],
                [
                    'label' => 'Diagnostic amiante en copropriete',
                    'slug' => 'diagnostic-amiante-copropriete',
                    'reason' => 'Relier la réglementation à un cas terrain où les arbitrages sont concrets.',
                ],
            ],
        ];
    }
}
