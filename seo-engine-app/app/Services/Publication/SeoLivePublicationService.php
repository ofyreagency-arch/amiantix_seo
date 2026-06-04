<?php

declare(strict_types=1);

namespace App\Services\Publication;

use App\Models\SeoPage;
use App\Models\SeoSite;
use App\Models\SeoSiteSitemap;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class SeoLivePublicationService
{
    public function __construct(
        private readonly PublishedPageObservationService $publishedObservation,
    ) {}

    public function publish(SeoPage $page, SeoSite $site): SeoPage
    {
        return match ($site->resolvedPublicationMode()) {
            'disabled' => throw new RuntimeException('La publication réelle est désactivée pour ce site.'),
            'laravel_bridge' => $this->publishViaWebhook($page, $site, signed: true),
            'symfony_bridge' => $this->publishViaWebhook($page, $site, signed: true),
            'wordpress_bridge' => $this->publishViaWebhook($page, $site, signed: true),
            'webhook_api' => $this->publishViaWebhook($page, $site),
            default => $this->publishToRuntime($page, $site),
        };
    }

    /**
     * @return array<string,mixed>
     */
    public function targetStatusForSite(SeoSite $site): array
    {
        $mode = $site->resolvedPublicationMode();
        $webhookUrl = $site->publicationWebhookUrl();

        return match ($mode) {
            'disabled' => [
                'mode' => 'disabled',
                'label' => 'Publication externe désactivée',
                'state' => 'warning',
                'detail' => 'Le moteur peut valider ses pages, mais aucune publication réelle CMS/site client n est activée pour ce site.',
                'engine_actionable' => false,
                'manual_required' => true,
            ],
            'laravel_bridge' => [
                'mode' => 'laravel_bridge',
                'label' => 'Bridge Laravel',
                'state' => ($webhookUrl && $site->publicationSharedSecret()) ? 'ok' : 'critical',
                'detail' => ($webhookUrl && $site->publicationSharedSecret())
                    ? 'Le moteur peut pousser une page validée vers un vrai site Laravel via un endpoint signé Praeviseo.'
                    : 'Le bridge Laravel demande un endpoint CMS et un secret partagé pour publier réellement sur le site client.',
                'engine_actionable' => (bool) ($webhookUrl && $site->publicationSharedSecret()),
                'manual_required' => ! ($webhookUrl && $site->publicationSharedSecret()),
                'target' => $webhookUrl ?: '—',
            ],
            'symfony_bridge' => [
                'mode' => 'symfony_bridge',
                'label' => 'Bridge Symfony',
                'state' => ($webhookUrl && $site->publicationSharedSecret()) ? 'ok' : 'critical',
                'detail' => ($webhookUrl && $site->publicationSharedSecret())
                    ? 'Le moteur peut pousser une page validée vers un vrai site Symfony via un endpoint signé Praeviseo.'
                    : 'Le bridge Symfony demande un endpoint CMS et un secret partagé pour publier réellement sur le site client.',
                'engine_actionable' => (bool) ($webhookUrl && $site->publicationSharedSecret()),
                'manual_required' => ! ($webhookUrl && $site->publicationSharedSecret()),
                'target' => $webhookUrl ?: '—',
            ],
            'wordpress_bridge' => [
                'mode' => 'wordpress_bridge',
                'label' => 'Plugin WordPress',
                'state' => ($webhookUrl && $site->publicationSharedSecret()) ? 'ok' : 'critical',
                'detail' => ($webhookUrl && $site->publicationSharedSecret())
                    ? 'Le moteur peut pousser une page validée vers un vrai site WordPress via le plugin officiel Praeviseo.'
                    : 'Le plugin WordPress doit encore être connecté pour activer la publication réelle et le monitoring.',
                'engine_actionable' => (bool) ($webhookUrl && $site->publicationSharedSecret()),
                'manual_required' => ! ($webhookUrl && $site->publicationSharedSecret()),
                'target' => $webhookUrl ?: '—',
            ],
            'webhook_api' => [
                'mode' => 'webhook_api',
                'label' => 'Webhook CMS/API',
                'state' => $webhookUrl ? 'ok' : 'critical',
                'detail' => $webhookUrl
                    ? 'Le moteur peut pousser une page validée vers le vrai site client via un endpoint CMS/API.'
                    : 'Mode webhook choisi, mais aucun endpoint CMS/API n est configuré.',
                'engine_actionable' => (bool) $webhookUrl,
                'manual_required' => ! $webhookUrl,
                'target' => $webhookUrl ?: '—',
            ],
            default => [
                'mode' => 'runtime',
                'label' => 'Runtime interne',
                'state' => $this->supportsLivePublication() ? 'ok' : 'warning',
                'detail' => $this->supportsLivePublication()
                    ? 'La publication reste servie par le runtime interne. C est utile pour tester, mais ce n est pas encore un vrai CMS client.'
                    : 'Le runtime interne n a pas encore toutes les colonnes nécessaires pour servir des pages live.',
                'engine_actionable' => $this->supportsLivePublication(),
                'manual_required' => ! $this->supportsLivePublication(),
            ],
        };
    }

    private function publishToRuntime(SeoPage $page, SeoSite $site): SeoPage
    {
        if (! $this->supportsLivePublication()) {
            return $page->refresh();
        }

        $page->forceFill([
            'published_live' => true,
            'published_live_at' => $page->published_live_at ?? now(),
            'live_url' => $this->liveUrlFor($page, $site),
        ])->save();

        return $this->finalizeSuccessfulPublication(
            $site,
            $page->refresh(),
            $this->defaultSitemapUrl($site)
        );
    }

    private function publishViaWebhook(SeoPage $page, SeoSite $site, bool $signed = false): SeoPage
    {
        if (! $this->supportsLivePublication()) {
            throw new RuntimeException('Le suivi live n est pas disponible dans ce runtime tant que les colonnes de publication ne sont pas présentes.');
        }

        $endpoint = $site->publicationWebhookUrl();
        $payload = $this->webhookPayload($page, $site);

        if (! $endpoint) {
            throw new RuntimeException('Aucun endpoint de publication CMS/API n est configuré pour ce site.');
        }

        if ($signed && ! $site->publicationSharedSecret()) {
            throw new RuntimeException('Le connecteur officiel demande un secret partagé avant toute publication réelle.');
        }

        try {
            $request = Http::timeout(12)
                ->acceptJson()
                ->asJson();

            if ($signed) {
                $signedHeaders = $this->signedHeaders($site, $payload);
                $this->logSignedWebhookRequest($site, $endpoint, $payload, $signedHeaders);
                $request = $request->withHeaders($signedHeaders);
            }

            $response = $request->post($endpoint, $payload);

            if (! $response->successful()) {
                $preview = trim((string) $response->body());

                throw new RuntimeException('La publication CMS/API a échoué : HTTP '.$response->status().($preview !== '' ? ' — '.mb_substr($preview, 0, 180) : ''));
            }

            $responsePayload = $response->json();
            $liveUrl = is_array($responsePayload)
                ? (string) (Arr::get($responsePayload, 'live_url') ?: Arr::get($responsePayload, 'url') ?: $this->liveUrlFor($page, $site))
                : $this->liveUrlFor($page, $site);
            $sitemapUrl = is_array($responsePayload)
                ? trim((string) (Arr::get($responsePayload, 'sitemap_url') ?: ''))
                : '';

            $page->forceFill([
                'published_live' => true,
                'published_live_at' => $page->published_live_at ?? now(),
                'live_url' => $liveUrl,
            ])->save();

            $this->rememberPublicationEvent($site, [
                'mode' => $site->resolvedPublicationMode(),
                'last_push_at' => now()->toIso8601String(),
                'last_push_status' => 'ok',
                'last_push_target' => $endpoint,
                'last_live_url' => $liveUrl,
                'last_sitemap_url' => $sitemapUrl !== '' ? $sitemapUrl : $this->defaultSitemapUrl($site),
                'last_error' => null,
            ]);

            return $this->finalizeSuccessfulPublication(
                $site,
                $page->refresh(),
                $sitemapUrl !== '' ? $sitemapUrl : $this->defaultSitemapUrl($site)
            );
        } catch (\Throwable $exception) {
            $this->rememberPublicationEvent($site, [
                'mode' => $site->resolvedPublicationMode(),
                'last_push_at' => now()->toIso8601String(),
                'last_push_status' => 'error',
                'last_push_target' => $endpoint,
                'last_error' => $exception->getMessage(),
            ]);

            throw $exception instanceof RuntimeException
                ? $exception
                : new RuntimeException($exception->getMessage(), previous: $exception);
        }
    }

    public function liveUrlFor(SeoPage $page, SeoSite $site): string
    {
        $baseUrl = rtrim((string) $site->url, '/');
        $prefix = $site->publicationPathPrefix();

        if (in_array($site->resolvedPublicationMode(), ['laravel_bridge', 'symfony_bridge', 'wordpress_bridge'], true) && $prefix) {
            return $baseUrl.'/'.trim($prefix, '/').$page->canonicalPath();
        }

        return $baseUrl.$page->canonicalPath();
    }

    public function resolveSiteByHost(string $host): ?SeoSite
    {
        $normalizedHost = $this->normalizeHost($host);

        return SeoSite::query()
            ->active()
            ->get()
            ->first(function (SeoSite $site) use ($normalizedHost): bool {
                $siteHost = $this->normalizeHost((string) parse_url((string) $site->url, PHP_URL_HOST));

                return $siteHost !== '' && $siteHost === $normalizedHost;
            });
    }

    public function livePagesQuery(SeoSite $site): Builder
    {
        $query = SeoPage::query()
            ->where('site_id', $site->site_id);

        if (! $this->supportsLivePublication()) {
            return $query->whereRaw('1 = 0');
        }

        return $query->publishedLive();
    }

    public function supportsLivePublication(): bool
    {
        return Schema::hasColumns('seo_pages', [
            'published_live',
            'published_live_at',
            'live_url',
        ]);
    }

    /**
     * @return array<string,mixed>
     */
    private function webhookPayload(SeoPage $page, SeoSite $site): array
    {
        return [
            'source' => 'praeviseo',
            'site' => [
                'site_id' => $site->site_id,
                'name' => $site->name,
                'url' => $site->url,
                'locale' => $site->locale,
                'preset' => $site->resolvedPreset(),
            ],
            'page' => [
                'id' => $page->id,
                'slug' => $page->slug,
                'keyword' => $page->keyword,
                'title' => $page->title,
                'h1' => $page->h1,
                'meta_description' => $page->meta_description,
                'content' => $page->content,
                'faq' => $page->faq_json ?? [],
                'schema' => $page->schema_json ?? [],
                'internal_links' => $page->internal_links_json ?? [],
                'canonical_url' => $page->canonical_url,
                'cluster' => $page->cluster,
                'forced_noindex' => (bool) $page->forced_noindex,
                'suggested_live_url' => $this->liveUrlFor($page, $site),
                'image' => [
                    'path' => $page->image_path,
                    'alt' => $page->image_alt,
                    'status' => $page->image_status,
                ],
            ],
        ];
    }

    private function normalizeHost(string $host): string
    {
        return ltrim(strtolower(trim($host)), '.');
    }

    /**
     * @param  array<string,mixed>  $publicationSettings
     */
    private function rememberPublicationEvent(SeoSite $site, array $publicationSettings): void
    {
        $settings = $site->settings_json ?? [];
        $settings['publication'] = array_merge($settings['publication'] ?? [], $publicationSettings);
        $site->forceFill(['settings_json' => $settings])->save();
    }

    private function finalizeSuccessfulPublication(SeoSite $site, SeoPage $page, ?string $sitemapUrl): SeoPage
    {
        if (trim((string) ($page->canonical_url ?? '')) === '') {
            $page->forceFill(['canonical_url' => (string) ($page->live_url ?? '')])->save();
            $page = $page->refresh();
        }

        $this->rememberSitemapUrl($site, $page, $sitemapUrl);
        $this->publishedObservation->followLivePublication($site, $page);

        return $page->refresh();
    }

    private function defaultSitemapUrl(SeoSite $site): ?string
    {
        $baseUrl = rtrim((string) $site->url, '/');

        if ($baseUrl === '') {
            return null;
        }

        return match ($site->resolvedPublicationMode()) {
            'laravel_bridge', 'symfony_bridge' => $site->publicationPathPrefix()
                ? $baseUrl.'/'.trim((string) $site->publicationPathPrefix(), '/').'-sitemap.xml'
                : $baseUrl.'/sitemap.xml',
            'wordpress_bridge' => $baseUrl.'/sitemap_index.xml',
            default => $baseUrl.'/sitemap.xml',
        };
    }

    private function rememberSitemapUrl(SeoSite $site, SeoPage $page, ?string $sitemapUrl): void
    {
        $sitemapUrl = trim((string) $sitemapUrl);

        if ($sitemapUrl === '') {
            return;
        }

        SeoSiteSitemap::query()->updateOrCreate(
            [
                'site_id' => $site->site_id,
                'url_hash' => sha1($sitemapUrl),
            ],
            [
                'site_crawl_id' => null,
                'url' => $sitemapUrl,
                'sitemap_type' => 'published_pages',
                'parent_url' => null,
                'lastmod_at' => now(),
                'discovered_at' => now(),
                'meta_json' => [
                    'source' => 'premium_publication',
                    'publication_mode' => $site->resolvedPublicationMode(),
                    'last_live_url' => (string) ($page->live_url ?? ''),
                ],
            ]
        );

        $this->rememberPublicationEvent($site, [
            'last_sitemap_url' => $sitemapUrl,
            'last_sitemap_refresh_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * @param  array<string,mixed>  $payload
     * @return array<string,string>
     */
    private function signedHeaders(SeoSite $site, array $payload): array
    {
        $timestamp = (string) now()->timestamp;
        $body = (string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $signature = hash_hmac('sha256', $timestamp.'.'.$body, (string) $site->publicationSharedSecret());

        return [
            'User-Agent' => 'Praeviseo-LaravelBridge/1.0',
            'X-Praeviseo-Site-Id' => (string) $site->site_id,
            'X-Praeviseo-Timestamp' => $timestamp,
            'X-Praeviseo-Signature' => $signature,
        ];
    }

    /**
     * @param  array<string,mixed>  $payload
     * @param  array<string,string>  $headers
     */
    private function logSignedWebhookRequest(SeoSite $site, string $endpoint, array $payload, array $headers): void
    {
        try {
            $timestamp = (string) ($headers['X-Praeviseo-Timestamp'] ?? '');
            $body = (string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $secret = (string) $site->publicationSharedSecret();
            $logPath = storage_path('logs/praeviseo-bridge-signature.log');

            File::append($logPath, json_encode([
                'logged_at' => now()->toIso8601String(),
                'side' => 'praeviseo',
                'site_id' => (string) $site->site_id,
                'endpoint' => $endpoint,
                'timestamp' => $timestamp,
                'body' => $body,
                'signature' => (string) ($headers['X-Praeviseo-Signature'] ?? ''),
                'secret' => $secret,
                'secret_sha256' => hash('sha256', $secret),
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).PHP_EOL);
        } catch (\Throwable) {
            // Never block publication because the debug trace could not be written.
        }
    }
}
