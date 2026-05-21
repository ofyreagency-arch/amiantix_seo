<?php

declare(strict_types=1);

namespace App\ObservedSite;

use App\Models\SeoSitePage;
use App\Models\SeoSiteSnapshot;
use Illuminate\Support\Collection;

class SiteHealthService
{
    public function __construct(
        private readonly ObservedPageHealthService $pageHealth,
    ) {}

    public function calculate(string $siteId): array
    {
        $observedPages = SeoSitePage::query()->where('site_id', $siteId)->get();

        return $observedPages->isNotEmpty()
            ? $this->calculateObserved($observedPages)
            : $this->empty();
    }

    public function snapshot(string $siteId): void
    {
        $health = $this->calculate($siteId);
        $today = now()->toDateString();

        SeoSiteSnapshot::query()->updateOrCreate(
            ['site_id' => $siteId, 'snapshot_date' => $today],
            [
                'health_score' => $health['score'],
                'page_count' => $health['total_pages'],
                'published_count' => $health['published'],
                'avg_seo_score' => $health['breakdown']['seo'],
                'avg_quality_score' => $health['breakdown']['quality'],
                'avg_topical_score' => $health['breakdown']['topical'],
            ]
        );
    }

    public function history(string $siteId, int $days = 30): array
    {
        return SeoSiteSnapshot::query()
            ->where('site_id', $siteId)
            ->where('snapshot_date', '>=', now()->subDays($days)->toDateString())
            ->orderBy('snapshot_date')
            ->get(['snapshot_date', 'health_score', 'avg_seo_score', 'avg_quality_score', 'page_count'])
            ->toArray();
    }

    private function calculateObserved(Collection $pages): array
    {
        $total = $pages->count();
        $published = $pages->filter(fn (SeoSitePage $page): bool => $this->isObservedPublished($page))->count();
        $errors = $pages->filter(fn (SeoSitePage $page): bool => $this->isObservedError($page))->count();
        $draft = max(0, $total - $published - $errors);

        $pageScores = $pages->map(fn (SeoSitePage $page): array => $this->pageHealth->forPage($page));

        $avgSeo = (float) round($pageScores->avg('seo') ?? 0, 2);
        $avgQuality = (float) round($pageScores->avg('quality') ?? 0, 2);
        $avgTopical = (float) round($pageScores->avg('topical') ?? 0, 2);
        $avgIndexability = (float) round($pageScores->avg('indexability') ?? 0, 2);
        $publishedRate = $total > 0 ? $published / $total * 100 : 0;
        $errorRate = $total > 0 ? $errors / $total * 100 : 0;

        $score = (int) round(
            $avgSeo * 0.30
            + $avgQuality * 0.25
            + $avgTopical * 0.20
            + $avgIndexability * 0.15
            + $publishedRate * 0.10
            - $errorRate * 0.25
        );

        $score = max(0, min(100, $score));

        return [
            'score' => $score,
            'grade' => $this->grade($score),
            'color' => $this->color($score),
            'total_pages' => $total,
            'published' => $published,
            'draft' => $draft,
            'errors' => $errors,
            'breakdown' => [
                'seo' => (int) round($avgSeo),
                'quality' => (int) round($avgQuality),
                'topical' => (int) round($avgTopical),
                'indexability' => (int) round($avgIndexability),
                'published_pct' => (int) round($publishedRate),
            ],
            'clusters' => $pages
                ->filter(fn (SeoSitePage $page): bool => filled($page->cluster_label))
                ->groupBy('cluster_label')
                ->map->count()
                ->sortDesc()
                ->take(10)
                ->toArray(),
            'score_dist' => $this->scoreDist($pageScores->pluck('seo')->all()),
        ];
    }

    private function isObservedPublished(SeoSitePage $page): bool
    {
        $statusCode = (int) ($page->last_status_code ?? 0);

        return $statusCode >= 200 && $statusCode < 400;
    }

    private function isObservedError(SeoSitePage $page): bool
    {
        $statusCode = (int) ($page->last_status_code ?? 0);

        return $statusCode >= 400;
    }

    private function grade(int $score): string
    {
        return match (true) {
            $score >= 85 => 'A',
            $score >= 70 => 'B',
            $score >= 55 => 'C',
            $score >= 40 => 'D',
            default => 'F',
        };
    }

    private function color(int $score): string
    {
        return match (true) {
            $score >= 70 => '#16a34a',
            $score >= 50 => '#d97706',
            default => '#dc2626',
        };
    }

    private function empty(): array
    {
        return [
            'score' => 0,
            'grade' => 'F',
            'color' => '#dc2626',
            'total_pages' => 0,
            'published' => 0,
            'draft' => 0,
            'errors' => 0,
            'breakdown' => [
                'seo' => 0,
                'quality' => 0,
                'topical' => 0,
                'indexability' => 0,
                'published_pct' => 0,
            ],
            'clusters' => [],
            'score_dist' => [],
        ];
    }

    /**
     * @param  array<int,int|float>  $scores
     * @return array<string,int>
     */
    private function scoreDist(array $scores): array
    {
        $buckets = ['0-20' => 0, '21-40' => 0, '41-60' => 0, '61-80' => 0, '81-100' => 0];

        foreach ($scores as $score) {
            $s = (float) $score;

            match (true) {
                $s <= 20 => $buckets['0-20']++,
                $s <= 40 => $buckets['21-40']++,
                $s <= 60 => $buckets['41-60']++,
                $s <= 80 => $buckets['61-80']++,
                default => $buckets['81-100']++,
            };
        }

        return $buckets;
    }
}
