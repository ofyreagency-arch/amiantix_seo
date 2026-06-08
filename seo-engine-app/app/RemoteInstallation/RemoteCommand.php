<?php

declare(strict_types=1);

namespace App\RemoteInstallation;

/**
 * Central whitelist for every shell command that PraeviSEO may run on a client server.
 *
 * SECURITY: this class exists to prevent RCE (Remote Code Execution).
 * A remote installation product manipulates SSH / SFTP credentials and touches
 * real client servers. If user input could reach `$ssh->exec($request->command)`
 * or `exec($userInput)`, an attacker could run arbitrary commands such as:
 * - rm -rf /
 * - curl malware.sh | bash
 * - wget payload
 * - cat .env
 *
 * Rules enforced by this layer:
 * - no raw shell command from the frontend or request payload
 * - no free shell concatenation in controllers/services
 * - only predefined, reviewable, testable commands live here
 * - dynamic values must be reduced to safe quoted arguments only
 *
 * The rest of the remote installation backend must treat RemoteCommand as the
 * only allowed way to reach command execution.
 */
final class RemoteCommand
{
    private function __construct(
        public readonly string $label,
        public readonly string $command,
    ) {
    }

    public static function detectPhpVersion(string $projectPath): self
    {
        return new self(
            'detect_php_version',
            self::withinProject($projectPath, 'php -r "echo PHP_VERSION;"'),
        );
    }

    public static function detectProjectDirectory(string $projectPath): self
    {
        return new self(
            'detect_project_directory',
            '[ -d '.self::quote($projectPath).' ] && echo present || echo missing',
        );
    }

    public static function detectComposer(string $projectPath): self
    {
        return new self(
            'detect_composer',
            self::withinProject($projectPath, 'composer --version'),
        );
    }

    public static function detectWriteAccess(string $projectPath): self
    {
        return new self(
            'detect_write_access',
            self::withinProject($projectPath, '[ -w . ] && echo writable || echo not_writable'),
        );
    }

    public static function detectEnvFile(string $projectPath): self
    {
        return new self(
            'detect_env_file',
            self::withinProject($projectPath, '[ -f .env ] && echo present || echo missing'),
        );
    }

    public static function detectAppUrl(string $projectPath): self
    {
        return new self(
            'detect_app_url',
            self::withinProject($projectPath, 'if [ -f .env ]; then grep -E "^APP_URL=" .env | tail -n 1 | cut -d "=" -f2- || true; else echo missing; fi'),
        );
    }

    public static function detectWorkerCount(string $projectPath): self
    {
        return new self(
            'detect_worker_count',
            self::withinProject($projectPath, 'ps aux | grep -E "queue:work|messenger:consume" | grep -v grep | wc -l'),
        );
    }

    public static function detectFrameworkVersion(string $projectPath, string $framework): self
    {
        $command = match ($framework) {
            'laravel' => 'php artisan --version',
            'symfony' => 'php bin/console --version',
            default => 'echo unknown',
        };

        return new self(
            'detect_framework_version',
            self::withinProject($projectPath, $command),
        );
    }

    public static function detectInstalledPhpExtensions(string $projectPath): self
    {
        return new self(
            'detect_php_extensions',
            self::withinProject($projectPath, 'php -m'),
        );
    }

    public static function detectDiskFreeMegabytes(string $projectPath): self
    {
        return new self(
            'detect_disk_free_megabytes',
            self::withinProject($projectPath, "df -Pm . | awk 'NR==2 {print \$4}'"),
        );
    }

    public static function detectQueueDriver(string $projectPath): self
    {
        return new self(
            'detect_queue_driver',
            self::withinProject($projectPath, 'if [ -f .env ]; then grep -E "^QUEUE_CONNECTION=" .env | tail -n 1 | cut -d "=" -f2- || true; else echo missing; fi'),
        );
    }

    public static function detectSchedulerEntries(string $projectPath): self
    {
        return new self(
            'detect_scheduler_entries',
            self::withinProject($projectPath, 'crontab -l 2>/dev/null | grep -E "schedule:run|cron:run|messenger:consume" | wc -l'),
        );
    }

    public static function detectSupervisorProcesses(string $projectPath): self
    {
        return new self(
            'detect_supervisor_processes',
            self::withinProject($projectPath, 'if command -v supervisorctl >/dev/null 2>&1; then supervisorctl status 2>/dev/null | wc -l; else echo 0; fi'),
        );
    }

