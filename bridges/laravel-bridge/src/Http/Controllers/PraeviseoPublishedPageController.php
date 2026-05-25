<?php

declare(strict_types=1);

namespace Praeviseo\LaravelBridge\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Praeviseo\LaravelBridge\Models\PraeviseoPublishedPage;

final class PraeviseoPublishedPageController
{
    public function __invoke(Request $request, string $slug): View
    {
        $page = PraeviseoPublishedPage::query()->where('slug', $slug)->firstOrFail();

        return view('praeviseo-laravel-bridge::published-page', [
            'page' => $page,
        ]);
    }
}
