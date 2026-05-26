<?php

declare(strict_types=1);

namespace App\RemoteInstallation;

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
