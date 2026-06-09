<?php

declare(strict_types=1);

namespace App\Services\SemanticGraph\Analyzers;

use App\Models\SeoSearchConsoleMetric;
use App\Models\SeoSemanticLink;
use App\Models\SeoSitePage;
use App\ObservedSite\ObservedPageEmbeddingService;
use App\ObservedSite\ObservedQueryEmbeddingService;
use App\Services\SemanticGraph\Support\ObservedSemanticSupport;
use Illuminate\Support\Collection;
use Ofyre\SeoEngine\Contracts\VectorStore;
use Ofyre\SeoEngine\Services\Embeddings\SemanticSimilarityService;

class QueryOpportunityAnalyzer
{
    public function __construct(
        private readonly ObservedPageEmbeddingService $pageEmbeddings,
        private readonly ObservedQueryEmbeddingService $queryEmbeddings,
        private readonly VectorStore $vectors,
        private readonly SemanticSimilarityService $similarity,
        private readonly ObservedSemanticSupport $support,
    ) {}

    /**
     * @return array<int,array<string,mixed>>
     */
    public function analyze(string $siteId, bool $forceEmbeddings = false, int $window = 28, int $limit = 250): array
    {
        $this->pageEmbeddings->embedSite($siteId, force: $forceEmbeddings);
        $this->queryEmbeddings->embedSite($siteId, $window, $limit, $forceEmbeddings);

        $pages = $this->support->businessPagesForSite($siteId)->keyBy('normalized_url');
        $pageVectors = collect($this->vectors->forEntityKeys('observed_page', $pages->keys()->all()))
            ->keyBy(fn (object $vector): string => (string) ($vector->entity_key ?? ''));

        $metrics = SeoSearchConsoleMetric::query()
            ->where('site_id', $siteId)
            ->whereNotNull('query')
            ->where('metric_date', '>=', now()->subDays($window)->toDateString())
            ->selectRaw('query, MAX(url) as url, SUM(clicks) as clicks, SUM(impressions) as impressions, AVG(ctr) as ctr, AVG(position) as position')
            ->groupBy('query')
            ->orderByDesc('impressions')
            ->limit($limit)
            ->get();

        SeoSemanticLink::query()
            ->where('site_id', $siteId)
            ->where('relation_type', 'observed_query_match')
            ->delete();

        $opportunities = [];

        foreach ($metrics as $metric) {
            $query = trim((string) ($metric->query ?? ''));
            if ($query === '') {
                continue;
            }

            $queryVector = $this->vectors->find('observed_query', $this->queryEmbeddings->queryEntityKey($query));
            if (! $queryVector) {
                continue;
            }

            $scores = collect();
            foreach ($pages as $pageUrl => $page) {
                $pageVector = $pageVectors->get($pageUrl);
                if (! $pageVector) {
                    continue;
                }

                $score = $this->similarity->cosine(
                    array_map(static fn (mixed $value): float => (float) $value, $queryVector->embedding_json ?? []),
                    array_map(static fn (mixed $value): float => (float) $value, $pageVector->embedding_json ?? []),
                );

                $scores->push([
                    'page' => $page,
                    'score' => round($score, 4),
                ]);
            }

            $best = $scores->sortByDesc('score')->first();
            if ($best === null || (float) $best['score'] < (float) config('seo-engine.embeddings.query_match_min_score', 0.6)) {
                continue;
            }

            /** @var SeoSitePage $bestPage */
            $bestPage = $best['page'];
            $observedUrl = $this->support->normalizeUrl((string) ($metric->url ?? ''));
            $currentPage = $pages->get($observedUrl);
            $currentScore = 0.0;

            if ($currentPage) {
                $currentScore = (float) ($scores->first(fn (array $row): bool => $row['page']->id === $currentPage->id)['score'] ?? 0.0);
            }

            $action = $this->decideAction(
                bestScore: (float) $best['score'],
                currentScore: $currentScore,
                currentPage: $currentPage,
                bestPage: $bestPage,
                impressions: (int) ($metric->impressions ?? 0),
                position: (float) ($metric->position ?? 0.0),
                query: $query
            );

            if ($action === null) {
                continue;
            }

            SeoSemanticLink::query()->create([
                'site_id' => $siteId,
                'relation_type' => 'observed_query_match',
                'source_key' => $bestPage->normalized_url,
                'source_id' => $bestPage->id,
                'target_key' => $this->queryEmbeddings->queryEntityKey($query),
                'target_id' => null,
                'label' => $query,
                'url' => $observedUrl,
                'reason' => $action,
                'similarity_score' => (float) $best['score'],
                'meta_json' => [
                    'query' => $query,
                    'observed_url' => $observedUrl,
                    'current_page_id' => $currentPage?->id,
                    'current_page_url' => $currentPage?->normalized_url,
                    'current_score' => round($currentScore, 4),
                    'best_page_url' => $bestPage->normalized_url,
                    'impressions' => (int) ($metric->impressions ?? 0),
                    'clicks' => (int) ($metric->clicks ?? 0),
                    'ctr' => (float) ($metric->ctr ?? 0.0),
                    'position' => (float) ($metric->position ?? 0.0),
                    'cluster' => $bestPage->cluster_label,
                    'recommended_action' => $action,
                ],
            ]);

            $opportunities[] = [
                'query' => $query,
                'page_id' => $bestPage->id,
                'action' => $action,
                'score' => (float) $best['score'],
            ];
        }

        return $opportunities;
    }

    private function decideAction(
        float $bestScore,
        float $currentScore,
        ?SeoSitePage $currentPage,
        SeoSitePage $bestPage,
        int $impressions,
        float $position,
        string $query,
    ): ?string {
        $threshold = (float) config('seo-engine.embeddings.query_match_threshold', 0.78);
        $wrongPageGap = (float) config('seo-engine.embeddings.query_match_wrong_page_gap', 0.06);

        if ($impressions < (int) config('seo-engine.embeddings.query_match_min_impressions', 5)) {
            return null;
        }

        if ($currentPage) {
            if ($currentPage->id !== $bestPage->id && $bestScore >= 0.84 && ($bestScore - $currentScore) >= $wrongPageGap) {
                return 'review_wrong_ranking_page';
            }

            if ($position >= (float) config('seo-engine.embeddings.query_match_refresh_position_threshold', 12.0)) {
                return 'refresh_existing_page';
            }

            if ($bestScore >= $threshold && $currentPage->cluster_label !== $bestPage->cluster_label) {
                return 'review_query_cluster';
            }

            return null;
        }

        if ($bestScore >= (float) config('seo-engine.embeddings.query_match_create_min_score', $threshold)) {
            return 'create_dedicated_page';
        }

        return 'monitor_query';
    }
}
