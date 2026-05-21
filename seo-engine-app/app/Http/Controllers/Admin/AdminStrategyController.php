<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SeoSite;
use App\Models\SeoStrategyItem;
use App\Recommendations\SiteStrategyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AdminStrategyController extends Controller
{
    public function __construct(private readonly SiteStrategyService $strategy) {}

    public function show(string $siteId): View
    {
        $site  = SeoSite::query()->where('site_id', $siteId)->firstOrFail();
        $items = $this->strategy->items($siteId);

        return view('admin.sites.strategy', compact('site', 'items'));
    }

    public function generate(string $siteId): RedirectResponse
    {
        $site = SeoSite::query()->where('site_id', $siteId)->firstOrFail();
        $this->strategy->generate($site);

        return redirect()->route('admin.sites.strategy', $siteId)
            ->with('success', 'Stratégie générée par IA.');
    }

    public function done(string $siteId, int $itemId): RedirectResponse
    {
        $this->strategy->markDone($itemId);

        return redirect()->route('admin.sites.strategy', $siteId);
    }
}
