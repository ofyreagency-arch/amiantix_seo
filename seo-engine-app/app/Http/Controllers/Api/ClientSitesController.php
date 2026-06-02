<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\ActionLayer\SeoSuggestionWorkflowService;
use App\Http\Controllers\Controller;
use App\Jobs\RunObservedSiteCrawlJob;
use App\Jobs\RunRemoteInstallationJob;
use App\Models\RemoteInstallation;
use App\Models\SeoPage;
use App\Models\SeoSearchConsoleMetric;
use App\Models\SeoSite;
use App\Models\SeoSiteCrawlIssue;
use App\Models\SeoSiteCrawl;
use App\Models\SeoSiteGoogleConnection;
use App\Models\SeoSitePage;
use App\Models\SeoSiteSnapshot;
use App\Models\SeoSuggestion;
use App\Services\Media\SeoPageImageGenerator;
use App\Services\Publication\SeoLivePublicationService;
use App\Models\User;
use App\RemoteInstallation\InstallationPrecheckService;
use App\Runtime\PremiumArticleGenerationService;
use App\Runtime\SeoEngineContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Ofyre\SeoEngine\Services\Console\SeoGeneratePageRunner;
use Ofyre\SeoEngine\Services\Rewrite\SeoRewriteService;
use Throwable;

