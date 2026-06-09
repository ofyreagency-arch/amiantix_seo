<?php

declare(strict_types=1);

namespace Praeviseo\LaravelBridge;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\ServiceProvider;
use Praeviseo\LaravelBridge\Console\PraeviseoConnectCommand;
use Praeviseo\LaravelBridge\Http\Middleware\ApplyNativePagePatch;
use Praeviseo\LaravelBridge\Services\NativePageHtmlPatcher;

final class PraeviseoLaravelBridgeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/praeviseo-bridge.php', 'praeviseo-bridge');
        $this->app->singleton(NativePageHtmlPatcher::class);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'praeviseo-laravel-bridge');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->publishes([
            __DIR__.'/../config/praeviseo-bridge.php' => config_path('praeviseo-bridge.php'),
        ], 'praeviseo-laravel-bridge-config');

        if ($this->app->runningInConsole()) {
            $this->commands([
                PraeviseoConnectCommand::class,
            ]);
        }

        $this->app->make(Kernel::class)->pushMiddleware(ApplyNativePagePatch::class);
    }
}
