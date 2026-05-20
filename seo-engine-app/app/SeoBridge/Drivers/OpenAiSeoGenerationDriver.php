<?php

declare(strict_types=1);

namespace App\SeoBridge\Drivers;

use App\Models\SeoPage;
use App\Services\SeoEngineContext;
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
    ) {}

    public function generatePage(string $keyword, string $status): object
    {
        $result = $this->generator->generatePayload($keyword);
        $slug = Str::slug(Str::lower($keyword));

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

    public function improvePage(object $page, array $audit = []): object
    {
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
