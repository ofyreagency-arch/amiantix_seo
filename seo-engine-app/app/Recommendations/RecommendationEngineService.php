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
    public function __construct(
        private readonly SiteUnderstandingService $understanding,
        private readonly PageClassifierService $classifier,
        private readonly BusinessIntentService $businessIntent,
        private readonly RecommendationEligibilityService $eligibility,
        private readonly RecommendationScoringService $scoring,
        private readonly ImpactEstimatorService $impactEstimator,
    ) {}

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
            ->filter()
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
        return collect($orphans)->take(8)->map(function (array $page) use ($siteId): ?array {
            $label = $this->pageLabel($page['title'] ?? null, $page['url'] ?? null);
            $orphanScore = (int) round(((float) ($page['orphan_score'] ?? 0)) * 100);
            $inlinks = (int) ($page['inlinks'] ?? 0);

            return $this->finalizeRecommendation([
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
                    'orphan_score' => (float) ($page['orphan_score'] ?? 0),
                ]),
                'generated_at' => now(),
            ], $page);
        })->filter()->values()->all();
    }

    /**
     * @param  array<int,array<string,mixed>>  $pages
     * @return array<int,array<string,mixed>>
     */
    private function fromWeakPages(string $siteId, array $pages): array
    {
        return collect($pages)->take(8)->map(function (array $page) use ($siteId): ?array {
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

            return $this->finalizeRecommendation([
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
            ], $page);
        })->filter()->values()->all();
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

        return collect($pairs)->take(8)->map(function (array $pair) use ($siteId, $pageContexts): ?array {
            $sourceId = (int) ($pair['source_id'] ?? 0);
            $targetId = (int) ($pair['target_id'] ?? 0);
            $source = $pageContexts[$sourceId] ?? ['label' => 'Page '.$sourceId, 'url' => $pair['source_url'] ?? null];
            $target = $pageContexts[$targetId] ?? ['label' => (string) ($pair['label'] ?? 'Page '.$targetId), 'url' => $pair['target_url'] ?? ($pair['url'] ?? null)];
            $score = (float) ($pair['similarity_score'] ?? $pair['score'] ?? 0);

            return $this->finalizeRecommendation([
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
                    'similarity_score' => $score,
                ]),
                'generated_at' => now(),
            ], $source, $target);
        })->filter()->values()->all();
    }

    /**
     * @param  array<int,array<string,mixed>>  $gaps
     * @return array<int,array<string,mixed>>
     */
    private function fromGaps(string $siteId, array $gaps): array
    {
        return collect($gaps)->take(8)->map(function (array $gap) use ($siteId): ?array {
            $cluster = (string) ($gap['cluster'] ?? 'untitled-cluster');

            return $this->finalizeRecommendation([
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
            ], [
                'url' => null,
                'path' => null,
                'title' => $cluster,
                'cluster' => $cluster,
                'word_count' => (int) ($gap['avg_word_count'] ?? 0),
                'authority_score' => (float) ($gap['avg_authority'] ?? 0),
                'indexability_state' => 'indexable',
            ]);
        })->filter()->values()->all();
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
            ->get([
                'id',
                'title',
                'normalized_url',
                'path',
                'indexability_state',
                'cluster_label',
                'latest_word_count',
                'authority_score',
            ])
            ->mapWithKeys(fn (SeoSitePage $page): array => [
                $page->id => [
                    'label' => $this->pageLabel($page->title, $page->normalized_url),
                    'url' => $page->normalized_url,
                    'path' => $page->path,
                    'title' => $page->title,
                    'cluster' => $page->cluster_label,
                    'indexability_state' => $page->indexability_state,
                    'word_count' => (int) $page->latest_word_count,
                    'authority_score' => (float) $page->authority_score,
                ],
            ])
            ->all();
    }

    /**
     * @param  array<string,mixed>  $item
     * @param  array<string,mixed>|null  $pageContext
     * @param  array<string,mixed>|null  $secondaryContext
     * @return array<string,mixed>|null
     */
    private function finalizeRecommendation(array $item, ?array $pageContext = null, ?array $secondaryContext = null): ?array
    {
        $primaryContext = $this->hydrateContext(
            (string) ($item['site_id'] ?? ''),
            (int) ($item['site_page_id'] ?? 0),
            $this->normalizedContext($pageContext ?? [])
        );
        $secondary = $secondaryContext !== null
            ? $this->hydrateContext(
                (string) ($item['site_id'] ?? ''),
                (int) ($item['meta_json']['target_id'] ?? 0),
                $this->normalizedContext($secondaryContext)
            )
            : null;
        $classification = $this->classifier->classify($primaryContext);
        $businessIntent = $this->businessIntent->classify($primaryContext);
        $signals = $this->signalsFor($item, $primaryContext);
        $eligibility = $this->eligibility->evaluate((string) ($item['type'] ?? ''), $classification, $businessIntent, $signals);

        if ($secondary !== null && ($item['type'] ?? null) === 'differentiate_intent') {
            $secondaryClassification = $this->classifier->classify($secondary);
            $secondaryBusinessIntent = $this->businessIntent->classify($secondary);

            if (
                ! $eligibility['eligible']
                && ! $this->eligibility->evaluate((string) ($item['type'] ?? ''), $secondaryClassification, $secondaryBusinessIntent, $this->signalsFor($item, $secondary))['eligible']
            ) {
                return null;
            }
        } elseif (! $eligibility['eligible']) {
            return null;
        }

        $impact = $this->impactEstimator->estimate((string) ($item['type'] ?? ''), $classification, $businessIntent, $signals);
        $scoring = $this->scoring->score((string) ($item['type'] ?? ''), $classification, $businessIntent, $eligibility, $impact, $signals);
        $meta = is_array($item['meta_json'] ?? null) ? $item['meta_json'] : [];

        $item['priority'] = $scoring['priority'];
        $item['estimated_impact'] = $impact['estimated_impact'];
        $item['meta_json'] = array_merge($meta, [
            'page_classification' => $classification,
            'business_intent' => $businessIntent,
            'eligibility' => $eligibility,
            'impact_estimate' => $impact,
            'scoring' => $scoring,
        ]);
        $item['reasoning'] = $this->decorateReasoning((string) ($item['reasoning'] ?? ''), $scoring, $impact);

        return $item;
    }

    /**
     * @param  array<string,mixed>  $context
     * @return array<string,mixed>
     */
    private function normalizedContext(array $context): array
    {
        return [
            'url' => $context['url'] ?? $context['normalized_url'] ?? null,
            'path' => $context['path'] ?? null,
            'title' => $context['title'] ?? null,
            'meta_description' => $context['meta_description'] ?? null,
            'primary_h1' => $context['primary_h1'] ?? null,
            'cluster' => $context['cluster'] ?? $context['cluster_label'] ?? null,
            'indexability_state' => $context['indexability_state'] ?? 'unknown',
            'word_count' => (int) ($context['word_count'] ?? $context['latest_word_count'] ?? 0),
            'authority_score' => (float) ($context['authority_score'] ?? 0),
            'orphan_score' => (float) ($context['orphan_score'] ?? 0),
            'gsc_impressions' => (int) ($context['gsc_impressions'] ?? 0),
            'gsc_clicks' => (int) ($context['gsc_clicks'] ?? 0),
            'gsc_ctr' => (float) ($context['gsc_ctr'] ?? 0),
            'gsc_position' => (float) ($context['gsc_position'] ?? 0),
        ];
    }

    /**
     * @param  array<string,mixed>  $context
     * @return array<string,mixed>
     */
    private function hydrateContext(string $siteId, int $sitePageId, array $context): array
    {
        if ($siteId === '' || $sitePageId <= 0) {
            return $context;
        }

        $page = SeoSitePage::query()
            ->where('site_id', $siteId)
            ->where('id', $sitePageId)
            ->first([
                'normalized_url',
                'path',
                'title',
                'cluster_label',
                'indexability_state',
                'latest_word_count',
                'authority_score',
                'orphan_score',
            ]);

        if (! $page) {
            return $context;
        }

        return array_merge([
            'url' => $page->normalized_url,
            'path' => $page->path,
            'title' => $page->title,
            'cluster' => $page->cluster_label,
            'indexability_state' => $page->indexability_state,
            'word_count' => (int) $page->latest_word_count,
            'authority_score' => (float) $page->authority_score,
            'orphan_score' => (float) $page->orphan_score,
        ], array_filter($context, static fn (mixed $value): bool => $value !== null && $value !== ''));
    }

    /**
     * @param  array<string,mixed>  $item
     * @param  array<string,mixed>  $context
     * @return array<string,mixed>
     */
    private function signalsFor(array $item, array $context): array
    {
        $meta = is_array($item['meta_json'] ?? null) ? $item['meta_json'] : [];

        return [
            'indexability_state' => $context['indexability_state'] ?? 'unknown',
            'word_count' => (int) ($context['word_count'] ?? 0),
            'authority_score' => (float) ($context['authority_score'] ?? 0),
            'orphan_score' => (float) ($context['orphan_score'] ?? ($meta['orphan_score'] ?? 0)),
            'impressions' => (int) ($context['gsc_impressions'] ?? $meta['impressions'] ?? 0),
            'clicks' => (int) ($context['gsc_clicks'] ?? $meta['clicks'] ?? 0),
            'ctr' => (float) ($context['gsc_ctr'] ?? $meta['ctr'] ?? 0),
            'position' => (float) ($context['gsc_position'] ?? $meta['position'] ?? 0),
        ];
    }

    /**
     * @param  array<string,mixed>  $scoring
     * @param  array<string,mixed>  $impact
     */
    private function decorateReasoning(string $reasoning, array $scoring, array $impact): string
    {
        $parts = [];

        foreach (array_slice((array) ($scoring['positive_factors'] ?? []), 0, 4) as $factor) {
            $parts[] = '+ '.$factor;
        }

        foreach (array_slice((array) ($scoring['negative_factors'] ?? []), 0, 2) as $factor) {
            $parts[] = '- '.$factor;
        }

        $suffix = $parts !== []
            ? ' Facteurs : '.implode(' ; ', $parts).'.'
            : '';

        return trim($reasoning).' Score '.(int) ($scoring['recommendation_score'] ?? 0).'/100.'
            .' Impact estimé : +'.(int) ($impact['monthly_gain_min'] ?? 0)
            .' à +'.(int) ($impact['monthly_gain_max'] ?? 0)
            .' visites/mois. Confiance '.(int) ($impact['confidence'] ?? 0).'%.'.
            $suffix;
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
