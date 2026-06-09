<?php

declare(strict_types=1);

namespace App\Services\SemanticGraph\Analyzers;

use App\Models\SeoSitePage;
use App\ObservedSite\BusinessPageRelevanceFilter;

class PillarAnalyzer
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
                ->where('pillar_likelihood', '>=', 0.6)
                ->orderByDesc('pillar_likelihood')
                ->limit(12);
        })
            ->map(fn (SeoSitePage $page): array => [
                'id' => $page->id,
                'url' => $page->normalized_url,
                'title' => $page->title,
                'cluster' => $page->cluster_label,
                'pillar_likelihood' => (float) $page->pillar_likelihood,
                'authority_score' => (float) $page->authority_score,
            ])
            ->all();
    }
}
