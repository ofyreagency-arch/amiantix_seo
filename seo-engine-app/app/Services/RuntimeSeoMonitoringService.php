<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\SeoPage;
use App\Models\SeoSearchConsoleMetric;
use Ofyre\SeoEngine\Services\Monitoring\SeoMonitoringService;

class RuntimeSeoMonitoringService extends SeoMonitoringService
{
    protected function candidatePages(array $prioritizedIds): iterable
    {
        $query = SeoPage::query();

        if ($prioritizedIds !== []) {
            $query->whereIn('id', $prioritizedIds);
        }

        return $query->orderBy('seo_score')->get();
    }

    protected function agedPublishedPages(int $days): iterable
    {
        return SeoPage::query()
            ->published()
            ->where(function ($query) use ($days): void {
                $query->whereNull('last_audit_at')
                    ->orWhere('last_audit_at', '<', now()->subDays($days));
            })
            ->get();
    }

    protected function markIndexed(object $page, bool $indexed): void
    {
        if ($page instanceof SeoPage) {
            $page->forceFill([
                'is_indexed' => $indexed,
            ])->save();
        }
    }

    protected function persistSearchConsoleHistory(object $page, array $metrics, array $audit): void
    {
        if (! $page instanceof SeoPage) {
            return;
        }

        SeoSearchConsoleMetric::query()->create([
            'seo_page_id' => $page->id,
            'metric_date' => now()->toDateString(),
            'window_days' => 30,
            'query' => null,
            'url' => rtrim((string) config('app.url'), '/').$page->canonicalPath(),
            'clicks' => (float) ($metrics['ctr'] ?? 0) * (float) ($metrics['impressions'] ?? 0),
            'impressions' => (float) ($metrics['impressions'] ?? 0),
            'ctr' => (float) ($metrics['ctr'] ?? 0),
            'position' => (float) ($metrics['position'] ?? 0),
            'is_indexed' => $metrics['indexed'] ?? null,
            'coverage_json' => $metrics['coverage'] ?? [],
            'payload_json' => [
                'queries' => $metrics['queries'] ?? [],
                'audit_score' => $audit['score'] ?? null,
            ],
        ]);
    }

    protected function persistMonitoringState(object $page, array $metrics, array $audit): void
    {
        if (! $page instanceof SeoPage) {
            return;
        }

        $page->forceFill([
            'duplicate_risk_score' => in_array('duplicate_risk_high', $audit['issues'] ?? [], true) ? 80 : (in_array('duplicate_risk_medium', $audit['issues'] ?? [], true) ? 50 : 0),
            'last_audit_at' => now(),
            'is_indexed' => $metrics['indexed'] ?? $page->is_indexed,
        ])->save();
    }
}
