<?php

declare(strict_types=1);

namespace App\Services\SemanticGraph\Analyzers;

use App\Models\SeoSitePage;

class OrphanAnalyzer
{
    /**
     * @return array<int,array<string,mixed>>
     */
    public function analyze(string $siteId): array
    {
        return SeoSitePage::query()
            ->where('site_id', $siteId)
            ->where('orphan_score', '>=', 0.95)
            ->orderByDesc('orphan_score')
            ->get()
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
