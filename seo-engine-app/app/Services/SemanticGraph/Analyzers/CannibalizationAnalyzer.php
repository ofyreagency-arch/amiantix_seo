<?php

declare(strict_types=1);

namespace App\Services\SemanticGraph\Analyzers;

use App\Models\SeoSearchConsoleMetric;
use App\Models\SeoSemanticLink;
use App\Models\SeoSitePage;
use App\Services\SemanticGraph\Support\ObservedSemanticSupport;
use Ofyre\SeoEngine\Contracts\CannibalizationActionDecider;

class CannibalizationAnalyzer
{
    public function __construct(
        private readonly ObservedSemanticSupport $support,
        private readonly CannibalizationActionDecider $decider,
    ) {}

    /**
     * @return array<int,array<string,mixed>>
     */
    public function analyze(string $siteId): array
    {
        $pages = SeoSitePage::query()->where('site_id', $siteId)->get()->keyBy('normalized_url');

        $neighbors = SeoSemanticLink::query()
            ->where('site_id', $siteId)
            ->whereIn('relation_type', [
                'semantic_similarity_same_cluster',
                'semantic_similarity_same_intent',
                'observed_overlap',
            ])
            ->get();

        $queryRows = SeoSearchConsoleMetric::query()
            ->where('site_id', $siteId)
            ->whereNotNull('query')
            ->where('metric_date', '>=', now()->subDays(28)->toDateString())
            ->get()
            ->groupBy(fn (SeoSearchConsoleMetric $metric): string => $this->support->normalizeUrl((string) ($metric->url ?? '')));

        SeoSemanticLink::query()
            ->where('site_id', $siteId)
            ->where('relation_type', 'observed_cannibalization')
            ->delete();

        $risks = [];

        foreach ($neighbors as $neighbor) {
            $sourcePage = $pages->get((string) $neighbor->source_key);
            $targetPage = $pages->get((string) $neighbor->target_key);

            if (! $sourcePage || ! $targetPage || $neighbor->source_key >= $neighbor->target_key) {
                continue;
            }

            $sourceQueries = collect($queryRows->get($sourcePage->normalized_url, collect()))->pluck('query')->filter()->map('strval');
            $targetQueries = collect($queryRows->get($targetPage->normalized_url, collect()))->pluck('query')->filter()->map('strval');
            $queryOverlap = $sourceQueries->intersect($targetQueries)->unique()->values();
            $queryOverlapScore = $sourceQueries->isNotEmpty() || $targetQueries->isNotEmpty()
                ? round($queryOverlap->count() / max(1, $sourceQueries->merge($targetQueries)->unique()->count()), 4)
                : 0.0;

            $sameCluster = (bool) ($neighbor->meta_json['same_cluster'] ?? false);
            $sameIntent = (bool) ($neighbor->meta_json['same_intent'] ?? false);
            $score = round(min(1.0, (float) $neighbor->similarity_score + ($queryOverlapScore * 0.25)), 4);

            if (! ($sameCluster || $sameIntent || $queryOverlapScore >= 0.2) || $score < 0.84) {
                continue;
            }

            $sourceMetrics = collect($queryRows->get($sourcePage->normalized_url, collect()));
            $targetMetrics = collect($queryRows->get($targetPage->normalized_url, collect()));
            $sourceImpressions = (int) round((float) $sourceMetrics->sum('impressions'));
            $targetImpressions = (int) round((float) $targetMetrics->sum('impressions'));

            $recommendedAction = $this->decider->decide(
                clusterMatch: $sameCluster,
                intentMatch: $sameIntent,
                sourceIntent: (string) ($neighbor->meta_json['source_cluster'] ?? ''),
                targetIntent: (string) ($neighbor->meta_json['target_cluster'] ?? ''),
                sourceImpressions: $sourceImpressions,
                targetImpressions: $targetImpressions,
                rawScore: (float) $neighbor->similarity_score,
            );

            SeoSemanticLink::query()->create([
                'site_id' => $siteId,
                'relation_type' => 'observed_cannibalization',
                'source_key' => $sourcePage->normalized_url,
                'source_id' => $sourcePage->id,
                'target_key' => $targetPage->normalized_url,
                'target_id' => $targetPage->id,
                'label' => $this->support->pageLabel($targetPage),
                'url' => $targetPage->normalized_url,
                'reason' => $recommendedAction,
                'similarity_score' => $score,
                'meta_json' => [
                    'same_cluster' => $sameCluster,
                    'same_intent' => $sameIntent,
                    'query_overlap_score' => $queryOverlapScore,
                    'query_overlap' => $queryOverlap->take(10)->values()->all(),
                    'source_impressions' => $sourceImpressions,
                    'target_impressions' => $targetImpressions,
                    'recommended_action' => $recommendedAction,
                ],
            ]);

            $risks[] = [
                'source_id' => $sourcePage->id,
                'target_id' => $targetPage->id,
                'score' => $score,
                'action' => $recommendedAction,
            ];
        }

        return $risks;
    }
}
