<?php

declare(strict_types=1);

namespace App\RemoteInstallation;

use App\RemoteInstallation\Connectors\RemoteConnector;
use App\RemoteInstallation\Connectors\SftpRemoteConnector;
use App\RemoteInstallation\Connectors\SshRemoteConnector;
use App\RemoteInstallation\Exceptions\RemoteInstallationException;
use Illuminate\Support\Str;
use Throwable;

class InstallationPrecheckService
{
    /**
     * @param array<string,mixed> $data
     */
    public function run(array $data): InstallationReadinessReport
    {
        $validated = [];
        $warnings = [];
        $blockers = [];
        $autofixable = [];
        $manualActions = [];
        $detected = [
            'framework' => null,
            'php_version' => null,
            'composer_version' => null,
            'bridge_status' => null,
            'project_path' => $this->projectPath($data),
            'access_method' => (string) ($data['access_method'] ?? ''),
        ];

        $projectPath = $this->projectPath($data);

        if (! $this->isSafeProjectPath($projectPath)) {
            $blockers[] = $this->blocker(
                'project_path',
                'Chemin du projet invalide',
                'PraeviSEO a besoin d un chemin absolu valide vers le site, par exemple /var/www/mon-site.',
                false,
            );
            $manualActions[] = $this->item(
                'project_path_fix',
                'Chemin du projet à corriger',
                'Renseignez le vrai dossier du site avant de relancer le diagnostic.',
            );

            return $this->report($validated, $warnings, $blockers, $autofixable, $manualActions, $detected);
        }

        $connector = $this->connectorFor($data);

        try {
            $connector->connect();
            $validated[] = $this->item('ssh', 'SSH valide', 'Le serveur répond bien et PraeviSEO peut ouvrir une session sécurisée.');

            $projectDirectoryState = Str::lower($this->runRequired($connector, RemoteCommand::detectProjectDirectory($projectPath)));

            if (! str_contains($projectDirectoryState, 'present')) {
                $blockers[] = $this->blocker(
                    'project_directory',
                    'Dossier projet introuvable',
                    'PraeviSEO ne trouve pas le dossier du site à cet emplacement sur le serveur distant.',
                    false,
                );
                $manualActions[] = $this->item(
                    'project_directory_manual',
                    'Chemin du projet à vérifier',
                    'Confirmez le dossier exact du site sur le serveur avant de relancer le diagnostic.',
                );

                return $this->report($validated, $warnings, $blockers, $autofixable, $manualActions, $detected);
            }

            $validated[] = $this->item(
                'project_directory',
                'Dossier projet trouvé',
                sprintf('PraeviSEO a trouvé le site dans %s.', $projectPath)
            );

            $framework = $this->detectFramework($connector, $projectPath, (string) ($data['framework_hint'] ?? ''));
            $detected['framework'] = $framework;
            $validated[] = $this->item(
                'framework',
                'Framework détecté',
                sprintf('PraeviSEO a reconnu un site %s dans ce dossier.', ucfirst($framework))
            );

            $phpVersion = $this->runRequired($connector, RemoteCommand::detectPhpVersion($projectPath));
            $detected['php_version'] = $phpVersion;
            $validated[] = $this->item('php', 'PHP valide', sprintf('Version détectée : %s.', $phpVersion));

            $composerVersion = $this->runRequired($connector, RemoteCommand::detectComposer($projectPath));
            $detected['composer_version'] = $composerVersion;
            $validated[] = $this->item('composer', 'Composer valide', sprintf('Version détectée : %s.', $composerVersion));

            $writeAccess = Str::lower($this->runRequired($connector, RemoteCommand::detectWriteAccess($projectPath)));

            if (! str_contains($writeAccess, 'writable')) {
                $blockers[] = $this->blocker(
                    'permissions',
                    'Permissions insuffisantes',
                    'PraeviSEO ne peut pas écrire dans le dossier du projet pour installer le bridge et ses dépendances.',
                    true,
                );
                $autofixable[] = $this->item(
                    'permissions_autofix',
                    'Permissions simples',
                    'Si le système le permet, PraeviSEO pourra tenter une correction simple des droits avant l installation.',
                );
            } else {
                $validated[] = $this->item('permissions', 'Permissions valides', 'Le dossier du projet est accessible en écriture.');
            }

            if (in_array($framework, ['laravel', 'symfony'], true)) {
                $envState = Str::lower($this->runRequired($connector, RemoteCommand::detectEnvFile($projectPath)));

                if (! str_contains($envState, 'present')) {
                    $blockers[] = $this->blocker(
                        'env_file',
                        'Fichier .env manquant',
                        'PraeviSEO n a pas trouvé de fichier .env utilisable pour préparer l activation.',
                        false,
                    );
                    $manualActions[] = $this->item(
                        'env_file_manual',
                        'Créer ou restaurer le .env',
                        'Le site doit disposer de son fichier .env avant de poursuivre l installation.',
                    );
                } else {
                    $validated[] = $this->item('env_file', 'Fichier .env trouvé', 'PraeviSEO a trouvé la configuration principale du site.');

                    $appUrl = trim($this->runRequired($connector, RemoteCommand::detectAppUrl($projectPath)));

                    if ($appUrl === '' || Str::lower($appUrl) === 'missing') {
                        $blockers[] = $this->blocker(
                            'app_url',
                            'APP_URL absente',
                            'PraeviSEO a besoin de l URL publique du site pour finaliser proprement la connexion et l activation.',
                            true,
                        );
                        $autofixable[] = $this->item(
                            'app_url_autofix',
                            'APP_URL automatique',
                            'PraeviSEO pourra proposer puis injecter une APP_URL cohérente avant l installation.',
                        );
                    } else {
                        $validated[] = $this->item('app_url', 'APP_URL valide', sprintf('Valeur détectée : %s.', $appUrl));
                    }
                }
            }

            $workerCount = (int) trim($this->runOptional($connector, RemoteCommand::detectWorkerCount($projectPath)) ?: '0');

            if ($workerCount > 0) {
                $validated[] = $this->item('worker', 'Worker détecté', sprintf('%d worker(s) ont été repérés pour traiter la file ou le monitoring.', $workerCount));
            } else {
                $warnings[] = $this->item(
                    'worker',
                    'Aucun worker détecté',
                    'L installation peut continuer, mais certaines étapes asynchrones auront besoin d un worker ou d une queue active.',
                );
                $manualActions[] = $this->item(
                    'worker_manual',
                    'Worker à activer',
                    'Préparez un worker de queue ou un superviseur si vous voulez une activation entièrement fluide.',
                );
            }

            if ($this->bridgeInstalled($connector, $projectPath, $framework)) {
                $detected['bridge_status'] = 'installed';
                $validated[] = $this->item(
                    'bridge',
                    'Bridge PraeviSEO détecté',
                    'Le bridge PraeviSEO semble déjà présent sur le site. L installation pourra surtout valider et activer la connexion.',
                );
            } else {
                $detected['bridge_status'] = 'missing';
                $warnings[] = $this->item(
                    'bridge',
                    'Bridge PraeviSEO à installer',
                    'PraeviSEO devra encore installer le bridge officiel sur ce site pendant la vraie phase d installation.',
                );
                $autofixable[] = $this->item(
                    'bridge_install',
                    'Installation du bridge',
                    'PraeviSEO pourra installer automatiquement le bridge adapté au framework détecté.',
                );
            }

            $autofixable[] = $this->item(
                'cache',
                'Cache et autoload',
                'PraeviSEO pourra ensuite relancer optimize:clear, dump-autoload et le warmup nécessaire si besoin.',
            );
            $autofixable[] = $this->item(
                'bridge_migrations',
                'Migrations du bridge',
                'PraeviSEO pourra aussi lancer les migrations et la préparation du bridge pendant l installation réelle.',
            );
        } catch (RemoteInstallationException $exception) {
            $blockers[] = $this->blocker(
                'connectivity',
                'Connexion impossible',
                $exception->getMessage(),
                false,
            );
            $manualActions[] = $this->item(
                'connectivity_manual',
                'Accès serveur à vérifier',
                'Vérifiez l hôte, le port, l utilisateur, le secret SSH/SFTP et le chemin du projet avant de relancer.',
            );
        } catch (Throwable $exception) {
            $blockers[] = $this->blocker(
                'unexpected',
                'Diagnostic interrompu',
                $exception->getMessage() !== ''
                    ? $exception->getMessage()
                    : 'PraeviSEO n a pas pu terminer le diagnostic de préparation.',
                false,
            );
        } finally {
            $connector->disconnect();
        }

        return $this->report($validated, $warnings, $blockers, $autofixable, $manualActions, $detected);
    }

