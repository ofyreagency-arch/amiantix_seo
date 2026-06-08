<?php

declare(strict_types=1);

namespace App\RemoteInstallation\Strategies;

use App\Models\RemoteInstallation;
use App\Models\SeoSite;
use App\RemoteInstallation\Connectors\RemoteConnector;
use App\RemoteInstallation\Exceptions\RemoteInstallationException;
use App\RemoteInstallation\RemoteCommand;
use App\RemoteInstallation\RemoteEnvironment;
use App\RemoteInstallation\RemoteCommandResult;
use Illuminate\Support\Facades\Log;

class SymfonyInstallerStrategy implements InstallerStrategy
{
    /**
     * SECURITY: every remote command below must come from RemoteCommand.
     * This keeps Symfony installation steps reviewable and blocks arbitrary
     * shell execution from ever reaching the client server.
     */
    public function install(RemoteConnector $connector, RemoteInstallation $installation, SeoSite $site, RemoteEnvironment $environment): void
    {
        $databaseUrlResult = $connector->run(RemoteCommand::ensureSymfonyDatabaseUrl($environment->projectPath), 60);

        if (! $databaseUrlResult->successful) {
            throw RemoteInstallationException::execution('PraeviSEO n a pas pu préparer DATABASE_URL sur le site Symfony avant l installation.');
        }

        $allowPluginResult = $connector->run(RemoteCommand::allowSymfonyBridgePlugin($environment->projectPath), 60);

        if (! $allowPluginResult->successful) {
            throw RemoteInstallationException::execution('Composer n autorise pas encore le plugin officiel du bridge Symfony.');
        }

        if (! $connector->fileExists($environment->projectPath.'/vendor/doctrine/orm')) {
            $doctrineResult = $connector->run(RemoteCommand::installSymfonyDoctrine($environment->projectPath), 300);

            if (! $doctrineResult->successful) {
                throw RemoteInstallationException::execution('Doctrine ORM n a pas pu être préparé sur le site Symfony avant l installation du bridge.');
            }
        }

        $result = $connector->run(RemoteCommand::installSymfonyBridge($environment->projectPath), 240);

        if (! $result->successful) {
            throw RemoteInstallationException::execution('Composer n est pas disponible ou l installation PraeviSEO a échoué sur le site Symfony.');
        }

        $autoloadResult = $connector->run(RemoteCommand::dumpSymfonyAutoload($environment->projectPath), 180);

        if (! $autoloadResult->successful) {
            $this->logCommandFailure(
                phase: 'dump_symfony_autoload',
                installation: $installation,
                site: $site,
                environment: $environment,
                command: RemoteCommand::dumpSymfonyAutoload($environment->projectPath),
                result: $autoloadResult,
            );

            throw RemoteInstallationException::execution('Le bridge Symfony a été installé mais son auto-enregistrement Composer a échoué.');
        }

        $installation->markProgress(RemoteInstallation::STATUS_INSTALLING, 'package_installed', 55, 'Package PraeviSEO installé sur Symfony.');
    }

    public function configure(RemoteConnector $connector, RemoteInstallation $installation, SeoSite $site, RemoteEnvironment $environment): void
    {
        $code = $site->publicationConnectCode();

        if (! $code) {
            throw RemoteInstallationException::execution('Code de connexion PraeviSEO introuvable pour ce site.');
        }

        $siteUrl = rtrim((string) $site->url, '/');

        if ($siteUrl !== '') {
            $appUrlResult = $connector->run(RemoteCommand::ensureSymfonyAppUrl($environment->projectPath, $siteUrl), 60);

            if (! $appUrlResult->successful) {
                $this->logCommandFailure(
                    phase: 'ensure_symfony_app_url',
                    installation: $installation,
                    site: $site,
                    environment: $environment,
                    command: RemoteCommand::ensureSymfonyAppUrl($environment->projectPath, $siteUrl),
                    result: $appUrlResult,
                );

                throw RemoteInstallationException::execution('PraeviSEO n a pas pu aligner APP_URL sur le site Symfony avant la connexion.');
            }
        }

        $clearCommand = RemoteCommand::clearSymfonyCache($environment->projectPath);
        $clearResult = $connector->run($clearCommand, 180);

        if (! $clearResult->successful) {
            $this->logCommandFailure(
                phase: 'clear_symfony_cache',
                installation: $installation,
                site: $site,
                environment: $environment,
                command: $clearCommand,
                result: $clearResult,
            );

            throw RemoteInstallationException::execution('Le cache Symfony n a pas pu être préparé avant la connexion PraeviSEO.');
        }

        $praeviseoUrl = rtrim((string) config('app.url'), '/');

        if ($praeviseoUrl === '') {
            throw RemoteInstallationException::execution('L URL du cockpit PraeviSEO n est pas configurée côté moteur.');
        }

        $connectCommand = RemoteCommand::connectSymfony(
            $environment->projectPath,
            $code,
            $praeviseoUrl,
            $site->publicationPathPrefix() ?? 'ressources',
        );
        $connectResult = $connector->run($connectCommand, 180);

        if (! $connectResult->successful) {
            $this->logCommandFailure(
                phase: 'connect_symfony',
                installation: $installation,
                site: $site,
                environment: $environment,
                command: $connectCommand,
                result: $connectResult,
            );

            throw RemoteInstallationException::execution('PraeviSEO n a pas pu être configuré automatiquement sur Symfony.');
        }

        $installation->markProgress(RemoteInstallation::STATUS_CONFIGURING, 'symfony_connected', 75, 'Connexion PraeviSEO configurée sur Symfony.');
    }

    private function logCommandFailure(
        string $phase,
        RemoteInstallation $installation,
        SeoSite $site,
        RemoteEnvironment $environment,
        RemoteCommand $command,
        RemoteCommandResult $result,
    ): void {
        Log::error('Remote Symfony configuration command failed.', [
            'phase' => $phase,
            'installation_id' => $installation->id,
            'site_id' => $site->site_id,
            'framework' => $environment->framework,
            'project_path' => $environment->projectPath,
            'command_label' => $command->label,
            'command' => $command->command,
            'stdout' => $result->output,
            'stderr' => $result->errorOutput,
            'exit_code' => $result->exitCode,
            'successful' => $result->successful,
        ]);
    }

    public function activate(RemoteConnector $connector, RemoteInstallation $installation, SeoSite $site, RemoteEnvironment $environment): void
    {
        $schemaResult = $connector->run(RemoteCommand::updateSymfonyDoctrineSchema($environment->projectPath), 180);

        if (! $schemaResult->successful) {
            throw RemoteInstallationException::execution('PraeviSEO a été installé mais la table praeviseo_published_pages n a pas pu être préparée.');
        }

        $result = $connector->run(RemoteCommand::clearSymfonyCache($environment->projectPath), 180);

        if (! $result->successful) {
            throw RemoteInstallationException::execution('PraeviSEO a été installé mais l activation finale Symfony a échoué.');
        }

        $installation->markProgress(RemoteInstallation::STATUS_ACTIVATING, 'symfony_activated', 90, 'Activation finale Symfony terminée.');
    }
}
