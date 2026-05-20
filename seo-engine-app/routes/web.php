<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\AdminAuthController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminPagesController;
use App\Http\Controllers\Admin\AdminSitesController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/admin/login', [AdminAuthController::class, 'showLogin'])->name('admin.login');
Route::post('/admin/login', [AdminAuthController::class, 'login'])->name('admin.login.post');
Route::post('/admin/logout', [AdminAuthController::class, 'logout'])->name('admin.logout');

Route::middleware('admin.auth')->prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');

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
});
