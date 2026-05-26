<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\RunRemoteInstallationJob;
use App\Models\RemoteInstallation;
use App\Models\SeoPage;
use App\Models\SeoSearchConsoleMetric;
use App\Models\SeoSite;
use App\Models\SeoSiteGoogleConnection;
use App\Models\SeoSitePage;
use App\Models\SeoSuggestion;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class ClientSitesController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $sites = $user->seoSites()
            ->with(['googleConnection', 'latestRemoteInstallation'])
            ->select([
                'seo_sites.id',
                'seo_sites.site_id',
                'seo_sites.name',
                'seo_sites.url',
                'seo_sites.niche',
                'seo_sites.locale',
                'seo_sites.preset',
                'seo_sites.is_active',
                'seo_sites.webhook_url',
                'seo_sites.gsc_site_url',
                'seo_sites.gsc_credentials_path',
                'seo_sites.created_at',
                'seo_sites.settings_json',
            ])
            ->orderBy('seo_sites.created_at')
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
            ->with(['googleConnection', 'latestRemoteInstallation'])
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
            'site_id' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9_-]+$/'],
            'name' => ['required', 'string', 'max:255'],
            'url' => ['required', 'url', 'max:500'],
            'niche' => ['nullable', 'string', 'max:100'],
            'locale' => ['nullable', 'string', 'max:20'],
            'preset' => ['nullable', 'string', 'in:generic,amiantix'],
            'publication_mode' => ['nullable', 'string', 'in:runtime,laravel_bridge,symfony_bridge,wordpress_bridge,webhook_api,disabled'],
            'publication_path_prefix' => ['nullable', 'string', 'max:120'],
        ]);

        $existingSite = SeoSite::query()
            ->where('site_id', $data['site_id'])
            ->first();

        if ($existingSite) {
            return $this->attachExistingSiteIfAllowed($user, $existingSite, $data['url']);
        }

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

        $site = $site->fresh(['googleConnection', 'latestRemoteInstallation']);

        return response()->json([
            'site' => $this->serializeSite($site),
        ], 201);
    }

    public function claim(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'connect_code' => ['nullable', 'string', 'max:32'],
            'site_id' => ['nullable', 'string', 'max:64'],
            'url' => ['nullable', 'url', 'max:500'],
        ]);

        $site = null;

        if (! empty($data['connect_code'])) {
            $site = SeoSite::resolveByPublicationConnectCode((string) $data['connect_code']);

            if (! $site) {
                return response()->json([
                    'message' => 'Code de connexion invalide.',
                ], 422);
            }
        } elseif (! empty($data['site_id'])) {
            $site = SeoSite::query()
                ->where('site_id', (string) $data['site_id'])
                ->first();
        }

        if (! $site) {
            return response()->json([
                'message' => 'Site introuvable.',
            ], 404);
        }

        if (! empty($data['site_id']) && $site->site_id !== $data['site_id']) {
            return response()->json([
                'message' => 'Le code de connexion ne correspond pas au site demandé.',
            ], 422);
        }

        return $this->attachExistingSiteIfAllowed($user, $site, $data['url'] ?? null);
    }

    public function updateGsc(Request $request, string $siteId): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        /** @var SeoSite $site */
        $site = $user->seoSites()
            ->with(['googleConnection', 'latestRemoteInstallation'])
            ->where('site_id', $siteId)
            ->firstOrFail();

        $data = $request->validate([
            'gsc_connection_mode' => ['nullable', 'string', 'in:service_account,oauth_google'],
            'gsc_property_url' => ['required', 'string', 'max:500'],
            'gsc_credentials_path' => ['nullable', 'string', 'max:500'],
            'gsc_account_email' => ['nullable', 'email', 'max:255'],
        ]);

        $connectionMode = (string) ($data['gsc_connection_mode'] ?? 'service_account');
        $credentialsPath = trim((string) ($data['gsc_credentials_path'] ?? ''));
        $resolvedCredentialsPath = $credentialsPath !== ''
            ? $credentialsPath
            : (string) (Config::get('services.google_search_console.credentials')
                ?: Config::get('seo-engine.search_console.credentials')
                ?: '');

        $site->forceFill([
            'gsc_site_url' => $data['gsc_property_url'],
            'gsc_credentials_path' => $connectionMode === 'service_account'
                ? ($resolvedCredentialsPath !== '' ? $resolvedCredentialsPath : null)
                : null,
        ])->save();

        $this->syncGoogleConnection($site, [
            ...$data,
            'gsc_connection_mode' => $connectionMode,
            'gsc_credentials_path' => $connectionMode === 'service_account' ? $resolvedCredentialsPath : null,
        ]);

        $site = $site->fresh(['googleConnection', 'latestRemoteInstallation']);

        return response()->json([
            'site' => $this->serializeSite($site),
        ]);
    }

    public function requestInstallation(Request $request, string $siteId): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        /** @var SeoSite $site */
        $site = $user->seoSites()
            ->with(['googleConnection', 'latestRemoteInstallation'])
            ->where('site_id', $siteId)
            ->firstOrFail();

        $data = $request->validate([
            'hosting_provider' => ['required', 'string', 'in:vps_linux,ovh,ionos,hostinger,oswitch,vercel,other'],
            'access_method' => ['required', 'string', 'in:ssh,sftp,api'],
            'ssh_host' => ['nullable', 'string', 'max:255'],
            'ssh_port' => ['nullable', 'integer', 'between:1,65535'],
            'ssh_username' => ['nullable', 'string', 'max:120'],
            'ssh_project_path' => ['nullable', 'string', 'max:500'],
            'ssh_secret' => ['nullable', 'string', 'max:10000'],
            'ssh_sudo_command' => ['nullable', 'string', 'max:120'],
            'sftp_host' => ['nullable', 'string', 'max:255'],
            'sftp_port' => ['nullable', 'integer', 'between:1,65535'],
            'sftp_username' => ['nullable', 'string', 'max:120'],
            'sftp_password' => ['nullable', 'string', 'max:4000'],
            'sftp_project_path' => ['nullable', 'string', 'max:500'],
            'framework_hint' => ['nullable', 'string', 'max:120'],
            'api_platform' => ['nullable', 'string', 'max:120'],
            'api_token' => ['nullable', 'string', 'max:4000'],
            'api_project_id' => ['nullable', 'string', 'max:255'],
            'api_account_name' => ['nullable', 'string', 'max:255'],
            'api_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->validateInstallationAccess($data);

        $payload = $this->buildInstallationPayload($data);

        $installation = RemoteInstallation::query()->create([
            'site_id' => $site->site_id,
            'status' => RemoteInstallation::STATUS_PENDING,
            'current_step' => 'pending',
            'progress' => 0,
            'hosting_provider' => (string) ($data['hosting_provider'] ?? 'other'),
            'connection_type' => (string) ($data['access_method'] ?? 'ssh'),
            'encrypted_credentials' => $payload['encrypted_credentials'],
            'connection_metadata' => $payload['connection_metadata'],
            'logs_json' => [[
                'at' => now()->toIso8601String(),
                'level' => 'info',
                'step' => 'pending',
                'message' => 'Installation distante planifiée.',
            ]],
        ]);

        $settings = $site->settings_json ?? [];
        $publication = is_array($settings['publication'] ?? null) ? $settings['publication'] : [];
        $publication['bridge_status'] = 'requested';
        $settings['publication'] = $publication;
        $site->forceFill(['settings_json' => $settings])->save();

        RunRemoteInstallationJob::dispatch($installation->id);

        $site = $site->fresh(['googleConnection', 'latestRemoteInstallation']);

        return response()->json([
            'site' => $this->serializeSite($site),
            'installation' => $this->serializeInstallation($installation),
        ], 202);
    }

    public function installationStatus(Request $request, string $siteId): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        /** @var SeoSite $site */
        $site = $user->seoSites()
            ->with(['googleConnection', 'latestRemoteInstallation'])
            ->where('site_id', $siteId)
            ->firstOrFail();

        return response()->json([
            'site' => $this->serializeSite($site),
            'installation' => $this->serializeInstallation($site->latestRemoteInstallation),
        ]);
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

    private function attachExistingSiteIfAllowed(User $user, SeoSite $site, ?string $requestedUrl = null): JsonResponse
    {
        if ($requestedUrl && rtrim($site->url, '/') !== rtrim($requestedUrl, '/')) {
            return response()->json([
                'message' => 'Le site existe deja mais l URL ne correspond pas.',
            ], 422);
        }

        if ($user->seoSites()->where('seo_sites.id', $site->id)->exists()) {
            $site = $site->fresh(['googleConnection', 'latestRemoteInstallation']);

            return response()->json([
                'site' => $this->serializeSite($site),
            ]);
        }

        if (! $site->users()->exists()) {
            $user->seoSites()->attach($site->id, ['role' => 'owner']);
            $site = $site->fresh(['googleConnection', 'latestRemoteInstallation']);

            return response()->json([
                'site' => $this->serializeSite($site),
                'claimed_existing' => true,
            ]);
        }

        return response()->json([
            'message' => 'Ce site existe deja dans PraeviSEO. Utilisez le code de connexion pour le rattacher.',
        ], 409);
    }

    /**
     * @param array<string,mixed> $data
     */
    private function validateInstallationAccess(array $data): void
    {
        $method = (string) ($data['access_method'] ?? '');

        $requiredMap = match ($method) {
            'ssh' => [
                'ssh_host' => 'Merci de renseigner l hôte SSH.',
                'ssh_username' => 'Merci de renseigner l utilisateur SSH.',
                'ssh_project_path' => 'Merci de renseigner le chemin du projet.',
                'ssh_secret' => 'Merci de renseigner un accès SSH.',
            ],
            'sftp' => [
                'sftp_host' => 'Merci de renseigner l hôte SFTP ou FTP.',
                'sftp_username' => 'Merci de renseigner l identifiant SFTP ou FTP.',
                'sftp_password' => 'Merci de renseigner le mot de passe SFTP ou FTP.',
                'sftp_project_path' => 'Merci de renseigner le dossier du site.',
            ],
            'api' => [
                'api_platform' => 'Merci de renseigner la plateforme d hébergement.',
                'api_token' => 'Merci de renseigner le jeton API.',
                'api_project_id' => 'Merci de renseigner l identifiant du projet ou du site.',
            ],
            default => [],
        };

        foreach ($requiredMap as $field => $message) {
            if (trim((string) ($data[$field] ?? '')) === '') {
                throw ValidationException::withMessages([
                    $field => $message,
                ]);
            }
        }

        if ($method === 'ssh') {
            $this->assertValidRemoteHost((string) ($data['ssh_host'] ?? ''), 'ssh_host');
        }

        if ($method === 'sftp') {
            $this->assertValidRemoteHost((string) ($data['sftp_host'] ?? ''), 'sftp_host');
        }
    }

    private function assertValidRemoteHost(string $host, string $field): void
    {
        $normalized = trim($host);

        if ($normalized === '') {
            return;
        }

        $isIp = filter_var($normalized, FILTER_VALIDATE_IP) !== false;
        $isHostname = preg_match(
            '/^(?=.{1,253}$)(?!-)(?:[a-zA-Z0-9-]{1,63}\.)*[a-zA-Z0-9-]{1,63}$/',
            $normalized
        ) === 1;

        if (! $isIp && ! $isHostname) {
            throw ValidationException::withMessages([
                $field => 'L hote distant doit etre une adresse IP ou un nom de domaine valide.',
            ]);
        }

        if (in_array(strtolower($normalized), ['localhost', 'localhost.localdomain'], true)) {
            throw ValidationException::withMessages([
                $field => 'PraeviSEO a besoin d un hote distant public, pas d un localhost.',
            ]);
        }
    }

    /**
     * @param array<string,mixed> $data
     * @return array{encrypted_credentials:array<string,mixed>,connection_metadata:array<string,mixed>}
     */
    private function buildInstallationPayload(array $data): array
    {
        $method = (string) ($data['access_method'] ?? 'ssh');

        $payload = [
            'encrypted_credentials' => [],
            'connection_metadata' => [
                'hosting_provider' => (string) ($data['hosting_provider'] ?? 'other'),
                'access_method' => $method,
                'requested_at' => now()->toIso8601String(),
            ],
        ];

        if ($method === 'ssh') {
            $payload['encrypted_credentials'] = [
                'host' => trim((string) ($data['ssh_host'] ?? '')),
                'port' => (int) ($data['ssh_port'] ?? 22),
                'username' => trim((string) ($data['ssh_username'] ?? '')),
                'secret' => trim((string) ($data['ssh_secret'] ?? '')),
            ];

            $payload['connection_metadata'] = [
                ...$payload['connection_metadata'],
                'host' => trim((string) ($data['ssh_host'] ?? '')),
                'port' => (int) ($data['ssh_port'] ?? 22),
                'username' => trim((string) ($data['ssh_username'] ?? '')),
                'project_path' => trim((string) ($data['ssh_project_path'] ?? '')),
                'sudo_command' => trim((string) ($data['ssh_sudo_command'] ?? '')) ?: null,
            ];
        }

        if ($method === 'sftp') {
            $payload['encrypted_credentials'] = [
                'host' => trim((string) ($data['sftp_host'] ?? '')),
                'port' => (int) ($data['sftp_port'] ?? 22),
                'username' => trim((string) ($data['sftp_username'] ?? '')),
                'password' => trim((string) ($data['sftp_password'] ?? '')),
            ];

            $payload['connection_metadata'] = [
                ...$payload['connection_metadata'],
                'host' => trim((string) ($data['sftp_host'] ?? '')),
                'port' => (int) ($data['sftp_port'] ?? 22),
                'username' => trim((string) ($data['sftp_username'] ?? '')),
                'project_path' => trim((string) ($data['sftp_project_path'] ?? '')),
                'framework_hint' => trim((string) ($data['framework_hint'] ?? '')) ?: null,
            ];
        }

        if ($method === 'api') {
            $payload['encrypted_credentials'] = [
                'token' => trim((string) ($data['api_token'] ?? '')),
            ];

            $payload['connection_metadata'] = [
                ...$payload['connection_metadata'],
                'platform' => trim((string) ($data['api_platform'] ?? '')),
                'project_id' => trim((string) ($data['api_project_id'] ?? '')),
                'account_name' => trim((string) ($data['api_account_name'] ?? '')) ?: null,
                'notes' => trim((string) ($data['api_notes'] ?? '')) ?: null,
            ];
        }

        return $payload;
    }

    /**
     * @param array<string,mixed> $data
     */
    private function syncGoogleConnection(SeoSite $site, array $data): void
    {
        $propertyUrl = trim((string) ($data['gsc_property_url'] ?? ''));
        $credentialsPath = trim((string) ($data['gsc_credentials_path'] ?? ''));
        $accountEmail = trim((string) ($data['gsc_account_email'] ?? ''));
        $mode = trim((string) ($data['gsc_connection_mode'] ?? ''));

        if ($propertyUrl === '' && $credentialsPath === '' && $accountEmail === '' && $mode === '') {
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

    private function serializeSite(SeoSite $site): array
    {
        $pageQuery = SeoPage::query()->where('site_id', $site->site_id);
        $suggestionQuery = SeoSuggestion::query()
            ->whereHas('page', fn ($query) => $query->where('site_id', $site->site_id));
        $hasPublishedLiveColumn = Schema::hasColumn('seo_pages', 'published_live');
        $gscSnapshot = $this->searchConsoleSnapshot($site->site_id);
        $installation = $site->relationLoaded('latestRemoteInstallation')
            ? $site->getRelation('latestRemoteInstallation')
            : $site->latestRemoteInstallation()->first();

        $pagesPublished = (clone $pageQuery)->where('status', 'published')->count();
        $pagesLive = $hasPublishedLiveColumn
            ? (clone $pageQuery)->where('published_live', true)->count()
            : 0;
        $pendingSuggestions = (clone $suggestionQuery)->where('status', 'pending')->count();
        $gscStatus = $site->resolvedGscConnectionStatus();
        $gscConnected = in_array($gscStatus, ['configured', 'connected', 'connected_empty'], true);
        $bridgeConnected = $site->publicationBridgeStatus() === 'connected';

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
            'installation' => $this->serializeInstallation($installation),
            'created_at' => $site->created_at,
            'summary' => [
                'pages_total' => (clone $pageQuery)->count(),
                'pages_published' => $pagesPublished,
                'pages_live' => $pagesLive,
                'pending_suggestions' => $pendingSuggestions,
                'observed_pages' => SeoSitePage::query()->where('site_id', $site->site_id)->count(),
                'gsc_impressions' => $gscSnapshot['impressions'],
                'gsc_clicks' => $gscSnapshot['clicks'],
                'gsc_ctr' => $gscSnapshot['ctr'],
                'gsc_indexed_pages' => $gscSnapshot['indexed_pages'],
                'gsc_indexation_synced' => $gscSnapshot['indexed_pages_synced'],
                'gsc_previous_impressions' => $gscSnapshot['previous_impressions'],
                'gsc_previous_clicks' => $gscSnapshot['previous_clicks'],
                'gsc_previous_ctr' => $gscSnapshot['previous_ctr'],
                'gsc_delta_impressions' => $gscSnapshot['delta_impressions'],
                'gsc_delta_clicks' => $gscSnapshot['delta_clicks'],
                'gsc_delta_ctr_points' => $gscSnapshot['delta_ctr_points'],
                'gsc_non_indexed_pages' => $gscSnapshot['non_indexed_pages'],
                'top_rising_pages' => $gscSnapshot['top_rising_pages'],
                'top_falling_pages' => $gscSnapshot['top_falling_pages'],
                'top_queries' => $gscSnapshot['top_queries'],
                'top_rising_queries' => $gscSnapshot['top_rising_queries'],
                'top_falling_queries' => $gscSnapshot['top_falling_queries'],
                'new_queries' => $gscSnapshot['new_queries'],
                'indexation_alerts' => $gscSnapshot['indexation_alerts'],
            ],
            'readiness' => [
                'bridge_connected' => $bridgeConnected,
                'gsc_connected' => $gscConnected,
                'has_published_pages' => $pagesPublished > 0,
                'has_live_pages' => $pagesLive > 0,
            ],
            'next_action' => $this->nextActionForSite(
                site: $site,
                bridgeConnected: $bridgeConnected,
                gscConnected: $gscConnected,
                pagesPublished: $pagesPublished,
                pagesLive: $pagesLive,
                pendingSuggestions: $pendingSuggestions,
            ),
        ];
    }

    /**
     * @return array{
     *     impressions:float,
     *     clicks:float,
     *     ctr:float,
     *     indexed_pages:int,
     *     indexed_pages_synced:bool,
     *     previous_impressions:float,
     *     previous_clicks:float,
     *     previous_ctr:float,
     *     delta_impressions:float,
     *     delta_clicks:float,
     *     delta_ctr_points:float,
     *     non_indexed_pages:int,
     *     top_rising_pages:array<int,array<string,mixed>>,
     *     top_falling_pages:array<int,array<string,mixed>>,
     *     top_queries:array<int,array<string,mixed>>,
     *     top_rising_queries:array<int,array<string,mixed>>,
     *     top_falling_queries:array<int,array<string,mixed>>,
     *     new_queries:array<int,array<string,mixed>>,
     *     indexation_alerts:array<int,array<string,mixed>>
     * }
     */
    private function searchConsoleSnapshot(string $siteId): array
    {
        $baseQuery = SeoSearchConsoleMetric::query()
            ->where('site_id', $siteId)
            ->whereNull('query')
            ->where('window_days', 28);

        $latestMetricDate = (clone $baseQuery)->max('metric_date');

        if (! $latestMetricDate) {
            return [
                'impressions' => 0.0,
                'clicks' => 0.0,
                'ctr' => 0.0,
                'indexed_pages' => 0,
                'indexed_pages_synced' => false,
                'previous_impressions' => 0.0,
                'previous_clicks' => 0.0,
                'previous_ctr' => 0.0,
                'delta_impressions' => 0.0,
                'delta_clicks' => 0.0,
                'delta_ctr_points' => 0.0,
                'non_indexed_pages' => 0,
                'top_rising_pages' => [],
                'top_falling_pages' => [],
                'top_queries' => [],
                'top_rising_queries' => [],
                'top_falling_queries' => [],
                'new_queries' => [],
                'indexation_alerts' => [],
            ];
        }

        $latestMetricDate = Carbon::parse((string) $latestMetricDate)->toDateString();
        $previousMetricDate = (clone $baseQuery)
            ->whereDate('metric_date', '<', $latestMetricDate)
            ->max('metric_date');

        $snapshotRows = (clone $baseQuery)
            ->whereDate('metric_date', $latestMetricDate)
            ->orderByDesc('id')
            ->get(['id', 'url', 'clicks', 'impressions', 'ctr', 'position', 'is_indexed', 'coverage_json', 'payload_json']);
        $previousRows = $previousMetricDate
            ? (clone $baseQuery)
                ->whereDate('metric_date', Carbon::parse((string) $previousMetricDate)->toDateString())
                ->orderByDesc('id')
                ->get(['id', 'url', 'clicks', 'impressions', 'ctr', 'position', 'is_indexed', 'coverage_json', 'payload_json'])
            : collect();
        $queryRows = SeoSearchConsoleMetric::query()
            ->where('site_id', $siteId)
            ->whereNotNull('query')
            ->where('window_days', 28)
            ->whereDate('metric_date', $latestMetricDate)
            ->orderByDesc('impressions')
            ->get(['query', 'clicks', 'impressions', 'ctr', 'position']);
        $previousQueryRows = $previousMetricDate
            ? SeoSearchConsoleMetric::query()
                ->where('site_id', $siteId)
                ->whereNotNull('query')
                ->where('window_days', 28)
                ->whereDate('metric_date', Carbon::parse((string) $previousMetricDate)->toDateString())
                ->orderByDesc('impressions')
                ->get(['query', 'clicks', 'impressions', 'ctr', 'position'])
            : collect();

        $aggregateRow = $snapshotRows->first(
            fn (SeoSearchConsoleMetric $metric): bool => trim((string) $metric->url) === ''
        );
        $previousAggregateRow = $previousRows->first(
            fn (SeoSearchConsoleMetric $metric): bool => trim((string) $metric->url) === ''
        );

        $pageRows = $snapshotRows->filter(
            fn (SeoSearchConsoleMetric $metric): bool => trim((string) $metric->url) !== ''
        );

        $deduplicatedRows = $pageRows->unique(function (SeoSearchConsoleMetric $metric): string {
            return $this->searchConsoleUrlKey($metric);
        })->values();
        $previousPageRows = $previousRows
            ->filter(fn (SeoSearchConsoleMetric $metric): bool => trim((string) $metric->url) !== '')
            ->unique(fn (SeoSearchConsoleMetric $metric): string => $this->searchConsoleUrlKey($metric))
            ->values()
            ->keyBy(fn (SeoSearchConsoleMetric $metric): string => $this->searchConsoleUrlKey($metric));

        $impressions = $aggregateRow ? (float) $aggregateRow->impressions : (float) $deduplicatedRows->sum('impressions');
        $clicks = $aggregateRow ? (float) $aggregateRow->clicks : (float) $deduplicatedRows->sum('clicks');
        $ctr = $aggregateRow ? (float) $aggregateRow->ctr : ($impressions > 0 ? $clicks / $impressions : 0.0);
        $previousImpressions = $previousAggregateRow
            ? (float) $previousAggregateRow->impressions
            : (float) $previousPageRows->sum('impressions');
        $previousClicks = $previousAggregateRow
            ? (float) $previousAggregateRow->clicks
            : (float) $previousPageRows->sum('clicks');
        $previousCtr = $previousAggregateRow
            ? (float) $previousAggregateRow->ctr
            : ($previousImpressions > 0 ? $previousClicks / $previousImpressions : 0.0);
        $indexedPagesSynced = $deduplicatedRows->contains(
            fn (SeoSearchConsoleMetric $metric): bool => $metric->is_indexed !== null
        );
        $pageSignals = $deduplicatedRows
            ->map(function (SeoSearchConsoleMetric $metric) use ($previousPageRows): array {
                $key = $this->searchConsoleUrlKey($metric);
                /** @var SeoSearchConsoleMetric|null $previousMetric */
                $previousMetric = $previousPageRows->get($key);
                $currentImpressions = (float) $metric->impressions;
                $beforeImpressions = (float) ($previousMetric?->impressions ?? 0.0);
                $deltaImpressions = $currentImpressions - $beforeImpressions;

                return [
                    'label' => $this->searchConsoleLabelFromUrl((string) $metric->url),
                    'slug' => $this->searchConsoleSlugFromUrl((string) $metric->url),
                    'url' => (string) $metric->url,
                    'impressions' => (int) round($currentImpressions),
                    'previous_impressions' => (int) round($beforeImpressions),
                    'delta_impressions' => (int) round($deltaImpressions),
                    'delta_percent' => $beforeImpressions > 0.0
                        ? round(($deltaImpressions / $beforeImpressions) * 100, 1)
                        : 100.0,
                    'clicks' => (int) round((float) $metric->clicks),
                    'ctr' => round(((float) $metric->ctr) * 100, 2),
                    'position' => round((float) $metric->position, 1),
                ];
            })
            ->filter(fn (array $item): bool => ($item['impressions'] > 0 || $item['previous_impressions'] > 0))
            ->values();
        $topRisingPages = $pageSignals
            ->filter(fn (array $item): bool => (int) $item['delta_impressions'] > 0)
            ->sortByDesc(fn (array $item): int => (int) $item['delta_impressions'])
            ->take(3)
            ->values()
            ->all();
        $topFallingPages = $pageSignals
            ->filter(fn (array $item): bool => (int) $item['delta_impressions'] < 0)
            ->sortBy(fn (array $item): int => (int) $item['delta_impressions'])
            ->take(3)
            ->values()
            ->all();
        $queryTotals = $queryRows
            ->groupBy(fn (SeoSearchConsoleMetric $metric): string => mb_strtolower(trim((string) $metric->query)))
            ->map(function ($rows, string $query): array {
                /** @var \Illuminate\Support\Collection<int, SeoSearchConsoleMetric> $rows */
                $impressions = (float) $rows->sum('impressions');
                $clicks = (float) $rows->sum('clicks');
                $positionWeighted = $impressions > 0
                    ? $rows->reduce(
                        fn (float $carry, SeoSearchConsoleMetric $metric): float => $carry + (((float) $metric->position) * ((float) $metric->impressions)),
                        0.0
                    ) / $impressions
                    : 0.0;

                return [
                    'query' => $query,
                    'impressions' => (int) round($impressions),
                    'clicks' => (int) round($clicks),
                    'ctr' => round($impressions > 0 ? ($clicks / $impressions) * 100 : 0.0, 2),
                    'position' => round($positionWeighted, 1),
                ];
            });
        $previousQueryTotals = $previousQueryRows
            ->groupBy(fn (SeoSearchConsoleMetric $metric): string => mb_strtolower(trim((string) $metric->query)))
            ->map(function ($rows, string $query): array {
                /** @var \Illuminate\Support\Collection<int, SeoSearchConsoleMetric> $rows */
                $impressions = (float) $rows->sum('impressions');
                $clicks = (float) $rows->sum('clicks');
                $positionWeighted = $impressions > 0
                    ? $rows->reduce(
                        fn (float $carry, SeoSearchConsoleMetric $metric): float => $carry + (((float) $metric->position) * ((float) $metric->impressions)),
                        0.0
                    ) / $impressions
                    : 0.0;

                return [
                    'query' => $query,
                    'impressions' => (int) round($impressions),
                    'clicks' => (int) round($clicks),
                    'ctr' => round($impressions > 0 ? ($clicks / $impressions) * 100 : 0.0, 2),
                    'position' => round($positionWeighted, 1),
                ];
            });
        $querySignals = $queryTotals
            ->map(function (array $item, string $query) use ($previousQueryTotals): array {
                $previous = $previousQueryTotals->get($query, [
                    'impressions' => 0,
                    'clicks' => 0,
                    'ctr' => 0.0,
                    'position' => 0.0,
                ]);
                $deltaImpressions = (int) $item['impressions'] - (int) ($previous['impressions'] ?? 0);
                $previousImpressions = (int) ($previous['impressions'] ?? 0);

                return [
                    ...$item,
                    'previous_impressions' => $previousImpressions,
                    'delta_impressions' => $deltaImpressions,
                    'delta_percent' => $previousImpressions > 0
                        ? round(($deltaImpressions / $previousImpressions) * 100, 1)
                        : 100.0,
                ];
            })
            ->filter(fn (array $item): bool => $item['query'] !== '' && $item['impressions'] > 0)
            ->values();
        $topQueries = $querySignals
            ->sortByDesc(fn (array $item): int => (int) $item['impressions'])
            ->take(5)
            ->values()
            ->all();
        $topRisingQueries = $querySignals
            ->filter(fn (array $item): bool => (int) $item['delta_impressions'] > 0)
            ->sortByDesc(fn (array $item): int => (int) $item['delta_impressions'])
            ->take(5)
            ->values()
            ->all();
        $topFallingQueries = $querySignals
            ->filter(fn (array $item): bool => (int) $item['delta_impressions'] < 0)
            ->sortBy(fn (array $item): int => (int) $item['delta_impressions'])
            ->take(5)
            ->values()
            ->all();
        $newQueries = $querySignals
            ->filter(fn (array $item): bool => (int) $item['previous_impressions'] === 0 && (int) $item['impressions'] > 0)
            ->sortByDesc(fn (array $item): int => (int) $item['impressions'])
            ->take(5)
            ->values()
            ->all();
        $indexationAlerts = $deduplicatedRows
            ->filter(fn (SeoSearchConsoleMetric $metric): bool => $metric->is_indexed === false)
            ->map(function (SeoSearchConsoleMetric $metric): array {
                $coverage = is_array($metric->coverage_json) ? $metric->coverage_json : [];
                $payload = is_array($metric->payload_json) ? $metric->payload_json : [];
                $inspection = is_array($payload['inspection'] ?? null) ? $payload['inspection'] : [];
                $detail = (string) (
                    $coverage['coverageState']
                    ?? data_get($inspection, 'inspectionResult.indexStatusResult.coverageState')
                    ?? data_get($inspection, 'inspectionResult.indexStatusResult.verdict')
                    ?? 'Google ne lit pas encore cette page comme indexée.'
                );

                return [
                    'label' => $this->searchConsoleLabelFromUrl((string) $metric->url),
                    'slug' => $this->searchConsoleSlugFromUrl((string) $metric->url),
                    'url' => (string) $metric->url,
                    'state' => $detail,
                    'detail' => $detail,
                ];
            })
            ->take(5)
            ->values()
            ->all();

        return [
            'impressions' => $impressions,
            'clicks' => $clicks,
            'ctr' => $ctr,
            'indexed_pages' => $deduplicatedRows->where('is_indexed', true)->count(),
            'indexed_pages_synced' => $indexedPagesSynced,
            'previous_impressions' => $previousImpressions,
            'previous_clicks' => $previousClicks,
            'previous_ctr' => $previousCtr,
            'delta_impressions' => $impressions - $previousImpressions,
            'delta_clicks' => $clicks - $previousClicks,
            'delta_ctr_points' => round(($ctr - $previousCtr) * 100, 2),
            'non_indexed_pages' => $deduplicatedRows->where('is_indexed', false)->count(),
            'top_rising_pages' => $topRisingPages,
            'top_falling_pages' => $topFallingPages,
            'top_queries' => $topQueries,
            'top_rising_queries' => $topRisingQueries,
            'top_falling_queries' => $topFallingQueries,
            'new_queries' => $newQueries,
            'indexation_alerts' => $indexationAlerts,
        ];
    }

    private function searchConsoleSlugFromUrl(string $url): string
    {
        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');

        return $path;
    }

    private function searchConsoleLabelFromUrl(string $url): string
    {
        $slug = $this->searchConsoleSlugFromUrl($url);

        if ($slug === '') {
            return (string) parse_url($url, PHP_URL_HOST);
        }

        $lastSegment = (string) last(array_filter(explode('/', $slug)));
        $normalized = str_replace(['-', '_'], ' ', $lastSegment);

        return mb_convert_case($normalized, MB_CASE_TITLE, 'UTF-8');
    }

    private function searchConsoleUrlKey(SeoSearchConsoleMetric $metric): string
    {
        $url = trim((string) $metric->url);

        if ($url === '') {
            return 'metric:'.$metric->id;
        }

        $payload = is_array($metric->payload_json) ? $metric->payload_json : [];
        $inspection = is_array($payload['inspection'] ?? null) ? $payload['inspection'] : [];
        $canonical = trim((string) data_get($inspection, 'inspectionResult.indexStatusResult.googleCanonical', ''));

        if ($canonical === '') {
            $canonical = trim((string) data_get($inspection, 'inspectionResult.indexStatusResult.userCanonical', ''));
        }

        if ($canonical !== '') {
            $url = $canonical;
        }

        $host = Str::lower((string) parse_url($url, PHP_URL_HOST));
        $host = preg_replace('/^www\./', '', $host ?? '') ?? '';
        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');

        return $host.'|'.$path;
    }

    /**
     * @return array{kind:string,label:string,detail:string,priority:string}
     */
    private function nextActionForSite(
        SeoSite $site,
        bool $bridgeConnected,
        bool $gscConnected,
        int $pagesPublished,
        int $pagesLive,
        int $pendingSuggestions,
    ): array {
        $latestInstallation = $site->relationLoaded('latestRemoteInstallation')
            ? $site->getRelation('latestRemoteInstallation')
            : $site->latestRemoteInstallation()->first();

        if ($latestInstallation && ! $bridgeConnected) {
            if ($latestInstallation->status === RemoteInstallation::STATUS_FAILED) {
                return [
                    'kind' => 'installation_failed',
                    'label' => 'Installation PraeviSEO à relancer',
                    'detail' => $latestInstallation->error_message ?: 'PraeviSEO n a pas pu terminer l installation distante sur ce site.',
                    'priority' => 'high',
                ];
            }

            if ($latestInstallation->status !== RemoteInstallation::STATUS_COMPLETED) {
                return [
                    'kind' => 'installation_requested',
                    'label' => 'PraeviSEO prépare votre installation',
                    'detail' => 'Vos accès ont bien été enregistrés. PraeviSEO travaille maintenant automatiquement sur votre site.',
                    'priority' => 'medium',
                ];
            }
        }

        if ($site->publicationBridgeStatus() === 'requested' && ! $bridgeConnected) {
            return [
                'kind' => 'installation_requested',
                'label' => 'PraeviSEO prépare votre installation',
                'detail' => 'Vos accès ont bien été enregistrés. PraeviSEO travaille maintenant automatiquement sur votre site.',
                'priority' => 'medium',
            ];
        }

        if (! $bridgeConnected) {
            return [
                'kind' => 'connect_bridge',
                'label' => 'Connecter le bridge officiel',
                'detail' => 'Installez le bridge pour activer la vraie publication et le monitoring du site public.',
                'priority' => 'high',
            ];
        }

        if (! $gscConnected) {
            return [
                'kind' => 'connect_gsc',
                'label' => 'Relier Google Search Console',
                'detail' => 'Activez les signaux Google pour laisser PraeviSEO détecter les vraies opportunités SEO.',
                'priority' => 'high',
            ];
        }

        if ($pendingSuggestions > 0) {
            return [
                'kind' => 'review_optimizations',
                'label' => 'Valider les optimisations en attente',
                'detail' => 'Le moteur a déjà trouvé des actions utiles. Passez en revue les suggestions en attente.',
                'priority' => 'medium',
            ];
        }

        if ($pagesPublished === 0) {
            return [
                'kind' => 'publish_first_page',
                'label' => 'Publier votre première page',
                'detail' => 'Le bridge est prêt. Il reste à publier un premier contenu pour démarrer la boucle SEO réelle.',
                'priority' => 'medium',
            ];
        }

        if ($pagesLive === 0 && $site->resolvedPublicationMode() !== 'runtime') {
            return [
                'kind' => 'publish_live',
                'label' => 'Pousser une première publication live',
                'detail' => 'Une page est prête côté moteur. Le prochain cap est de la pousser sur le vrai site client.',
                'priority' => 'medium',
            ];
        }

        return [
            'kind' => 'monitor',
            'label' => 'Laisser tourner le monitoring',
            'detail' => 'Le site est branché. PraeviSEO surveille maintenant les signaux et rouvrira des actions si besoin.',
            'priority' => 'low',
        ];
    }

    private function serializeInstallation(?RemoteInstallation $installation): array
    {
        if (! $installation) {
            return [
                'status' => 'not_started',
                'current_step' => null,
                'progress' => 0,
                'hosting_provider' => null,
                'access_method' => null,
                'requested_at' => null,
                'started_at' => null,
                'completed_at' => null,
                'failed_at' => null,
                'error_message' => null,
                'detected_framework' => null,
                'detected_php_version' => null,
                'detected_composer' => null,
                'logs' => [],
            ];
        }

        return [
            'status' => $installation->status,
            'current_step' => $installation->current_step,
            'progress' => $installation->progress,
            'hosting_provider' => $installation->hosting_provider,
            'access_method' => $installation->connection_type,
            'requested_at' => $installation->created_at?->toIso8601String(),
            'started_at' => $installation->started_at?->toIso8601String(),
            'completed_at' => $installation->completed_at?->toIso8601String(),
            'failed_at' => $installation->failed_at?->toIso8601String(),
            'error_message' => $installation->error_message,
            'detected_framework' => $installation->detected_framework,
            'detected_php_version' => $installation->detected_php_version,
            'detected_composer' => $installation->detected_composer,
            'logs' => $installation->safeLogs(),
        ];
    }
}
