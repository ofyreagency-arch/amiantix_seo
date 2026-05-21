<?php

declare(strict_types=1);

use App\Http\Controllers\Api\SeoAdminController;
use App\Http\Controllers\Api\SeoRuntimeController;
use App\Http\Middleware\EnsureAdminToken;
use Illuminate\Support\Facades\Route;

// Admin routes — gestion des sites clients (token admin séparé)
Route::middleware([EnsureAdminToken::class])->prefix('admin')->group(function (): void {
    Route::get('/sites', [SeoAdminController::class, 'index']);
    Route::post('/sites', [SeoAdminController::class, 'store']);
    Route::patch('/sites/{siteId}', [SeoAdminController::class, 'update']);
    Route::delete('/sites/{siteId}', [SeoAdminController::class, 'destroy']);
    Route::post('/sites/{siteId}/rotate-token', [SeoAdminController::class, 'rotateToken']);
});

// Routes clients — authentifiées par token de site
Route::middleware(['seo.engine.log', 'seo.engine.token', 'throttle:seo-engine'])->group(function (): void {
    Route::post('/seo/generate', [SeoRuntimeController::class, 'generate']);
    Route::post('/seo/rewrite', [SeoRuntimeController::class, 'rewrite']);
    Route::post('/seo/analyze', [SeoRuntimeController::class, 'analyze']);
    Route::get('/seo/opportunities', [SeoRuntimeController::class, 'opportunities']);
    Route::post('/seo/autopilot', [SeoRuntimeController::class, 'autopilot']);
    Route::get('/seo/search-console', [SeoRuntimeController::class, 'searchConsole']);
    Route::get('/seo/internal-links', [SeoRuntimeController::class, 'internalLinks']);
    Route::get('/seo/indexation', [SeoRuntimeController::class, 'indexation']);
    Route::get('/seo/runtime-summary', [SeoRuntimeController::class, 'runtimeSummary']);
    Route::get('/seo/observed-pages', [SeoRuntimeController::class, 'observedPages']);
    Route::get('/seo/pages', [SeoRuntimeController::class, 'pages']);
    Route::get('/seo/sitemap', [SeoRuntimeController::class, 'sitemap']);
});
