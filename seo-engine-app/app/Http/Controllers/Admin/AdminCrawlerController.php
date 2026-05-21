<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SeoSite;
use App\ObservedSite\SiteCrawlerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AdminCrawlerController extends Controller
{
    public function __construct(private readonly SiteCrawlerService $crawler) {}

    public function show(string $siteId): View
    {
        $site    = SeoSite::query()->where('site_id', $siteId)->firstOrFail();
        $results = $this->crawler->results($siteId);

        return view('admin.sites.crawler', compact('site', 'results'));
    }

    public function start(string $siteId): RedirectResponse
    {
        $site = SeoSite::query()->where('site_id', $siteId)->firstOrFail();
        $this->crawler->crawl($site);

        return redirect()->route('admin.sites.crawler', $siteId)
            ->with('success', 'Crawl terminé.');
    }
}
