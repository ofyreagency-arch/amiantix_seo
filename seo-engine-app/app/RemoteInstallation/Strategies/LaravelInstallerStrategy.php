<?php

declare(strict_types=1);

namespace App\RemoteInstallation\Strategies;

use App\Models\RemoteInstallation;
use App\Models\SeoSite;
use App\RemoteInstallation\Connectors\RemoteConnector;
use App\RemoteInstallation\Exceptions\RemoteInstallationException;
use App\RemoteInstallation\RemoteEnvironment;

class LaravelInstallerStrategy implements InstallerStrategy
{
    public function install(RemoteConnector $connector, RemoteInstallation $installation, SeoSite $site, RemoteEnvironment $environment): void
    {
        $result = $connector->run($this->withinProject(
            $environment->projectPath,
            'composer require praeviseo/laravel-bridge --no-interaction --no-progress'
        ), 240);

        if (! $result->successful) {
            throw RemoteInstallationException::execution('Composer n est pas disponible ou l installation PraeviSEO a échoué sur le site Laravel.');
        }

        $installation->markProgress(RemoteInstallation::STATUS_INSTALLING, 'package_installed', 55, 'Package PraeviSEO installé sur Laravel.');
    }

    public function configure(RemoteConnector $connector, RemoteInstallation $installation, SeoSite $site, RemoteEnvironment $environment): void
    {
        $code = $site->publicationConnectCode();

        if (! $code) {
            throw RemoteInstallationException::execution('Code de connexion PraeviSEO introuvable pour ce site.');
        }

        $result = $connector->run($this->withinProject(
            $environment->projectPath,
            sprintf('php artisan praeviseo:connect %s', $this->quote($code))
        ), 180);

        if (! $result->successful) {
            throw RemoteInstallationException::execution('PraeviSEO n a pas pu être configuré automatiquement sur Laravel.');
        }

        $installation->markProgress(RemoteInstallation::STATUS_CONFIGURING, 'laravel_connected', 75, 'Connexion PraeviSEO configurée sur Laravel.');
    }

    public function activate(RemoteConnector $connector, RemoteInstallation $installation, SeoSite $site, RemoteEnvironment $environment): void
    {
        $result = $connector->run($this->withinProject(
            $environment->projectPath,
            'php artisan optimize:clear'
        ), 120);

        if (! $result->successful) {
            throw RemoteInstallationException::execution('PraeviSEO a été installé mais l activation finale Laravel a échoué.');
        }

        $installation->markProgress(RemoteInstallation::STATUS_ACTIVATING, 'laravel_activated', 90, 'Activation finale Laravel terminée.');
    }

    private function withinProject(string $path, string $command): string
    {
        return 'cd '.$this->quote($path).' && '.$command;
    }

    private function quote(string $value): string
    {
        return "'".str_replace("'", "'\"'\"'", $value)."'";
    }
}
