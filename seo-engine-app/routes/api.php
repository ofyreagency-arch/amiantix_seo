<?php

declare(strict_types=1);

use App\Http\Controllers\Api\SeoRuntimeController;
use Illuminate\Support\Facades\Route;

Route::middleware(['seo.engine.log', 'seo.engine.token', 'throttle:seo-engine'])->group(function (): void {
    Route::post('/seo/generate', [SeoRuntimeController::class, 'generate']);
    Route::post('/seo/rewrite', [SeoRuntimeController::class, 'rewrite']);
    Route::post('/seo/analyze', [SeoRuntimeController::class, 'analyze']);
    Route::get('/seo/opportunities', [SeoRuntimeController::class, 'opportunities']);
    Route::post('/seo/autopilot', [SeoRuntimeController::class, 'autopilot']);
    Route::get('/seo/search-console', [SeoRuntimeController::class, 'searchConsole']);
    Route::get('/seo/internal-links', [SeoRuntimeController::class, 'internalLinks']);
    Route::get('/seo/indexation', [SeoRuntimeController::class, 'indexation']);
    Route::get('/seo/pages', [SeoRuntimeController::class, 'pages']);
});
