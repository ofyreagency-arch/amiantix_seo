<?php

declare(strict_types=1);

namespace App\Services\SemanticGraph\Analyzers;

use App\Models\SeoSitePage;
use App\ObservedSite\BusinessPageRelevanceFilter;

class AuthorityAnalyzer
{
    public function __construct(
        private readonly BusinessPageRelevanceFilter $businessPages,
    ) {}

    /**
     * @return array{top_pages:array<int,array<string,mixed>>,avg_authority:float}
     */
    public function analyze(string $siteId): array
    {
        $pages = $this->businessPages->loadObservedPages($siteId, function ($query): void {
            $query
                ->orderByDesc('authority_score')
                ->limit(10);
        });

        return [
            'top_pages' => $pages->map(fn (SeoSitePage $page): array => [
                'id' => $page->id,
                'url' => $page->normalized_url,
                'title' => $page->title,
                'authority_score' => (float) $page->authority_score,
                'inlinks' => (int) $page->internal_inlinks,
                'outlinks' => (int) $page->internal_outlinks,
            ])->all(),
            'avg_authority' => round((float) ($pages->avg('authority_score') ?? 0), 4),
        ];
    }
}
