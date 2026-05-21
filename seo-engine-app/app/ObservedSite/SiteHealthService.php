<?php

declare(strict_types=1);

namespace App\ObservedSite;

use App\Models\SeoPage;
use App\Models\SeoSitePage;
use App\Models\SeoSiteSnapshot;
use Illuminate\Support\Collection;

class SiteHealthService
{
    public function calculate(string $siteId): array
    {
        $observedPages = SeoSitePage::query()->where('site_id', $siteId)->get();

        if ($observedPages->isNotEmpty()) {
            return $this->calculateObserved($observedPages);
        }

        $legacyPages = SeoPage::query()->where('site_id', $siteId)->get();

        if ($legacyPages->isNotEmpty()) {
            return $this->calculateLegacy($legacyPages);
        }

        return $this->empty();
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

        $pageScores = $pages->map(fn (SeoSitePage $page): array => $this->observedScoresForPage($page));

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

    private function calculateLegacy(Collection $pages): array
    {
        $total = $pages->count();

        $avgSeo = (float) ($pages->avg('seo_score') ?? 0);
        $avgQuality = (float) ($pages->avg('quality_score') ?? 0);
        $avgTopical = (float) ($pages->avg('topical_score') ?? 0);
        $avgIndexable = (float) ($pages->avg('indexability_score') ?? 0);
        $publishedRate = $pages->where('status', 'published')->count() / $total * 100;
        $errorRate = $pages->where('status', 'error')->count() / $total * 100;

        $score = (int) round(
            $avgSeo * 0.30
            + $avgQuality * 0.25
            + $avgTopical * 0.20
            + $publishedRate * 0.15
            + $avgIndexable * 0.10
            - $errorRate * 0.5
        );

        $score = max(0, min(100, $score));

        return [
            'score' => $score,
            'grade' => $this->grade($score),
            'color' => $this->color($score),
            'total_pages' => $total,
            'published' => $pages->where('status', 'published')->count(),
            'draft' => $pages->where('status', 'draft')->count(),
            'errors' => $pages->where('status', 'error')->count(),
            'breakdown' => [
                'seo' => (int) round($avgSeo),
                'quality' => (int) round($avgQuality),
                'topical' => (int) round($avgTopical),
                'indexability' => (int) round($avgIndexable),
                'published_pct' => (int) round($publishedRate),
            ],
            'clusters' => $pages->groupBy('cluster')->map->count()->sortDesc()->take(10)->toArray(),
            'score_dist' => $this->scoreDist($pages->pluck('seo_score')->all()),
        ];
    }

    private function observedScoresForPage(SeoSitePage $page): array
    {
        $statusCode = (int) ($page->last_status_code ?? 0);
        $wordCount = (int) ($page->latest_word_count ?? 0);
        $authority = $this->clampPercent($page->authority_score * 100);
        $orphanPenalty = $this->clampPercent($page->orphan_score * 100);
        $overlapPenalty = $this->clampPercent($page->overlap_score * 100);
        $pillar = $this->clampPercent($page->pillar_likelihood * 100);
        $isHealthyStatus = $statusCode >= 200 && $statusCode < 400;
        $isIndexable = str_contains((string) $page->indexability_state, 'index');

        $seo = 0;
        $seo += filled($page->title) ? 25 : 0;
        $seo += filled($page->meta_description) ? 20 : 0;
        $seo += filled($page->canonical_url) ? 10 : 0;
        $seo += $isHealthyStatus ? 10 : 0;
        $seo += match (true) {
            $wordCount >= 1200 => 35,
            $wordCount >= 700 => 28,
            $wordCount >= 350 => 18,
            $wordCount > 0 => 10,
            default => 0,
        };

        $quality = 0;
        $quality += match (true) {
            $wordCount >= 1500 => 45,
            $wordCount >= 900 => 36,
            $wordCount >= 500 => 24,
            $wordCount > 0 => 12,
            default => 0,
        };
        $quality += filled($page->title) ? 15 : 0;
        $quality += filled($page->meta_description) ? 10 : 0;
        $quality += $isHealthyStatus ? 10 : 0;
        $quality += (int) round($authority * 0.20);
        $quality += (int) round((100 - $overlapPenalty) * 0.20);

        $topical = (int) round(
            $authority * 0.45
            + $pillar * 0.20
            + (100 - $orphanPenalty) * 0.20
            + (100 - $overlapPenalty) * 0.15
        );

        $indexability = 0;
        $indexability += $isHealthyStatus ? 60 : 0;
        $indexability += $isIndexable ? 25 : 0;
        $indexability += filled($page->canonical_url) ? 15 : 0;

        return [
            'seo' => max(0, min(100, $seo)),
            'quality' => max(0, min(100, $quality)),
            'topical' => max(0, min(100, $topical)),
            'indexability' => max(0, min(100, $indexability)),
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

    private function clampPercent(float $value): float
    {
        return max(0, min(100, $value));
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
