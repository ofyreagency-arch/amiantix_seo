<?php

declare(strict_types=1);

namespace Ofyre\SeoEngine\Services\Embeddings;

use Ofyre\SeoEngine\Contracts\EmbeddableContentRepository;
use Ofyre\SeoEngine\Contracts\SemanticLinkPolicyProvider;
use Ofyre\SeoEngine\Contracts\SemanticLinkRepository;
use Ofyre\SeoEngine\Contracts\VectorStore;

class InternalLinkSuggestionService
{
    public function __construct(
        private readonly EmbeddableContentRepository $content,
        private readonly VectorStore $vectors,
        private readonly SemanticLinkRepository $links,
        private readonly SemanticLinkPolicyProvider $policy,
        private readonly SemanticSimilarityService $similarity,
    ) {}

    /**
     * @return array{pages:int,suggestions:int}
     */
    public function refresh(?string $slug = null, int $limit = 100): array
    {
        $pages = collect($this->content->publishedPagesForSemanticLinks($slug, $limit))
            ->keyBy(fn (object $page): string => (string) ($page->slug ?? ''))
            ->filter(fn (object $page, string $key): bool => $key !== '');

        $embeddings = collect($this->vectors->forEntityKeys('page', $pages->keys()->all()))
            ->keyBy(fn (object $embedding): string => (string) ($embedding->entity_key ?? ''));

        $threshold = (float) config('seo-engine.embeddings.internal_link_threshold', 0.84);
        $maxSuggestions = (int) config('seo-engine.embeddings.max_internal_link_suggestions', 4);
        $maxCrossClusterSuggestions = (int) config('seo-engine.embeddings.policy.max_cross_cluster_suggestions', 1);
        $maxGenericTargets = (int) config('seo-engine.embeddings.policy.max_generic_targets', 2);
        $totalSuggestions = 0;

        foreach ($pages as $sourceKey => $page) {
            $sourceEmbedding = $embeddings->get($sourceKey);

            if (! $sourceEmbedding) {
                $this->links->replaceInternalLinkSuggestions($sourceKey, []);
                continue;
            }

            $existingUrls = collect($page->internal_links_json ?? [])
                ->pluck('url')
                ->filter()
                ->values();

            $suggestions = $pages
                ->except($sourceKey)
                ->map(function (object $candidate, string $candidateKey) use ($embeddings, $sourceEmbedding, $page): ?array {
                    $candidateEmbedding = $embeddings->get($candidateKey);

                    if (! $candidateEmbedding) {
                        return null;
                    }

                    $score = $this->similarity->cosine(
                        array_map(static fn (mixed $value): float => (float) $value, $sourceEmbedding->embedding_json ?? []),
                        array_map(static fn (mixed $value): float => (float) $value, $candidateEmbedding->embedding_json ?? []),
                    );

                    $policy = $this->policy->evaluate($page, $candidate, $score);

                    return [
                        'page' => $candidate,
                        'score' => $policy['score'],
                        'raw_score' => $score,
                        'policy' => $policy,
                    ];
                })
                ->filter()
                ->filter(fn (array $candidate): bool => ($candidate['policy']['accepted'] ?? false) && $candidate['score'] >= $threshold)
                ->sortByDesc('score')
                ->values()
                ->reduce(function (array $carry, array $candidate) use ($page, $existingUrls, $maxSuggestions, $maxCrossClusterSuggestions, $maxGenericTargets): array {
                    if (count($carry['items']) >= $maxSuggestions) {
                        return $carry;
                    }

                    $targetPage = $candidate['page'];
                    $url = method_exists($targetPage, 'canonicalPath')
                        ? (string) $targetPage->canonicalPath()
                        : '/'.ltrim((string) ($targetPage->slug ?? ''), '/');

                    if ($existingUrls->contains($url)) {
                        return $carry;
                    }

                    $meta = $candidate['policy']['meta'] ?? [];
                    $clusterMatch = (bool) ($meta['cluster_match'] ?? false);
                    $targetIsGeneric = (bool) ($meta['target_is_generic'] ?? false);

                    if (! $clusterMatch && $carry['cross_cluster'] >= $maxCrossClusterSuggestions) {
                        return $carry;
                    }

                    if ($targetIsGeneric && $carry['generic_targets'] >= $maxGenericTargets) {
                        return $carry;
                    }

                    $carry['items'][] = [
                        'target_key' => (string) ($targetPage->slug ?? ''),
                        'target_id' => isset($targetPage->id) ? (int) $targetPage->id : null,
                        'label' => (string) ($targetPage->keyword ?? $targetPage->title ?? $targetPage->slug ?? ''),
                        'url' => $url,
                        'reason' => collect($candidate['policy']['reasons'] ?? ['semantic_similarity'])->implode(','),
                        'similarity_score' => round((float) $candidate['score'], 4),
                        'meta' => array_merge($meta, [
                            'raw_similarity_score' => round((float) $candidate['raw_score'], 4),
                            'cluster_match' => $clusterMatch,
                            'source_cluster' => (string) ($page->cluster ?? ''),
                            'target_cluster' => (string) ($targetPage->cluster ?? ''),
                            'policy_reasons' => $candidate['policy']['reasons'] ?? ['semantic_similarity'],
                        ]),
                    ];

                    if (! $clusterMatch) {
                        $carry['cross_cluster']++;
                    }

                    if ($targetIsGeneric) {
                        $carry['generic_targets']++;
                    }

                    return $carry;
                }, [
                    'items' => [],
                    'cross_cluster' => 0,
                    'generic_targets' => 0,
                ])['items'];

            $totalSuggestions += $this->links->replaceInternalLinkSuggestions($sourceKey, $suggestions);
        }

        return [
            'pages' => $pages->count(),
            'suggestions' => $totalSuggestions,
        ];
    }
}
