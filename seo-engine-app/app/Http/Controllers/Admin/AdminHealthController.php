<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SeoSite;
use App\ObservedSite\SiteHealthService;
use Illuminate\View\View;

class AdminHealthController extends Controller
{
    public function __construct(private readonly SiteHealthService $health) {}

    public function show(string $siteId): View
    {
        $site    = SeoSite::query()->where('site_id', $siteId)->firstOrFail();
        $health  = $this->health->calculate($siteId);
        $history = $this->health->history($siteId, 30);

        $this->health->snapshot($siteId);

        return view('admin.sites.health', compact('site', 'health', 'history'));
    }
}