class ClientSitesController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $sites = $user->seoSites()
            ->with(['googleConnection', 'latestRemoteInstallation', 'latestObservedCrawl'])
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
            ->with(['googleConnection', 'latestRemoteInstallation', 'latestObservedCrawl'])
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
        $site->loadMissing('latestObservedCrawl');

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
            ->with(['googleConnection', 'latestRemoteInstallation', 'latestObservedCrawl'])
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
        $site->loadMissing('latestObservedCrawl');

        return response()->json([
            'site' => $this->serializeSite($site),
        ]);
    }

    public function startObservedCrawl(Request $request, string $siteId): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        /** @var SeoSite $site */
        $site = $user->seoSites()
            ->with(['googleConnection', 'latestRemoteInstallation', 'latestObservedCrawl'])
            ->where('site_id', $siteId)
            ->firstOrFail();

        $crawl = $this->scheduleObservedCrawlIfIdle($site, 'premium_client');

        if ($crawl && in_array((string) $crawl->status, ['pending', 'running'], true) && (int) $crawl->id === (int) optional($site->latestObservedCrawl)->id) {
            return response()->json([
                'site' => $this->serializeSite($site),
                'crawl' => $this->serializeObservedCrawl($crawl),
            ], 202);
        }

        $site = $site->fresh(['googleConnection', 'latestRemoteInstallation', 'latestObservedCrawl']);

        return response()->json([
            'site' => $this->serializeSite($site),
            'crawl' => $this->serializeObservedCrawl($crawl->fresh()),
        ], 202);
    }

    public function startPremiumArticleGeneration(
        Request $request,
        string $siteId,
        SeoGeneratePageRunner $runner,
        SeoEngineContext $context,
        PremiumArticleGenerationService $articles,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        /** @var SeoSite $site */
        $site = $user->seoSites()
            ->with(['googleConnection', 'latestRemoteInstallation', 'latestObservedCrawl'])
            ->where('site_id', $siteId)
            ->firstOrFail();

        try {
            $this->markActionState($site, 'generation', 'running', 'PraeviSEO prépare un nouveau sujet éditorial à publier sur le site.');
            $keyword = $articles->resolveCandidateKeyword($site);

            if ($keyword === null) {
                $reason = $articles->limitReason($site);
                $this->markActionState(
                    $site,
                    'generation',
                    'failed',
                    $reason ?: 'Aucun nouveau sujet assez clair n est encore prêt à devenir un article.',
                    $reason ?: 'PraeviSEO n a pas encore trouvé de recherche Google assez utile et distincte pour ouvrir un nouvel article fiable.'
                );

                return response()->json([
                    'message' => $reason ?: 'Aucun nouveau sujet assez clair n est encore prêt à devenir un article sur ce site.',
                ], 422);
            }

            $context->loadFromSite($site);
            $result = $runner->run($keyword, 'published', false);
            $page = $result['page'] instanceof SeoPage
                ? $result['page']->fresh()
                : SeoPage::query()->where('site_id', $site->site_id)->find((int) ($result['page']->id ?? 0));

            if (! $page instanceof SeoPage) {
                throw new \RuntimeException('PraeviSEO a généré une page, mais n a pas pu la rattacher correctement au site.');
            }

            $this->appendExecutionHistory(
                $site,
                'Nouvel article généré',
                sprintf('PraeviSEO a créé "%s" à partir du sujet "%s".', (string) $page->title, $keyword),
                'default',
                'article_generated',
            );
            $this->markActionState(
                $site,
                'generation',
                'completed',
                sprintf('Un nouvel article "%s" est prêt dans le moteur et peut maintenant être enrichi, illustré puis publié.', (string) $page->title)
            );
            $site = $site->fresh(['googleConnection', 'latestRemoteInstallation', 'latestObservedCrawl']);

            return response()->json([
                'site' => $this->serializeSite($site),
                'generation' => [
                    'page_id' => (int) $page->id,
                    'slug' => (string) $page->slug,
                    'title' => (string) $page->title,
                    'keyword' => $keyword,
                    'status' => (string) $page->status,
                    'warning' => $result['warning'] ?? null,
                ],
            ], 202);
        } catch (Throwable $e) {
            $this->markActionState(
                $site,
                'generation',
                'failed',
                'La génération du nouvel article a échoué pour le moment.',
                $this->premiumActionErrorMessage($e, 'PraeviSEO n a pas pu créer automatiquement le nouvel article prévu.')
            );
            $this->appendExecutionHistory($site, 'Génération interrompue', $e->getMessage(), 'danger', 'generation_failed');

            return response()->json([
                'message' => 'PraeviSEO n a pas pu générer le nouvel article pour le moment.',
            ], 500);
        }
    }

    public function startPremiumRewrite(Request $request, string $siteId, SeoRewriteService $rewrite): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        /** @var SeoSite $site */
        $site = $user->seoSites()
            ->with(['googleConnection', 'latestRemoteInstallation', 'latestObservedCrawl'])
            ->where('site_id', $siteId)
            ->firstOrFail();

        try {
            $this->markActionState($site, 'rewrite', 'running', 'PraeviSEO prépare la prochaine amélioration de contenu.');
            $page = $this->resolveRewriteCandidatePage($siteId);

            if (! $page) {
                $this->markActionState(
                    $site,
                    'rewrite',
                    'failed',
                    'Aucune page utile n est encore assez claire pour une réécriture.',
                    'PraeviSEO n a pas encore trouvé de page avec assez de matière et de priorité pour préparer une amélioration fiable.'
                );

                return response()->json([
                    'message' => 'Aucune page claire n est encore prête pour une réécriture premium sur ce site.',
                ], 422);
            }

            $existingSuggestion = $page->suggestions()
                ->where('status', 'pending')
                ->latest('created_at')
                ->first();

            $suggestion = $existingSuggestion ?: $rewrite->createSuggestion($page->fresh(['suggestions']), 'enrich');
            $this->appendExecutionHistory(
                $site,
                'Réécriture préparée',
                sprintf('PraeviSEO a préparé une amélioration pour la page "%s".', (string) $page->title),
                'default',
                'rewrite_prepared',
            );
            $this->markActionState($site, 'rewrite', 'completed', sprintf('Une amélioration est prête pour "%s".', (string) $page->title));
            $site = $site->fresh(['googleConnection', 'latestRemoteInstallation', 'latestObservedCrawl']);

            return response()->json([
                'site' => $this->serializeSite($site),
                'rewrite' => [
                    'page_id' => (int) $page->id,
                    'slug' => (string) $page->slug,
                    'title' => (string) $page->title,
                    'suggestion_id' => (int) ($suggestion->id ?? 0),
                    'already_pending' => $existingSuggestion !== null,
                ],
            ], 202);
        } catch (Throwable $e) {
            $this->markActionState(
                $site,
                'rewrite',
                'failed',
                'La préparation de réécriture a échoué pour le moment.',
                $this->premiumActionErrorMessage($e, 'PraeviSEO n a pas pu préparer la réécriture à cause d un blocage technique ou d une page encore incomplète.')
            );
            $this->appendExecutionHistory($site, 'Réécriture interrompue', $e->getMessage(), 'danger', 'rewrite_failed');

            return response()->json([
                'message' => 'PraeviSEO n a pas pu préparer la réécriture pour le moment.',
            ], 500);
        }
    }

    public function startPremiumPublication(Request $request, string $siteId): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        /** @var SeoSite $site */
        $site = $user->seoSites()
            ->with(['googleConnection', 'latestRemoteInstallation', 'latestObservedCrawl'])
            ->where('site_id', $siteId)
            ->firstOrFail();

        try {
            $this->markActionState($site, 'publication', 'running', 'PraeviSEO prépare l envoi de la meilleure page vers le site.');
            $page = $this->resolvePublicationCandidatePage($siteId);

            if (! $page) {
                $this->markActionState(
                    $site,
                    'publication',
                    'failed',
                    'Aucune page moteur n est encore prête pour une publication live.',
                    'PraeviSEO n a pas encore de page suffisamment prête côté moteur pour l envoyer sur le site public.'
                );

                return response()->json([
                    'message' => 'Aucune page publiée côté moteur n est encore prête à être poussée en live.',
                ], 422);
            }

            $page = app(SeoLivePublicationService::class)->publish($page, $site);
            $this->appendExecutionHistory(
                $site,
                'Publication envoyée',
                sprintf('PraeviSEO a poussé la page "%s" vers le site live.', (string) $page->title),
                'default',
                'publication_sent',
            );
            $this->markActionState($site, 'publication', 'completed', sprintf('La page "%s" a été envoyée vers le site live.', (string) $page->title));
            $this->scheduleObservedCrawlIfIdle($site, 'after_publication');
            $site = $site->fresh(['googleConnection', 'latestRemoteInstallation', 'latestObservedCrawl']);

            return response()->json([
                'site' => $this->serializeSite($site),
                'publication' => [
                    'page_id' => (int) $page->id,
                    'slug' => (string) $page->slug,
                    'title' => (string) $page->title,
                    'live_url' => (string) ($page->live_url ?? ''),
                    'published_live_at' => $page->published_live_at?->toIso8601String(),
                ],
            ], 202);
        } catch (Throwable $e) {
            $this->markActionState(
                $site,
                'publication',
                'failed',
                'La publication live a échoué pour le moment.',
                $this->premiumActionErrorMessage($e, 'PraeviSEO n a pas pu envoyer la page vers le site live. La connexion de publication doit être vérifiée.')
            );
            $this->appendExecutionHistory($site, 'Publication interrompue', $e->getMessage(), 'danger', 'publication_failed');

            return response()->json([
                'message' => 'PraeviSEO n a pas pu publier cette page pour le moment.',
            ], 500);
        }
    }

    public function startPremiumInternalLinking(
        Request $request,
        string $siteId,
        SeoRewriteService $rewrite,
        SeoSuggestionWorkflowService $workflow,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        /** @var SeoSite $site */
        $site = $user->seoSites()
            ->with(['googleConnection', 'latestRemoteInstallation', 'latestObservedCrawl'])
            ->where('site_id', $siteId)
            ->firstOrFail();

        try {
            $this->markActionState($site, 'linking', 'running', 'PraeviSEO prépare le renfort de liens internes.');
            ['page' => $page, 'suggestion' => $existingSuggestion] = $this->resolveInternalLinkingCandidate($siteId);

            if (! $page) {
                $this->markActionState(
                    $site,
                    'linking',
                    'failed',
                    'Aucune page n est encore assez claire pour un renfort de liens internes.',
                    'PraeviSEO n a pas encore trouvé de page prioritaire avec assez de contexte pour ouvrir des liens internes utiles.'
                );

                return response()->json([
                    'message' => 'Aucune page claire n est encore prête pour un renfort de liens internes sur ce site.',
                ], 422);
            }

            $suggestion = $existingSuggestion ?: $rewrite->createSuggestion($page->fresh(['suggestions', 'observedPage']), 'add-internal-links-only');
            $internalLinks = $this->extractSuggestionInternalLinks($suggestion);

            if ($internalLinks === []) {
                $this->markActionState(
                    $site,
                    'linking',
                    'failed',
                    'PraeviSEO n a pas encore trouvé de liens internes suffisamment utiles.',
                    'Le site a encore besoin de plus de pages bien reliées ou de suggestions plus nettes avant un maillage automatique fiable.'
                );

                return response()->json([
                    'message' => 'PraeviSEO n a pas encore trouvé de liens internes suffisamment utiles à appliquer automatiquement sur cette page.',
                ], 422);
            }

            $result = $workflow->apply($suggestion->fresh());
            $this->appendExecutionHistory(
                $site,
                'Maillage renforcé',
                sprintf('PraeviSEO a ajouté %d lien(s) interne(s) utiles sur "%s".', count($internalLinks), (string) $page->title),
                'default',
                'linking_applied',
            );
            $this->markActionState($site, 'linking', 'completed', sprintf('%d lien(s) interne(s) utile(s) ont été ajoutés sur "%s".', count($internalLinks), (string) $page->title));
            $page->refresh();
            $this->scheduleObservedCrawlIfIdle($site, 'after_linking');
            $site = $site->fresh(['googleConnection', 'latestRemoteInstallation', 'latestObservedCrawl']);

            return response()->json([
                'site' => $this->serializeSite($site),
                'linking' => [
                    'page_id' => (int) $page->id,
                    'slug' => (string) $page->slug,
                    'title' => (string) $page->title,
                    'suggestion_id' => (int) $suggestion->id,
                    'links_applied' => count($internalLinks),
                    'updated_fields' => $result['updated_fields'],
                    'already_pending' => $existingSuggestion !== null,
                ],
            ], 202);
        } catch (Throwable $e) {
            $this->markActionState(
                $site,
                'linking',
                'failed',
                'Le renfort de maillage a échoué pour le moment.',
                $this->premiumActionErrorMessage($e, 'PraeviSEO n a pas pu appliquer le maillage automatiquement. Une vérification du contenu cible ou du workflow est nécessaire.')
            );
            $this->appendExecutionHistory($site, 'Maillage interrompu', $e->getMessage(), 'danger', 'linking_failed');

            return response()->json([
                'message' => 'PraeviSEO n a pas pu renforcer le maillage pour le moment.',
            ], 500);
        }
    }

    public function startPremiumImageGeneration(
        Request $request,
        string $siteId,
        SeoPageImageGenerator $images,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        /** @var SeoSite $site */
        $site = $user->seoSites()
            ->with(['googleConnection', 'latestRemoteInstallation', 'latestObservedCrawl'])
            ->where('site_id', $siteId)
            ->firstOrFail();

        try {
            $this->markActionState($site, 'images', 'running', 'PraeviSEO prépare une image SEO utile pour la meilleure page.');
            $page = $this->resolveImageCandidatePage($siteId);

            if (! $page) {
                $this->markActionState(
                    $site,
                    'images',
                    'failed',
                    'Aucune page n a encore besoin d image prioritaire.',
                    'PraeviSEO n a pas encore trouvé de page assez prioritaire sans image approuvée.'
                );

                return response()->json([
                    'message' => 'Aucune page claire n est encore prête pour une image SEO automatique sur ce site.',
                ], 422);
            }

            $page = $images->generate($page);
            $page = $images->approve($page);
            $this->appendExecutionHistory(
                $site,
                'Image SEO générée',
                sprintf('PraeviSEO a généré puis approuvé une image pour "%s".', (string) $page->title),
                'default',
                'image_generated',
            );
            $this->markActionState($site, 'images', 'completed', sprintf('Une image SEO est prête pour "%s".', (string) $page->title));
            $site = $site->fresh(['googleConnection', 'latestRemoteInstallation', 'latestObservedCrawl']);

            return response()->json([
                'site' => $this->serializeSite($site),
                'image' => [
                    'page_id' => (int) $page->id,
                    'slug' => (string) $page->slug,
                    'title' => (string) $page->title,
                    'image_path' => (string) ($page->image_path ?? ''),
                    'image_status' => (string) ($page->image_status ?? ''),
                ],
            ], 202);
        } catch (Throwable $e) {
            $this->markActionState(
                $site,
                'images',
                'failed',
                'La génération d image a échoué pour le moment.',
                $this->premiumActionErrorMessage($e, 'PraeviSEO n a pas pu générer automatiquement l image prévue.')
            );
            $this->appendExecutionHistory($site, 'Image SEO interrompue', $e->getMessage(), 'danger', 'image_failed');

            return response()->json([
                'message' => 'PraeviSEO n a pas pu générer l image SEO pour le moment.',
            ], 500);
        }
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

        $data = $this->validateInstallationRequest($request);

        $this->validateInstallationAccess($data);

        $precheck = app(InstallationPrecheckService::class)->run($data);
        $this->persistInstallationDoctorState($site, $data, $precheck->toArray(), $precheck->isReady() ? 'ready' : 'blocked');

        if (! $precheck->isReady()) {
            return response()->json([
                'message' => 'L installation ne peut pas continuer tant que les blocages du diagnostic ne sont pas levés.',
                'report' => $precheck->toArray(),
            ], 422);
        }

        $payload = $this->buildInstallationPayload($data);
        $payload['connection_metadata']['precheck_report'] = $precheck->toArray();

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
        $this->persistInstallationDoctorState($site, $data, $precheck->toArray(), 'installation_started');
        $this->appendExecutionHistory(
            $site,
            'Installation premium demandée',
            'PraeviSEO a bien enregistré vos accès et prépare maintenant l activation distante du site.',
            'secondary',
            'installation_requested',
        );

        RunRemoteInstallationJob::dispatch($installation->id);

        $site = $site->fresh(['googleConnection', 'latestRemoteInstallation']);

        return response()->json([
            'site' => $this->serializeSite($site),
            'installation' => $this->serializeInstallation($installation),
        ], 202);
    }

    public function installationPrecheck(Request $request, string $siteId): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        /** @var SeoSite $site */
        $site = $user->seoSites()
            ->where('site_id', $siteId)
            ->firstOrFail();

        $data = $this->validateInstallationRequest($request);

        $this->validateInstallationAccess($data);

        $report = app(InstallationPrecheckService::class)->run($data);
        $this->persistInstallationDoctorState($site, $data, $report->toArray(), $report->isReady() ? 'ready' : 'blocked');

        return response()->json([
            'report' => $report->toArray(),
        ]);
    }

    /**
     * @return array<string,mixed>
     */
    private function validateInstallationRequest(Request $request): array
    {
        return $request->validate([
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
    }

    public function installationStatus(Request $request, string $siteId): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        /** @var SeoSite $site */
        $site = $user->seoSites()
            ->with(['googleConnection', 'latestRemoteInstallation', 'latestObservedCrawl'])
            ->where('site_id', $siteId)
            ->firstOrFail();

        return response()->json([
            'site' => $this->serializeSite($site),
            'installation' => $this->serializeInstallation($site->latestRemoteInstallation),
            'crawl' => $this->serializeObservedCrawl($site->latestObservedCrawl),
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
        $observedSnapshot = $this->observedSiteSnapshot($site->site_id);
        $observedHealth = $this->observedSiteHealthSnapshot($site->site_id);
        $observedHealthHistory = $this->observedSiteHealthHistory($site->site_id);
        $observedHealthDelta = $this->observedSiteHealthDelta($observedHealthHistory);
        $installation = $site->relationLoaded('latestRemoteInstallation')
            ? $site->getRelation('latestRemoteInstallation')
            : $site->latestRemoteInstallation()->first();
        $crawl = $site->relationLoaded('latestObservedCrawl')
            ? $site->getRelation('latestObservedCrawl')
            : $site->latestObservedCrawl()->first();

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
            'gsc_data_as_of' => $this->resolvedGscDataAsOf($site),
            'installation_doctor' => $this->serializeInstallationDoctor($site),
            'installation' => $this->serializeInstallation($installation),
            'crawl' => $this->serializeObservedCrawl($crawl),
            'publication_target' => $this->publicationTargetStatus($site),
            'execution_history' => $this->executionHistory($site),
            'action_statuses' => $this->actionStatuses($site, $crawl),
            'created_at' => $site->created_at,
            'summary' => [
                'pages_total' => (clone $pageQuery)->count(),
                'pages_published' => $pagesPublished,
                'pages_live' => $pagesLive,
                'pending_suggestions' => $pendingSuggestions,
                'observed_pages' => $observedSnapshot['total'],
                'observed_weak_pages' => $observedSnapshot['weak_pages'],
                'observed_orphan_pages' => $observedSnapshot['orphan_pages'],
                'observed_pillar_candidates' => $observedSnapshot['pillar_candidates'],
                'observed_avg_authority' => $observedSnapshot['avg_authority'],
                'observed_avg_orphan' => $observedSnapshot['avg_orphan'],
                'observed_pillar_pages' => $observedSnapshot['pillar_pages'],
                'observed_link_gap_pages' => $observedSnapshot['link_gap_pages'],
                'observed_orphan_alerts' => $observedSnapshot['orphan_alerts'],
                'observed_weak_page_details' => $observedSnapshot['weak_page_details'],
                'observed_site_health_score' => $observedHealth['health_score'],
                'observed_snapshot_date' => $observedHealth['snapshot_date'],
                'observed_avg_seo_score' => $observedHealth['avg_seo_score'],
                'observed_avg_quality_score' => $observedHealth['avg_quality_score'],
                'observed_avg_topical_score' => $observedHealth['avg_topical_score'],
                'observed_crawl_issues' => $observedHealth['crawl_issues'],
                'observed_health_history' => $observedHealthHistory,
                'observed_health_delta' => $observedHealthDelta,
                'gsc_impressions' => $gscSnapshot['impressions'],
                'gsc_clicks' => $gscSnapshot['clicks'],
                'gsc_ctr' => $gscSnapshot['ctr'],
                'gsc_indexed_pages' => $gscSnapshot['indexed_pages'],
                'gsc_indexation_synced' => $gscSnapshot['indexed_pages_synced'],
                'gsc_indexation_scope' => $gscSnapshot['indexation_scope'],
                'gsc_indexation_scope_label' => $gscSnapshot['indexation_scope_label'],
                'gsc_indexation_scope_hint' => $gscSnapshot['indexation_scope_hint'],
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
     * @return array<string,mixed>
     */
    private function publicationTargetStatus(SeoSite $site): array
    {
        /** @var SeoLivePublicationService $service */
        $service = app(SeoLivePublicationService::class);
        $target = $service->targetStatusForSite($site);

        return [
            'mode' => (string) ($target['mode'] ?? 'runtime'),
            'label' => (string) ($target['label'] ?? 'Publication live'),
            'state' => (string) ($target['state'] ?? 'warning'),
            'detail' => (string) ($target['detail'] ?? 'La publication réelle n est pas encore entièrement prête.'),
            'engine_actionable' => (bool) ($target['engine_actionable'] ?? false),
            'manual_required' => (bool) ($target['manual_required'] ?? true),
            'target' => isset($target['target']) ? (string) $target['target'] : null,
        ];
    }

    /**
     * @return array<string,mixed>|null
     */
    private function serializeObservedCrawl(?SeoSiteCrawl $crawl): ?array
    {
        if (! $crawl) {
            return null;
        }

        $meta = is_array($crawl->meta_json) ? $crawl->meta_json : [];

        return [
            'id' => (int) $crawl->id,
            'status' => (string) $crawl->status,
            'base_url' => (string) $crawl->base_url,
            'max_pages' => (int) $crawl->max_pages,
            'discovered_url_count' => (int) $crawl->discovered_url_count,
            'crawled_url_count' => (int) $crawl->crawled_url_count,
            'started_at' => $crawl->started_at?->toIso8601String(),
            'completed_at' => $crawl->completed_at?->toIso8601String(),
            'requested_at' => $crawl->created_at?->toIso8601String(),
            'issues_count' => (int) ($meta['issues_count'] ?? 0),
            'error' => isset($meta['error']) ? (string) $meta['error'] : null,
            'trigger' => isset($meta['trigger']) ? (string) $meta['trigger'] : null,
        ];
    }

    private function scheduleObservedCrawlIfIdle(SeoSite $site, string $trigger): SeoSiteCrawl
    {
        $latestCrawl = $site->relationLoaded('latestObservedCrawl')
            ? $site->getRelation('latestObservedCrawl')
            : $site->latestObservedCrawl()->first();

        if ($latestCrawl && in_array((string) $latestCrawl->status, ['pending', 'running'], true)) {
            return $latestCrawl;
        }

        $crawl = SeoSiteCrawl::query()->create([
            'site_id' => $site->site_id,
            'base_url' => rtrim((string) $site->url, '/'),
            'status' => 'pending',
            'max_pages' => 80,
            'meta_json' => [
                'trigger' => $trigger,
            ],
        ]);

        RunObservedSiteCrawlJob::dispatch($crawl->id);
        $this->appendExecutionHistory(
            $site,
            $this->executionHistoryLabelForTrigger($trigger),
            $this->executionHistoryDetailForTrigger($trigger),
            'secondary',
            $trigger,
        );

        return $crawl;
    }

    /**
     * @return array<int,array{at:string,label:string,detail:string,tone:string,kind:string}>
     */
    private function executionHistory(SeoSite $site): array
    {
        $history = data_get($site->settings_json, 'automation.history', []);

        if (! is_array($history)) {
            return [];
        }

        return collect($history)
            ->filter(fn (mixed $entry): bool => is_array($entry) && filled($entry['label'] ?? null))
            ->map(fn (array $entry): array => [
                'at' => (string) ($entry['at'] ?? now()->toIso8601String()),
                'label' => (string) ($entry['label'] ?? ''),
                'detail' => (string) ($entry['detail'] ?? ''),
                'tone' => in_array((string) ($entry['tone'] ?? ''), ['default', 'secondary', 'danger'], true)
                    ? (string) $entry['tone']
                    : 'secondary',
                'kind' => (string) ($entry['kind'] ?? 'event'),
            ])
            ->sortByDesc('at')
            ->values()
            ->all();
    }

    private function appendExecutionHistory(
        SeoSite $site,
        string $label,
        string $detail,
        string $tone = 'secondary',
        string $kind = 'event',
    ): void {
        $settings = $site->settings_json ?? [];
        $automation = is_array($settings['automation'] ?? null) ? $settings['automation'] : [];
        $history = is_array($automation['history'] ?? null) ? $automation['history'] : [];

        $history[] = [
            'at' => now()->toIso8601String(),
            'label' => $label,
            'detail' => $detail,
            'tone' => in_array($tone, ['default', 'secondary', 'danger'], true) ? $tone : 'secondary',
            'kind' => $kind,
        ];

        $automation['history'] = collect($history)
            ->sortByDesc('at')
            ->take(40)
            ->values()
            ->all();

        $settings['automation'] = $automation;
        $site->forceFill(['settings_json' => $settings])->save();
        $site->refresh();
    }

    private function executionHistoryLabelForTrigger(string $trigger): string
    {
        return match ($trigger) {
            'after_publication' => 'Relecture relancée après publication',
            'after_linking' => 'Relecture relancée après maillage',
            default => 'Crawl premium demandé',
        };
    }

    private function executionHistoryDetailForTrigger(string $trigger): string
    {
        return match ($trigger) {
            'after_publication' => 'PraeviSEO relit le site après une publication pour vérifier le résultat visible.',
            'after_linking' => 'PraeviSEO relit le site après un renfort de liens internes pour contrôler la nouvelle structure.',
            default => 'PraeviSEO a lancé une nouvelle lecture complète du site pour préparer ou contrôler les prochaines actions.',
        };
    }

    /**
     * @return array{
     *   crawl:array{state:string,label:string,detail:string,updated_at:?string,error:?string},
     *   generation:array{state:string,label:string,detail:string,updated_at:?string,error:?string},
     *   rewrite:array{state:string,label:string,detail:string,updated_at:?string,error:?string},
     *   linking:array{state:string,label:string,detail:string,updated_at:?string,error:?string},
     *   images:array{state:string,label:string,detail:string,updated_at:?string,error:?string},
     *   publication:array{state:string,label:string,detail:string,updated_at:?string,error:?string},
     *   monitoring:array{state:string,label:string,detail:string,updated_at:?string,error:?string}
     * }
     */
    private function actionStatuses(SeoSite $site, ?SeoSiteCrawl $crawl): array
    {
        $stored = data_get($site->settings_json, 'automation.actions', []);
        $stored = is_array($stored) ? $stored : [];

        $crawlState = match ((string) ($crawl?->status ?? '')) {
            'running' => 'running',
            'pending' => 'pending',
            'completed' => 'completed',
            'failed' => 'failed',
            default => 'idle',
        };

        return [
            'crawl' => [
                'state' => $crawlState,
                'label' => $this->actionStateLabel($crawlState),
                'detail' => $this->crawlStatusDetail($crawl),
                'updated_at' => $crawl?->completed_at?->toIso8601String()
                    ?? $crawl?->started_at?->toIso8601String()
                    ?? $crawl?->created_at?->toIso8601String(),
                'error' => $crawl?->status === 'failed'
                    ? ($crawl->meta_json['error'] ?? 'La relecture premium a rencontré un blocage.')
                    : null,
            ],
            'generation' => $this->normalizeActionStatus($stored['generation'] ?? null),
            'rewrite' => $this->normalizeActionStatus($stored['rewrite'] ?? null),
            'linking' => $this->normalizeActionStatus($stored['linking'] ?? null),
            'images' => $this->normalizeActionStatus($stored['images'] ?? null),
            'publication' => $this->normalizeActionStatus($stored['publication'] ?? null),
            'monitoring' => $this->monitoringActionStatus($site, $crawl),
        ];
    }

    /**
     * @param mixed $raw
     * @return array{state:string,label:string,detail:string,updated_at:?string,error:?string}
     */
    private function normalizeActionStatus(mixed $raw): array
    {
        $payload = is_array($raw) ? $raw : [];
        $state = (string) ($payload['state'] ?? 'idle');

        if (! in_array($state, ['idle', 'pending', 'running', 'completed', 'failed'], true)) {
            $state = 'idle';
        }

        return [
            'state' => $state,
            'label' => $this->actionStateLabel($state),
            'detail' => (string) ($payload['detail'] ?? ''),
            'updated_at' => isset($payload['updated_at']) ? (string) $payload['updated_at'] : null,
            'error' => isset($payload['error']) && filled($payload['error']) ? (string) $payload['error'] : null,
        ];
    }

    private function actionStateLabel(string $state): string
    {
        return match ($state) {
            'pending' => 'Planifié',
            'running' => 'En cours',
            'completed' => 'Terminé',
            'failed' => 'À vérifier',
            default => 'À ouvrir',
        };
    }

    private function markActionState(SeoSite $site, string $action, string $state, string $detail, ?string $error = null): void
    {
        $settings = $site->settings_json ?? [];
        $automation = is_array($settings['automation'] ?? null) ? $settings['automation'] : [];
        $actions = is_array($automation['actions'] ?? null) ? $automation['actions'] : [];

        $actions[$action] = [
            'state' => $state,
            'detail' => $detail,
            'updated_at' => now()->toIso8601String(),
            'error' => $state === 'failed' ? $error : null,
        ];

        $automation['actions'] = $actions;
        $settings['automation'] = $automation;
        $site->forceFill(['settings_json' => $settings])->save();
        $site->refresh();
    }

    /**
     * @return array{state:string,label:string,detail:string,updated_at:?string,error:?string}
     */
    private function monitoringActionStatus(SeoSite $site, ?SeoSiteCrawl $crawl): array
    {
        $bridgeConnected = $site->publicationBridgeStatus() === 'connected';
        $connection = $site->resolvedGoogleConnection();
        $gscStatus = (string) ($connection?->connection_status ?? '');
        $gscConnected = in_array($gscStatus, ['connected', 'configured', 'connected_empty'], true);
        $dataAsOf = $this->resolvedGscDataAsOf($site);

        if (! $bridgeConnected && ! $gscConnected) {
            return [
                'state' => 'idle',
                'label' => $this->actionStateLabel('idle'),
                'detail' => 'PraeviSEO attend encore la connexion au site et la lecture Google avant de surveiller automatiquement chaque action.',
                'updated_at' => null,
                'error' => null,
            ];
        }

        if (! $bridgeConnected) {
            return [
                'state' => 'idle',
                'label' => $this->actionStateLabel('idle'),
                'detail' => 'La surveillance continue se mettra vraiment en place dès que PraeviSEO pourra agir directement sur le site.',
                'updated_at' => $connection?->last_sync_at?->toIso8601String(),
                'error' => null,
            ];
        }

        if (! $gscConnected) {
            return [
                'state' => 'idle',
                'label' => $this->actionStateLabel('idle'),
                'detail' => 'PraeviSEO peut déjà agir sur le site, mais la lecture continue sera plus précise une fois Google relié.',
                'updated_at' => null,
                'error' => null,
            ];
        }

        if ($crawl && (string) $crawl->status === 'failed') {
            return [
                'state' => 'failed',
                'label' => $this->actionStateLabel('failed'),
                'detail' => 'La dernière relecture automatique a besoin d une vérification avant que la surveillance continue reprenne normalement.',
                'updated_at' => $crawl->completed_at?->toIso8601String()
                    ?? $crawl->started_at?->toIso8601String()
                    ?? $crawl->created_at?->toIso8601String(),
                'error' => isset($crawl->meta_json['error']) && filled($crawl->meta_json['error'])
                    ? (string) $crawl->meta_json['error']
                    : 'La dernière relecture automatique n a pas pu aller au bout.',
            ];
        }

        if ($crawl && in_array((string) $crawl->status, ['pending', 'running'], true)) {
            return [
                'state' => 'running',
                'label' => $this->actionStateLabel('running'),
                'detail' => 'PraeviSEO relit actuellement le site pour contrôler les dernières actions et préparer la prochaine priorité utile.',
                'updated_at' => $crawl->started_at?->toIso8601String()
                    ?? $crawl->created_at?->toIso8601String(),
                'error' => null,
            ];
        }

        if ($dataAsOf !== null) {
            return [
                'state' => 'completed',
                'label' => 'Surveillance active',
                'detail' => sprintf(
                    'PraeviSEO suit déjà les retours du site et de Google. Les dernières données utiles remontent actuellement jusqu au %s.',
                    $dataAsOf
                ),
                'updated_at' => $connection?->last_sync_at?->toIso8601String()
                    ?? $crawl?->completed_at?->toIso8601String(),
                'error' => null,
            ];
        }

        return [
            'state' => 'pending',
            'label' => $this->actionStateLabel('pending'),
            'detail' => 'PraeviSEO a tout ce qu il faut pour surveiller le site en continu. La prochaine lecture automatique complètera ce suivi.',
            'updated_at' => $connection?->last_validated_at?->toIso8601String()
                ?? $connection?->last_sync_at?->toIso8601String(),
            'error' => null,
        ];
    }

    private function premiumActionErrorMessage(Throwable $e, string $fallback): string
    {
        $message = trim($e->getMessage());

        return $message !== '' ? $message : $fallback;
    }

    private function crawlStatusDetail(?SeoSiteCrawl $crawl): string
    {
        if (! $crawl) {
            return 'Aucune relecture premium n a encore été lancée sur ce site.';
        }

        return match ((string) $crawl->status) {
            'running' => sprintf(
                'PraeviSEO relit actuellement %d page(s) sur %d maximum.',
                (int) $crawl->crawled_url_count,
                (int) $crawl->max_pages
            ),
            'pending' => 'Une nouvelle relecture premium a été demandée et va démarrer automatiquement.',
            'completed' => sprintf(
                'La dernière relecture a parcouru %d page(s) et remonté %d point(s) à surveiller.',
                (int) $crawl->crawled_url_count,
                (int) ((is_array($crawl->meta_json) ? ($crawl->meta_json['issues_count'] ?? 0) : 0))
            ),
            'failed' => isset(($crawl->meta_json ?? [])['error'])
                ? (string) (($crawl->meta_json ?? [])['error'])
                : 'La dernière relecture premium a échoué pour le moment.',
            default => 'PraeviSEO peut relancer une lecture complète du site à tout moment.',
        };
    }

    private function resolveRewriteCandidatePage(string $siteId): ?SeoPage
    {
        return SeoPage::query()
            ->where('site_id', $siteId)
            ->withCount([
                'suggestions as pending_suggestions_count' => fn ($query) => $query->where('status', 'pending'),
            ])
            ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', ['published'])
            ->orderByRaw('CASE WHEN observed_site_page_id IS NULL THEN 1 ELSE 0 END')
            ->orderByDesc('seo_score')
            ->orderByDesc('updated_at')
            ->first();
    }

    private function resolvePublicationCandidatePage(string $siteId): ?SeoPage
    {
        $query = SeoPage::query()
            ->where('site_id', $siteId)
            ->where(function ($builder): void {
                $builder
                    ->where('status', 'published')
                    ->orWhereNotNull('published_at');
            });

        if (Schema::hasColumn('seo_pages', 'published_live')) {
            $query->where(function ($builder): void {
                $builder
                    ->whereNull('published_live')
                    ->orWhere('published_live', false);
            });
        }

        return $query
            ->orderByDesc('published_at')
            ->orderByDesc('updated_at')
            ->first();
    }

    private function resolveImageCandidatePage(string $siteId): ?SeoPage
    {
        return SeoPage::query()
            ->where('site_id', $siteId)
            ->where(function (Builder $query): void {
                $query
                    ->whereNull('image_path')
                    ->orWhere('image_path', '')
                    ->orWhereNull('image_status')
                    ->orWhere('image_status', '!=', 'approved');
            })
            ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', ['published'])
            ->orderByRaw('CASE WHEN published_live = 1 THEN 0 ELSE 1 END')
            ->orderByDesc('seo_score')
            ->orderByDesc('updated_at')
            ->first();
    }

    /**
     * @return array{page:?SeoPage,suggestion:?SeoSuggestion}
     */
    private function resolveInternalLinkingCandidate(string $siteId): array
    {
        /** @var SeoSuggestion|null $pendingSuggestion */
        $pendingSuggestion = SeoSuggestion::query()
            ->with('page')
            ->where('status', 'pending')
            ->whereHas('page', fn (Builder $query) => $query->where('site_id', $siteId))
            ->latest('created_at')
            ->get()
            ->first(fn (SeoSuggestion $suggestion): bool => $this->extractSuggestionInternalLinks($suggestion) !== []);

        if ($pendingSuggestion && $pendingSuggestion->page) {
            return [
                'page' => $pendingSuggestion->page,
                'suggestion' => $pendingSuggestion,
            ];
        }

        $page = SeoPage::query()
            ->select('seo_pages.*')
            ->leftJoin('seo_site_pages', 'seo_site_pages.id', '=', 'seo_pages.observed_site_page_id')
            ->where('seo_pages.site_id', $siteId)
            ->whereNotNull('seo_pages.observed_site_page_id')
            ->with('observedPage')
            ->orderByRaw('CASE WHEN seo_pages.status = ? THEN 0 ELSE 1 END', ['published'])
            ->orderByRaw('COALESCE(seo_site_pages.internal_inlinks, 9999) ASC')
            ->orderByRaw('COALESCE(seo_site_pages.orphan_score, 0) DESC')
            ->orderByDesc('seo_pages.seo_score')
            ->orderByDesc('seo_pages.updated_at')
            ->first();

        return [
            'page' => $page,
            'suggestion' => null,
        ];
    }

    /**
     * @return array<int,array{label:string,url:string,reason:mixed}>
     */
    private function extractSuggestionInternalLinks(SeoSuggestion $suggestion): array
    {
        $payload = is_array($suggestion->suggestions_json) ? $suggestion->suggestions_json : [];

        return collect($payload['internal_links'] ?? [])
            ->filter(fn (mixed $item): bool => is_array($item) && filled($item['url'] ?? null))
            ->map(fn (array $item): array => [
                'label' => (string) ($item['label'] ?? $item['text'] ?? $item['url']),
                'url' => (string) ($item['url'] ?? ''),
                'reason' => $item['reason'] ?? null,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{
     *     total:int,
     *     weak_pages:int,
     *     orphan_pages:int,
     *     pillar_candidates:int,
     *     avg_authority:int,
     *     avg_orphan:int,
     *     pillar_pages:array<int,array<string,mixed>>,
     *     link_gap_pages:array<int,array<string,mixed>>,
     *     orphan_alerts:array<int,array<string,mixed>>,
     *     weak_page_details:array<int,array<string,mixed>>
     * }
     */
    private function observedSiteSnapshot(string $siteId): array
    {
        $pages = SeoSitePage::query()
            ->where('site_id', $siteId)
            ->get([
                'id',
                'site_id',
                'normalized_url',
                'path',
                'title',
                'indexability_state',
                'latest_word_count',
                'internal_inlinks',
                'internal_outlinks',
                'authority_score',
                'orphan_score',
                'overlap_score',
                'pillar_likelihood',
                'cluster_label',
                'last_seen_at',
            ]);

        if ($pages->isEmpty()) {
            return [
                'total' => 0,
                'weak_pages' => 0,
                'orphan_pages' => 0,
                'pillar_candidates' => 0,
                'avg_authority' => 0,
                'avg_orphan' => 0,
                'pillar_pages' => [],
                'link_gap_pages' => [],
                'orphan_alerts' => [],
                'weak_page_details' => [],
            ];
        }

        $weakPages = $pages->filter(function (SeoSitePage $page): bool {
            return (int) $page->latest_word_count < 300
                || (float) $page->authority_score < 0.20
                || (string) $page->indexability_state !== 'indexable';
        });
        $orphanPages = $pages->filter(fn (SeoSitePage $page): bool => (float) $page->orphan_score >= 0.75);
        $pillarPages = $pages
            ->filter(fn (SeoSitePage $page): bool => (float) $page->pillar_likelihood >= 0.70)
            ->sortByDesc(fn (SeoSitePage $page): float => (float) $page->pillar_likelihood)
            ->values();
        $linkGapPages = $pages
            ->filter(function (SeoSitePage $page): bool {
                return (string) $page->indexability_state === 'indexable'
                    && (
                        (int) $page->internal_inlinks <= 1
                        || ((float) $page->authority_score >= 0.20 && (int) $page->internal_inlinks <= 2)
                    );
            })
            ->sortBy([
                fn (SeoSitePage $page): int => (int) $page->internal_inlinks,
                fn (SeoSitePage $page): float => -1 * (float) $page->authority_score,
            ])
            ->values();

        return [
            'total' => $pages->count(),
            'weak_pages' => $weakPages->count(),
            'orphan_pages' => $orphanPages->count(),
            'pillar_candidates' => $pillarPages->count(),
            'avg_authority' => (int) round($pages->avg('authority_score') * 100),
            'avg_orphan' => (int) round($pages->avg('orphan_score') * 100),
            'pillar_pages' => $pillarPages
                ->take(6)
                ->map(fn (SeoSitePage $page): array => $this->observedPagePayload($page))
                ->values()
                ->all(),
            'link_gap_pages' => $linkGapPages
                ->take(6)
                ->map(fn (SeoSitePage $page): array => $this->observedPagePayload($page))
                ->values()
                ->all(),
            'orphan_alerts' => $orphanPages
                ->sortByDesc(fn (SeoSitePage $page): float => (float) $page->orphan_score)
                ->take(6)
                ->map(fn (SeoSitePage $page): array => $this->observedPagePayload($page))
                ->values()
                ->all(),
            'weak_page_details' => $weakPages
                ->sortBy([
                    fn (SeoSitePage $page): int => (string) $page->indexability_state === 'indexable' ? 1 : 0,
                    fn (SeoSitePage $page): int => (int) $page->latest_word_count,
                    fn (SeoSitePage $page): float => (float) $page->authority_score,
                ])
                ->take(6)
                ->map(fn (SeoSitePage $page): array => $this->observedPagePayload($page))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array{
     *     health_score:int,
     *     snapshot_date:string|null,
     *     avg_seo_score:int,
     *     avg_quality_score:int,
     *     avg_topical_score:int,
     *     crawl_issues:int
     * }
     */
    private function observedSiteHealthSnapshot(string $siteId): array
    {
        $snapshot = SeoSiteSnapshot::query()
            ->where('site_id', $siteId)
            ->orderByDesc('snapshot_date')
            ->orderByDesc('id')
            ->first([
                'health_score',
                'avg_seo_score',
                'avg_quality_score',
                'avg_topical_score',
                'snapshot_date',
            ]);

        return [
            'health_score' => $snapshot ? (int) round((float) $snapshot->health_score) : 0,
            'snapshot_date' => $snapshot?->snapshot_date?->toDateString(),
            'avg_seo_score' => $snapshot ? (int) round((float) $snapshot->avg_seo_score) : 0,
            'avg_quality_score' => $snapshot ? (int) round((float) $snapshot->avg_quality_score) : 0,
            'avg_topical_score' => $snapshot ? (int) round((float) $snapshot->avg_topical_score) : 0,
            'crawl_issues' => SeoSiteCrawlIssue::query()->where('site_id', $siteId)->count(),
        ];
    }

    /**
     * @return array<int,array{
     *     snapshot_date:string,
     *     health_score:int,
     *     avg_seo_score:int,
     *     avg_quality_score:int,
     *     page_count:int
     * }>
     */
    private function observedSiteHealthHistory(string $siteId, int $days = 14): array
    {
        return SeoSiteSnapshot::query()
            ->where('site_id', $siteId)
            ->where('snapshot_date', '>=', now()->subDays($days)->toDateString())
            ->orderBy('snapshot_date')
            ->get(['snapshot_date', 'health_score', 'avg_seo_score', 'avg_quality_score', 'page_count'])
            ->map(fn (SeoSiteSnapshot $snapshot): array => [
                'snapshot_date' => (string) $snapshot->snapshot_date,
                'health_score' => (int) $snapshot->health_score,
                'avg_seo_score' => (int) $snapshot->avg_seo_score,
                'avg_quality_score' => (int) $snapshot->avg_quality_score,
                'page_count' => (int) $snapshot->page_count,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<int,array{health_score:int}>  $history
     */
    private function observedSiteHealthDelta(array $history): int
    {
        if (count($history) < 2) {
            return 0;
        }

        $latestIndex = array_key_last($history);

        if ($latestIndex === null || $latestIndex === 0) {
            return 0;
        }

        return (int) ($history[$latestIndex]['health_score'] ?? 0) - (int) ($history[$latestIndex - 1]['health_score'] ?? 0);
    }

    /**
     * @return array<string,mixed>
     */
    private function observedPagePayload(SeoSitePage $page): array
    {
        $path = trim((string) ($page->path ?? ''), '/');
        $slug = $path === '' ? '' : basename($path);
        $label = trim((string) ($page->title ?? '')) !== ''
            ? (string) $page->title
            : ($path === '' ? 'Page d accueil' : str_replace(['-', '_'], ' ', ucfirst($slug)));

        return [
            'label' => $label,
            'slug' => $slug,
            'url' => (string) $page->normalized_url,
            'path' => $page->path ? (string) $page->path : '/',
            'authority_score' => (int) round((float) $page->authority_score * 100),
            'orphan_score' => (int) round((float) $page->orphan_score * 100),
            'overlap_score' => (int) round((float) $page->overlap_score * 100),
            'pillar_likelihood' => (int) round((float) $page->pillar_likelihood * 100),
            'internal_inlinks' => (int) $page->internal_inlinks,
            'internal_outlinks' => (int) $page->internal_outlinks,
            'latest_word_count' => (int) $page->latest_word_count,
            'indexability_state' => (string) $page->indexability_state,
            'cluster_label' => $page->cluster_label ? (string) $page->cluster_label : null,
            'last_seen_at' => $page->last_seen_at,
        ];
    }

    /**
     * @return array{
     *     impressions:float,
     *     clicks:float,
     *     ctr:float,
     *     indexed_pages:int,
     *     indexed_pages_synced:bool,
     *     indexation_scope:string,
     *     indexation_scope_label:string,
     *     indexation_scope_hint:string,
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
                'indexation_scope' => 'inspected_urls',
                'indexation_scope_label' => 'URLs inspectées via Google',
                'indexation_scope_hint' => 'PraeviSEO compte ici les URLs qu’il suit et inspecte dans Google Search Console. Le rapport Pages complet de Google peut afficher davantage d’URLs.',
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
        $freshnessCutoff = Carbon::parse($latestMetricDate)->subDays(7)->toDateString();
        $previousMetricDate = (clone $baseQuery)
            ->whereDate('metric_date', '<', $latestMetricDate)
            ->max('metric_date');

        $snapshotRows = (clone $baseQuery)
            ->whereDate('metric_date', $latestMetricDate)
            ->orderByDesc('id')
            ->get(['id', 'metric_date', 'url', 'clicks', 'impressions', 'ctr', 'position', 'is_indexed', 'coverage_json', 'payload_json']);
        $previousRows = $previousMetricDate
            ? (clone $baseQuery)
                ->whereDate('metric_date', Carbon::parse((string) $previousMetricDate)->toDateString())
                ->orderByDesc('id')
                ->get(['id', 'metric_date', 'url', 'clicks', 'impressions', 'ctr', 'position', 'is_indexed', 'coverage_json', 'payload_json'])
            : collect();
        $pageHistory = (clone $baseQuery)
            ->whereNotNull('url')
            ->whereDate('metric_date', '>=', Carbon::parse($latestMetricDate)->subDays(45)->toDateString())
            ->orderByDesc('metric_date')
            ->orderByDesc('id')
            ->get(['id', 'metric_date', 'url', 'clicks', 'impressions', 'ctr', 'position', 'is_indexed', 'coverage_json', 'payload_json']);
        $queryRows = SeoSearchConsoleMetric::query()
            ->where('site_id', $siteId)
            ->whereNotNull('query')
            ->where('window_days', 28)
            ->whereDate('metric_date', $latestMetricDate)
            ->orderByDesc('impressions')
            ->get(['metric_date', 'query', 'clicks', 'impressions', 'ctr', 'position']);
        $queryHistory = SeoSearchConsoleMetric::query()
            ->where('site_id', $siteId)
            ->whereNotNull('query')
            ->where('window_days', 28)
            ->whereDate('metric_date', '>=', Carbon::parse($latestMetricDate)->subDays(45)->toDateString())
            ->orderByDesc('metric_date')
            ->orderByDesc('id')
            ->get(['id', 'metric_date', 'query', 'clicks', 'impressions', 'ctr', 'position']);

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
        $pageHistoryByKey = $pageHistory
            ->groupBy(fn (SeoSearchConsoleMetric $metric): string => $this->searchConsoleUrlKey($metric));
        $currentIndexationRows = $pageHistoryByKey
            ->map(function ($rows) use ($freshnessCutoff) {
                /** @var \Illuminate\Support\Collection<int, SeoSearchConsoleMetric> $rows */
                return $rows->first(
                    fn (SeoSearchConsoleMetric $metric): bool => $metric->metric_date?->toDateString() >= $freshnessCutoff
                );
            })
            ->filter();
        $currentPerformanceRows = $pageHistoryByKey
            ->map(function ($rows) use ($freshnessCutoff) {
                /** @var \Illuminate\Support\Collection<int, SeoSearchConsoleMetric> $rows */
                return $rows->first(
                    fn (SeoSearchConsoleMetric $metric): bool => $metric->metric_date?->toDateString() >= $freshnessCutoff
                        && $this->metricHasAnalytics($metric)
                );
            })
            ->filter();
        $previousPerformanceRows = $currentPerformanceRows
            ->map(function (SeoSearchConsoleMetric $currentMetric, string $key) use ($pageHistoryByKey) {
                /** @var \Illuminate\Support\Collection<int, SeoSearchConsoleMetric> $rows */
                $rows = $pageHistoryByKey->get($key, collect());

                return $rows->first(
                    fn (SeoSearchConsoleMetric $metric): bool => $this->metricHasAnalytics($metric)
                        && $this->isMetricOlderThan($metric, $currentMetric)
                );
            });

        $impressions = $aggregateRow ? (float) $aggregateRow->impressions : (float) $deduplicatedRows->sum('impressions');
        $clicks = $aggregateRow ? (float) $aggregateRow->clicks : (float) $deduplicatedRows->sum('clicks');
        $ctr = $aggregateRow ? (float) $aggregateRow->ctr : ($impressions > 0 ? $clicks / $impressions : 0.0);
        $previousImpressions = $previousAggregateRow
            ? (float) $previousAggregateRow->impressions
            : (float) $previousPerformanceRows->sum('impressions');
        $previousClicks = $previousAggregateRow
            ? (float) $previousAggregateRow->clicks
            : (float) $previousPerformanceRows->sum('clicks');
        $previousCtr = $previousAggregateRow
            ? (float) $previousAggregateRow->ctr
            : ($previousImpressions > 0 ? $previousClicks / $previousImpressions : 0.0);
        $indexedPagesSynced = $currentIndexationRows->contains(
            fn (SeoSearchConsoleMetric $metric): bool => $metric->is_indexed !== null
        );
        $pageSignals = $currentPerformanceRows
            ->map(function (SeoSearchConsoleMetric $metric, string $key) use ($previousPerformanceRows): array {
                /** @var SeoSearchConsoleMetric|null $previousMetric */
                $previousMetric = $previousPerformanceRows->get($key);
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
        $querySignals = $queryHistory
            ->groupBy(fn (SeoSearchConsoleMetric $metric): string => mb_strtolower(trim((string) $metric->query)))
            ->map(function ($rows, string $query) use ($freshnessCutoff): ?array {
                /** @var \Illuminate\Support\Collection<int, SeoSearchConsoleMetric> $rows */
                $currentMetric = $rows->first(
                    fn (SeoSearchConsoleMetric $metric): bool => $metric->metric_date?->toDateString() >= $freshnessCutoff
                );

                if (! $currentMetric) {
                    return null;
                }

                $currentDate = $currentMetric->metric_date?->toDateString();
                $currentRows = $rows->filter(
                    fn (SeoSearchConsoleMetric $metric): bool => $metric->metric_date?->toDateString() === $currentDate
                );
                $previousMetric = $rows->first(
                    fn (SeoSearchConsoleMetric $metric): bool => $this->isMetricOlderThan($metric, $currentMetric)
                );
                $previousDate = $previousMetric?->metric_date?->toDateString();
                $previousRows = $previousDate
                    ? $rows->filter(fn (SeoSearchConsoleMetric $metric): bool => $metric->metric_date?->toDateString() === $previousDate)
                    : collect();
                $item = $this->aggregateQueryRows($query, $currentRows);
                $previous = $previousRows->isNotEmpty()
                    ? $this->aggregateQueryRows($query, $previousRows)
                    : [
                    'impressions' => 0,
                    'clicks' => 0,
                    'ctr' => 0.0,
                    'position' => 0.0,
                ];
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
            ->filter()
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
        $indexationAlerts = $currentIndexationRows
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
            'indexed_pages' => $currentIndexationRows->where('is_indexed', true)->count(),
            'indexed_pages_synced' => $indexedPagesSynced,
            'indexation_scope' => 'inspected_urls',
            'indexation_scope_label' => 'URLs inspectées via Google',
            'indexation_scope_hint' => 'PraeviSEO compte ici les URLs qu’il suit et inspecte dans Google Search Console. Le rapport Pages complet de Google peut afficher davantage d’URLs.',
            'previous_impressions' => $previousImpressions,
            'previous_clicks' => $previousClicks,
            'previous_ctr' => $previousCtr,
            'delta_impressions' => $impressions - $previousImpressions,
            'delta_clicks' => $clicks - $previousClicks,
            'delta_ctr_points' => round(($ctr - $previousCtr) * 100, 2),
            'non_indexed_pages' => $currentIndexationRows->where('is_indexed', false)->count(),
            'top_rising_pages' => $topRisingPages,
            'top_falling_pages' => $topFallingPages,
            'top_queries' => $topQueries,
            'top_rising_queries' => $topRisingQueries,
            'top_falling_queries' => $topFallingQueries,
            'new_queries' => $newQueries,
            'indexation_alerts' => $indexationAlerts,
        ];
    }

    private function metricHasAnalytics(SeoSearchConsoleMetric $metric): bool
    {
        $payload = is_array($metric->payload_json) ? $metric->payload_json : [];

        if (($payload['scope'] ?? null) === 'site_totals') {
            return true;
        }

        if (is_array($payload['analytics'] ?? null) && $payload['analytics'] !== []) {
            return true;
        }

        return (float) $metric->impressions > 0.0
            || (float) $metric->clicks > 0.0
            || (float) $metric->position > 0.0;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, SeoSearchConsoleMetric>  $rows
     * @return array{query:string,impressions:int,clicks:int,ctr:float,position:float}
     */
    private function aggregateQueryRows(string $query, \Illuminate\Support\Collection $rows): array
    {
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
    }

    private function isMetricOlderThan(SeoSearchConsoleMetric $candidate, SeoSearchConsoleMetric $reference): bool
    {
        $candidateDate = $candidate->metric_date?->toDateString();
        $referenceDate = $reference->metric_date?->toDateString();

        if ($candidateDate === null || $referenceDate === null) {
            return false;
        }

        if ($candidateDate < $referenceDate) {
            return true;
        }

        return $candidateDate === $referenceDate && (int) $candidate->id < (int) $reference->id;
    }

    private function resolvedGscDataAsOf(SeoSite $site): ?string
    {
        $connection = $site->resolvedGoogleConnection();
        $lastSync = is_array($connection?->meta_json['last_sync'] ?? null)
            ? $connection->meta_json['last_sync']
            : null;

        if (! is_array($lastSync)) {
            return null;
        }

        $candidates = collect([
            data_get($lastSync, 'analytics.site_totals.end_date'),
            data_get($lastSync, 'analytics.top_pages.end_date'),
            data_get($lastSync, 'analytics.top_query_pages.end_date'),
            data_get($lastSync, 'analytics.top_queries.end_date'),
        ])->filter(fn (mixed $value): bool => is_string($value) && trim($value) !== '');

        $date = $candidates->first();

        return is_string($date) ? $date : null;
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

    /**
     * @param array<string,mixed> $inputs
     * @param array<string,mixed> $report
     */
    private function persistInstallationDoctorState(SeoSite $site, array $inputs, array $report, string $status): void
    {
        $settings = $site->settings_json ?? [];
        $settings['installation_doctor'] = [
            'status' => $status,
            'last_run_at' => now()->toIso8601String(),
            'last_inputs' => $this->sanitizeInstallationDoctorInputs($inputs),
            'last_report' => $report,
        ];

        $site->forceFill(['settings_json' => $settings])->save();
        $site->refresh();
    }

    /**
     * @param array<string,mixed> $inputs
     * @return array<string,string|null>
     */
    private function sanitizeInstallationDoctorInputs(array $inputs): array
    {
        $keys = [
            'hosting_provider',
            'access_method',
            'framework_hint',
            'ssh_host',
            'ssh_port',
            'ssh_username',
            'ssh_project_path',
            'sftp_host',
            'sftp_port',
            'sftp_username',
            'sftp_project_path',
        ];

        $sanitized = [];

        foreach ($keys as $key) {
            $value = $inputs[$key] ?? null;
            $sanitized[$key] = $value === null ? null : trim((string) $value);
        }

        return $sanitized;
    }

    /**
     * @return array{
     *   status:string,
     *   last_run_at:?string,
     *   last_inputs:array<string,string|null>,
     *   last_report:?array<string,mixed>
     * }
     */
    private function serializeInstallationDoctor(SeoSite $site): array
    {
        $doctor = data_get($site->settings_json, 'installation_doctor', []);
        $doctor = is_array($doctor) ? $doctor : [];
        $inputs = is_array($doctor['last_inputs'] ?? null) ? $doctor['last_inputs'] : [];
        $report = is_array($doctor['last_report'] ?? null) ? $doctor['last_report'] : null;

        return [
            'status' => (string) ($doctor['status'] ?? 'idle'),
            'last_run_at' => isset($doctor['last_run_at']) ? (string) $doctor['last_run_at'] : null,
            'last_inputs' => collect($inputs)
                ->map(fn (mixed $value): ?string => $value === null ? null : (string) $value)
                ->all(),
            'last_report' => $report,
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
                'readiness_report' => null,
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
            'readiness_report' => data_get($installation->connection_metadata, 'precheck_report'),
            'logs' => $installation->safeLogs(),
        ];
    }
}
