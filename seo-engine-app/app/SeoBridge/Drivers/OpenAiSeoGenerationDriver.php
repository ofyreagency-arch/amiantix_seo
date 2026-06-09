<?php

declare(strict_types=1);

namespace App\SeoBridge\Drivers;

use App\Models\SeoPage;
use App\Models\SeoSite;
use App\Runtime\SeoEngineContext;
use App\Understanding\SiteProfileGate;
use Illuminate\Support\Str;
use Ofyre\SeoEngine\Contracts\SeoGenerationDriver;
use Ofyre\SeoEngine\Services\Generation\SeoGenerationService;
use Ofyre\SeoEngine\Services\Scoring\SeoScoreRefreshService;

class OpenAiSeoGenerationDriver implements SeoGenerationDriver
{
    public function __construct(
        private readonly SeoGenerationService $generator,
        private readonly SeoScoreRefreshService $scoreRefresh,
        private readonly SeoEngineContext $context,
        private readonly SiteProfileGate $siteProfileGate,
    ) {}

    public function generatePage(string $keyword, string $status): object
    {
        $this->siteProfileGate->assertReady(
            SeoSite::query()->where('site_id', $this->context->siteId())->first(),
        );

        $result = $this->generator->generatePayload($keyword);

        if (config('seo-engine.require_site_profile', true) && ($result['generation_source'] ?? '') === 'fallback') {
            throw new \RuntimeException(
                (string) ($result['generation_error'] ?? 'La génération IA a échoué. Aucun contenu générique ne sera produit.')
            );
        }

        $this->assertNoGenericContent((string) data_get($result, 'payload.content', ''));

        $slug = $this->resolveSlug($keyword);

        $page = SeoPage::query()->firstOrNew([
            'site_id' => $this->context->siteId(),
            'slug'    => $slug,
        ]);
        $page->forceFill([
            'site_id' => $this->context->siteId(),
            'keyword' => $keyword,
            'slug' => $slug,
            'cluster' => $result['cluster'],
            'status' => $status === 'published' ? 'draft' : ($status !== '' ? $status : 'draft'),
            'title' => (string) ($result['payload']['title'] ?? $keyword),
            'h1' => (string) ($result['payload']['h1'] ?? $keyword),
            'meta_description' => (string) ($result['payload']['meta_description'] ?? ''),
            'content' => (string) ($result['payload']['content'] ?? ''),
            'faq_json' => $result['payload']['faq'] ?? [],
            'schema_json' => $result['payload']['schema'] ?? [],
            'generation_source' => (string) ($result['generation_source'] ?? 'unknown'),
            'generation_error' => $result['generation_error'] ?? null,
            'generation_trace_json' => $result['generation_trace'] ?? null,
            'internal_links_json' => $this->generator->generateInternalLinks((object) [
                'keyword' => $keyword,
                'cluster' => $result['cluster'],
                'slug' => $slug,
            ]),
            'canonical_url' => rtrim($this->context->url(), '/').'/'.$slug,
            'image_prompt' => $this->generator->generateImagePrompt($keyword, $result['cluster']),
            'published_at' => $status === 'published' ? now() : $page->published_at,
        ])->save();

        return $this->scoreRefresh->refresh($page->refresh(), createAudit: true);
    }

    private function resolveSlug(string $keyword): string
    {
        $siteId = $this->context->siteId();
        $baseSlug = Str::slug(Str::lower($keyword));
        $baseSlug = $baseSlug !== '' ? $baseSlug : 'page-seo';

        $sameSitePage = SeoPage::query()
            ->where('site_id', $siteId)
            ->where('slug', $baseSlug)
            ->first();

        if ($sameSitePage && Str::lower(trim((string) $sameSitePage->keyword)) === Str::lower(trim($keyword))) {
            return $baseSlug;
        }

        if (! SeoPage::query()->where('slug', $baseSlug)->exists()) {
            return $baseSlug;
        }

        $suffix = 2;

        do {
            $candidate = $baseSlug.'-'.$suffix;
            $exists = SeoPage::query()->where('slug', $candidate)->exists();
            $suffix++;
        } while ($exists);

        return $candidate;
    }

    private function assertNoGenericContent(string $content): void
    {
        if (! config('seo-engine.require_site_profile', true)) {
            return;
        }

        $forbidden = [
            'Field example',
            'SaaS knowledge base',
            'Write a business article for a professional SaaS',
            'Operational context',
        ];

        foreach ($forbidden as $phrase) {
            if (str_contains($content, $phrase)) {
                throw new \RuntimeException('Contenu générique interdit détecté: '.$phrase);
            }
        }
    }

    public function improvePage(object $page, array $audit = []): object
    {
        $this->siteProfileGate->assertReady(
            SeoSite::query()->where('site_id', $this->context->siteId())->first(),
        );

        $result = $this->generator->improvePayload($page, $audit);

        if (! $page instanceof SeoPage) {
            $page = SeoPage::query()->findOrFail((int) $page->id);
        }

        $page->forceFill([
            'cluster' => $result['cluster'],
            'title' => (string) ($result['payload']['title'] ?? $page->title),
            'h1' => (string) ($result['payload']['h1'] ?? $page->h1),
            'meta_description' => (string) ($result['payload']['meta_description'] ?? $page->meta_description),
            'content' => (string) ($result['payload']['content'] ?? $page->content),
            'faq_json' => $result['payload']['faq'] ?? $page->faq_json,
            'schema_json' => $result['payload']['schema'] ?? $page->schema_json,
            'internal_links_json' => $this->generator->generateInternalLinks($page),
        ])->save();

        return $this->scoreRefresh->refresh($page->refresh(), $audit, createAudit: true);
    }
}
