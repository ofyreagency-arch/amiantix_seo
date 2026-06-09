<?php

declare(strict_types=1);

namespace Praeviseo\LaravelBridge\Services;

use Illuminate\Http\Request;
use Praeviseo\LaravelBridge\Models\PraeviseoNativePagePatch;
use Praeviseo\LaravelBridge\Models\PraeviseoPublishedPage;
use RuntimeException;

final class PraeviseoBridgeService
{
    /**
     * @return array<string,mixed>
     */
    public function publishFromRequest(Request $request): array
    {
        $this->assertSignedRequest($request);

        $payload = $request->validate([
            'source' => ['required', 'string'],
            'site.site_id' => ['required', 'string'],
            'publication.scope' => ['nullable', 'string'],
            'publication.target_path' => ['nullable', 'string'],
            'publication.target_url' => ['nullable', 'string'],
            'page.id' => ['required', 'integer'],
            'page.slug' => ['required', 'string'],
            'page.title' => ['required', 'string'],
            'page.h1' => ['nullable', 'string'],
            'page.meta_description' => ['nullable', 'string'],
            'page.content' => ['required', 'string'],
            'page.faq' => ['nullable', 'array'],
            'page.schema' => ['nullable', 'array'],
            'page.internal_links' => ['nullable', 'array'],
            'page.canonical_url' => ['nullable', 'string'],
            'page.cluster' => ['nullable', 'string'],
            'page.forced_noindex' => ['nullable', 'boolean'],
            'page.suggested_live_url' => ['nullable', 'string'],
            'page.image.path' => ['nullable', 'string'],
            'page.image.alt' => ['nullable', 'string'],
        ]);

        if ((string) data_get($payload, 'publication.scope', 'bridge_article') === 'native_update') {
            return $this->publishNativeUpdate($payload);
        }

        $siteId = (string) data_get($payload, 'site.site_id');
        $prefix = trim((string) config('praeviseo-bridge.prefix', 'ressources'), '/');
        $slug = trim((string) data_get($payload, 'page.slug'), '/');
        $liveUrl = rtrim((string) config('app.url'), '/').'/'.$prefix.'/'.$slug;

        $page = PraeviseoPublishedPage::query()->updateOrCreate(
            [
                'praeviseo_site_id' => $siteId,
                'external_page_id' => (int) data_get($payload, 'page.id'),
            ],
            [
                'slug' => $slug,
                'title' => (string) data_get($payload, 'page.title'),
                'h1' => data_get($payload, 'page.h1'),
                'meta_description' => data_get($payload, 'page.meta_description'),
                'content_html' => (string) data_get($payload, 'page.content'),
                'faq_json' => data_get($payload, 'page.faq', []),
                'schema_json' => data_get($payload, 'page.schema', []),
                'internal_links_json' => data_get($payload, 'page.internal_links', []),
                'canonical_url' => data_get($payload, 'page.canonical_url') ?: $liveUrl,
                'live_url' => data_get($payload, 'page.suggested_live_url') ?: $liveUrl,
                'cluster' => data_get($payload, 'page.cluster'),
                'is_noindex' => (bool) data_get($payload, 'page.forced_noindex', false),
                'image_path' => data_get($payload, 'page.image.path'),
                'image_alt' => data_get($payload, 'page.image.alt'),
                'publication_state' => 'published',
                'last_published_at' => now(),
            ],
        );

        return [
            'status' => 'ok',
            'updated' => true,
            'slug' => $page->slug,
            'live_url' => $page->live_url ?: $liveUrl,
            'sitemap_url' => rtrim((string) config('app.url'), '/').'/'.$prefix.'-sitemap.xml',
        ];
    }

    /**
     * @param  array<string,mixed>  $payload
     * @return array<string,mixed>
     */
    private function publishNativeUpdate(array $payload): array
    {
        $siteId = (string) data_get($payload, 'site.site_id');
        $targetPath = '/'.trim((string) data_get($payload, 'publication.target_path', ''), '/');
        $targetUrl = trim((string) data_get($payload, 'publication.target_url', ''));
        $baseUrl = rtrim((string) config('app.url'), '/');
        $liveUrl = $targetUrl !== '' ? $targetUrl : $baseUrl.$targetPath;

        $patch = PraeviseoNativePagePatch::query()->updateOrCreate(
            [
                'praeviseo_site_id' => $siteId,
                'target_path' => $targetPath,
            ],
            [
                'external_page_id' => (int) data_get($payload, 'page.id'),
                'title' => (string) data_get($payload, 'page.title'),
                'h1' => data_get($payload, 'page.h1'),
                'meta_description' => data_get($payload, 'page.meta_description'),
                'content_html' => (string) data_get($payload, 'page.content'),
                'faq_json' => data_get($payload, 'page.faq', []),
                'schema_json' => data_get($payload, 'page.schema', []),
                'internal_links_json' => data_get($payload, 'page.internal_links', []),
                'canonical_url' => data_get($payload, 'page.canonical_url') ?: $liveUrl,
                'live_url' => data_get($payload, 'page.suggested_live_url') ?: $liveUrl,
                'publication_state' => 'published',
                'last_published_at' => now(),
            ],
        );

        return [
            'status' => 'ok',
            'updated' => true,
            'scope' => 'native_update',
            'target_path' => $patch->target_path,
            'live_url' => $patch->live_url ?: $liveUrl,
            'sitemap_url' => $baseUrl.'/sitemap.xml',
        ];
    }

    private function assertSignedRequest(Request $request): void
    {
        $configuredSiteId = trim((string) config('praeviseo-bridge.site_id', ''));
        $secret = trim((string) config('praeviseo-bridge.secret', ''));

        if ($secret === '') {
            throw new RuntimeException('PRAEVISEO_BRIDGE_SECRET manquant.');
        }

        $headerSiteId = trim((string) $request->header('X-Praeviseo-Site-Id', ''));
        $timestamp = trim((string) $request->header('X-Praeviseo-Timestamp', ''));
        $signature = trim((string) $request->header('X-Praeviseo-Signature', ''));

        if ($configuredSiteId !== '' && $headerSiteId !== $configuredSiteId) {
            throw new RuntimeException('Site PraeviSEO non autorisé.');
        }

        if ($timestamp === '' || $signature === '') {
            throw new RuntimeException('Headers de signature manquants.');
        }

        if (abs(now()->timestamp - (int) $timestamp) > 300) {
            throw new RuntimeException('Timestamp PraeviSEO expiré.');
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$request->getContent(), $secret);

        if (! hash_equals($expected, $signature)) {
            throw new RuntimeException('Signature PraeviSEO invalide.');
        }
    }
}
