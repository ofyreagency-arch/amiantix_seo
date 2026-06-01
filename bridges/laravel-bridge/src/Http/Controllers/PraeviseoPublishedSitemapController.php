<?php

declare(strict_types=1);

namespace Praeviseo\LaravelBridge\Http\Controllers;

use Illuminate\Http\Response;
use Praeviseo\LaravelBridge\Models\PraeviseoPublishedPage;

final class PraeviseoPublishedSitemapController
{
    public function __invoke(): Response
    {
        $pages = PraeviseoPublishedPage::query()
            ->where('publication_state', 'published')
            ->orderByDesc('last_published_at')
            ->get(['live_url', 'last_published_at']);

        $xml = view('praeviseo-laravel-bridge::published-sitemap', [
            'pages' => $pages,
        ])->render();

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
        ]);
    }
}
