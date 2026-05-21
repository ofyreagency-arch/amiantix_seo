<?php

declare(strict_types=1);

namespace App\Services\SemanticGraph\Analyzers;

use App\Models\SeoSitePage;
use App\Models\SeoSitePageSnapshot;
use Illuminate\Support\Str;

class ClusterAnalyzer
{
    /**
     * @return array<int,array<string,mixed>>
     */
    public function analyze(string $siteId): array
    {
        $pages = SeoSitePage::query()
            ->where('site_id', $siteId)
            ->whereNotNull('last_snapshot_id')
            ->get();

        $snapshots = SeoSitePageSnapshot::query()
            ->where('site_id', $siteId)
            ->whereIn('id', $pages->pluck('last_snapshot_id')->filter()->all())
            ->get()
            ->keyBy('id');

        foreach ($pages as $page) {
            $snapshot = $snapshots->get($page->last_snapshot_id);
            $cluster = $this->inferCluster($page, $snapshot);
            if ($cluster !== '' && $page->cluster_label !== $cluster) {
                $page->forceFill(['cluster_label' => $cluster])->save();
            }
        }

        return SeoSitePage::query()
            ->where('site_id', $siteId)
            ->selectRaw('cluster_label, COUNT(*) as aggregate_count')
            ->whereNotNull('cluster_label')
            ->groupBy('cluster_label')
            ->orderByDesc('aggregate_count')
            ->get()
            ->map(fn (SeoSitePage $page): array => [
                'cluster' => (string) $page->cluster_label,
                'page_count' => (int) $page->aggregate_count,
            ])
            ->all();
    }

    private function inferCluster(SeoSitePage $page, ?SeoSitePageSnapshot $snapshot): string
    {
        $source = implode(' ', array_filter([
            $page->path,
            $page->title,
            $page->primary_h1,
            implode(' ', $snapshot?->h2_json ?? []),
        ]));

        $tokens = collect(preg_split('/[^a-z0-9]+/i', Str::lower(Str::ascii($source))) ?: [])
            ->map(static fn (string $token): string => trim($token))
            ->filter(fn (string $token): bool => strlen($token) >= 4)
            ->reject(fn (string $token): bool => in_array($token, ['https', 'html', 'page', 'avec', 'pour', 'dans', 'site', 'from', 'that', 'your'], true))
            ->values();

        if ($tokens->isEmpty()) {
            return 'general';
        }

        $cluster = (string) $tokens->countBy()->sortDesc()->keys()->first();

        return $cluster !== '' ? $cluster : 'general';
    }
}