    public static function detectRedisAvailability(string $projectPath): self
    {
        return new self(
            'detect_redis_availability',
            self::withinProject($projectPath, "if php -m | grep -i '^redis$' >/dev/null 2>&1; then echo extension; elif command -v redis-cli >/dev/null 2>&1; then redis-cli ping 2>/dev/null || echo missing; else echo missing; fi"),
        );
    }

    public static function detectStorageWriteAccess(string $projectPath, string $framework): self
    {
        $command = match ($framework) {
            'laravel' => 'for dir in storage bootstrap/cache storage/logs; do [ -e "$dir" ] && [ ! -w "$dir" ] && { echo not_writable:$dir; exit 0; }; done; echo writable',
            'symfony' => 'for dir in var var/cache var/log; do [ -e "$dir" ] && [ ! -w "$dir" ] && { echo not_writable:$dir; exit 0; }; done; echo writable',
            default => 'echo unknown',
        };

        return new self(
            'detect_storage_write_access',
            self::withinProject($projectPath, $command),
        );
    }

    public static function detectInternetConnectivity(string $projectPath): self
    {
        return new self(
            'detect_internet_connectivity',
            self::withinProject($projectPath, 'if command -v curl >/dev/null 2>&1; then curl -I -L -s --max-time 8 https://repo.packagist.org/packages.json >/dev/null && echo ok || echo missing; else echo missing; fi'),
        );
    }

    public static function detectDomainDns(string $projectPath, string $host): self
    {
        return new self(
            'detect_domain_dns',
            self::withinProject($projectPath, 'if command -v getent >/dev/null 2>&1; then getent ahosts '.self::quote($host).' >/dev/null 2>&1 && echo ok || echo missing; else echo unknown; fi'),
        );
    }

    public static function detectHttpsStatus(string $projectPath, string $url): self
    {
        return new self(
            'detect_https_status',
            self::withinProject($projectPath, 'if command -v curl >/dev/null 2>&1; then curl -I -L -s --max-time 8 '.self::quote($url).' >/dev/null && echo ok || echo missing; else echo unknown; fi'),
        );
    }

    public static function detectDatabaseAccess(string $projectPath, string $framework): self
    {
        $command = match ($framework) {
            'laravel' => 'php artisan tinker --execute="try { DB::connection()->getPdo(); echo \'ok\'; } catch (Throwable $e) { echo \'missing\'; }"',
            'symfony' => 'if [ -f .env ] && grep -E "^DATABASE_URL=" .env >/dev/null 2>&1; then echo configured; else echo missing; fi',
            default => 'echo unknown',
        };

        return new self(
            'detect_database_access',
            self::withinProject($projectPath, $command),
        );
    }

    public static function detectPraeviseoConnectCommand(string $projectPath, string $framework): self
    {
        $command = match ($framework) {
            'laravel' => 'php artisan list --raw | grep -E "^praeviseo:connect$" >/dev/null 2>&1 && echo present || echo missing',
            'symfony' => 'php bin/console list --raw | grep -E "^praeviseo:connect$" >/dev/null 2>&1 && echo present || echo missing',
            default => 'echo unknown',
        };

        return new self(
            'detect_praeviseo_connect_command',
            self::withinProject($projectPath, $command),
        );
    }

    public static function detectPraeviseoUrl(string $projectPath, string $framework): self
    {
        $command = match ($framework) {
            'laravel' => 'if [ -f .env ]; then grep -E "^PRAEVISEO_URL=" .env | tail -n 1 | cut -d "=" -f2- || true; else echo missing; fi',
            'symfony' => 'if [ -f .env.local ] && grep -E "^PRAEVISEO_URL=" .env.local >/dev/null 2>&1; then grep -E "^PRAEVISEO_URL=" .env.local | tail -n 1 | cut -d "=" -f2-; elif [ -f .env ] && grep -E "^PRAEVISEO_URL=" .env >/dev/null 2>&1; then grep -E "^PRAEVISEO_URL=" .env | tail -n 1 | cut -d "=" -f2-; else echo missing; fi',
            default => 'echo missing',
        };

        return new self(
            'detect_praeviseo_url',
            self::withinProject($projectPath, $command),
        );
    }

    public static function installLaravelBridge(string $projectPath): self
    {
        return new self(
            'install_laravel_bridge',
            self::withinProject($projectPath, 'composer require praeviseo/laravel-bridge --no-interaction --no-progress'),
        );
    }

