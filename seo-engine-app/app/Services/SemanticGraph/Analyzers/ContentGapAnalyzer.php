<?php

declare(strict_types=1);

namespace App\Services\SemanticGraph\Analyzers;

use App\Models\SeoSitePage;

class ContentGapAnalyzer
{
    /**
     * @return array<int,array<string,mixed>>
     */
    public function analyze(string $siteId): array
    {
        $clusters = SeoSitePage::query()
            ->where('site_id', $siteId)
            ->whereNotNull('cluster_label')
            ->get()
            ->groupBy('cluster_label');

        $gaps = [];

        foreach ($clusters as $cluster => $pages) {
            $pageCount = $pages->count();
            $avgWords = (float) ($pages->avg('latest_word_count') ?? 0);
            $avgAuthority = (float) ($pages->avg('authority_score') ?? 0);

            if ($pageCount <= 1 || $avgWords < 500 || $avgAuthority < 0.25) {
                $gaps[] = [
                    'cluster' => (string) $cluster,
                    'page_count' => $pageCount,
                    'avg_word_count' => (int) round($avgWords),
                    'avg_authority' => round($avgAuthority, 4),
                    'reason' => $pageCount <= 1 ? 'undercovered_cluster' : 'weak_cluster_depth',
                ];
            }
        }

        return $gaps;
    }
}
