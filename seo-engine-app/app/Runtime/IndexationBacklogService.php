<?php

declare(strict_types=1);

namespace App\Runtime;

use App\Models\SeoPage;
use App\Models\SeoSearchConsoleMetric;
use App\ObservedSite\SeoPageObservedLinkService;

class IndexationBacklogService
{
    public function __construct(
        private readonly SeoPageObservedLinkService $observedLinks,
    ) {}

    /**
     * @return array{
     *   summary:array{google_not_indexed:int,without_google_signal:int,observed_404:int,observed_redirect:int,observed_noindex:int,total:int},
     *   items:array<int,array<string,mixed>>
     * }
     */
    public function summarize(string $siteId): array
    {
        $pages = SeoPage::query()
            ->where('site_id', $siteId)
            ->get([
                'id',
                'site_id',
                'observed_site_page_id',
                'keyword',
                'slug',
                'title',
                'status',
                'canonical_url',
                'published_live',
                'published_live_at',
                'is_indexed',
            ]);

        if ($pages->isEmpty()) {
            return [
                'summary' => [
                    'google_not_indexed' => 0,
                    'without_google_signal' => 0,
                    'observed_404' => 0,
                    'observed_redirect' => 0,
                    'observed_noindex' => 0,
                    'total' => 0,
                ],
                'items' => [],
            ];
        }

        $latestMetrics = SeoSearchConsoleMetric::query()
            ->whereIn('seo_page_id', $pages->pluck('id'))
            ->whereNull('query')
            ->orderByDesc('metric_date')
            ->orderByDesc('id')
            ->get()
            ->groupBy('seo_page_id')
            ->map(fn ($group) => $group->first());

        $items = collect();

        foreach ($pages as $page) {
            $metric = $latestMetrics->get($page->id);
            $observed = $this->observedLinks->resolveMatch($page)['page'] ?? null;
            $label = trim((string) ($page->title ?: $page->keyword ?: $page->slug));

            if ($metric && $metric->is_indexed === false) {
                $items->push($this->makeItem(
                    type: 'google_not_indexed',
                    label: $label,
                    page: $page,
                    source: 'Google inspection',
                    reason: $this->coverageReason($metric->coverage_json),
                    action: 'Verifier canonical, sitemap, maillage et qualite avant nouvelle demande d indexation.',
                    priorityScore: 520,
                ));
            }

            if ($page->isPublishedInEngine() && ! $metric) {
                $items->push($this->makeItem(
                    type: 'without_google_signal',
                    label: $label,
                    page: $page,
                    source: 'Moteur + GSC',
                    reason: 'Page publiee cote moteur sans signal Google recent exploitable.',
                    action: 'Verifier la publication publique, le sitemap et les liens internes avant d attendre des impressions.',
                    priorityScore: $page->isPublishedLive() ? 320 : 240,
                ));
            }

            if ($observed && (int) ($observed->last_status_code ?? 0) >= 400) {
                $items->push($this->makeItem(
                    type: 'observed_404',
                    label: $label,
                    page: $page,
                    source: 'Crawl observed',
                    reason: 'La page resolue par le runtime repond en erreur HTTP '.$observed->last_status_code.'.',
                    action: 'Corriger la destination publiee ou supprimer la page casse cote site client.',
                    priorityScore: 500,
                    observedPath: $observed->path,
                ));
            }

            if ($observed && (int) ($observed->last_status_code ?? 0) >= 300 && (int) ($observed->last_status_code ?? 0) < 400) {
                $items->push($this->makeItem(
                    type: 'observed_redirect',
                    label: $label,
                    page: $page,
                    source: 'Crawl observed',
                    reason: 'La page publiee aboutit actuellement sur une redirection HTTP '.$observed->last_status_code.'.',
                    action: 'Verifier l URL finale, la canonical et eviter de pousser une ancienne route en production.',
                    priorityScore: 360,
                    observedPath: $observed->path,
                ));
            }

            if ($observed && in_array((string) $observed->indexability_state, ['noindex', 'non_indexable', 'blocked'], true)) {
                $items->push($this->makeItem(
                    type: 'observed_noindex',
                    label: $label,
                    page: $page,
                    source: 'Crawl observed',
                    reason: 'Le runtime observe actuellement un etat d indexabilite '.$observed->indexability_state.'.',
                    action: 'Verifier robots, meta noindex, canonicals et etat reel de la page cote site client.',
                    priorityScore: 340,
                    observedPath: $observed->path,
                ));
            }
        }

        $deduped = $items
            ->sortByDesc('priority_score')
            ->values();

        return [
            'summary' => [
                'google_not_indexed' => $deduped->where('type', 'google_not_indexed')->count(),
                'without_google_signal' => $deduped->where('type', 'without_google_signal')->count(),
                'observed_404' => $deduped->where('type', 'observed_404')->count(),
                'observed_redirect' => $deduped->where('type', 'observed_redirect')->count(),
                'observed_noindex' => $deduped->where('type', 'observed_noindex')->count(),
                'total' => $deduped->count(),
            ],
            'items' => $deduped->take(8)->all(),
        ];
    }

    /**
     * @param  array<int,string>|null  $coverage
     * @return array<string,mixed>
     */
    private function makeItem(
        string $type,
        string $label,
        SeoPage $page,
        string $source,
        string $reason,
        string $action,
        int $priorityScore,
        ?string $observedPath = null,
    ): array {
        return [
            'type' => $type,
            'label' => $label,
            'page_id' => $page->id,
            'slug' => $page->slug,
            'status' => $page->status,
            'source' => $source,
            'reason' => $reason,
            'action' => $action,
            'observed_path' => $observedPath,
            'priority_score' => $priorityScore,
        ];
    }

    /**
     * @param  array<int,string>|null  $coverage
     */
    private function coverageReason(?array $coverage): string
    {
        if (! is_array($coverage) || $coverage === []) {
            return 'Google confirme que la page n est pas indexee, sans detail de couverture supplementaire stocke.';
        }

        $coverageState = collect($coverage)
            ->first(fn (string $item): bool => str_starts_with($item, 'coverage_state:'));

        if (is_string($coverageState)) {
            return 'Google remonte : '.trim(substr($coverageState, strlen('coverage_state:'))).'.';
        }

        return 'Google confirme un probleme d indexation sur cette page.';
    }
}
