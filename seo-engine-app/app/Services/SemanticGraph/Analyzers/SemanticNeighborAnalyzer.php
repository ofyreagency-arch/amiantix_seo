<?php

declare(strict_types=1);

namespace App\Services\SemanticGraph\Analyzers;

use App\Models\SeoSemanticLink;
use App\Models\SeoSitePage;
use App\Models\SeoSitePageSnapshot;
use App\ObservedSite\ObservedPageEmbeddingService;
use App\Services\SemanticGraph\Support\ObservedSemanticSupport;
use Ofyre\SeoEngine\Contracts\SemanticLinkPolicyProvider;
use Ofyre\SeoEngine\Contracts\VectorStore;
use Ofyre\SeoEngine\Services\Embeddings\SemanticSimilarityService;

class SemanticNeighborAnalyzer
{
    public function __construct(
        private readonly ObservedPageEmbeddingService $embeddings,
        private readonly VectorStore $vectors,
        private readonly SemanticSimilarityService $similarity,
        private readonly ObservedSemanticSupport $support,
        private readonly SemanticLinkPolicyProvider $policy,
    ) {}

    /**
     * @return array<int,array<string,mixed>>
     */
    public function analyze(string $siteId, bool $forceEmbeddings = false): array
    {
        $this->embeddings->embedSite($siteId, force: $forceEmbeddings);

        $pages = SeoSitePage::query()
            ->where('site_id', $siteId)
            ->whereNotNull('last_snapshot_id')
            ->get()
            ->keyBy('normalized_url');

        $snapshots = SeoSitePageSnapshot::query()
            ->where('site_id', $siteId)
            ->whereIn('id', $pages->pluck('last_snapshot_id')->filter()->all())
            ->get()
            ->keyBy('id');

        $vectors = collect($this->vectors->forEntityKeys('observed_page', $pages->keys()->all()))
            ->keyBy(fn (object $vector): string => (string) ($vector->entity_key ?? ''));

        SeoSemanticLink::query()
            ->where('site_id', $siteId)
            ->whereIn('relation_type', [
                'semantic_similarity_same_cluster',
                'semantic_similarity_same_intent',
                'semantic_similarity_cross_cluster',
                'pillar_target',
                'observed_overlap',
            ])
            ->delete();

        $relations = [];

        foreach ($pages as $sourceUrl => $sourcePage) {
            $sourceVector = $vectors->get($sourceUrl);
            $sourceSnapshot = $this->support->snapshotForPage($sourcePage, $snapshots);
            if (! $sourceVector) {
                continue;
            }

            $sourceIntentTokens = $this->support->intentTokens($sourcePage, $sourceSnapshot);

            foreach ($pages as $targetUrl => $targetPage) {
                if ($sourceUrl === $targetUrl) {
                    continue;
                }

                $targetVector = $vectors->get($targetUrl);
                $targetSnapshot = $this->support->snapshotForPage($targetPage, $snapshots);
                if (! $targetVector) {
                    continue;
                }

                $rawSimilarity = $this->similarity->cosine(
                    array_map(static fn (mixed $value): float => (float) $value, $sourceVector->embedding_json ?? []),
                    array_map(static fn (mixed $value): float => (float) $value, $targetVector->embedding_json ?? []),
                );

                if ($rawSimilarity < 0.76) {
                    continue;
                }

                $targetIntentTokens = $this->support->intentTokens($targetPage, $targetSnapshot);
                $intentSimilarity = $this->support->intentSimilarity($sourceIntentTokens, $targetIntentTokens);
                $policy = $this->policy->evaluate(
                    $this->policyPage($sourcePage),
                    $this->policyPage($targetPage),
                    $rawSimilarity
                );
                $sameCluster = (bool) ($policy['meta']['cluster_match'] ?? false);
                $sameIntent = (bool) ($policy['meta']['intent_match'] ?? false);

                $relationType = $sameCluster
                    ? 'semantic_similarity_same_cluster'
                    : ($sameIntent ? 'semantic_similarity_same_intent' : 'semantic_similarity_cross_cluster');

                $meta = [
                    'source_cluster' => $sourcePage->cluster_label,
                    'target_cluster' => $targetPage->cluster_label,
                    'intent_similarity' => $intentSimilarity,
                    'same_cluster' => $sameCluster,
                    'same_intent' => $sameIntent,
                    'pillar_target' => (bool) ($policy['meta']['target_is_pillar'] ?? false),
                    'policy_reasons' => $policy['reasons'] ?? [],
                    'raw_similarity_score' => round($rawSimilarity, 4),
                ];

                $relations[] = $this->storeRelation(
                    $siteId,
                    $relationType,
                    $sourcePage,
                    $targetPage,
                    $rawSimilarity,
                    $relationType,
                    $meta
                );

                if ((bool) ($policy['meta']['target_is_pillar'] ?? false) && $rawSimilarity >= 0.82) {
                    $relations[] = $this->storeRelation(
                        $siteId,
                        'pillar_target',
                        $sourcePage,
                        $targetPage,
                        min(1.0, $rawSimilarity + 0.02),
                        'pillar_target',
                        $meta
                    );
                }

                if ($rawSimilarity >= 0.88) {
                    $relations[] = $this->storeRelation(
                        $siteId,
                        'observed_overlap',
                        $sourcePage,
                        $targetPage,
                        $rawSimilarity,
                        'overlap_detection',
                        $meta
                    );
                }
            }
        }

        return $relations;
    }

    /**
     * @param  array<string,mixed>  $meta
     * @return array<string,mixed>
     */
    private function storeRelation(
        string $siteId,
        string $relationType,
        SeoSitePage $sourcePage,
        SeoSitePage $targetPage,
        float $score,
        string $reason,
        array $meta,
    ): array {
        SeoSemanticLink::query()->create([
            'site_id' => $siteId,
            'relation_type' => $relationType,
            'source_key' => $sourcePage->normalized_url,
            'source_id' => $sourcePage->id,
            'target_key' => $targetPage->normalized_url,
            'target_id' => $targetPage->id,
            'label' => $this->support->pageLabel($targetPage),
            'url' => $targetPage->normalized_url,
            'reason' => $reason,
            'similarity_score' => round($score, 4),
            'meta_json' => $meta,
        ]);

        return [
            'relation_type' => $relationType,
            'source_id' => $sourcePage->id,
            'target_id' => $targetPage->id,
            'score' => round($score, 4),
        ];
    }

    private function policyPage(SeoSitePage $page): object
    {
        return (object) [
            'slug' => trim((string) $page->path, '/'),
            'keyword' => (string) ($page->primary_h1 ?? $page->title ?? ''),
            'title' => (string) ($page->title ?? ''),
            'cluster' => (string) ($page->cluster_label ?? ''),
            'internal_inbound_count' => (int) ($page->internal_inlinks ?? 0),
        ];
    }
}
