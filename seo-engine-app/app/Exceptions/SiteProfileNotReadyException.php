<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

final class SiteProfileNotReadyException extends RuntimeException
{
    public static function forSite(string $siteId, string $status): self
    {
        return new self(sprintf(
            'Le profil métier du site %s n est pas prêt (statut: %s). La génération est bloquée jusqu à la fin de l analyse automatique.',
            $siteId,
            $status !== '' ? $status : 'pending',
        ));
    }
}
