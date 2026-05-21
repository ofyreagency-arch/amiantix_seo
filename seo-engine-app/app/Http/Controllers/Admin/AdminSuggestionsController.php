<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SeoPage;
use App\Models\SeoSite;
use App\Models\SeoSuggestion;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AdminSuggestionsController extends Controller
{
    public function show(string $siteId): View
    {
        $site = SeoSite::query()->where('site_id', $siteId)->firstOrFail();

        $pending = SeoSuggestion::query()
            ->whereHas('page', fn ($q) => $q->where('site_id', $siteId))
            ->where('status', 'pending')
            ->with('page:id,keyword,slug,status')
            ->orderByDesc('created_at')
            ->paginate(20);

        $stats = [
            'pending'  => SeoSuggestion::query()->whereHas('page', fn ($q) => $q->where('site_id', $siteId))->where('status', 'pending')->count(),
            'applied'  => SeoSuggestion::query()->whereHas('page', fn ($q) => $q->where('site_id', $siteId))->where('status', 'applied')->count(),
            'rejected' => SeoSuggestion::query()->whereHas('page', fn ($q) => $q->where('site_id', $siteId))->where('status', 'rejected')->count(),
        ];

        return view('admin.sites.autopilot', compact('site', 'pending', 'stats'));
    }

    public function approve(string $siteId, int $id): RedirectResponse
    {
        $suggestion = SeoSuggestion::query()->findOrFail($id);
        $suggestion->update(['status' => 'applied', 'applied_at' => now()]);

        return redirect()->route('admin.sites.autopilot', $siteId)
            ->with('success', 'Suggestion approuvée.');
    }

    public function reject(string $siteId, int $id): RedirectResponse
    {
        $suggestion = SeoSuggestion::query()->findOrFail($id);
        $suggestion->update(['status' => 'rejected', 'rejected_at' => now()]);

        return redirect()->route('admin.sites.autopilot', $siteId);
    }
}
