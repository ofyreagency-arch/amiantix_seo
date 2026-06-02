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
            'framework_version' => null,
            'queue_driver' => null,
            'disk_free_mb' => null,
            'praeviseo_url' => null,
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
            $this->evaluatePhpVersion($phpVersion, $warnings, $blockers, $manualActions);

            $composerVersion = $this->runRequired($connector, RemoteCommand::detectComposer($projectPath));
            $detected['composer_version'] = $composerVersion;
            $validated[] = $this->item('composer', 'Composer valide', sprintf('Version détectée : %s.', $composerVersion));

            $frameworkVersion = $this->runOptional($connector, RemoteCommand::detectFrameworkVersion($projectPath, $framework));
            if ($frameworkVersion !== null && trim($frameworkVersion) !== '' && Str::lower(trim($frameworkVersion)) !== 'unknown') {
                $detected['framework_version'] = trim($frameworkVersion);
                $validated[] = $this->item('framework_version', 'Version framework détectée', sprintf('Version détectée : %s.', trim($frameworkVersion)));
            }

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

            $phpExtensions = $this->runOptional($connector, RemoteCommand::detectInstalledPhpExtensions($projectPath));
            if ($phpExtensions !== null) {
                $this->evaluatePhpExtensions($phpExtensions, $warnings, $blockers, $manualActions);
            }

            $diskFreeMb = (int) trim($this->runOptional($connector, RemoteCommand::detectDiskFreeMegabytes($projectPath)) ?: '0');
            if ($diskFreeMb > 0) {
                $detected['disk_free_mb'] = (string) $diskFreeMb;

                if ($diskFreeMb < 256) {
                    $blockers[] = $this->blocker(
                        'disk_space',
                        'Espace disque insuffisant',
                        sprintf('Le serveur ne laisse qu environ %d Mo disponibles dans le dossier projet.', $diskFreeMb),
                        false,
                    );
                    $manualActions[] = $this->item(
                        'disk_space_manual',
                        'Libérer de l espace disque',
                        'Libérez de la place avant de lancer l installation pour éviter un échec pendant Composer ou le cache.',
                    );
                } elseif ($diskFreeMb < 1024) {
                    $warnings[] = $this->item(
                        'disk_space',
                        'Espace disque limité',
                        sprintf('Le serveur laisse environ %d Mo libres. L installation peut passer, mais reste serrée.', $diskFreeMb),
                    );
                } else {
                    $validated[] = $this->item('disk_space', 'Espace disque suffisant', sprintf('Environ %d Mo restent disponibles pour l installation.', $diskFreeMb));
                }
            }

            $storageAccess = Str::lower($this->runOptional($connector, RemoteCommand::detectStorageWriteAccess($projectPath, $framework)) ?: 'unknown');
            if (str_starts_with($storageAccess, 'not_writable:')) {
                $path = Str::after($storageAccess, 'not_writable:');
                $blockers[] = $this->blocker(
                    'storage_permissions',
                    'Droits d écriture incomplets',
                    sprintf('PraeviSEO ne peut pas écrire correctement dans %s.', $path),
                    true,
                );
                $autofixable[] = $this->item(
                    'storage_permissions_autofix',
                    'Droits storage/cache/logs',
                    'PraeviSEO pourra tenter une correction simple des droits sur les dossiers techniques du framework.',
                );
            } elseif ($storageAccess === 'writable') {
                $validated[] = $this->item('storage_permissions', 'Droits techniques valides', 'Les dossiers storage/cache/logs nécessaires sont accessibles en écriture.');
            }

            $databaseState = Str::lower(trim($this->runOptional($connector, RemoteCommand::detectDatabaseAccess($projectPath, $framework)) ?: 'unknown'));
            if (in_array($databaseState, ['ok', 'configured'], true)) {
                $validated[] = $this->item('database', 'Accès base de données détecté', 'La configuration base de données nécessaire au site semble bien présente.');
            } elseif ($databaseState === 'missing') {
                $warnings[] = $this->item(
                    'database',
                    'Base de données à vérifier',
                    'PraeviSEO n a pas pu confirmer la configuration base de données depuis ce diagnostic.',
                );
                $manualActions[] = $this->item(
                    'database_manual',
                    'Configuration base à confirmer',
                    'Confirmez que le site peut déjà se connecter à sa base avant l installation premium.',
                );
            }

            $internetState = Str::lower(trim($this->runOptional($connector, RemoteCommand::detectInternetConnectivity($projectPath)) ?: 'unknown'));
            if ($internetState === 'ok') {
                $validated[] = $this->item('internet', 'Connectivité internet détectée', 'Le serveur semble bien pouvoir joindre les dépendances externes nécessaires.');
            } elseif ($internetState === 'missing') {
                $warnings[] = $this->item(
                    'internet',
                    'Connectivité internet à confirmer',
                    'PraeviSEO n a pas pu vérifier clairement l accès sortant du serveur vers les dépendances Composer.',
                );
            }

            $queueDriver = trim($this->runOptional($connector, RemoteCommand::detectQueueDriver($projectPath)) ?: '');
            if ($queueDriver !== '' && Str::lower($queueDriver) !== 'missing') {
                $detected['queue_driver'] = $queueDriver;
                $validated[] = $this->item('queue_driver', 'Queue détectée', sprintf('Driver détecté : %s.', $queueDriver));
            } else {
                $warnings[] = $this->item(
                    'queue_driver',
                    'Queue non détectée',
                    'PraeviSEO n a pas trouvé de driver de queue explicite dans le .env du site.',
                );
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

            $schedulerEntries = (int) trim($this->runOptional($connector, RemoteCommand::detectSchedulerEntries($projectPath)) ?: '0');
            if ($schedulerEntries > 0) {
                $validated[] = $this->item('scheduler', 'Scheduler détecté', sprintf('%d entrée(s) cron liées au scheduler ou au worker ont été repérées.', $schedulerEntries));
            } else {
                $warnings[] = $this->item(
                    'scheduler',
                    'Aucun scheduler détecté',
                    'PraeviSEO n a pas trouvé de cron scheduler évident pour relancer les tâches automatiques.',
                );
                $manualActions[] = $this->item(
                    'scheduler_manual',
                    'Scheduler à activer',
                    'Ajoutez un cron ou un scheduler si vous voulez que les automatisations tournent sans intervention.',
                );
            }

            $supervisorProcesses = (int) trim($this->runOptional($connector, RemoteCommand::detectSupervisorProcesses($projectPath)) ?: '0');
            if ($supervisorProcesses > 0) {
                $validated[] = $this->item('supervisor', 'Supervisor détecté', sprintf('%d processus Supervisor ont été repérés.', $supervisorProcesses));
            } else {
                $warnings[] = $this->item(
                    'supervisor',
                    'Supervisor non détecté',
                    'Aucun superviseur de worker n a été repéré pour fiabiliser les tâches de fond.',
                );
            }

            $redisState = Str::lower(trim($this->runOptional($connector, RemoteCommand::detectRedisAvailability($projectPath)) ?: 'missing'));
            if (str_contains($redisState, 'pong') || $redisState === 'extension') {
                $validated[] = $this->item('redis', 'Redis détecté', 'Le serveur semble déjà prêt pour un driver Redis ou un cache plus robuste.');
            } else {
                $warnings[] = $this->item(
                    'redis',
                    'Redis non détecté',
                    'PraeviSEO peut fonctionner sans Redis, mais la queue et le cache avancé seront moins robustes.',
                );
            }

            if ($this->bridgeInstalled($connector, $projectPath, $framework)) {
                $detected['bridge_status'] = 'installed';
                $validated[] = $this->item(
                    'bridge',
                    'Bridge PraeviSEO détecté',
                    'Le bridge PraeviSEO semble déjà présent sur le site. L installation pourra surtout valider et activer la connexion.',
                );

                $praeviseoCommand = Str::lower(trim($this->runOptional($connector, RemoteCommand::detectPraeviseoConnectCommand($projectPath, $framework)) ?: 'missing'));
                if ($praeviseoCommand === 'present') {
                    $validated[] = $this->item(
                        'praeviseo_command',
                        'Commande PraeviSEO détectée',
                        'La commande de connexion PraeviSEO est déjà disponible dans le framework.',
                    );
                } else {
                    $warnings[] = $this->item(
                        'praeviseo_command',
                        'Commande PraeviSEO à vérifier',
                        'Le bridge semble présent, mais la commande de connexion n a pas encore été confirmée.',
                    );
                }

                $praeviseoUrl = trim($this->runOptional($connector, RemoteCommand::detectPraeviseoUrl($projectPath, $framework)) ?: '');
                if ($praeviseoUrl === '' || Str::lower($praeviseoUrl) === 'missing') {
                    $praeviseoUrl = 'https://app.praeviseo.com';
                }

                $detected['praeviseo_url'] = $praeviseoUrl;

                $praeviseoHost = parse_url($praeviseoUrl, PHP_URL_HOST);
                if (is_string($praeviseoHost) && $praeviseoHost !== '') {
                    $apiDnsState = Str::lower(trim($this->runOptional($connector, RemoteCommand::detectDomainDns($projectPath, $praeviseoHost)) ?: 'unknown'));
                    if ($apiDnsState === 'ok') {
                        $validated[] = $this->item(
                            'praeviseo_api_dns',
                            'API PraeviSEO résolue',
                            sprintf('Le serveur résout bien le domaine %s utilisé par le bridge.', $praeviseoHost),
                        );
                    } else {
                        $blockers[] = $this->blocker(
                            'praeviseo_api_dns',
                            'API PraeviSEO introuvable',
                            sprintf('Le serveur ne résout pas le domaine %s utilisé par le bridge.', $praeviseoHost),
                            false,
                        );
                        $manualActions[] = $this->item(
                            'praeviseo_api_dns_manual',
                            'URL API PraeviSEO à corriger',
                            'Corrigez PRAEVISEO_URL ou pointez le bridge vers le vrai domaine PraeviSEO avant l installation.',
                        );
                    }
                }

                $apiConnectUrl = rtrim($praeviseoUrl, '/').'/api/bridge/connect';
                $apiHttpsState = Str::lower(trim($this->runOptional($connector, RemoteCommand::detectHttpsStatus($projectPath, $apiConnectUrl)) ?: 'unknown'));
                if ($apiHttpsState === 'ok') {
                    $validated[] = $this->item(
                        'praeviseo_api_https',
                        'API PraeviSEO joignable en HTTPS',
                        'Le bridge peut déjà joindre l endpoint HTTPS de connexion PraeviSEO.',
                    );
                } elseif (($blockers[array_key_last($blockers)]['key'] ?? null) !== 'praeviseo_api_dns') {
                    $warnings[] = $this->item(
                        'praeviseo_api_https',
                        'API PraeviSEO à vérifier',
                        'Le bridge n a pas encore confirmé l accès HTTPS à l endpoint /api/bridge/connect de PraeviSEO.',
                    );
                }
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

            if (in_array($framework, ['laravel', 'symfony'], true) && isset($appUrl) && $appUrl !== '' && Str::lower($appUrl) !== 'missing') {
                $host = parse_url($appUrl, PHP_URL_HOST);
                $scheme = parse_url($appUrl, PHP_URL_SCHEME);

                if (is_string($host) && $host !== '') {
                    $dnsState = Str::lower(trim($this->runOptional($connector, RemoteCommand::detectDomainDns($projectPath, $host)) ?: 'unknown'));
                    if ($dnsState === 'ok') {
                        $validated[] = $this->item('dns', 'DNS du domaine détecté', sprintf('Le domaine %s répond déjà côté DNS.', $host));
                    } elseif ($dnsState === 'missing') {
                        $warnings[] = $this->item(
                            'dns',
                            'DNS du domaine à vérifier',
                            sprintf('PraeviSEO n a pas confirmé la résolution DNS de %s depuis le serveur.', $host),
                        );
                    }
                }

                if (is_string($scheme) && Str::lower($scheme) === 'https') {
                    $httpsState = Str::lower(trim($this->runOptional($connector, RemoteCommand::detectHttpsStatus($projectPath, $appUrl)) ?: 'unknown'));
                    if ($httpsState === 'ok') {
                        $validated[] = $this->item('https', 'HTTPS détecté', 'Le site répond déjà correctement en HTTPS.');
                    } elseif ($httpsState === 'missing') {
                        $warnings[] = $this->item(
                            'https',
                            'HTTPS à vérifier',
                            'PraeviSEO n a pas réussi à confirmer le certificat ou la réponse HTTPS du site.',
                        );
                    }
                }
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
            'storage_permissions' => 30,
            'php_version' => 45,
            'php_extensions' => 40,
            'disk_space' => 25,
            'praeviseo_api_dns' => 50,
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

        $technicalPenalty = 0;
        $installationPenalty = 0;
        foreach ($blockers as $blocker) {
            $key = $blocker['key'];
            if (in_array($key, ['connectivity', 'project_path', 'project_directory', 'permissions', 'storage_permissions', 'php_version', 'php_extensions', 'disk_space'], true)) {
                $technicalPenalty += $criticalWeights[$key] ?? 20;
            }

            if (in_array($key, ['framework', 'env_file', 'app_url', 'praeviseo_api_dns'], true)) {
                $installationPenalty += $criticalWeights[$key] ?? 20;
            }
        }

        foreach ($warnings as $warning) {
            $key = $warning['key'];
            if (in_array($key, ['worker', 'scheduler', 'supervisor', 'redis', 'database', 'dns', 'https', 'queue_driver', 'internet', 'praeviseo_api_https'], true)) {
                $installationPenalty += 5;
            } else {
                $technicalPenalty += 4;
            }
        }

        $technicalScore = max(0, 100 - min(100, $technicalPenalty));
        $installationScore = max(0, 100 - min(100, $installationPenalty));

        return new InstallationReadinessReport(
            $score,
            $validated,
            $warnings,
            $blockers,
            $autofixable,
            $manualActions,
            $detected,
            [
                'global' => $score,
                'technical' => $technicalScore,
                'installation' => $installationScore,
            ],
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

    /**
     * @param array<int,array{key:string,label:string,detail:string}> $warnings
     * @param array<int,array{key:string,label:string,detail:string,autofixable:bool}> $blockers
     * @param array<int,array{key:string,label:string,detail:string}> $manualActions
     */
    private function evaluatePhpVersion(string $phpVersion, array &$warnings, array &$blockers, array &$manualActions): void
    {
        if (preg_match('/(\d+\.\d+\.\d+)/', $phpVersion, $matches) !== 1) {
            return;
        }

        $normalized = $matches[1];

        if (version_compare($normalized, '8.2.0', '<')) {
            $blockers[] = $this->blocker(
                'php_version',
                'Version PHP trop ancienne',
                sprintf('PraeviSEO demande au minimum PHP 8.2. La version détectée est %s.', $normalized),
                false,
            );
            $manualActions[] = $this->item(
                'php_version_manual',
                'Mettre PHP à jour',
                'Passez le site sur une version PHP plus récente avant de lancer l installation premium.',
            );
        } elseif (version_compare($normalized, '8.3.0', '<')) {
            $warnings[] = $this->item(
                'php_version',
                'Version PHP acceptable mais à surveiller',
                sprintf('La version %s peut fonctionner, mais PraeviSEO est plus à l aise sur PHP 8.3 ou plus.', $normalized),
            );
        }
    }

    /**
     * @param array<int,array{key:string,label:string,detail:string}> $warnings
     * @param array<int,array{key:string,label:string,detail:string,autofixable:bool}> $blockers
     * @param array<int,array{key:string,label:string,detail:string}> $manualActions
     */
    private function evaluatePhpExtensions(string $phpExtensions, array &$warnings, array &$blockers, array &$manualActions): void
    {
        $loaded = collect(preg_split('/\R+/', Str::lower($phpExtensions)) ?: [])
            ->map(static fn (?string $line): string => trim((string) $line))
            ->filter()
            ->values()
            ->all();

        $required = ['ctype', 'curl', 'iconv', 'json', 'mbstring', 'openssl', 'pdo', 'tokenizer', 'xml'];
        $missing = array_values(array_diff($required, $loaded));

        if ($missing === []) {
            return;
        }

        $blockers[] = $this->blocker(
            'php_extensions',
            'Extensions PHP manquantes',
            sprintf('PraeviSEO n a pas trouvé ces extensions PHP attendues : %s.', implode(', ', $missing)),
            false,
        );
        $manualActions[] = $this->item(
            'php_extensions_manual',
            'Activer les extensions PHP',
            'Activez les extensions PHP manquantes avant de relancer l installation premium.',
        );
    }
}
