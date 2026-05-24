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
use App\Http\Controllers\PublicSeoPageController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () { return view('welcome'); })->name('home');
Route::view('/fonctionnalites', 'public.features')->name('public.features');
Route::view('/tarifs', 'public.pricing')->name('public.pricing');
Route::view('/contact', 'public.contact')->name('public.contact');
Route::post('/contact', function (\Illuminate\Http\Request $request) {
    $request->validate([
        'name'    => 'required|string|max:200',
        'email'   => 'required|email|max:200',
        'message' => 'required|string|max:5000',
    ]);
    return redirect()->route('public.contact')->with('contact_sent', true);
})->name('public.contact.submit');

Route::get('/admin/login', [AdminAuthController::class, 'showLogin'])->name('admin.login');
Route::post('/admin/login', [AdminAuthController::class, 'login'])->name('admin.login.post');
Route::post('/admin/logout', [AdminAuthController::class, 'logout'])->name('admin.logout');

Route::middleware('admin.auth')->prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');
    Route::get('/system', [AdminSystemController::class, 'show'])->name('system');

    Route::get('/sites', [AdminSitesController::class, 'index'])->name('sites.index');
    Route::post('/sites', [AdminSitesController::class, 'store'])->name('sites.store');
    Route::get('/sites/{siteId}', [AdminSitesController::class, 'show'])->name('sites.show');
    Route::post('/sites/{siteId}/google-connection', [AdminSitesController::class, 'updateGoogleConnection'])->name('sites.google-connection.update');
    Route::post('/sites/{siteId}/rotate-token', [AdminSitesController::class, 'rotateToken'])->name('sites.rotate-token');
    Route::delete('/sites/{siteId}', [AdminSitesController::class, 'destroy'])->name('sites.destroy');

    Route::post('/sites/{siteId}/generate', [AdminPagesController::class, 'generate'])->name('pages.generate');
    Route::post('/sites/{siteId}/autopilot', [AdminPagesController::class, 'autopilot'])->name('pages.autopilot');
    Route::get('/sites/{siteId}/pages/{pageId}', [AdminPagesController::class, 'show'])->name('pages.show');
    Route::delete('/sites/{siteId}/pages/{pageId}', [AdminPagesController::class, 'destroy'])->name('pages.destroy');
    Route::post('/sites/{siteId}/pages/{pageId}/rewrite', [AdminPagesController::class, 'rewrite'])->name('pages.rewrite');
    Route::post('/sites/{siteId}/pages/{pageId}/analyze', [AdminPagesController::class, 'analyze'])->name('pages.analyze');
    Route::post('/sites/{siteId}/pages/{pageId}/suggestions/{suggestionId}/apply', [AdminPagesController::class, 'applySuggestion'])->name('pages.suggestions.apply');
    Route::post('/sites/{siteId}/pages/{pageId}/publish', [AdminPagesController::class, 'publish'])->name('pages.publish');
    Route::post('/sites/{siteId}/pages/{pageId}/publish-live', [AdminPagesController::class, 'publishLive'])->name('pages.publish-live');
    Route::get('/sites/{siteId}/pages/{pageId}/preview', [AdminPagesController::class, 'preview'])->name('pages.preview');
    Route::post('/sites/{siteId}/pages/{pageId}/quick-fix', [AdminPagesController::class, 'quickFix'])->name('pages.quick-fix');

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

Route::get('/sitemap.xml', [PublicSeoPageController::class, 'sitemap'])->name('public.sitemap');
Route::get('/{slug}', [PublicSeoPageController::class, 'show'])
    ->where('slug', '^(?!admin(?:/|$)).+')
    ->name('public.page');
