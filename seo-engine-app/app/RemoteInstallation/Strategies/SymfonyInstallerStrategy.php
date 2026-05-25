<?php

declare(strict_types=1);

namespace App\RemoteInstallation\Strategies;

use App\Models\RemoteInstallation;
use App\Models\SeoSite;
use App\RemoteInstallation\Connectors\RemoteConnector;
use App\RemoteInstallation\Exceptions\RemoteInstallationException;
use App\RemoteInstallation\RemoteEnvironment;

class SymfonyInstallerStrategy implements InstallerStrategy
{
    public function install(RemoteConnector $connector, RemoteInstallation $installation, SeoSite $site, RemoteEnvironment $environment): void
    {
        $result = $connector->run($this->withinProject(
            $environment->projectPath,
            'composer require praeviseo/symfony-bridge --no-interaction --no-progress --no-scripts'
        ), 240);

        if (! $result->successful) {
            throw RemoteInstallationException::execution('Composer n est pas disponible ou l installation PraeviSEO a échoué sur le site Symfony.');
        }

        $installation->markProgress(RemoteInstallation::STATUS_INSTALLING, 'package_installed', 55, 'Package PraeviSEO installé sur Symfony.');
    }

    public function configure(RemoteConnector $connector, RemoteInstallation $installation, SeoSite $site, RemoteEnvironment $environment): void
    {
        $code = $site->publicationConnectCode();

        if (! $code) {
            throw RemoteInstallationException::execution('Code de connexion PraeviSEO introuvable pour ce site.');
        }

        $clearResult = $connector->run($this->withinProject(
            $environment->projectPath,
            'php bin/console cache:clear'
        ), 180);

        if (! $clearResult->successful) {
            throw RemoteInstallationException::execution('Le cache Symfony n a pas pu être préparé avant la connexion PraeviSEO.');
        }

        $connectResult = $connector->run($this->withinProject(
            $environment->projectPath,
            sprintf('php bin/console praeviseo:connect %s', $this->quote($code))
        ), 180);

        if (! $connectResult->successful) {
            throw RemoteInstallationException::execution('PraeviSEO n a pas pu être configuré automatiquement sur Symfony.');
        }

        $installation->markProgress(RemoteInstallation::STATUS_CONFIGURING, 'symfony_connected', 75, 'Connexion PraeviSEO configurée sur Symfony.');
    }

    public function activate(RemoteConnector $connector, RemoteInstallation $installation, SeoSite $site, RemoteEnvironment $environment): void
    {
        $result = $connector->run($this->withinProject(
            $environment->projectPath,
            'php bin/console cache:clear'
        ), 180);

        if (! $result->successful) {
            throw RemoteInstallationException::execution('PraeviSEO a été installé mais l activation finale Symfony a échoué.');
        }

        $installation->markProgress(RemoteInstallation::STATUS_ACTIVATING, 'symfony_activated', 90, 'Activation finale Symfony terminée.');
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
