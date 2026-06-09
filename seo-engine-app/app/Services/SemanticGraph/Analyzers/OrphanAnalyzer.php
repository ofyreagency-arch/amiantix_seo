<?php

declare(strict_types=1);

namespace App\Services\SemanticGraph\Analyzers;

use App\Models\SeoSitePage;
use App\ObservedSite\BusinessPageRelevanceFilter;

class OrphanAnalyzer
{
    public function __construct(
        private readonly BusinessPageRelevanceFilter $businessPages,
    ) {}

    /**
     * @return array<int,array<string,mixed>>
     */
    public function analyze(string $siteId): array
    {
        return $this->businessPages->loadObservedPages($siteId, function ($query): void {
            $query
                ->where('orphan_score', '>=', 0.95)
                ->orderByDesc('orphan_score');
        })
            ->map(fn (SeoSitePage $page): array => [
                'id' => $page->id,
                'url' => $page->normalized_url,
                'title' => $page->title,
                'orphan_score' => (float) $page->orphan_score,
                'inlinks' => (int) $page->internal_inlinks,
            ])
            ->all();
    }
}
