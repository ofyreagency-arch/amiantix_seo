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

        self::assertStringContainsString('composer require praeviseo/symfony-bridge', $command->command);
        self::assertStringNotContainsString('--no-scripts', $command->command);
    }

    public function test_allow_symfony_bridge_plugin_enables_composer_plugin(): void
    {
        $command = RemoteCommand::allowSymfonyBridgePlugin('/var/www/client');

        self::assertStringContainsString('allow-plugins.praeviseo/symfony-bridge true', $command->command);
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
