<?php

declare(strict_types=1);

namespace App\Services\Publication;

use App\Models\SeoPage;
use App\Models\SeoSite;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class SeoLivePublicationService
{
    public function publish(SeoPage $page, SeoSite $site): SeoPage
    {
        return match ($site->resolvedPublicationMode()) {
            'disabled' => throw new RuntimeException('La publication réelle est désactivée pour ce site.'),
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

        return $page->refresh();
    }

    private function publishViaWebhook(SeoPage $page, SeoSite $site): SeoPage
    {
        if (! $this->supportsLivePublication()) {
            throw new RuntimeException('Le suivi live n est pas disponible dans ce runtime tant que les colonnes de publication ne sont pas présentes.');
        }

        $endpoint = $site->publicationWebhookUrl();

        if (! $endpoint) {
            throw new RuntimeException('Aucun endpoint de publication CMS/API n est configuré pour ce site.');
        }

        try {
            $response = Http::timeout(12)
                ->acceptJson()
                ->post($endpoint, $this->webhookPayload($page, $site));

            if (! $response->successful()) {
                $preview = trim((string) $response->body());

                throw new RuntimeException('La publication CMS/API a échoué : HTTP '.$response->status().($preview !== '' ? ' — '.mb_substr($preview, 0, 180) : ''));
            }

            $payload = $response->json();
            $liveUrl = is_array($payload)
                ? (string) (Arr::get($payload, 'live_url') ?: Arr::get($payload, 'url') ?: $this->liveUrlFor($page, $site))
                : $this->liveUrlFor($page, $site);

            $page->forceFill([
                'published_live' => true,
                'published_live_at' => $page->published_live_at ?? now(),
                'live_url' => $liveUrl,
            ])->save();

            $this->rememberPublicationEvent($site, [
                'mode' => 'webhook_api',
                'last_push_at' => now()->toIso8601String(),
                'last_push_status' => 'ok',
                'last_push_target' => $endpoint,
                'last_live_url' => $liveUrl,
                'last_error' => null,
            ]);

            return $page->refresh();
        } catch (\Throwable $exception) {
            $this->rememberPublicationEvent($site, [
                'mode' => 'webhook_api',
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
        return rtrim((string) $site->url, '/').$page->canonicalPath();
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
}