    /**
     * @param array<string,mixed> $data
     */
    private function connectorFor(array $data): RemoteConnector
    {
        $method = (string) ($data['access_method'] ?? 'ssh');

        return match ($method) {
            'ssh' => new SshRemoteConnector([
                'host' => (string) ($data['ssh_host'] ?? ''),
                'port' => (int) ($data['ssh_port'] ?? 22),
                'username' => (string) ($data['ssh_username'] ?? ''),
                'secret' => (string) ($data['ssh_secret'] ?? ''),
            ]),
            'sftp' => new SftpRemoteConnector([
                'host' => (string) ($data['sftp_host'] ?? ''),
                'port' => (int) ($data['sftp_port'] ?? 22),
                'username' => (string) ($data['sftp_username'] ?? ''),
                'password' => (string) ($data['sftp_password'] ?? ''),
            ]),
            default => throw RemoteInstallationException::unsupported(
                'Ce mode d accès n est pas encore supporté pour le diagnostic premium.'
            ),
        };
    }

    private function detectFramework(RemoteConnector $connector, string $projectPath, string $hint): string
    {
        $normalizedHint = Str::lower(trim($hint));

        if (in_array($normalizedHint, ['laravel', 'symfony', 'wordpress'], true)) {
            return $normalizedHint;
        }

        if ($connector->fileExists($projectPath.'/artisan')) {
            return 'laravel';
        }

        if ($connector->fileExists($projectPath.'/bin/console')) {
            return 'symfony';
        }

        if ($connector->fileExists($projectPath.'/wp-config.php')) {
            return 'wordpress';
        }

        throw RemoteInstallationException::detection(
            'PraeviSEO n a pas reconnu automatiquement Laravel, Symfony ou WordPress dans ce dossier.'
        );
    }

