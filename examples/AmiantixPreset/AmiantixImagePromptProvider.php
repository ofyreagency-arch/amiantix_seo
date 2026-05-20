<?php

declare(strict_types=1);

namespace Ofyre\SeoEngine\Examples\AmiantixPreset;

use Ofyre\SeoEngine\Contracts\ImagePromptProvider;

final class AmiantixImagePromptProvider implements ImagePromptProvider
{
    public function promptFor(string $keyword, ?string $cluster): string
    {
        return sprintf(
            'Illustration editoriale photo-realiste pour un article Amiantix sur "%s". Montrer un contexte professionnel batiment ou chantier, avec signalisation, protection des intervenants, lecture documentaire et ambiance serieuse. Cluster: %s. Eviter toute dramatisation, logo, texte incruste ou rendu publicitaire.',
            $keyword,
            $cluster ?: 'amiante'
        );
    }
}
