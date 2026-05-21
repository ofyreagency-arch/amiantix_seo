<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SeoSite;
use App\Models\SeoSiteGoogleConnection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SeoAdminController extends Controller
{
    public function index(): JsonResponse
    {
        $sites = SeoSite::query()
            ->with('googleConnection')
            ->select(['id', 'site_id', 'name', 'url', 'niche', 'locale', 'preset', 'is_active', 'webhook_url', 'gsc_site_url', 'gsc_credentials_path', 'created_at'])
            ->orderBy('created_at')
            ->get();

        return response()->json([
            'sites' => $sites->map(fn (SeoSite $site): array => [
                'id' => $site->id,
                'site_id' => $site->site_id,
                'name' => $site->name,
                'url' => $site->url,
                'niche' => $site->niche,
                'locale' => $site->locale,
                'preset' => $site->preset,
                'is_active' => $site->is_active,
                'webhook_url' => $site->webhook_url,
                'gsc_property_url' => $site->resolvedGscSiteUrl(),
                'gsc_connection_mode' => $site->resolvedGscConnectionMode(),
                'gsc_connection_status' => $site->resolvedGscConnectionStatus(),
                'gsc_account_email' => $site->resolvedGoogleConnection()?->google_account_email,
                'created_at' => $site->created_at,
            ]),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'site_id'              => ['required', 'string', 'max:64', 'unique:seo_sites,site_id', 'regex:/^[a-z0-9_-]+$/'],
            'name'                 => ['required', 'string', 'max:255'],
            'url'                  => ['required', 'url', 'max:500'],
            'niche'                => ['nullable', 'string', 'max:100'],
            'locale'               => ['nullable', 'string', 'max:20'],
            'preset'               => ['nullable', 'string', 'in:generic,amiantix'],
            'webhook_url'          => ['nullable', 'url', 'max:500'],
            'gsc_site_url'         => ['nullable', 'string', 'max:500'],
            'gsc_credentials_path' => ['nullable', 'string', 'max:500'],
            'gsc_connection_mode'  => ['nullable', 'string', 'in:service_account,oauth_google'],
            'gsc_property_url'     => ['nullable', 'string', 'max:500'],
            'gsc_account_email'    => ['nullable', 'email', 'max:255'],
        ]);

        ['token' => $raw, 'hash' => $hash] = SeoSite::generateToken();

        $site = SeoSite::query()->create([
            ...$data,
            'niche'          => $data['niche'] ?? 'general',
            'locale'         => $data['locale'] ?? 'en',
            'preset'         => $data['preset'] ?? (($data['niche'] ?? null) === 'amiante' ? 'amiantix' : 'generic'),
            'api_token_hash' => $hash,
            'is_active'      => true,
            'gsc_site_url' => $data['gsc_property_url'] ?? $data['gsc_site_url'] ?? null,
            'gsc_credentials_path' => $data['gsc_credentials_path'] ?? null,
        ]);

        $this->syncGoogleConnection($site, $data);

        return response()->json([
            'site'      => [
                ...$site->only(['id', 'site_id', 'name', 'url', 'niche', 'locale', 'preset', 'created_at']),
                'gsc_property_url' => $site->resolvedGscSiteUrl(),
                'gsc_connection_mode' => $site->resolvedGscConnectionMode(),
                'gsc_connection_status' => $site->resolvedGscConnectionStatus(),
            ],
            'api_token' => $raw,
            'warning'   => 'Store this token now — it will never be shown again.',
        ], 201);
    }

    public function rotateToken(string $siteId): JsonResponse
    {
        $site = SeoSite::query()->where('site_id', $siteId)->firstOrFail();

        ['token' => $raw, 'hash' => $hash] = SeoSite::generateToken();
        $site->update(['api_token_hash' => $hash]);

        return response()->json([
            'site_id'   => $site->site_id,
            'api_token' => $raw,
            'warning'   => 'Store this token now — it will never be shown again.',
        ]);
    }

    public function update(Request $request, string $siteId): JsonResponse
    {
        $site = SeoSite::query()->where('site_id', $siteId)->firstOrFail();

        $data = $request->validate([
            'name'                 => ['sometimes', 'string', 'max:255'],
            'url'                  => ['sometimes', 'url', 'max:500'],
            'niche'                => ['sometimes', 'string', 'max:100'],
            'locale'               => ['sometimes', 'string', 'max:20'],
            'preset'               => ['sometimes', 'string', 'in:generic,amiantix'],
            'webhook_url'          => ['sometimes', 'nullable', 'url', 'max:500'],
            'gsc_site_url'         => ['sometimes', 'nullable', 'string', 'max:500'],
            'gsc_credentials_path' => ['sometimes', 'nullable', 'string', 'max:500'],
            'gsc_connection_mode'  => ['sometimes', 'nullable', 'string', 'in:service_account,oauth_google'],
            'gsc_property_url'     => ['sometimes', 'nullable', 'string', 'max:500'],
            'gsc_account_email'    => ['sometimes', 'nullable', 'email', 'max:255'],
            'is_active'            => ['sometimes', 'boolean'],
        ]);

        if (array_key_exists('gsc_property_url', $data)) {
            $data['gsc_site_url'] = $data['gsc_property_url'];
        }

        $site->update($data);
        $this->syncGoogleConnection($site, $data, clearWhenExplicitlyEmpty: true);

        $site = $site->fresh(['googleConnection']);

        return response()->json([
            'site' => [
                ...$site->toArray(),
                'gsc_property_url' => $site->resolvedGscSiteUrl(),
                'gsc_connection_mode' => $site->resolvedGscConnectionMode(),
                'gsc_connection_status' => $site->resolvedGscConnectionStatus(),
            ],
        ]);
    }

    public function destroy(string $siteId): JsonResponse
    {
        $site = SeoSite::query()->where('site_id', $siteId)->firstOrFail();
        $site->update(['is_active' => false]);

        return response()->json(['message' => "Site {$siteId} deactivated."]);
    }

    /**
     * @param  array<string,mixed>  $data
     */
    private function syncGoogleConnection(SeoSite $site, array $data, bool $clearWhenExplicitlyEmpty = false): void
    {
        $modeProvided = array_key_exists('gsc_connection_mode', $data);
        $propertyProvided = array_key_exists('gsc_property_url', $data) || array_key_exists('gsc_site_url', $data);
        $credentialsProvided = array_key_exists('gsc_credentials_path', $data);
        $emailProvided = array_key_exists('gsc_account_email', $data);

        if (! $modeProvided && ! $propertyProvided && ! $credentialsProvided && ! $emailProvided) {
            return;
        }

        $propertyUrl = trim((string) ($data['gsc_property_url'] ?? $data['gsc_site_url'] ?? ''));
        $credentialsPath = trim((string) ($data['gsc_credentials_path'] ?? ''));
        $accountEmail = trim((string) ($data['gsc_account_email'] ?? ''));
        $mode = trim((string) ($data['gsc_connection_mode'] ?? ''));

        $hasConnectionData = $propertyUrl !== '' || $credentialsPath !== '' || $accountEmail !== '' || $mode !== '';

        if (! $hasConnectionData && $clearWhenExplicitlyEmpty) {
            SeoSiteGoogleConnection::query()->where('site_id', $site->site_id)->delete();

            return;
        }

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
