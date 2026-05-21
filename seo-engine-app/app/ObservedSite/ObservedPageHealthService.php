<?php

declare(strict_types=1);

namespace App\ObservedSite;

use App\Models\SeoSitePage;

final class ObservedPageHealthService
{
    /**
     * @return array{
     *   seo:int,
     *   quality:int,
     *   topical:int,
     *   indexability:int,
     *   health_score:int,
     *   flags:array<int,string>
     * }
     */
    public function forPage(SeoSitePage $page): array
    {
        $seo = $this->seoScore($page);
        $quality = $this->qualityScore($page);
        $topical = $this->topicalScore($page);
        $indexability = $this->indexabilityScore($page);
        $flags = $this->flagsFor($page);

        $healthScore = $this->clamp(
            ($seo * 0.30)
            + ($quality * 0.25)
            + ($topical * 0.20)
            + ($indexability * 0.25)
        );

        return [
            'seo' => $seo,
            'quality' => $quality,
            'topical' => $topical,
            'indexability' => $indexability,
            'health_score' => $healthScore,
            'flags' => $flags,
        ];
    }

    private function seoScore(SeoSitePage $page): int
    {
        $wordCount = $this->wordCount($page);

        $score = 0;
        $score += filled($page->title) ? 25 : 0;
        $score += filled($page->meta_description) ? 20 : 0;
        $score += filled($page->canonical_url) ? 10 : 0;
        $score += $this->isHealthyStatus($page) ? 10 : 0;
        $score += match (true) {
            $wordCount >= 1200 => 35,
            $wordCount >= 700 => 28,
            $wordCount >= 350 => 18,
            $wordCount > 0 => 10,
            default => 0,
        };

        return $this->clamp($score);
    }

    private function qualityScore(SeoSitePage $page): int
    {
        $wordCount = $this->wordCount($page);
        $authority = $this->percent($page->authority_score);
        $overlapPenalty = $this->percent($page->overlap_score);

        $score = 0;
        $score += match (true) {
            $wordCount >= 1500 => 45,
            $wordCount >= 900 => 36,
            $wordCount >= 500 => 24,
            $wordCount > 0 => 12,
            default => 0,
        };
        $score += filled($page->title) ? 15 : 0;
        $score += filled($page->meta_description) ? 10 : 0;
        $score += $this->isHealthyStatus($page) ? 10 : 0;
        $score += (int) round($authority * 0.20);
        $score += (int) round((100 - $overlapPenalty) * 0.20);

        return $this->clamp($score);
    }

    private function topicalScore(SeoSitePage $page): int
    {
        $authority = $this->percent($page->authority_score);
        $orphanPenalty = $this->percent($page->orphan_score);
        $overlapPenalty = $this->percent($page->overlap_score);
        $pillar = $this->percent($page->pillar_likelihood);

        $score = ($authority * 0.45)
            + ($pillar * 0.20)
            + ((100 - $orphanPenalty) * 0.20)
            + ((100 - $overlapPenalty) * 0.15);

        if (filled($page->cluster_label)) {
            $score += 5;
        }

        if (filled($page->primary_h1)) {
            $score += 5;
        }

        return $this->clamp($score);
    }

    private function indexabilityScore(SeoSitePage $page): int
    {
        $score = 0;
        $score += $this->isHealthyStatus($page) ? 60 : 0;
        $score += $this->isIndexable($page) ? 25 : 0;
        $score += filled($page->canonical_url) ? 15 : 0;

        return $this->clamp($score);
    }

    /**
     * @return array<int,string>
     */
    private function flagsFor(SeoSitePage $page): array
    {
        $flags = [];

        if (! filled($page->title)) {
            $flags[] = 'missing_title';
        }

        if (! filled($page->meta_description)) {
            $flags[] = 'missing_meta_description';
        }

        if (! filled($page->cluster_label)) {
            $flags[] = 'missing_cluster_signal';
        }

        if ((float) $page->authority_score < 0.20) {
            $flags[] = 'low_authority';
        }

        if ((float) $page->orphan_score >= 0.75) {
            $flags[] = 'orphan_high';
        }

        if ((float) $page->overlap_score >= 0.75) {
            $flags[] = 'overlap_high';
        }

        if (! $this->isIndexable($page)) {
            $flags[] = 'non_indexable';
        }

        if (! $this->isHealthyStatus($page)) {
            $flags[] = 'unhealthy_status';
        }

        return $flags;
    }

    private function wordCount(SeoSitePage $page): int
    {
        return max(0, (int) ($page->latest_word_count ?? 0));
    }

    private function isHealthyStatus(SeoSitePage $page): bool
    {
        $statusCode = (int) ($page->last_status_code ?? 0);

        return $statusCode >= 200 && $statusCode < 400;
    }

    private function isIndexable(SeoSitePage $page): bool
    {
        return (string) ($page->indexability_state ?? 'unknown') === 'indexable';
    }

    private function percent(float $value): float
    {
        return max(0, min(100, $value * 100));
    }

    private function clamp(int|float $value): int
    {
        return max(0, min(100, (int) round($value)));
    }
}
