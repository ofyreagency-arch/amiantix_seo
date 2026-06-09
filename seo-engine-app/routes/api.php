<?php

declare(strict_types=1);

use App\Http\Controllers\Api\SeoAdminController;
use App\Http\Controllers\Api\ClientAuthController;
use App\Http\Controllers\Api\ClientSitesController;
use App\Http\Controllers\Api\ClientWorkspaceController;
use App\Http\Controllers\Api\SeoBridgeConnectController;
use App\Http\Controllers\Api\SeoRuntimeController;
use App\Http\Middleware\EnsureAdminToken;
use Illuminate\Support\Facades\Route;

Route::prefix('client/auth')->group(function (): void {
    Route::post('/register', [ClientAuthController::class, 'register']);
    Route::post('/login', [ClientAuthController::class, 'login']);

    Route::middleware('client.auth')->group(function (): void {
        Route::get('/me', [ClientAuthController::class, 'me']);
        Route::post('/logout', [ClientAuthController::class, 'logout']);
    });
});

Route::middleware('client.auth')->prefix('client')->group(function (): void {
    Route::get('/sites', [ClientSitesController::class, 'index']);
    Route::get('/sites/{siteId}', [ClientSitesController::class, 'show']);
    Route::post('/sites', [ClientSitesController::class, 'store']);
    Route::post('/sites/claim', [ClientSitesController::class, 'claim']);
    Route::post('/sites/{siteId}/installation-precheck', [ClientSitesController::class, 'installationPrecheck'])
        ->middleware('throttle:6,1');
    Route::post('/sites/{siteId}/installation', [ClientSitesController::class, 'requestInstallation'])
        ->middleware('throttle:6,1');
    Route::get('/sites/{siteId}/installation-status', [ClientSitesController::class, 'installationStatus']);
    Route::post('/sites/{siteId}/crawl', [ClientSitesController::class, 'startObservedCrawl'])
        ->middleware('throttle:6,1');
    Route::post('/sites/{siteId}/generate', [ClientSitesController::class, 'startPremiumArticleGeneration'])
        ->middleware('throttle:6,1');
    Route::post('/sites/{siteId}/rewrite', [ClientSitesController::class, 'startPremiumRewrite'])
        ->middleware('throttle:6,1');
    Route::post('/sites/{siteId}/linking', [ClientSitesController::class, 'startPremiumInternalLinking'])
        ->middleware('throttle:6,1');
    Route::post('/sites/{siteId}/images', [ClientSitesController::class, 'startPremiumImageGeneration'])
        ->middleware('throttle:6,1');
    Route::post('/sites/{siteId}/publish', [ClientSitesController::class, 'startPremiumPublication'])
        ->middleware('throttle:6,1');
    Route::post('/sites/{siteId}/confirm-preview', [ClientSitesController::class, 'confirmPreviewPublish'])
        ->middleware('throttle:6,1');
    Route::patch('/sites/{siteId}/gsc', [ClientSitesController::class, 'updateGsc']);
    Route::get('/optimizations', [ClientWorkspaceController::class, 'optimizations']);
    Route::get('/action-preview', [ClientWorkspaceController::class, 'actionPreview']);
    Route::get('/publications', [ClientWorkspaceController::class, 'publications']);
    Route::delete('/publications/{pageId}', [ClientWorkspaceController::class, 'destroyPublication']);
    Route::get('/settings', [ClientWorkspaceController::class, 'settings']);
    Route::patch('/settings/profile', [ClientWorkspaceController::class, 'updateProfile']);
});

// Admin routes — gestion des sites clients (token admin séparé)
Route::middleware([EnsureAdminToken::class])->prefix('admin')->group(function (): void {
    Route::get('/sites', [SeoAdminController::class, 'index']);
    Route::get('/sites/{siteId}', [SeoAdminController::class, 'show']);
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

Route::post('/bridge/connect', [SeoBridgeConnectController::class, 'connect'])
    ->middleware('throttle:seo-engine');
