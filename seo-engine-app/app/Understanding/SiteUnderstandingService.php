<?php

declare(strict_types=1);

namespace App\Understanding;

use App\Models\SeoSite;
use App\Models\SeoSitePage;
use App\Models\SeoSitePageSnapshot;
use App\Services\SemanticGraph\SemanticGraphEngine;
use App\Services\SemanticGraph\Analyzers\AuthorityAnalyzer;
use App\Services\SemanticGraph\Analyzers\ClusterAnalyzer;
use App\Services\SemanticGraph\Analyzers\ContentGapAnalyzer;
use App\Services\SemanticGraph\Analyzers\OrphanAnalyzer;
use App\Services\SemanticGraph\Analyzers\PillarAnalyzer;

class SiteUnderstandingService
{
    public function __construct(
        private readonly SemanticGraphEngine $graph,
        private readonly ClusterAnalyzer $clusters,
        private readonly OrphanAnalyzer $orphans,
        private readonly AuthorityAnalyzer $authority,
        private readonly PillarAnalyzer $pillars,
        private readonly ContentGapAnalyzer $gaps,
    ) {}

    /**
     * @return array<string,mixed>
     */
    public function analyze(SeoSite $site, bool $forceEmbeddings = false): array
    {
        $graph = $this->graph->build($site->site_id, $forceEmbeddings);
        $clusterSummary = $this->clusters->analyze($site->site_id);
        $orphanSummary = $this->orphans->analyze($site->site_id);
        $authoritySummary = $this->authority->analyze($site->site_id);
        $pillarSummary = $this->pillars->analyze($site->site_id);
        $gapSummary = $this->gaps->analyze($site->site_id);
        $weakPages = $this->weakPages($site->site_id);

        return [
            'clusters' => $clusterSummary,
            'semantic_neighbors' => $graph['semantic_neighbors'],
            'internal_link_suggestions' => $graph['internal_link_suggestions'],
            'query_opportunities' => $graph['query_opportunities'],
            'cannibalization' => $graph['cannibalization_risks'],
            'pillar_pages' => $pillarSummary,
            'orphan_pages' => $orphanSummary,
            'weak_pages' => $weakPages,
            'overlaps' => $graph['semantic_neighbors'],
            'content_gaps' => $gapSummary,
            'weak_internal_linking' => $graph['internal_link_suggestions'],
            'opportunities' => [
                'orphan_pages' => count($orphanSummary),
                'content_gaps' => count($gapSummary),
                'overlap_pairs' => count($graph['semantic_neighbors']),
                'weak_pages' => count($weakPages),
                'query_opportunities' => count($graph['query_opportunities']),
                'cannibalization_risks' => count($graph['cannibalization_risks']),
            ],
            'authority' => $authoritySummary,
        ];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function weakPages(string $siteId): array
    {
        $pages = SeoSitePage::query()
            ->where('site_id', $siteId)
            ->whereNotNull('last_snapshot_id')
            ->where(function ($query): void {
                $query->where('latest_word_count', '<', 300)
                    ->orWhere('authority_score', '<', 0.20)
                    ->orWhere('indexability_state', '!=', 'indexable');
            })
            ->limit(20)
            ->get();

        $snapshots = SeoSitePageSnapshot::query()
            ->where('site_id', $siteId)
            ->whereIn('id', $pages->pluck('last_snapshot_id')->filter()->all())
            ->get()
            ->keyBy('id');

        return $pages->map(function (SeoSitePage $page) use ($snapshots): array {
            $snapshot = $snapshots->get($page->last_snapshot_id);

            return [
                'id' => $page->id,
                'url' => $page->normalized_url,
                'title' => $page->title,
                'cluster' => $page->cluster_label,
                'word_count' => (int) $page->latest_word_count,
                'authority_score' => (float) $page->authority_score,
                'indexability_state' => $page->indexability_state,
                'missing_h1' => empty($snapshot?->h1_json ?? []),
            ];
        })->all();
    }
}
