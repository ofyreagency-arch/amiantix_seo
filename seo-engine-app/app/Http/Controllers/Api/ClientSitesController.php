<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SeoPage;
use App\Models\SeoSearchConsoleMetric;
use App\Models\SeoSite;
use App\Models\SeoSitePage;
use App\Models\SeoSuggestion;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientSitesController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $sites = $user->seoSites()
            ->with('googleConnection')
            ->select(['seo_sites.id', 'site_id', 'name', 'url', 'niche', 'locale', 'preset', 'is_active', 'webhook_url', 'gsc_site_url', 'gsc_credentials_path', 'created_at'])
            ->orderBy('created_at')
            ->get();

        return response()->json([
            'sites' => $sites->map(fn (SeoSite $site): array => $this->serializeSite($site)),
        ]);
    }

    public function show(Request $request, string $siteId): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $site = $user->seoSites()
            ->with('googleConnection')
            ->where('site_id', $siteId)
            ->firstOrFail();

        return response()->json([
            'site' => $this->serializeSite($site),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'site_id' => ['required', 'string', 'max:64', 'unique:seo_sites,site_id', 'regex:/^[a-z0-9_-]+$/'],
            'name' => ['required', 'string', 'max:255'],
            'url' => ['required', 'url', 'max:500'],
            'niche' => ['nullable', 'string', 'max:100'],
            'locale' => ['nullable', 'string', 'max:20'],
            'preset' => ['nullable', 'string', 'in:generic,amiantix'],
            'publication_mode' => ['nullable', 'string', 'in:runtime,laravel_bridge,symfony_bridge,wordpress_bridge,webhook_api,disabled'],
            'publication_path_prefix' => ['nullable', 'string', 'max:120'],
        ]);

        ['hash' => $hash] = SeoSite::generateToken();

        $site = SeoSite::query()->create([
            'site_id' => $data['site_id'],
            'name' => $data['name'],
            'url' => $data['url'],
            'niche' => $data['niche'] ?? 'general',
            'locale' => $data['locale'] ?? 'fr',
            'preset' => $data['preset'] ?? 'generic',
            'api_token_hash' => $hash,
            'is_active' => true,
        ]);

        $this->syncPublicationTarget($site, $data);

        $user->seoSites()->attach($site->id, ['role' => 'owner']);

        $site = $site->fresh(['googleConnection']);

        return response()->json([
            'site' => $this->serializeSite($site),
        ], 201);
    }

    /**
     * @param array<string,mixed> $data
     */
    private function syncPublicationTarget(SeoSite $site, array $data): void
    {
        $settings = $site->settings_json ?? [];
        $publication = is_array($settings['publication'] ?? null) ? $settings['publication'] : [];

        if (array_key_exists('publication_mode', $data)) {
            $publication['mode'] = (string) ($data['publication_mode'] ?: 'runtime');
        }

        if (array_key_exists('publication_path_prefix', $data)) {
            $publication['path_prefix'] = trim((string) ($data['publication_path_prefix'] ?? ''), '/') ?: null;
        }

        if (in_array((string) ($publication['mode'] ?? ''), ['laravel_bridge', 'symfony_bridge', 'wordpress_bridge'], true)) {
            $publication['connect_code'] = $publication['connect_code'] ?? SeoSite::generatePublicationConnectCode();
            $publication['bridge_status'] = $publication['bridge_status'] ?? 'pending';
        }

        $settings['publication'] = $publication;
        $site->forceFill(['settings_json' => $settings])->save();
    }

    private function serializeSite(SeoSite $site): array
    {
        $pageQuery = SeoPage::query()->where('site_id', $site->site_id);
        $suggestionQuery = SeoSuggestion::query()
            ->whereHas('page', fn ($query) => $query->where('site_id', $site->site_id));

        return [
            'id' => $site->id,
            'site_id' => $site->site_id,
            'name' => $site->name,
            'url' => $site->url,
            'niche' => $site->niche,
            'locale' => $site->locale,
            'preset' => $site->preset,
            'is_active' => $site->is_active,
            'webhook_url' => $site->webhook_url,
            'publication_mode' => $site->resolvedPublicationMode(),
            'publication_mode_label' => $site->resolvedPublicationModeLabel(),
            'publication_connect_code' => $site->publicationConnectCode(),
            'publication_bridge_status' => $site->publicationBridgeStatus(),
            'publication_path_prefix' => $site->publicationPathPrefix(),
            'gsc_property_url' => $site->resolvedGscSiteUrl(),
            'gsc_connection_mode' => $site->resolvedGscConnectionMode(),
            'gsc_connection_status' => $site->resolvedGscConnectionStatus(),
            'gsc_account_email' => $site->resolvedGoogleConnection()?->google_account_email,
            'gsc_last_sync_at' => $site->resolvedGoogleConnection()?->last_sync_at,
            'created_at' => $site->created_at,
            'summary' => [
                'pages_total' => (clone $pageQuery)->count(),
                'pages_published' => (clone $pageQuery)->where('status', 'published')->count(),
                'pending_suggestions' => (clone $suggestionQuery)->where('status', 'pending')->count(),
                'observed_pages' => SeoSitePage::query()->where('site_id', $site->site_id)->count(),
                'search_console_metrics' => SeoSearchConsoleMetric::query()->where('site_id', $site->site_id)->count(),
            ],
        ];
    }
}
