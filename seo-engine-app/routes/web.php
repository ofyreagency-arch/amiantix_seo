<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\AdminAuthController;
use App\Http\Controllers\Admin\AdminCrawlerController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminHealthController;
use App\Http\Controllers\Admin\AdminPagesController;
use App\Http\Controllers\Admin\AdminSemanticController;
use App\Http\Controllers\Admin\AdminSitesController;
use App\Http\Controllers\Admin\AdminStrategyController;
use App\Http\Controllers\Admin\AdminSuggestionsController;
use App\Http\Controllers\Admin\AdminSystemController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/admin/login', [AdminAuthController::class, 'showLogin'])->name('admin.login');
Route::post('/admin/login', [AdminAuthController::class, 'login'])->name('admin.login.post');
Route::post('/admin/logout', [AdminAuthController::class, 'logout'])->name('admin.logout');

Route::middleware('admin.auth')->prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');
    Route::get('/system', [AdminSystemController::class, 'show'])->name('system');

    Route::get('/sites', [AdminSitesController::class, 'index'])->name('sites.index');
    Route::post('/sites', [AdminSitesController::class, 'store'])->name('sites.store');
    Route::get('/sites/{siteId}', [AdminSitesController::class, 'show'])->name('sites.show');
    Route::post('/sites/{siteId}/rotate-token', [AdminSitesController::class, 'rotateToken'])->name('sites.rotate-token');
    Route::delete('/sites/{siteId}', [AdminSitesController::class, 'destroy'])->name('sites.destroy');

    Route::post('/sites/{siteId}/generate', [AdminPagesController::class, 'generate'])->name('pages.generate');
    Route::post('/sites/{siteId}/autopilot', [AdminPagesController::class, 'autopilot'])->name('pages.autopilot');
    Route::get('/sites/{siteId}/pages/{pageId}', [AdminPagesController::class, 'show'])->name('pages.show');
    Route::post('/sites/{siteId}/pages/{pageId}/rewrite', [AdminPagesController::class, 'rewrite'])->name('pages.rewrite');
    Route::post('/sites/{siteId}/pages/{pageId}/analyze', [AdminPagesController::class, 'analyze'])->name('pages.analyze');
    Route::post('/sites/{siteId}/pages/{pageId}/suggestions/{suggestionId}/apply', [AdminPagesController::class, 'applySuggestion'])->name('pages.suggestions.apply');
    Route::post('/sites/{siteId}/pages/{pageId}/publish', [AdminPagesController::class, 'publish'])->name('pages.publish');
    Route::get('/sites/{siteId}/pages/{pageId}/preview', [AdminPagesController::class, 'preview'])->name('pages.preview');

    // Intelligence
    Route::get('/sites/{siteId}/health',                    [AdminHealthController::class,      'show'])->name('sites.health');
    Route::get('/sites/{siteId}/strategy',                  [AdminStrategyController::class,    'show'])->name('sites.strategy');
    Route::post('/sites/{siteId}/strategy/generate',        [AdminStrategyController::class,    'generate'])->name('sites.strategy.generate');
    Route::post('/sites/{siteId}/strategy/{itemId}/done',   [AdminStrategyController::class,    'done'])->name('sites.strategy.done');
    Route::get('/sites/{siteId}/semantic',                  [AdminSemanticController::class,    'show'])->name('sites.semantic');
    Route::get('/sites/{siteId}/semantic/data',             [AdminSemanticController::class,    'data'])->name('sites.semantic.data');
    Route::get('/sites/{siteId}/crawler',                   [AdminCrawlerController::class,     'show'])->name('sites.crawler');
    Route::post('/sites/{siteId}/crawler/start',            [AdminCrawlerController::class,     'start'])->name('sites.crawler.start');
    Route::get('/sites/{siteId}/autopilot',                 [AdminSuggestionsController::class, 'show'])->name('sites.autopilot');
    Route::post('/sites/{siteId}/suggestions/{id}/approve', [AdminSuggestionsController::class, 'approve'])->name('sites.suggestions.approve');
    Route::post('/sites/{siteId}/suggestions/{id}/reject',  [AdminSuggestionsController::class, 'reject'])->name('sites.suggestions.reject');
});
