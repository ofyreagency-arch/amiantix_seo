<?php

declare(strict_types=1);

namespace Praeviseo\SymfonyBridge;

use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

final class PraeviseoSymfonyBridge extends AbstractBundle
{
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->scalarNode('publication_prefix')->defaultValue('ressources')->end()
            ->end();
    }

    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->import('../config/services.php');
        $container->parameters()
            ->set('praeviseo_symfony_bridge.publication_prefix', $config['publication_prefix'] ?? 'ressources');
    }

    public function configureRoutes(RoutingConfigurator $routes, string $environment): void
    {
        $routes->import('../config/routes.php');
    }
}
