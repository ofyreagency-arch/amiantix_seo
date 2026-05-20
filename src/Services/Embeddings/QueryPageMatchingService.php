<?php

declare(strict_types=1);

namespace Ofyre\SeoEngine\Services\Embeddings;

use Ofyre\SeoEngine\Contracts\EmbeddableContentRepository;
use Ofyre\SeoEngine\Contracts\SemanticLinkRepository;
use Ofyre\SeoEngine\Contracts\VectorStore;

class QueryPageMatchingService
{
    public function __construct(
        private readonly EmbeddableContentRepository $content,
        private readonly VectorStore $vectors,
        private readonly SemanticLinkRepository $links,
        private readonly ContentEmbeddingService $embeddings,
        private readonly SemanticSimilarityService $similarity,
    ) {}

    /**
     * @return array{queries:int,opportunities:int,embedded:int,skipped:int}
     */
    public function refresh(?string $slug = null, int $window = 28, int $limit = 250, bool $force = false): array
    {
        $embedSummary = $this->embeddings->embedQueries($slug, $window, $limit, $force);

        $pages = collect($this->content->publishedPagesForSemanticLinks(null, 500))
            ->keyBy(fn (object $page): string => (string) ($page->slug ?? ''))
            ->filter(fn (object $page, string $key): bool => $key !== '');

        $pageEmbeddings = collect($this->vectors->forEntityKeys('page', $pages->keys()->all()))
            ->keyBy(fn (object $embedding): string => (string) ($embedding->entity_key ?? ''));

        $queries = collect($this->content->queriesForMatching($slug, $window, $limit));
        $matchesByPage = [];

        foreach ($queries as $metric) {
            $query = trim((string) ($metric->query ?? ''));
            if ($query === '') {
                continue;
            }

            $queryEmbedding = $this->vectors->find('query', $this->embeddings->queryEntityKey($query));
            if (! $queryEmbedding) {
                continue;
            }

            $scores = $pages
                ->map(function (object $page, string $pageSlug) use ($pageEmbeddings, $queryEmbedding): ?array {
                    $pageEmbedding = $pageEmbeddings->get($pageSlug);

                    if (! $pageEmbedding) {
                        return null;
                    }

                    $score = $this->similarity->cosine(
                        array_map(static fn (mixed $value): float => (float) $value, $queryEmbedding->embedding_json ?? []),
                        array_map(static fn (mixed $value): float => (float) $value, $pageEmbedding->embedding_json ?? []),
                    );

                    return [
                        'page' => $page,
                        'score' => round($score, 4),
                    ];
                })
                ->filter()
                ->sortByDesc('score')
                ->values();

            if ($scores->isEmpty()) {
                continue;
            }

            $best = $scores->first();
            $bestPage = $best['page'];
            $bestSlug = (string) ($bestPage->slug ?? '');
            $bestScore = (float) $best['score'];

            $currentPage = $metric->page;
            $currentSlug = (string) ($currentPage->slug ?? '');

            if ($currentSlug === '' && isset($metric->seo_page_id)) {
                $matchedCurrentPage = $pages->first(fn (object $page): bool => (int) ($page->id ?? 0) === (int) $metric->seo_page_id);
                $currentSlug = (string) ($matchedCurrentPage->slug ?? '');
                $currentPage = $matchedCurrentPage ?? $currentPage;
            }
            $currentScore = 0.0;

            if ($currentSlug !== '') {
                $currentScore = (float) (($scores->first(fn (array $row): bool => (string) ($row['page']->slug ?? '') === $currentSlug)['score'] ?? 0.0));
            }

            $decision = $this->decideOpportunity($metric, $bestSlug, $bestScore, $currentSlug, $currentScore);

            if ($decision === null) {
                continue;
            }

            $sourceSlug = $decision['source_slug'];
            $sourcePage = $pages->get($sourceSlug);

            if (! $sourcePage) {
                continue;
            }

            $matchesByPage[$sourceSlug] ??= [];
            $matchesByPage[$sourceSlug][] = [
                'source_key' => $sourceSlug,
                'source_id' => isset($sourcePage->id) ? (int) $sourcePage->id : null,
                'target_key' => $this->embeddings->queryEntityKey($query),
                'label' => $query,
                'url' => (string) ($metric->url ?? ''),
                'reason' => $decision['action'],
                'similarity_score' => $bestScore,
                'meta' => [
                    'query' => $query,
                    'current_page_slug' => $currentSlug !== '' ? $currentSlug : null,
                    'current_page_score' => round($currentScore, 4),
                    'best_match_slug' => $bestSlug,
                    'best_match_score' => round($bestScore, 4),
                    'recommended_action' => $decision['action'],
                    'impressions' => (int) ($metric->impressions ?? 0),
                    'clicks' => (int) ($metric->clicks ?? 0),
                    'ctr' => (float) ($metric->ctr ?? 0.0),
                    'position' => (float) ($metric->position ?? 0.0),
                    'traffic_delta' => (int) ($metric->traffic_delta ?? 0),
                    'window_days' => $window,
                    'cluster' => (string) ($metric->cluster ?? ''),
                    'recommended_target_slug' => $bestSlug,
                    'served_by_current_page' => $currentSlug !== '' && $currentSlug === $sourceSlug,
                ],
            ];
        }

        $scopedPages = $slug !== null ? [$slug] : $pages->keys()->all();
        $stored = 0;

        foreach ($scopedPages as $pageSlug) {
            $stored += $this->links->replaceQueryPageMatches($pageSlug, $matchesByPage[$pageSlug] ?? []);
        }

        return [
            'queries' => $queries->count(),
            'opportunities' => $stored,
            'embedded' => $embedSummary['embedded'],
            'skipped' => $embedSummary['skipped'],
        ];
    }

