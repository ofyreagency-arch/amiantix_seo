<?php

declare(strict_types=1);

namespace Tests\Unit\RemoteInstallation;

use App\RemoteInstallation\RemoteCommand;
use PHPUnit\Framework\TestCase;

class SymfonyBridgeRemoteCommandTest extends TestCase
{
    public function test_install_symfony_bridge_runs_composer_without_no_scripts(): void
    {
        $command = RemoteCommand::installSymfonyBridge('/var/www/client');

        self::assertStringContainsString('composer require praeviseo/symfony-bridge:^0.1.4', $command->command);
        self::assertStringNotContainsString('--no-scripts', $command->command);
    }

    public function test_allow_symfony_bridge_plugin_enables_composer_plugin(): void
    {
        $command = RemoteCommand::allowSymfonyBridgePlugin('/var/www/client');

        self::assertStringContainsString('allow-plugins.praeviseo/symfony-bridge true', $command->command);
    }

    public function test_install_symfony_doctrine_runs_orm_pack(): void
    {
        $command = RemoteCommand::installSymfonyDoctrine('/var/www/client');

        self::assertStringContainsString('COMPOSER_ALLOW_SUPERUSER=1 composer require symfony/orm-pack', $command->command);
    }

    public function test_install_php_sqlite_extension_targets_pdo_sqlite(): void
    {
        $command = RemoteCommand::installPhpSqliteExtension('/var/www/client');

        self::assertStringContainsString('pdo_sqlite', $command->command);
        self::assertStringContainsString('php-sqlite3', $command->command);
    }

    public function test_ensure_symfony_database_url_writes_sqlite_default(): void
    {
        $command = RemoteCommand::ensureSymfonyDatabaseUrl('/var/www/client');

        self::assertStringContainsString('DATABASE_URL=', $command->command);
        self::assertStringContainsString('sqlite:///%kernel.project_dir%/var/data.db', $command->command);
    }

    public function test_update_symfony_doctrine_schema_runs_schema_update(): void
    {
        $command = RemoteCommand::updateSymfonyDoctrineSchema('/var/www/client');

        self::assertStringContainsString('doctrine:schema:update --force', $command->command);
        self::assertStringNotContainsString('--complete', $command->command);
    }

    public function test_connect_symfony_passes_praeviseo_url_and_prefix(): void
    {
        $command = RemoteCommand::connectSymfony(
            '/var/www/client',
            'ABCD-EFGH-IJKL',
            'https://cockpit.praeviseo.test',
            'ressources',
        );

        self::assertStringContainsString("praeviseo:connect 'ABCD-EFGH-IJKL'", $command->command);
        self::assertStringContainsString("--praeviseo-url='https://cockpit.praeviseo.test'", $command->command);
        self::assertStringContainsString("--prefix='ressources'", $command->command);
    }

    public function test_ensure_symfony_app_url_writes_site_url(): void
    {
        $command = RemoteCommand::ensureSymfonyAppUrl('/var/www/client', 'https://client.example');

        self::assertStringContainsString('APP_URL=https://client.example', $command->command);
    }
}