    private function runRequired(RemoteConnector $connector, RemoteCommand $command): string
    {
        $result = $connector->run($command, 60);

        if (! $result->successful) {
            throw RemoteInstallationException::execution(
                sprintf('La vérification "%s" n a pas pu aboutir sur le serveur distant.', $command->label)
            );
        }

        return trim($result->output);
    }

    private function runOptional(RemoteConnector $connector, RemoteCommand $command): ?string
    {
        $result = $connector->run($command, 30);

        if (! $result->successful) {
            return null;
        }

        return trim($result->output);
    }

    /**
     * @param array<string,mixed> $data
     */
    private function projectPath(array $data): string
    {
        return trim((string) ($data['ssh_project_path'] ?? $data['sftp_project_path'] ?? ''));
    }

    private function bridgeInstalled(RemoteConnector $connector, string $projectPath, string $framework): bool
    {
        return match ($framework) {
            'laravel' => $connector->fileExists($projectPath.'/vendor/praeviseo/laravel-bridge/composer.json'),
            'symfony' => $connector->fileExists($projectPath.'/vendor/praeviseo/symfony-bridge/composer.json'),
            'wordpress' => $connector->fileExists($projectPath.'/wp-content/plugins/praeviseo-wordpress-bridge/praeviseo-wordpress-bridge.php'),
            default => false,
        };
    }

    private function isSafeProjectPath(string $path): bool
    {
        if ($path === '' || ! str_starts_with($path, '/')) {
            return false;
        }

        return preg_match('/^[A-Za-z0-9_\/\.\-\s]+$/', $path) === 1;
    }

    /**
     * @param array<int,array{key:string,label:string,detail:string}> $validated
     * @param array<int,array{key:string,label:string,detail:string}> $warnings
     * @param array<int,array{key:string,label:string,detail:string,autofixable:bool}> $blockers
     * @param array<int,array{key:string,label:string,detail:string}> $autofixable
     * @param array<int,array{key:string,label:string,detail:string}> $manualActions
     * @param array<string,string|null> $detected
     */
    private function report(
        array $validated,
        array $warnings,
        array $blockers,
        array $autofixable,
        array $manualActions,
        array $detected,
    ): InstallationReadinessReport {
        $criticalWeights = [
            'project_path' => 55,
            'project_directory' => 50,
            'connectivity' => 60,
            'unexpected' => 45,
            'framework' => 40,
            'env_file' => 35,
            'app_url' => 35,
            'permissions' => 25,
        ];

        $warningPenalty = count($warnings) * 5;
        $blockerPenalty = array_sum(array_map(
            fn (array $item): int => $criticalWeights[$item['key']] ?? 20,
            $blockers,
        ));

        $score = max(0, min(100, 100 - $warningPenalty - $blockerPenalty));

        if ($blockers !== []) {
            $score = min($score, 59);
        }

        return new InstallationReadinessReport(
            $score,
            $validated,
            $warnings,
            $blockers,
            $autofixable,
            $manualActions,
            $detected,
        );
    }

    /**
     * @return array{key:string,label:string,detail:string}
     */
    private function item(string $key, string $label, string $detail): array
    {
        return compact('key', 'label', 'detail');
    }

    /**
     * @return array{key:string,label:string,detail:string,autofixable:bool}
     */
    private function blocker(string $key, string $label, string $detail, bool $autofixable): array
    {
        return compact('key', 'label', 'detail', 'autofixable');
    }
}
