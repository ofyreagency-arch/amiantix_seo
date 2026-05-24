<?php

use App\Http\Controllers\PraeviseoBridgeController;
use App\Http\Controllers\PraeviseoPublishedPageController;
use Illuminate\Support\Facades\Route;

Route::post('/api/praeviseo/bridge/publish', [PraeviseoBridgeController::class, 'publish']);

Route::get('/'.trim((string) env('PRAEVISEO_BRIDGE_PREFIX', 'ressources'), '/').'/{slug}', [PraeviseoPublishedPageController::class, 'show'])
    ->name('praeviseo.published-page');