    public static function connectLaravel(string $projectPath, string $code): self
    {
        return new self(
            'connect_laravel',
            self::withinProject($projectPath, 'php artisan praeviseo:connect '.self::quote($code)),
        );
    }

    public static function clearLaravelCache(string $projectPath): self
    {
        return new self(
            'clear_laravel_cache',
            self::withinProject($projectPath, 'php artisan optimize:clear'),
        );
    }

    public static function allowSymfonyBridgePlugin(string $projectPath): self
    {
        return new self(
            'allow_symfony_bridge_plugin',
            self::withinProject($projectPath, self::composer('config --no-plugins allow-plugins.praeviseo/symfony-bridge true')),
        );
    }

    public static function ensureSymfonyDatabaseUrl(string $projectPath): self
    {
        $sqliteUrl = 'DATABASE_URL="sqlite:///%kernel.project_dir%/var/data.db"';
        $command = <<<SH
mkdir -p var
if [ ! -f .env ]; then
  echo 'APP_ENV=dev' > .env
fi
if grep -E '^DATABASE_URL=' .env >/dev/null 2>&1 && ! grep -E '^DATABASE_URL=.*(postgresql://app:!ChangeMe!|postgresql://127\\.0\\.0\\.1)' .env >/dev/null 2>&1; then
  echo present
else
  sed -i '/^DATABASE_URL=/d' .env
  printf '%s\n' '{$sqliteUrl}' >> .env
  echo configured
fi
SH;

        return new self(
            'ensure_symfony_database_url',
            self::withinProject($projectPath, $command),
        );
    }

    public static function installSymfonyDoctrine(string $projectPath): self
    {
        return new self(
            'install_symfony_doctrine',
            self::withinProject($projectPath, self::composer('require symfony/orm-pack --no-interaction --no-progress')),
        );
    }

    public static function installSymfonyBridge(string $projectPath): self
    {
        return new self(
            'install_symfony_bridge',
            self::withinProject($projectPath, self::composer('require praeviseo/symfony-bridge --no-interaction --no-progress')),
        );
    }

    public static function dumpSymfonyAutoload(string $projectPath): self
    {
        return new self(
            'dump_symfony_autoload',
            self::withinProject($projectPath, self::composer('dump-autoload --no-interaction')),
        );
    }

    public static function updateSymfonyDoctrineSchema(string $projectPath): self
    {
        return new self(
            'update_symfony_doctrine_schema',
            self::withinProject($projectPath, 'php bin/console doctrine:schema:update --force'),
        );
    }

    public static function countSymfonyPublishedPages(string $projectPath): self
    {
        return new self(
            'count_symfony_published_pages',
            self::withinProject($projectPath, 'php bin/console dbal:run-sql "SELECT COUNT(*) AS count FROM praeviseo_published_pages" --quiet 2>/dev/null | tail -n 1 || echo missing'),
        );
    }

    public static function ensureSymfonyAppUrl(string $projectPath, string $appUrl): self
    {
        $command = sprintf(
            "if [ -f .env ] && grep -q '^APP_URL=' .env; then sed -i 's|^APP_URL=.*|APP_URL=%s|' .env; else echo 'APP_URL=%s' >> .env; fi",
            $appUrl,
            $appUrl,
        );

        return new self(
            'ensure_symfony_app_url',
            self::withinProject($projectPath, $command),
        );
    }

    public static function clearSymfonyCache(string $projectPath): self
    {
        return new self(
            'clear_symfony_cache',
            self::withinProject($projectPath, 'php bin/console cache:clear'),
        );
    }

    public static function connectSymfony(
        string $projectPath,
        string $code,
        string $praeviseoUrl,
        ?string $prefix = null,
    ): self {
        $options = ' --praeviseo-url='.self::quote(rtrim($praeviseoUrl, '/'));

        if ($prefix !== null && trim($prefix) !== '') {
            $options .= ' --prefix='.self::quote(trim($prefix, '/'));
        }

        return new self(
            'connect_symfony',
            self::withinProject($projectPath, 'php bin/console praeviseo:connect '.self::quote($code).$options),
        );
    }

    private static function composer(string $command): string
    {
        return 'COMPOSER_ALLOW_SUPERUSER=1 composer '.$command;
    }

    private static function withinProject(string $path, string $command): string
    {
        return 'cd '.self::quote($path).' && '.$command;
    }

    private static function quote(string $value): string
    {
        return "'".str_replace("'", "'\"'\"'", $value)."'";
    }
}
