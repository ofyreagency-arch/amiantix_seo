<?php

declare(strict_types=1);

namespace Praeviseo\SymfonyBridge\Composer;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;

final class SymfonyAutoEnablePlugin implements PluginInterface, EventSubscriberInterface
{
    private Composer $composer;

    private IOInterface $io;

    public function activate(Composer $composer, IOInterface $io): void
    {
        $this->composer = $composer;
        $this->io = $io;
    }

    public function deactivate(Composer $composer, IOInterface $io): void
    {
    }

    public function uninstall(Composer $composer, IOInterface $io): void
    {
    }

    /**
     * @return array<string,string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            ScriptEvents::POST_AUTOLOAD_DUMP => 'syncBundleRegistration',
        ];
    }

    public function syncBundleRegistration(Event $event): void
    {
        $projectRoot = dirname((string) $this->composer->getConfig()->get('vendor-dir'));
        $this->syncBundlesPhp($projectRoot);
        $this->syncRoutesYaml($projectRoot);
    }

    private function syncBundlesPhp(string $projectRoot): void
    {
        $bundlesPath = $projectRoot.'/config/bundles.php';

        if (! is_file($bundlesPath)) {
            return;
        }

        $contents = (string) file_get_contents($bundlesPath);

        if (str_contains($contents, 'Praeviseo\\SymfonyBridge\\PraeviseoSymfonyBridge::class')) {
            return;
        }

        $bundleLine = "    \\Praeviseo\\SymfonyBridge\\PraeviseoSymfonyBridge::class => ['all' => true],";
        $updated = preg_replace('/\];\s*$/', $bundleLine.PHP_EOL.'];'.PHP_EOL, rtrim($contents));

        if (! is_string($updated) || $updated === '') {
            return;
        }

        file_put_contents($bundlesPath, $updated);

        $this->io->write('<info>PraeviSEO Symfony Bridge active automatiquement dans config/bundles.php.</info>');
    }

    private function syncRoutesYaml(string $projectRoot): void
    {
        $routesDir = $projectRoot.'/config/routes';
        $routesPath = $routesDir.'/praeviseo_bridge.yaml';

        if (is_file($routesPath)) {
            return;
        }

        if (! is_dir($routesDir) && ! mkdir($routesDir, 0775, true) && ! is_dir($routesDir)) {
            return;
        }

        $contents = <<<'YAML'
praeviseo_symfony_bridge:
    resource: '@PraeviseoSymfonyBridge/config/routes.php'
    type: php

YAML;

        file_put_contents($routesPath, $contents);

        $this->io->write('<info>PraeviSEO Symfony Bridge routes enregistrées dans config/routes/praeviseo_bridge.yaml.</info>');
    }
}
