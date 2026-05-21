<?php

declare(strict_types=1);

namespace App\Recommendations;

use App\Models\SeoRecommendation;
use App\Models\SeoSite;
use App\Models\SeoSitePage;
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

        $recommendations = $this->deduplicate(
            collect()
                ->merge($this->fromOrphans($site->site_id, $summary['orphan_pages']))
                ->merge($this->fromWeakPages($site->site_id, $summary['weak_pages']))
                ->merge($this->fromOverlaps($site->site_id, $summary['overlaps']))
                ->merge($this->fromGaps($site->site_id, $summary['content_gaps']))
        )
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
                'keywords_json' => $this->strategyKeywords($recommendation),
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
        return collect($orphans)->take(8)->map(function (array $page) use ($siteId): array {
            $label = $this->pageLabel($page['title'] ?? null, $page['url'] ?? null);
            $orphanScore = (int) round(((float) ($page['orphan_score'] ?? 0)) * 100);
            $inlinks = (int) ($page['inlinks'] ?? 0);

            return [
                'site_id' => $siteId,
                'site_page_id' => $page['id'],
                'site_crawl_id' => null,
                'type' => 'add_internal_links',
                'priority' => 10,
                'estimated_impact' => 'high',
                'difficulty' => 'low',
                'cluster' => $page['cluster'] ?? null,
                'title' => sprintf('Reconnect orphan page: %s', $label),
                'reasoning' => sprintf(
                    '%s is isolated in the observed graph with %d internal inlinks and an orphan score of %d%%.',
                    $label,
                    $inlinks,
                    $orphanScore
                ),
                'suggested_action' => 'Add contextual internal links from stronger cluster or pillar pages.',
                'status' => 'pending',
                'meta_json' => array_merge($page, [
                    'context_label' => $label,
                ]),
                'generated_at' => now(),
            ];
        })->all();
    }

    /**
     * @param  array<int,array<string,mixed>>  $pages
     * @return array<int,array<string,mixed>>
     */
    private function fromWeakPages(string $siteId, array $pages): array
    {
        return collect($pages)->take(8)->map(function (array $page) use ($siteId): array {
            $label = $this->pageLabel($page['title'] ?? null, $page['url'] ?? null);
            $reasons = [];

            if (((int) ($page['word_count'] ?? 0)) < 300) {
                $reasons[] = sprintf('%d words only', (int) ($page['word_count'] ?? 0));
            }

            if (((float) ($page['authority_score'] ?? 0)) < 0.20) {
                $reasons[] = sprintf('authority %.2f', (float) ($page['authority_score'] ?? 0));
            }

            if (($page['indexability_state'] ?? 'indexable') !== 'indexable') {
                $reasons[] = (string) ($page['indexability_state'] ?? 'non-indexable');
            }

            if (($page['missing_h1'] ?? false) === true) {
                $reasons[] = 'missing H1';
            }

            return [
                'site_id' => $siteId,
                'site_page_id' => $page['id'],
                'site_crawl_id' => null,
                'type' => 'refresh_page',
                'priority' => 20,
                'estimated_impact' => 'medium',
                'difficulty' => 'medium',
                'cluster' => $page['cluster'] ?? null,
                'title' => sprintf('Strengthen weak page: %s', $label),
                'reasoning' => sprintf(
                    '%s is underperforming in the observed crawl%s.',
                    $label,
                    $reasons !== [] ? ' because of '.implode(', ', $reasons) : ''
                ),
                'suggested_action' => 'Improve coverage depth, strengthen headings, and fix indexability or internal links.',
                'status' => 'pending',
                'meta_json' => array_merge($page, [
                    'context_label' => $label,
                    'reasons' => $reasons,
                ]),
                'generated_at' => now(),
            ];
        })->all();
    }

    /**
     * @param  array<int,array<string,mixed>>  $pairs
     * @return array<int,array<string,mixed>>
     */
    private function fromOverlaps(string $siteId, array $pairs): array
    {
        $pageContexts = $this->sitePageContexts(
            $siteId,
            collect($pairs)
                ->flatMap(fn (array $pair): array => [(int) ($pair['source_id'] ?? 0), (int) ($pair['target_id'] ?? 0)])
                ->filter(fn (int $id): bool => $id > 0)
                ->unique()
                ->values()
                ->all()
        );

        return collect($pairs)->take(8)->map(function (array $pair) use ($siteId, $pageContexts): array {
            $sourceId = (int) ($pair['source_id'] ?? 0);
            $targetId = (int) ($pair['target_id'] ?? 0);
            $source = $pageContexts[$sourceId] ?? ['label' => 'Page '.$sourceId, 'url' => $pair['source_url'] ?? null];
            $target = $pageContexts[$targetId] ?? ['label' => (string) ($pair['label'] ?? 'Page '.$targetId), 'url' => $pair['target_url'] ?? ($pair['url'] ?? null)];
            $score = (float) ($pair['similarity_score'] ?? $pair['score'] ?? 0);

            return [
                'site_id' => $siteId,
                'site_page_id' => $sourceId,
                'site_crawl_id' => null,
                'type' => 'differentiate_intent',
                'priority' => 30,
                'estimated_impact' => 'high',
                'difficulty' => 'medium',
                'cluster' => $pair['cluster'] ?? null,
                'title' => sprintf('Resolve overlap: %s vs %s', $source['label'], $target['label']),
                'reasoning' => sprintf(
                    '%s and %s are semantically very close (similarity %.2f) and likely compete for the same intent.',
                    $source['label'],
                    $target['label'],
                    $score
                ),
                'suggested_action' => 'Differentiate intent, merge if redundant, or strengthen one page as the canonical pillar.',
                'status' => 'pending',
                'meta_json' => array_merge($pair, [
                    'source_label' => $source['label'],
                    'source_url' => $source['url'],
                    'target_label' => $target['label'],
                    'target_url' => $target['url'],
                ]),
                'generated_at' => now(),
            ];
        })->all();
    }

    /**
     * @param  array<int,array<string,mixed>>  $gaps
     * @return array<int,array<string,mixed>>
     */
    private function fromGaps(string $siteId, array $gaps): array
    {
        return collect($gaps)->take(8)->map(function (array $gap) use ($siteId): array {
            $cluster = (string) ($gap['cluster'] ?? 'untitled-cluster');

            return [
                'site_id' => $siteId,
                'site_page_id' => null,
                'site_crawl_id' => null,
                'type' => 'create_page',
                'priority' => 40,
                'estimated_impact' => 'high',
                'difficulty' => 'medium',
                'cluster' => $cluster,
                'title' => sprintf('Expand cluster: %s', $cluster),
                'reasoning' => sprintf(
                    'Cluster %s is undercovered with %d page(s), %d average words, and %.2f average authority.',
                    $cluster,
                    (int) ($gap['page_count'] ?? 0),
                    (int) ($gap['avg_word_count'] ?? 0),
                    (float) ($gap['avg_authority'] ?? 0)
                ),
                'suggested_action' => 'Create supporting pages, enrich the cluster, and reinforce links to the pillar page.',
                'status' => 'pending',
                'meta_json' => $gap,
                'generated_at' => now(),
            ];
        })->all();
    }

    /**
     * @param  Collection<int,array<string,mixed>>  $recommendations
     * @return Collection<int,array<string,mixed>>
     */
    private function deduplicate(Collection $recommendations): Collection
    {
        return $recommendations
            ->unique(fn (array $item): string => $this->recommendationSignature($item))
            ->values();
    }

    /**
     * @param  array<string,mixed>  $item
     */
    private function recommendationSignature(array $item): string
    {
        return match ($item['type'] ?? null) {
            'add_internal_links', 'refresh_page' => sprintf('%s:%s', (string) $item['type'], (string) ($item['site_page_id'] ?? '')),
            'differentiate_intent' => sprintf(
                '%s:%s',
                (string) $item['type'],
                implode(':', collect([
                    (int) ($item['meta_json']['source_id'] ?? $item['site_page_id'] ?? 0),
                    (int) ($item['meta_json']['target_id'] ?? 0),
                ])->sort()->values()->all())
            ),
            'create_page' => sprintf('%s:%s', (string) $item['type'], (string) ($item['cluster'] ?? $item['meta_json']['cluster'] ?? '')),
            default => md5((string) json_encode($item)),
        };
    }

    private function pageLabel(?string $title, ?string $url): string
    {
        $title = trim((string) $title);

        if ($title !== '') {
            return $title;
        }

        $path = trim((string) parse_url((string) $url, PHP_URL_PATH), '/');

        return $path !== '' ? $path : ((string) $url !== '' ? (string) $url : 'Untitled page');
    }

    /**
     * @param  array<int,int>  $pageIds
     * @return array<int,array{label:string,url:?string}>
     */
    private function sitePageContexts(string $siteId, array $pageIds): array
    {
        return SeoSitePage::query()
            ->where('site_id', $siteId)
            ->whereIn('id', $pageIds)
            ->get(['id', 'title', 'normalized_url', 'path'])
            ->mapWithKeys(fn (SeoSitePage $page): array => [
                $page->id => [
                    'label' => $this->pageLabel($page->title, $page->normalized_url),
                    'url' => $page->normalized_url,
                ],
            ])
            ->all();
    }

    /**
     * @return array<int,string>
     */
    private function strategyKeywords(SeoRecommendation $recommendation): array
    {
        $meta = is_array($recommendation->meta_json) ? $recommendation->meta_json : [];
        $keywords = [];

        foreach ([
            $meta['context_label'] ?? null,
            $meta['source_label'] ?? null,
            $meta['target_label'] ?? null,
            $recommendation->cluster,
            $meta['indexability_state'] ?? null,
            $meta['reason'] ?? null,
        ] as $value) {
            $value = trim((string) $value);

            if ($value !== '') {
                $keywords[] = $value;
            }
        }

        return array_values(array_unique(array_slice($keywords, 0, 5)));
    }
}
