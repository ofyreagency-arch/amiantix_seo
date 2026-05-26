<?php

declare(strict_types=1);

namespace App\RemoteInstallation\Strategies;

use App\Models\RemoteInstallation;
use App\Models\SeoSite;
use App\RemoteInstallation\Connectors\RemoteConnector;
use App\RemoteInstallation\Exceptions\RemoteInstallationException;
use App\RemoteInstallation\RemoteCommand;
use App\RemoteInstallation\RemoteEnvironment;

class LaravelInstallerStrategy implements InstallerStrategy
{
    /**
     * SECURITY: every remote command below must come from RemoteCommand.
     * No request field or user-controlled shell fragment may ever reach the
     * connector directly, otherwise PraeviSEO would become an RCE vector.
     */
    public function install(RemoteConnector $connector, RemoteInstallation $installation, SeoSite $site, RemoteEnvironment $environment): void
    {
        $result = $connector->run(RemoteCommand::installLaravelBridge($environment->projectPath), 240);

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

        $result = $connector->run(RemoteCommand::connectLaravel($environment->projectPath, $code), 180);

        if (! $result->successful) {
            throw RemoteInstallationException::execution('PraeviSEO n a pas pu être configuré automatiquement sur Laravel.');
        }

        $installation->markProgress(RemoteInstallation::STATUS_CONFIGURING, 'laravel_connected', 75, 'Connexion PraeviSEO configurée sur Laravel.');
    }

    public function activate(RemoteConnector $connector, RemoteInstallation $installation, SeoSite $site, RemoteEnvironment $environment): void
    {
        $result = $connector->run(RemoteCommand::clearLaravelCache($environment->projectPath), 120);

        if (! $result->successful) {
            throw RemoteInstallationException::execution('PraeviSEO a été installé mais l activation finale Laravel a échoué.');
        }

        $installation->markProgress(RemoteInstallation::STATUS_ACTIVATING, 'laravel_activated', 90, 'Activation finale Laravel terminée.');
    }
}
