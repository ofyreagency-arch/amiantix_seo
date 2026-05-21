<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SeoPage;
use App\Models\SeoSite;
use App\Services\Preset\PresetManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminSitesController extends Controller
{
    public function __construct(
        private readonly PresetManager $presets,
    ) {}

    public function index(): View
    {
        $sites = SeoSite::query()->orderByDesc('created_at')->get();

        return view('admin.sites.index', [
            'sites' => $sites,
            'availablePresets' => $this->presets->availablePresets(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'site_id'     => ['required', 'string', 'max:60', 'unique:seo_sites,site_id'],
            'name'        => ['required', 'string', 'max:120'],
            'url'         => ['required', 'url', 'max:255'],
            'niche'       => ['required', 'string', 'max:80'],
            'locale'      => ['required', 'string', 'max:10'],
            'preset'      => ['required', 'string', 'in:generic,amiantix'],
            'webhook_url' => ['nullable', 'url', 'max:255'],
        ]);

        $token = SeoSite::generateToken();

        SeoSite::query()->create([
            ...$data,
            'api_token_hash' => $token['hash'],
            'is_active'      => true,
        ]);

        return redirect()->route('admin.sites.index')
            ->with('new_token', $token['token'])
            ->with('new_token_site', $data['name']);
    }

    public function show(string $siteId): View
    {
        $site  = SeoSite::query()->where('site_id', $siteId)->firstOrFail();
        $pages = SeoPage::query()
            ->where('site_id', $siteId)
            ->select(['id', 'keyword', 'slug', 'status', 'seo_score', 'quality_score', 'updated_at'])
            ->orderByDesc('updated_at')
            ->paginate(25);

        return view('admin.sites.show', compact('site', 'pages'));
    }

    public function rotateToken(string $siteId): RedirectResponse
    {
        $site  = SeoSite::query()->where('site_id', $siteId)->firstOrFail();
        $token = SeoSite::generateToken();
        $site->update(['api_token_hash' => $token['hash']]);

        return redirect()->route('admin.sites.show', $siteId)
            ->with('new_token', $token['token'])
            ->with('new_token_site', $site->name);
    }

    public function destroy(string $siteId): RedirectResponse
    {
        SeoSite::query()->where('site_id', $siteId)->update(['is_active' => false]);

        return redirect()->route('admin.sites.index')->with('success', 'Site désactivé.');
    }
}
