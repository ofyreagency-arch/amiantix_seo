<?php

declare(strict_types=1);

namespace App\RemoteInstallation;

use App\Models\RemoteInstallation;
use App\Models\SeoSite;
use App\RemoteInstallation\Connectors\RemoteConnector;
use App\RemoteInstallation\Connectors\SftpRemoteConnector;
use App\RemoteInstallation\Connectors\SshRemoteConnector;
use App\RemoteInstallation\Exceptions\RemoteInstallationException;
use App\RemoteInstallation\Strategies\InstallerStrategy;
use App\RemoteInstallation\Strategies\LaravelInstallerStrategy;
use App\RemoteInstallation\Strategies\SymfonyInstallerStrategy;
use Illuminate\Support\Str;
use Throwable;

class RemoteInstallationService
{
    public function run(RemoteInstallation $installation): void
    {
        $site = $installation->site()->firstOrFail();
        $connector = $this->connectorFor($installation);

        try {
            $installation->markProgress(RemoteInstallation::STATUS_CONNECTING, 'connecting_to_server', 10, 'Connexion sécurisée au serveur distant.');
            $connector->connect();

            $installation->markProgress(RemoteInstallation::STATUS_DETECTING, 'detecting_environment', 25, 'Détection de l’environnement du site.');
            $environment = $this->detectEnvironment($connector, $installation);

            $installation->forceFill([
                'detected_framework' => $environment->framework,
                'detected_php_version' => $environment->phpVersion,
                'detected_composer' => $environment->composerVersion,
            ])->save();

            $strategy = $this->strategyFor($environment->framework);

            $installation->markProgress(RemoteInstallation::STATUS_INSTALLING, 'installing_praeviseo', 40, 'Installation du package PraeviSEO sur le site.');
            $strategy->install($connector, $installation, $site, $environment);

            $installation->markProgress(RemoteInstallation::STATUS_CONFIGURING, 'configuring_site', 65, 'Configuration automatique de PraeviSEO sur le site.');
            $strategy->configure($connector, $installation, $site, $environment);

            $installation->markProgress(RemoteInstallation::STATUS_ACTIVATING, 'activating_monitoring', 85, 'Activation du monitoring et vérification de la connexion.');
            $strategy->activate($connector, $installation, $site, $environment);

            $site->refresh();

            if ($site->publicationBridgeStatus() !== 'connected') {
                throw RemoteInstallationException::execution(
                    'PraeviSEO a été installé mais la connexion finale au site n a pas encore été confirmée.'
                );
            }

            $installation->markProgress(RemoteInstallation::STATUS_COMPLETED, 'completed', 100, 'PraeviSEO est maintenant actif sur le site.');
        } catch (RemoteInstallationException $exception) {
            $installation->markProgress(RemoteInstallation::STATUS_FAILED, 'failed', 100, $exception->getMessage());
            throw $exception;
        } catch (Throwable $exception) {
            $installation->markProgress(
                RemoteInstallation::STATUS_FAILED,
                'failed',
                100,
                'PraeviSEO n a pas pu terminer l installation distante pour le moment.'
            );

            throw $exception;
        } finally {
            $connector->disconnect();
        }
    }

    private function connectorFor(RemoteInstallation $installation): RemoteConnector
    {
        $credentials = $installation->encrypted_credentials ?? [];

        return match ($installation->connection_type) {
            'ssh' => new SshRemoteConnector([
                'host' => (string) ($credentials['host'] ?? ''),
                'port' => (int) ($credentials['port'] ?? 22),
                'username' => (string) ($credentials['username'] ?? ''),
                'secret' => (string) ($credentials['secret'] ?? ''),
            ]),
            'sftp' => new SftpRemoteConnector([
                'host' => (string) ($credentials['host'] ?? ''),
                'port' => (int) ($credentials['port'] ?? 22),
                'username' => (string) ($credentials['username'] ?? ''),
                'password' => (string) ($credentials['password'] ?? ''),
            ]),
            default => throw RemoteInstallationException::unsupported(
                'Ce mode d accès n est pas encore supporté pour l installation distante.'
            ),
        };
    }

    private function detectEnvironment(RemoteConnector $connector, RemoteInstallation $installation): RemoteEnvironment
    {
        $metadata = $installation->connection_metadata ?? [];
        $projectPath = trim((string) ($metadata['project_path'] ?? ''));

        if (! $this->isSafeProjectPath($projectPath)) {
            throw RemoteInstallationException::invalidPath();
        }

        $framework = $this->detectFramework($connector, $projectPath, (string) ($metadata['framework_hint'] ?? ''));
        $phpVersion = $this->runRequired($connector, $this->withinProject($projectPath, 'php -r "echo PHP_VERSION;"'), 'PHP est introuvable sur le serveur.');
        $composerVersion = $this->runRequired($connector, $this->withinProject($projectPath, 'composer --version'), 'Composer est introuvable sur le serveur.');
        $writeAccess = $this->runRequired($connector, $this->withinProject($projectPath, '[ -w . ] && echo writable || echo not_writable'), 'Impossible de vérifier les permissions du projet.');

        if (! str_contains(Str::lower($writeAccess), 'writable')) {
            throw RemoteInstallationException::execution('PraeviSEO ne peut pas écrire dans le dossier du projet distant.');
        }

        return new RemoteEnvironment(
            framework: $framework,
            phpVersion: trim($phpVersion),
            composerVersion: trim($composerVersion),
            projectWritable: true,
            projectPath: $projectPath,
        );
    }

    private function detectFramework(RemoteConnector $connector, string $projectPath, string $hint): string
    {
        $normalizedHint = Str::lower(trim($hint));

        if (in_array($normalizedHint, ['laravel', 'symfony'], true)) {
            return $normalizedHint;
        }

        if ($connector->fileExists($projectPath.'/artisan')) {
            return 'laravel';
        }

        if ($connector->fileExists($projectPath.'/bin/console')) {
            return 'symfony';
        }

        if ($connector->fileExists($projectPath.'/wp-config.php')) {
            throw RemoteInstallationException::unsupported(
                'Le support WordPress distant n est pas encore activé automatiquement sur cette version.'
            );
        }

        throw RemoteInstallationException::detection(
            'PraeviSEO n a pas reconnu automatiquement Laravel ou Symfony dans ce dossier.'
        );
    }

    private function strategyFor(string $framework): InstallerStrategy
    {
        return match ($framework) {
            'laravel' => new LaravelInstallerStrategy(),
            'symfony' => new SymfonyInstallerStrategy(),
            default => throw RemoteInstallationException::unsupported(
                'PraeviSEO ne sait pas encore installer automatiquement ce framework.'
            ),
        };
    }

    private function runRequired(RemoteConnector $connector, string $command, string $errorMessage): string
    {
        $result = $connector->run($command, 60);

        if (! $result->successful || trim($result->output) === '') {
            throw RemoteInstallationException::execution($errorMessage);
        }

        return trim($result->output);
    }

    private function withinProject(string $path, string $command): string
    {
        return 'cd '.$this->quote($path).' && '.$command;
    }

    private function quote(string $value): string
    {
        return "'".str_replace("'", "'\"'\"'", $value)."'";
    }

    private function isSafeProjectPath(string $path): bool
    {
        if ($path === '' || ! str_starts_with($path, '/')) {
            return false;
        }

        return preg_match('/^[A-Za-z0-9_\/\.\-\s]+$/', $path) === 1;
    }
}
