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
use Illuminate\Support\Facades\Schema;

class ClientSitesController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $sites = $user->seoSites()
            ->with('googleConnection')
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

        $site = $site->fresh(['googleConnection']);

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
            $site = $site->fresh(['googleConnection']);

            return response()->json([
                'site' => $this->serializeSite($site),
            ]);
        }

        if (! $site->users()->exists()) {
            $user->seoSites()->attach($site->id, ['role' => 'owner']);
            $site = $site->fresh(['googleConnection']);

            return response()->json([
                'site' => $this->serializeSite($site),
                'claimed_existing' => true,
            ]);
        }

        return response()->json([
            'message' => 'Ce site existe deja dans PraeviSEO. Utilisez le code de connexion pour le rattacher.',
        ], 409);
    }

    private function serializeSite(SeoSite $site): array
    {
        $pageQuery = SeoPage::query()->where('site_id', $site->site_id);
        $suggestionQuery = SeoSuggestion::query()
            ->whereHas('page', fn ($query) => $query->where('site_id', $site->site_id));
        $hasPublishedLiveColumn = Schema::hasColumn('seo_pages', 'published_live');

        $pagesPublished = (clone $pageQuery)->where('status', 'published')->count();
        $pagesLive = $hasPublishedLiveColumn
            ? (clone $pageQuery)->where('published_live', true)->count()
            : 0;
        $pendingSuggestions = (clone $suggestionQuery)->where('status', 'pending')->count();
        $gscConnected = $site->resolvedGscConnectionStatus() === 'connected';
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
            'created_at' => $site->created_at,
            'summary' => [
                'pages_total' => (clone $pageQuery)->count(),
                'pages_published' => $pagesPublished,
                'pages_live' => $pagesLive,
                'pending_suggestions' => $pendingSuggestions,
                'observed_pages' => SeoSitePage::query()->where('site_id', $site->site_id)->count(),
                'search_console_metrics' => SeoSearchConsoleMetric::query()->where('site_id', $site->site_id)->count(),
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
}
