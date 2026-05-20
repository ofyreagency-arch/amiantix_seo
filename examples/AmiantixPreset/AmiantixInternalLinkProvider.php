<?php

declare(strict_types=1);

namespace Ofyre\SeoEngine\Examples\AmiantixPreset;

use Illuminate\Support\Str;
use Ofyre\SeoEngine\Contracts\InternalLinkProvider;

final class AmiantixInternalLinkProvider implements InternalLinkProvider
{
    public function linksFor(object $page): array
    {
        $cluster = $this->clusterForKeyword((string) ($page->keyword ?? ''));

        $links = [
            'ss3' => [
                ['label' => 'Travaux SS3', 'url' => '/travaux-ss3', 'reason' => 'Relier vers le cadre des operations de retrait ou encapsulage planifiees.'],
                ['label' => 'Confinement amiante', 'url' => '/confinement-amiante', 'reason' => 'Ajouter le volet maitrise des zones et protection des intervenants.'],
            ],
            'ss4' => [
                ['label' => 'Interventions SS4', 'url' => '/interventions-ss4', 'reason' => 'Relier vers le cadre des interventions sur materiaux amiantés.'],
                ['label' => 'Mode operatoire amiante', 'url' => '/mode-operatoire-amiante', 'reason' => 'Completer par la logique documentaire et procedurelle.'],
            ],
            'desamiantage' => [
                ['label' => 'Entreprise de desamiantage', 'url' => '/entreprise-desamiantage', 'reason' => 'Aider le lecteur a comprendre le role de l entreprise et son cadre d intervention.'],
                ['label' => 'Mesures d empoussièrement', 'url' => '/mesures-empoussierement-amiante', 'reason' => 'Approfondir la surveillance et la verification des risques.'],
            ],
            'confinement' => [
                ['label' => 'Confinement amiante', 'url' => '/confinement-amiante', 'reason' => 'Creer un lien vers les principes de protection et de maitrise de zone.'],
                ['label' => 'Desamiantage en site occupe', 'url' => '/desamiantage-site-occupe', 'reason' => 'Ajouter le contexte de coordination en locaux occupes.'],
            ],
            'dta' => [
                ['label' => 'Dossier technique amiante', 'url' => '/dossier-technique-amiante', 'reason' => 'Renforcer la partie obligations de suivi et d information.'],
                ['label' => 'Repérage amiante avant travaux', 'url' => '/reperage-amiante-avant-travaux', 'reason' => 'Relier le DTA aux besoins d investigation avant intervention.'],
            ],
            'empoussierement' => [
                ['label' => 'Mesures d empoussièrement', 'url' => '/mesures-empoussierement-amiante', 'reason' => 'Creer le lien vers la surveillance des fibres et la verification des conditions d intervention.'],
                ['label' => 'Confinement amiante', 'url' => '/confinement-amiante', 'reason' => 'Relier les controles aux mesures de maitrise terrain.'],
            ],
            'reperage' => [
                ['label' => 'Repérage amiante avant travaux', 'url' => '/reperage-amiante-avant-travaux', 'reason' => 'Consolider le sujet principal de preparation de chantier.'],
                ['label' => 'Diagnostic amiante copropriete', 'url' => '/diagnostic-amiante-copropriete', 'reason' => 'Relier vers un cas frequent de decision patrimoniale.'],
            ],
            'diagnostics' => [
                ['label' => 'Diagnostic amiante', 'url' => '/diagnostic-amiante', 'reason' => 'Renforcer la base du parcours d information.'],
                ['label' => 'Dossier technique amiante', 'url' => '/dossier-technique-amiante', 'reason' => 'Relier le diagnostic au suivi documentaire dans le temps.'],
            ],
            'copropriete' => [
                ['label' => 'Diagnostic amiante copropriete', 'url' => '/diagnostic-amiante-copropriete', 'reason' => 'Approfondir le cas d usage syndic et immeuble collectif.'],
                ['label' => 'Dossier technique amiante', 'url' => '/dossier-technique-amiante', 'reason' => 'Completer par la logique documentaire et la transmission aux occupants.'],
            ],
            'reglementation' => [
                ['label' => 'Reglementation amiante', 'url' => '/reglementation-amiante', 'reason' => 'Relier vers la vue d ensemble des obligations.'],
                ['label' => 'Repérage amiante avant travaux', 'url' => '/reperage-amiante-avant-travaux', 'reason' => 'Ancrer la reglementation dans un acte operationnel.'],
            ],
        ];

        return $links[$cluster] ?? $links['reglementation'];
    }

    public function clusterForKeyword(string $keyword): string
    {
        $normalized = Str::of(Str::ascii(Str::lower($keyword)))->value();

        return match (true) {
            str_contains($normalized, 'ss3') => 'ss3',
            str_contains($normalized, 'ss4') => 'ss4',
            str_contains($normalized, 'desamiantage') => 'desamiantage',
            str_contains($normalized, 'confinement') => 'confinement',
            str_contains($normalized, 'dta') => 'dta',
            str_contains($normalized, 'empoussierement') => 'empoussierement',
            str_contains($normalized, 'reperage') => 'reperage',
            str_contains($normalized, 'diagnostic') => 'diagnostics',
            str_contains($normalized, 'copropriete') => 'copropriete',
            default => 'reglementation',
        };
    }
}