    /**
     * @return array{source_slug:string,action:string}|null
     */
    private function decideOpportunity(object $metric, string $bestSlug, float $bestScore, string $currentSlug, float $currentScore): ?array
    {
        $threshold = (float) config('seo-engine.embeddings.query_match_threshold', 0.78);
        $wrongPageGap = (float) config('seo-engine.embeddings.query_match_wrong_page_gap', 0.06);
        $wrongPageMinScore = (float) config('seo-engine.embeddings.query_match_wrong_page_min_score', 0.84);
        $refreshPosition = (float) config('seo-engine.embeddings.query_match_refresh_position_threshold', 12.0);
        $strongImpressions = (int) config('seo-engine.embeddings.query_match_impression_threshold', 30);
        $minScore = (float) config('seo-engine.embeddings.query_match_min_score', 0.6);
        $minImpressions = (int) config('seo-engine.embeddings.query_match_min_impressions', 5);
        $createMinScore = (float) config('seo-engine.embeddings.query_match_create_min_score', $threshold);
        $createMinImpressions = (int) config('seo-engine.embeddings.query_match_create_min_impressions', 20);
        $createMaxPosition = (float) config('seo-engine.embeddings.query_match_create_max_position', 25.0);
        $ctr = (float) ($metric->ctr ?? 0.0);
        $position = (float) ($metric->position ?? 0.0);
        $impressions = (int) ($metric->impressions ?? 0);
        $cluster = (string) ($metric->cluster ?? '');

        if ($impressions < $minImpressions || $bestScore < $minScore) {
            return null;
        }

        if ($currentSlug !== '') {
            if ($bestScore < $threshold) {
                return [
                    'source_slug' => $currentSlug,
                    'action' => $impressions >= $createMinImpressions && $position <= $createMaxPosition
                        ? 'review_query_cluster'
                        : 'monitor_query',
                ];
            }

            if (
                $bestSlug !== ''
                && $bestSlug !== $currentSlug
                && $bestScore >= $wrongPageMinScore
                && ($bestScore - $currentScore) >= $wrongPageGap
            ) {
                return [
                    'source_slug' => $bestSlug,
                    'action' => 'review_wrong_ranking_page',
                ];
            }

            if ($position >= $refreshPosition || ($impressions >= $strongImpressions && $ctr < 0.02)) {
                return [
                    'source_slug' => $currentSlug,
                    'action' => 'refresh_existing_page',
                ];
            }

            if ($bestScore >= $threshold && $position >= 8.0 && $position <= 25.0) {
                return [
                    'source_slug' => $currentSlug,
                    'action' => 'differentiate_existing_page',
                ];
            }

            if ($impressions >= $createMinImpressions && $position <= $createMaxPosition && $cluster !== '') {
                return [
                    'source_slug' => $currentSlug,
                    'action' => 'review_query_cluster',
                ];
            }

            return null;
        }

        if ($bestSlug === '') {
            return null;
        }

        if ($bestScore >= $createMinScore && $impressions >= $createMinImpressions && $position <= $createMaxPosition) {
            return [
                'source_slug' => $bestSlug,
                'action' => 'create_dedicated_page',
            ];
        }

        return [
            'source_slug' => $bestSlug,
            'action' => ($position >= $refreshPosition || $impressions >= $strongImpressions)
                ? 'refresh_existing_page'
                : 'review_query_cluster',
        ];
    }
}
