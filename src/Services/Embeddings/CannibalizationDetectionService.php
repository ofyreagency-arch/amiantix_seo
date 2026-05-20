<?php

declare(strict_types=1);

namespace Ofyre\SeoEngine\Services\Embeddings;

use Ofyre\SeoEngine\Contracts\CannibalizationActionDecider;
use Ofyre\SeoEngine\Contracts\EmbeddableContentRepository;
use Ofyre\SeoEngine\Contracts\SemanticLinkPolicyProvider;
use Ofyre\SeoEngine\Contracts\SemanticLinkRepository;
use Ofyre\SeoEngine\Contracts\VectorStore;

class CannibalizationDetectionService
{
    public function __construct(
        private readonly EmbeddableContentRepository $content,
        private readonly VectorStore $vectors,
        private readonly SemanticLinkRepository $links,
        private readonly SemanticLinkPolicyProvider $policy,
        private readonly SemanticSimilarityService $similarity,
        private readonly CannibalizationActionDecider $decider,
    ) {}

    /**
     * @return array{pages:int,risks:int}
     */
    public function refresh(?string $slug = null, int $limit = 100): array
    {
        $pages = collect($this->content->publishedPagesForSemanticLinks($slug, $limit))
            ->keyBy(fn (object $page): string => (string) ($page->slug ?? ''))
            ->filter(fn (object $page, string $key): bool => $key !== '');

        $embeddings = collect($this->vectors->forEntityKeys('page', $pages->keys()->all()))
            ->keyBy(fn (object $embedding): string => (string) ($embedding->entity_key ?? ''));

        $threshold = (float) config('seo-engine.embeddings.cannibalization_threshold', 0.9);
        $maxRisks = (int) config('seo-engine.embeddings.max_cannibalization_risks', 4);
        $impressionThreshold = (int) config('seo-engine.embeddings.cannibalization_impression_threshold', 50);
        $positionGapThreshold = (float) config('seo-engine.embeddings.cannibalization_position_gap_threshold', 5.0);
        $totalRisks = 0;

        foreach ($pages as $sourceKey => $page) {
            $sourceEmbedding = $embeddings->get($sourceKey);

            if (! $sourceEmbedding) {
                $this->links->replaceCannibalizationRisks($sourceKey, []);
                continue;
            }

            $risks = $pages
                ->except($sourceKey)
                ->map(function (object $candidate, string $candidateKey) use ($embeddings, $sourceEmbedding, $page, $impressionThreshold, $positionGapThreshold): ?array {
                    $candidateEmbedding = $embeddings->get($candidateKey);

                    if (! $candidateEmbedding) {
                        return null;
                    }

                    $rawScore = $this->similarity->cosine(
                        array_map(static fn (mixed $value): float => (float) $value, $sourceEmbedding->embedding_json ?? []),
                        array_map(static fn (mixed $value): float => (float) $value, $candidateEmbedding->embedding_json ?? []),
                    );

                    $policy = $this->policy->evaluate($page, $candidate, $rawScore);
                    $sourceSignals = (array) ($page->search_console_json ?? []);
                    $targetSignals = (array) ($candidate->search_console_json ?? []);

                    $score = $rawScore;
                    if ((bool) ($policy['meta']['cluster_match'] ?? false)) {
                        $score += 0.03;
                    }
                    if ((bool) ($policy['meta']['intent_match'] ?? false)) {
                        $score += 0.03;
                    }
                    if (($sourceSignals['impressions'] ?? 0) >= $impressionThreshold && ($targetSignals['impressions'] ?? 0) >= $impressionThreshold) {
                        $score += 0.03;
                    }
                    if (
                        is_numeric($sourceSignals['position'] ?? null)
                        && is_numeric($targetSignals['position'] ?? null)
                        && abs((float) $sourceSignals['position'] - (float) $targetSignals['position']) <= $positionGapThreshold
                    ) {
                        $score += 0.02;
                    }

                    $score = round(min(1.0, $score), 4);

                    return [
                        'page' => $candidate,
                        'raw_score' => round($rawScore, 4),
                        'score' => $score,
                        'policy' => $policy,
                        'source_signals' => $sourceSignals,
                        'target_signals' => $targetSignals,
                    ];
                })
                ->filter()
                ->filter(fn (array $candidate): bool => $candidate['score'] >= $threshold)
                ->sortByDesc('score')
                ->take($maxRisks)
                ->map(function (array $candidate) use ($page): array {
                    $targetPage = $candidate['page'];
                    $url = method_exists($targetPage, 'canonicalPath')
                        ? (string) $targetPage->canonicalPath()
                        : '/'.ltrim((string) ($targetPage->slug ?? ''), '/');

                    return [
                        'target_key' => (string) ($targetPage->slug ?? ''),
                        'target_id' => isset($targetPage->id) ? (int) $targetPage->id : null,
                        'label' => (string) ($targetPage->keyword ?? $targetPage->title ?? $targetPage->slug ?? ''),
                        'url' => $url,
                        'reason' => $this->recommendedAction($candidate),
                        'similarity_score' => $candidate['score'],
                        'meta' => [
                            'raw_similarity_score' => $candidate['raw_score'],
                            'cluster_match' => (bool) ($candidate['policy']['meta']['cluster_match'] ?? false),
                            'intent_match' => (bool) ($candidate['policy']['meta']['intent_match'] ?? false),
                            'source_cluster' => (string) ($page->cluster ?? ''),
                            'target_cluster' => (string) ($targetPage->cluster ?? ''),
                            'source_impressions' => (int) ($candidate['source_signals']['impressions'] ?? 0),
                            'target_impressions' => (int) ($candidate['target_signals']['impressions'] ?? 0),
                            'source_position' => (float) ($candidate['source_signals']['position'] ?? 0.0),
                            'target_position' => (float) ($candidate['target_signals']['position'] ?? 0.0),
                            'policy_reasons' => $candidate['policy']['reasons'] ?? [],
                            'recommended_action' => $this->recommendedAction($candidate),
                        ],
                    ];
                })
                ->values()
                ->all();

            $totalRisks += $this->links->replaceCannibalizationRisks($sourceKey, $risks);
        }

        return [
            'pages' => $pages->count(),
            'risks' => $totalRisks,
        ];
    }

    /**
     * @param array{raw_score:float,score:float,policy:array<string,mixed>,source_signals:array<string,mixed>,target_signals:array<string,mixed>} $candidate
     */
    private function recommendedAction(array $candidate): string
    {
        return $this->decider->decide(
            clusterMatch: (bool) ($candidate['policy']['meta']['cluster_match'] ?? false),
            intentMatch: (bool) ($candidate['policy']['meta']['intent_match'] ?? false),
            sourceIntent: (string) ($candidate['policy']['meta']['source_intent'] ?? ''),
            targetIntent: (string) ($candidate['policy']['meta']['target_intent'] ?? ''),
            sourceImpressions: (int) ($candidate['source_signals']['impressions'] ?? 0),
            targetImpressions: (int) ($candidate['target_signals']['impressions'] ?? 0),
            rawScore: (float) ($candidate['raw_score'] ?? 0.0),
        );
    }
}
