<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\SeoPage;
use App\Models\SeoSiteSnapshot;

class SiteHealthService
{
    public function calculate(string $siteId): array
    {
        $pages = SeoPage::query()->where('site_id', $siteId)->get();
        $total = $pages->count();

        if ($total === 0) {
            return $this->empty();
        }

        $avgSeo         = (float) ($pages->avg('seo_score') ?? 0);
        $avgQuality     = (float) ($pages->avg('quality_score') ?? 0);
        $avgTopical     = (float) ($pages->avg('topical_score') ?? 0);
        $avgIndexable   = (float) ($pages->avg('indexability_score') ?? 0);
        $publishedRate  = $pages->where('status', 'published')->count() / $total * 100;
        $errorRate      = $pages->where('status', 'error')->count() / $total * 100;

        $score = round(
            $avgSeo * 0.30
            + $avgQuality * 0.25
            + $avgTopical * 0.20
            + $publishedRate * 0.15
            + $avgIndexable * 0.10
            - $errorRate * 0.5
        );

        $score = max(0, min(100, $score));

        return [
            'score'          => $score,
            'grade'          => $this->grade($score),
            'color'          => $this->color($score),
            'total_pages'    => $total,
            'published'      => $pages->where('status', 'published')->count(),
            'draft'          => $pages->where('status', 'draft')->count(),
            'errors'         => $pages->where('status', 'error')->count(),
            'breakdown'      => [
                'seo'           => round($avgSeo),
                'quality'       => round($avgQuality),
                'topical'       => round($avgTopical),
                'indexability'  => round($avgIndexable),
                'published_pct' => round($publishedRate),
            ],
            'clusters'       => $pages->groupBy('cluster')->map->count()->sortDesc()->take(10)->toArray(),
            'score_dist'     => $this->scoreDist($pages),
        ];
    }

    public function snapshot(string $siteId): void
    {
        $health = $this->calculate($siteId);
        $today  = now()->toDateString();

        SeoSiteSnapshot::query()->updateOrCreate(
            ['site_id' => $siteId, 'snapshot_date' => $today],
            [
                'health_score'      => $health['score'],
                'page_count'        => $health['total_pages'],
                'published_count'   => $health['published'],
                'avg_seo_score'     => $health['breakdown']['seo'],
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

    private function grade(int $score): string
    {
        return match(true) {
            $score >= 85 => 'A',
            $score >= 70 => 'B',
            $score >= 55 => 'C',
            $score >= 40 => 'D',
            default      => 'F',
        };
    }

    private function color(int $score): string
    {
        return match(true) {
            $score >= 70 => '#16a34a',
            $score >= 50 => '#d97706',
            default      => '#dc2626',
        };
    }

    private function empty(): array
    {
        return [
            'score' => 0, 'grade' => 'F', 'color' => '#dc2626',
            'total_pages' => 0, 'published' => 0, 'draft' => 0, 'errors' => 0,
            'breakdown' => ['seo' => 0, 'quality' => 0, 'topical' => 0, 'indexability' => 0, 'published_pct' => 0],
            'clusters' => [], 'score_dist' => [],
        ];
    }

    private function scoreDist($pages): array
    {
        $buckets = ['0-20' => 0, '21-40' => 0, '41-60' => 0, '61-80' => 0, '81-100' => 0];
        foreach ($pages as $page) {
            $s = (float) ($page->seo_score ?? 0);
            match(true) {
                $s <= 20  => $buckets['0-20']++,
                $s <= 40  => $buckets['21-40']++,
                $s <= 60  => $buckets['41-60']++,
                $s <= 80  => $buckets['61-80']++,
                default   => $buckets['81-100']++,
            };
        }
        return $buckets;
    }
}
