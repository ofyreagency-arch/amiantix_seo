<?php

declare(strict_types=1);

namespace App\Services\SemanticGraph\Analyzers;

use App\Models\SeoSemanticLink;
use App\Models\SeoSitePage;
use App\ObservedSite\ObservedPageEmbeddingService;
use Ofyre\SeoEngine\Contracts\VectorStore;
use Ofyre\SeoEngine\Services\Embeddings\SemanticSimilarityService;

class OverlapAnalyzer
{
    public function __construct(
        private readonly ObservedPageEmbeddingService $embeddings,
        private readonly VectorStore $vectors,
        private readonly SemanticSimilarityService $similarity,
    ) {}

    /**
     * @return array<int,array<string,mixed>>
     */
    public function analyze(string $siteId, bool $forceEmbeddings = false): array
    {
        $this->embeddings->embedSite($siteId, force: $forceEmbeddings);

        $pages = SeoSitePage::query()
            ->where('site_id', $siteId)
            ->whereNotNull('cluster_label')
            ->get()
            ->keyBy('normalized_url');

        $vectors = collect($this->vectors->forEntityKeys('observed_page', $pages->keys()->all()))
            ->keyBy(fn (object $vector): string => (string) ($vector->entity_key ?? ''));

        SeoSemanticLink::query()
            ->where('site_id', $siteId)
            ->where('relation_type', 'observed_overlap')
            ->delete();

        $pairs = [];

        foreach ($pages as $sourceUrl => $sourcePage) {
            $sourceVector = $vectors->get($sourceUrl);
            if (! $sourceVector) {
                continue;
            }

            foreach ($pages as $targetUrl => $targetPage) {
                if ($sourceUrl >= $targetUrl) {
                    continue;
                }

                $targetVector = $vectors->get($targetUrl);
                if (! $targetVector) {
                    continue;
                }

                $score = $this->similarity->cosine(
                    array_map(static fn (mixed $value): float => (float) $value, $sourceVector->embedding_json ?? []),
                    array_map(static fn (mixed $value): float => (float) $value, $targetVector->embedding_json ?? []),
                );

                if ($score < 0.88) {
                    continue;
                }

                SeoSemanticLink::query()->create([
                    'site_id' => $siteId,
                    'relation_type' => 'observed_overlap',
                    'source_key' => $sourceUrl,
                    'source_id' => $sourcePage->id,
                    'target_key' => $targetUrl,
                    'target_id' => $targetPage->id,
                    'label' => (string) ($targetPage->title ?: $targetPage->path),
                    'url' => $targetUrl,
                    'reason' => 'Observed semantic overlap',
                    'similarity_score' => round($score, 4),
                    'meta_json' => [
                        'source_cluster' => $sourcePage->cluster_label,
                        'target_cluster' => $targetPage->cluster_label,
                    ],
                ]);

                $pairs[] = [
                    'source_id' => $sourcePage->id,
                    'source_url' => $sourceUrl,
                    'target_id' => $targetPage->id,
                    'target_url' => $targetUrl,
                    'score' => round($score, 4),
                ];
            }
        }

        $highestByPage = collect($pairs)
            ->flatMap(fn (array $pair): array => [
                $pair['source_id'] => $pair['score'],
                $pair['target_id'] => $pair['score'],
            ])
            ->groupBy(fn (float $score, int $pageId): int => $pageId)
            ->map(fn ($scores): float => (float) max($scores->all()));

        SeoSitePage::query()
            ->where('site_id', $siteId)
            ->get()
            ->each(function (SeoSitePage $page) use ($highestByPage): void {
                $page->forceFill([
                    'overlap_score' => round((float) ($highestByPage[$page->id] ?? 0), 4),
                ])->save();
            });

        return $pairs;
    }
}
