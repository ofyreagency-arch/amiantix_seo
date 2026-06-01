<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Praeviseo\LaravelBridge\Http\Controllers\PraeviseoBridgeController;
use Praeviseo\LaravelBridge\Http\Controllers\PraeviseoPublishedPageController;
use Praeviseo\LaravelBridge\Http\Controllers\PraeviseoPublishedSitemapController;

Route::post('/api/praeviseo/bridge/publish', PraeviseoBridgeController::class);

Route::get('/'.trim((string) config('praeviseo-bridge.prefix', 'ressources'), '/').'/{slug}', PraeviseoPublishedPageController::class)
    ->name('praeviseo.published-page');

Route::get('/'.trim((string) config('praeviseo-bridge.prefix', 'ressources'), '/').'-sitemap.xml', PraeviseoPublishedSitemapController::class)
    ->name('praeviseo.published-sitemap');
