<?php

declare(strict_types=1);

use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return static function (RoutingConfigurator $routes): void {
    $routes->add('praeviseo_bridge_publish', '/api/praeviseo/bridge/publish')
        ->controller('praeviseo_symfony_bridge.controller.publish')
        ->methods(['POST']);

    $routes->add('praeviseo_bridge_public_page', '/{prefix}/{slug}')
        ->controller('praeviseo_symfony_bridge.controller.page')
        ->methods(['GET'])
        ->requirements([
            'prefix' => '.+',
            'slug' => '[^/]+',
        ]);

    $routes->add('praeviseo_bridge_public_sitemap', '/{sitemapPath}')
        ->controller('praeviseo_symfony_bridge.controller.sitemap')
        ->methods(['GET'])
        ->requirements([
            'sitemapPath' => '.+-sitemap\.xml',
        ]);

    $routes->add('praeviseo_bridge_public_sitemap_under_prefix', '/{prefix}/sitemap.xml')
        ->controller('praeviseo_symfony_bridge.controller.sitemap_under_prefix')
        ->methods(['GET'])
        ->requirements([
            'prefix' => '.+',
        ]);
};
