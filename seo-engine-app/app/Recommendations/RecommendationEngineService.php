<?php

declare(strict_types=1);

namespace App\Recommendations;

use App\Models\SeoRecommendation;
use App\Models\SeoSite;
use App\Models\SeoStrategyItem;
use App\Understanding\SiteUnderstandingService;
use Illuminate\Support\Collection;

class RecommendationEngineService
{
    public function __construct(private readonly SiteUnderstandingService $understanding) {}

    /**
     * @return Collection<int,SeoRecommendation>
     */
    public function generate(SeoSite $site, bool $forceEmbeddings = false): Collection
    {
        $summary = $this->understanding->analyze($site, $forceEmbeddings);

        SeoRecommendation::query()->where('site_id', $site->site_id)->delete();
        SeoStrategyItem::query()->where('site_id', $site->site_id)->delete();

        $recommendations = collect()
            ->merge($this->fromOrphans($site->site_id, $summary['orphan_pages']))
            ->merge($this->fromWeakPages($site->site_id, $summary['weak_pages']))
            ->merge($this->fromOverlaps($site->site_id, $summary['overlaps']))
            ->merge($this->fromGaps($site->site_id, $summary['content_gaps']))
            ->sortBy('priority')
            ->values();

        $saved = $recommendations->map(function (array $item): SeoRecommendation {
            return SeoRecommendation::query()->create($item);
        });

        foreach ($saved as $recommendation) {
            SeoStrategyItem::query()->create([
                'site_id' => $recommendation->site_id,
                'priority' => min(99, max(1, (int) round($recommendation->priority / 10))),
                'type' => $recommendation->type,
                'title' => $recommendation->title,
                'description' => $recommendation->reasoning,
                'keywords_json' => array_values(array_filter([$recommendation->cluster])),
                'estimated_impact' => $recommendation->estimated_impact,
                'status' => $recommendation->status,
                'generated_at' => $recommendation->generated_at,
            ]);
        }

        return $saved;
    }

    /**
     * @param  array<int,array<string,mixed>>  $orphans
     * @return array<int,array<string,mixed>>
     */
    private function fromOrphans(string $siteId, array $orphans): array
    {
        return collect($orphans)->take(8)->map(fn (array $page): array => [
            'site_id' => $siteId,
            'site_page_id' => $page['id'],
            'site_crawl_id' => null,
            'type' => 'add_internal_links',
            'priority' => 10,
            'estimated_impact' => 'high',
            'difficulty' => 'low',
            'cluster' => null,
            'title' => 'Reconnect orphan page',
            'reasoning' => 'This observed page has no internal inlinks and is effectively isolated in the site graph.',
            'suggested_action' => 'Add contextual internal links from stronger cluster or pillar pages.',
            'status' => 'pending',
            'meta_json' => $page,
            'generated_at' => now(),
        ])->all();
    }

    /**
     * @param  array<int,array<string,mixed>>  $pages
     * @return array<int,array<string,mixed>>
     */
    private function fromWeakPages(string $siteId, array $pages): array
    {
        return collect($pages)->take(8)->map(fn (array $page): array => [
            'site_id' => $siteId,
            'site_page_id' => $page['id'],
            'site_crawl_id' => null,
            'type' => 'refresh_page',
            'priority' => 20,
            'estimated_impact' => 'medium',
            'difficulty' => 'medium',
            'cluster' => $page['cluster'] ?? null,
            'title' => 'Strengthen weak observed page',
            'reasoning' => 'The page is thin, weakly linked, or not clearly indexable based on the latest crawl snapshot.',
            'suggested_action' => 'Improve coverage depth, strengthen headings, and fix indexability or internal links.',
            'status' => 'pending',
            'meta_json' => $page,
            'generated_at' => now(),
        ])->all();
    }

    /**
     * @param  array<int,array<string,mixed>>  $pairs
     * @return array<int,array<string,mixed>>
     */
    private function fromOverlaps(string $siteId, array $pairs): array
    {
        return collect($pairs)->take(8)->map(fn (array $pair): array => [
            'site_id' => $siteId,
            'site_page_id' => $pair['source_id'],
            'site_crawl_id' => null,
            'type' => 'differentiate_intent',
            'priority' => 30,
            'estimated_impact' => 'high',
            'difficulty' => 'medium',
            'cluster' => null,
            'title' => 'Resolve semantic overlap',
            'reasoning' => 'Two observed pages are semantically very close and likely compete for the same intent.',
            'suggested_action' => 'Differentiate intent, merge if redundant, or strengthen one page as the canonical pillar.',
            'status' => 'pending',
            'meta_json' => $pair,
            'generated_at' => now(),
        ])->all();
    }

    /**
     * @param  array<int,array<string,mixed>>  $gaps
     * @return array<int,array<string,mixed>>
     */
    private function fromGaps(string $siteId, array $gaps): array
    {
        return collect($gaps)->take(8)->map(fn (array $gap): array => [
            'site_id' => $siteId,
            'site_page_id' => null,
            'site_crawl_id' => null,
            'type' => 'create_page',
            'priority' => 40,
            'estimated_impact' => 'high',
            'difficulty' => 'medium',
            'cluster' => $gap['cluster'] ?? null,
            'title' => 'Expand undercovered cluster',
            'reasoning' => 'The crawl shows a shallow or weak cluster that lacks sufficient supporting coverage.',
            'suggested_action' => 'Create supporting pages, enrich the cluster, and reinforce links to the pillar page.',
            'status' => 'pending',
            'meta_json' => $gap,
            'generated_at' => now(),
        ])->all();
    }
}
