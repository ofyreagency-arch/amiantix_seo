<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SeoSite;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SeoAdminController extends Controller
{
    public function index(): JsonResponse
    {
        $sites = SeoSite::query()
            ->select(['id', 'site_id', 'name', 'url', 'niche', 'locale', 'preset', 'is_active', 'webhook_url', 'gsc_site_url', 'created_at'])
            ->orderBy('created_at')
            ->get();

        return response()->json(['sites' => $sites]);
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
        ]);

        ['token' => $raw, 'hash' => $hash] = SeoSite::generateToken();

        $site = SeoSite::query()->create([
            ...$data,
            'niche'          => $data['niche'] ?? 'general',
            'locale'         => $data['locale'] ?? 'en',
            'preset'         => $data['preset'] ?? (($data['niche'] ?? null) === 'amiante' ? 'amiantix' : 'generic'),
            'api_token_hash' => $hash,
            'is_active'      => true,
        ]);

        return response()->json([
            'site'      => $site->only(['id', 'site_id', 'name', 'url', 'niche', 'locale', 'preset', 'created_at']),
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
            'is_active'            => ['sometimes', 'boolean'],
        ]);

        $site->update($data);

        return response()->json(['site' => $site->fresh()]);
    }

    public function destroy(string $siteId): JsonResponse
    {
        $site = SeoSite::query()->where('site_id', $siteId)->firstOrFail();
        $site->update(['is_active' => false]);

        return response()->json(['message' => "Site {$siteId} deactivated."]);
    }
}
