<?php

declare(strict_types=1);

namespace Tests\Unit\RemoteInstallation;

use App\Models\RemoteInstallation;
use App\Models\SeoSite;
use App\RemoteInstallation\Connectors\RemoteConnector;
use App\RemoteInstallation\Exceptions\RemoteInstallationException;
use App\RemoteInstallation\RemoteCommand;
use App\RemoteInstallation\RemoteCommandResult;
use App\RemoteInstallation\RemoteEnvironment;
use App\RemoteInstallation\Strategies\WordPressInstallerStrategy;
use PHPUnit\Framework\TestCase;

class WordPressInstallerStrategyTest extends TestCase
{
    public function test_wordpress_strategy_stays_client_safe_until_supported(): void
    {
        $strategy = new WordPressInstallerStrategy();
        $connector = new class implements RemoteConnector {
            public function connect(): void
            {
            }

            public function run(RemoteCommand $command, int $timeoutSeconds = 60): RemoteCommandResult
            {
                return new RemoteCommandResult(true, '', '', 0);
            }

            public function fileExists(string $path): bool
            {
                return false;
            }

            public function readFile(string $path): ?string
            {
                return null;
            }

            public function disconnect(): void
            {
            }
        };

        $installation = new RemoteInstallation();
        $site = new SeoSite([
            'site_id' => 'wp-demo',
            'name' => 'WP Demo',
            'url' => 'https://wp-demo.test',
        ]);
        $environment = new RemoteEnvironment(
            framework: 'wordpress',
            phpVersion: '8.3',
            composerVersion: 'Composer 2.8',
            projectWritable: true,
            projectPath: '/var/www/html'
        );

        $this->expectException(RemoteInstallationException::class);
        $this->expectExceptionMessage('Le support WordPress distant sera activé dans une prochaine version sécurisée de PraeviSEO.');

        $strategy->install($connector, $installation, $site, $environment);
    }
}
