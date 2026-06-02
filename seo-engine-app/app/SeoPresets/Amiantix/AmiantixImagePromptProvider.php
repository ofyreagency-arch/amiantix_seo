<?php

declare(strict_types=1);

namespace App\SeoPresets\Amiantix;

use Illuminate\Support\Str;
use Ofyre\SeoEngine\Contracts\ImagePromptProvider;

class AmiantixImagePromptProvider implements ImagePromptProvider
{
    public function promptFor(string $keyword, ?string $cluster): string
    {
        $resolvedCluster = $cluster ?: $this->clusterForKeyword($keyword);
        $topic = Str::of(Str::ascii(Str::lower($keyword)))
            ->replace('-', ' ')
            ->squish()
            ->value();

        $scene = match ($resolvedCluster) {
            'copropriete' => 'immeuble habité, parties communes sécurisées, diagnostiqueur amiante en lecture de plans et repérage terrain',
            'reperage', 'dta' => 'diagnostiqueur amiante avec plans, checklists, repères de zones et pièces documentaires sur site technique',
            'ss3', 'ss4' => 'préparation d intervention amiante en zone technique, EPI visibles, coordination sécurité et balisage propre',
            'desamiantage', 'confinement', 'empoussierement' => 'chantier amiante maîtrisé, balisage, confinement propre, contrôle des accès et ambiance de sécurité',
            'diagnostics' => 'visite de diagnostic amiante réaliste dans un bâtiment occupé, prise de notes, lecture de surfaces et échange avec le client',
            default => 'scène professionnelle amiante mêlant diagnostic, préparation documentaire et arbitrage terrain',
        };

        $focus = match ($resolvedCluster) {
            'copropriete' => 'mettre l accent sur la coordination avec occupants, syndic et accès sensibles',
            'reperage', 'dta' => 'mettre l accent sur les documents, plans, annotations et cohérence de périmètre',
            'ss3', 'ss4' => 'mettre l accent sur la préparation d intervention, les zones sensibles et la maîtrise du risque',
            'desamiantage', 'confinement', 'empoussierement' => 'mettre l accent sur la sécurité, la méthode et la maîtrise des poussières',
            'diagnostics' => 'mettre l accent sur l expertise terrain et la lecture concrète du bâtiment',
            default => 'mettre l accent sur une expertise rassurante, précise et non anxiogène',
        };

        return sprintf(
            'Photographie éditoriale réaliste pour un article sur "%s", %s, lumière naturelle, style professionnel premium, sans texte dans l image, sans rendu 3D, sans illustration cartoon, %s.',
            $topic !== '' ? $topic : 'le risque amiante',
            $scene,
            $focus
        );
    }

    private function clusterForKeyword(string $keyword): string
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
}
