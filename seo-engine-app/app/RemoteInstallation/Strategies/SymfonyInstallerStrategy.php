<?php

declare(strict_types=1);

namespace App\RemoteInstallation\Strategies;

use App\Models\RemoteInstallation;
use App\Models\SeoSite;
use App\RemoteInstallation\Connectors\RemoteConnector;
use App\RemoteInstallation\Exceptions\RemoteInstallationException;
use App\RemoteInstallation\RemoteCommand;
use App\RemoteInstallation\RemoteEnvironment;

class SymfonyInstallerStrategy implements InstallerStrategy
{
    /**
     * SECURITY: every remote command below must come from RemoteCommand.
     * This keeps Symfony installation steps reviewable and blocks arbitrary
     * shell execution from ever reaching the client server.
     */
    public function install(RemoteConnector $connector, RemoteInstallation $installation, SeoSite $site, RemoteEnvironment $environment): void
    {
        $result = $connector->run(RemoteCommand::installSymfonyBridge($environment->projectPath), 240);

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

        $clearResult = $connector->run(RemoteCommand::clearSymfonyCache($environment->projectPath), 180);

        if (! $clearResult->successful) {
            throw RemoteInstallationException::execution('Le cache Symfony n a pas pu être préparé avant la connexion PraeviSEO.');
        }

        $connectResult = $connector->run(RemoteCommand::connectSymfony($environment->projectPath, $code), 180);

        if (! $connectResult->successful) {
            throw RemoteInstallationException::execution('PraeviSEO n a pas pu être configuré automatiquement sur Symfony.');
        }

        $installation->markProgress(RemoteInstallation::STATUS_CONFIGURING, 'symfony_connected', 75, 'Connexion PraeviSEO configurée sur Symfony.');
    }

    public function activate(RemoteConnector $connector, RemoteInstallation $installation, SeoSite $site, RemoteEnvironment $environment): void
    {
        $result = $connector->run(RemoteCommand::clearSymfonyCache($environment->projectPath), 180);

        if (! $result->successful) {
            throw RemoteInstallationException::execution('PraeviSEO a été installé mais l activation finale Symfony a échoué.');
        }

        $installation->markProgress(RemoteInstallation::STATUS_ACTIVATING, 'symfony_activated', 90, 'Activation finale Symfony terminée.');
    }
}
