<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SeoPage;
use App\Models\SeoSite;
use App\Models\SeoSiteGoogleConnection;
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
        $sites = SeoSite::query()
            ->with('googleConnection')
            ->orderByDesc('created_at')
            ->get();

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
            'gsc_connection_mode' => ['nullable', 'string', 'in:service_account,oauth_google'],
            'gsc_property_url' => ['nullable', 'string', 'max:500'],
            'gsc_credentials_path' => ['nullable', 'string', 'max:500'],
            'gsc_account_email' => ['nullable', 'email', 'max:255'],
        ]);

        $token = SeoSite::generateToken();

        $site = SeoSite::query()->create([
            ...$data,
            'api_token_hash' => $token['hash'],
            'is_active'      => true,
            'gsc_site_url' => $data['gsc_property_url'] ?? null,
            'gsc_credentials_path' => $data['gsc_credentials_path'] ?? null,
        ]);

        $this->syncGoogleConnection($site, $data);

        return redirect()->route('admin.sites.index')
            ->with('new_token', $token['token'])
            ->with('new_token_site', $data['name']);
    }

    public function show(string $siteId): View
    {
        $site  = SeoSite::query()->with('googleConnection')->where('site_id', $siteId)->firstOrFail();
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

    /**
     * @param  array<string,mixed>  $data
     */
    private function syncGoogleConnection(SeoSite $site, array $data): void
    {
        $propertyUrl = trim((string) ($data['gsc_property_url'] ?? ''));
        $credentialsPath = trim((string) ($data['gsc_credentials_path'] ?? ''));
        $accountEmail = trim((string) ($data['gsc_account_email'] ?? ''));
        $mode = trim((string) ($data['gsc_connection_mode'] ?? ''));

        $hasConnectionData = $propertyUrl !== '' || $credentialsPath !== '' || $accountEmail !== '' || $mode !== '';

        if (! $hasConnectionData) {
            return;
        }

        SeoSiteGoogleConnection::query()->updateOrCreate(
            ['site_id' => $site->site_id],
            [
                'connection_mode' => $mode !== '' ? $mode : 'service_account',
                'property_url' => $propertyUrl !== '' ? $propertyUrl : null,
                'google_account_email' => $accountEmail !== '' ? $accountEmail : null,
                'credentials_path' => $credentialsPath !== '' ? $credentialsPath : null,
                'connection_status' => ($propertyUrl !== '' || $credentialsPath !== '') ? 'configured' : 'not_connected',
                'last_error' => null,
            ],
        );
    }
}
