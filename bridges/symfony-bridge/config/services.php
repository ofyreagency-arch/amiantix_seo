<?php

declare(strict_types=1);

use Praeviseo\SymfonyBridge\Command\PraeviseoConnectCommand;
use Praeviseo\SymfonyBridge\Controller\PraeviseoBridgeController;
use Praeviseo\SymfonyBridge\Controller\PraeviseoPublishedPageController;
use Praeviseo\SymfonyBridge\Controller\PraeviseoPublishedSitemapController;
use Praeviseo\SymfonyBridge\Service\PraeviseoBridgeConfig;
use Praeviseo\SymfonyBridge\Service\PraeviseoBridgeService;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
        ->autowire()
        ->autoconfigure();

    $services->set(PraeviseoBridgeConfig::class)
        ->arg('$praeviseoUrl', '%env(default::PRAEVISEO_URL)%')
        ->arg('$appUrl', '%env(default::APP_URL)%')
        ->arg('$bridgeSecret', '%env(default::PRAEVISEO_BRIDGE_SECRET)%')
        ->arg('$bridgeSiteId', '%env(default::PRAEVISEO_BRIDGE_SITE_ID)%')
        ->arg('$bridgePrefix', '%env(default::PRAEVISEO_BRIDGE_PREFIX)%');

    $services->set(PraeviseoBridgeService::class);
    $services->set(PraeviseoBridgeController::class)->public();
    $services->alias('praeviseo_symfony_bridge.controller.publish', PraeviseoBridgeController::class)->public();
    $services->set(PraeviseoPublishedPageController::class)->public();
    $services->alias('praeviseo_symfony_bridge.controller.page', PraeviseoPublishedPageController::class)->public();
    $services->set(PraeviseoPublishedSitemapController::class)->public();
    $services->alias('praeviseo_symfony_bridge.controller.sitemap', PraeviseoPublishedSitemapController::class)->public();
    $services->alias('praeviseo_symfony_bridge.controller.sitemap_under_prefix', PraeviseoPublishedSitemapController::class.'::underPrefix')->public();
    $services->set(PraeviseoConnectCommand::class)
        ->arg('$projectDir', '%kernel.project_dir%')
        ->tag('console.command');
};
