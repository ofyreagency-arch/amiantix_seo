<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SeoPage;
use App\Models\SeoSite;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    public function index(): View
    {
        $stats = [
            'total_sites'  => SeoSite::query()->active()->count(),
            'total_pages'  => SeoPage::query()->count(),
            'published'    => SeoPage::query()->where('status', 'published')->count(),
            'this_week'    => SeoPage::query()->where('created_at', '>=', now()->subWeek())->count(),
        ];

        $sites = SeoSite::query()->active()->orderBy('name')->get()->map(fn (SeoSite $site) => [
            'site'       => $site,
            'pages'      => SeoPage::query()->where('site_id', $site->site_id)->count(),
            'published'  => SeoPage::query()->where('site_id', $site->site_id)->where('status', 'published')->count(),
        ]);

        $recent = SeoPage::query()
            ->select(['id', 'site_id', 'keyword', 'slug', 'status', 'seo_score', 'updated_at'])
            ->orderByDesc('updated_at')
            ->limit(10)
            ->get();

        return view('admin.dashboard', compact('stats', 'sites', 'recent'));
    }
}
