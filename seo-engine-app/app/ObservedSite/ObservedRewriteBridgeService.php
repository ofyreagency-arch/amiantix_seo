<?php

declare(strict_types=1);

namespace App\ObservedSite;

use App\Models\SeoPage;
use App\Models\SeoRecommendation;
use App\Models\SeoSitePage;
use App\Models\SeoSuggestion;
use Illuminate\Support\Collection;

class ObservedRewriteBridgeService
{
    public function __construct(
        private readonly ObservedPageHealthService $pageHealth,
        private readonly SeoPageObservedLinkService $links,
    ) {}

    /**
     * @return array<string,mixed>
     */
    public function contextForPage(SeoPage $page): array
    {
        $observedPage = $this->findObservedPage($page);

        if (! $observedPage) {
            return [
                'matched' => false,
                'queued' => false,
                'state' => 'unknown',
                'health' => null,
                'flags' => [],
                'recommendations' => [],
                'sections' => [],
                'faq' => [],
                'internal_links' => [],
                'rationale' => [],
            ];
        }

        $health = $this->pageHealth->forPage($observedPage);
        $recommendations = SeoRecommendation::query()
            ->where('site_id', $page->site_id)
            ->where('site_page_id', $observedPage->id)
            ->where('status', 'pending')
            ->orderBy('priority')
            ->limit(5)
            ->get();

        $state = $this->stateFor($observedPage, $health);
        $sections = $this->sectionsFor($health['flags'], $recommendations);
        $faq = $this->faqFor($health['flags'], $recommendations);
        $internalLinks = $this->internalLinksFor($observedPage, $recommendations);
        $rationale = $this->rationaleFor($health, $state, $recommendations);

        return [
            'matched' => true,
            'queued' => $sections !== [] || $faq !== [] || $internalLinks !== [] || $rationale !== [],
            'site_page_id' => $observedPage->id,
            'url' => $observedPage->normalized_url,
            'path' => $observedPage->path,
            'title' => $observedPage->title,
            'cluster_label' => $observedPage->cluster_label,
            'state' => $state,
            'health' => $health,
            'flags' => $health['flags'],
            'recommendations' => $recommendations->map(fn (SeoRecommendation $recommendation): array => [
                'type' => $recommendation->type,
                'priority' => $recommendation->priority,
                'title' => $recommendation->title,
                'suggested_action' => $recommendation->suggested_action,
                'meta' => $recommendation->meta_json ?? [],
            ])->all(),
            'sections' => $sections,
            'faq' => $faq,
            'internal_links' => $internalLinks,
            'rationale' => $rationale,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function syncForPage(SeoPage $page): array
    {
        $context = $this->contextForPage($page);

        if (! ($context['queued'] ?? false)) {
            $this->clearPending($page);

            return $context;
        }

        SeoSuggestion::query()->updateOrCreate(
            [
                'seo_page_id' => $page->id,
                'source' => 'observed_rewrite:auto',
                'status' => 'pending',
            ],
            [
                'signals_json' => [
                    'observed' => true,
                    'observed_state' => $context['state'],
                    'health_score' => $context['health']['health_score'] ?? null,
                    'flags' => $context['flags'],
                    'site_page_id' => $context['site_page_id'] ?? null,
                    'path' => $context['path'] ?? null,
                ],
                'suggestions_json' => [
                    'mode' => 'observed_rewrite',
                    'sections' => $context['sections'],
                    'faq' => $context['faq'],
                    'internal_links' => $context['internal_links'],
                    'rationale' => $context['rationale'],
                    'signals_summary' => [
                        'observed_state' => $context['state'],
                        'observed_flags' => count($context['flags'] ?? []),
                        'observed_recommendations' => count($context['recommendations'] ?? []),
                    ],
                ],
            ],
        );

        return $context;
    }

    private function clearPending(SeoPage $page): void
    {
        SeoSuggestion::query()
            ->where('seo_page_id', $page->id)
            ->where('source', 'observed_rewrite:auto')
            ->where('status', 'pending')
            ->delete();
    }

    private function findObservedPage(SeoPage $page): ?SeoSitePage
    {
        return $this->links->observedForPage($page);
    }

    /**
     * @param  array{health_score:int,flags:array<int,string>}  $health
     */
    private function stateFor(SeoSitePage $page, array $health): string
    {
        if ((int) ($page->last_status_code ?? 0) >= 400 || $health['health_score'] < 45) {
            return 'critical';
        }

        if ($health['flags'] !== [] || $health['health_score'] < 75) {
            return 'warning';
        }

        return 'healthy';
    }

    /**
     * @param  array<int,string>  $flags
     * @return array<int,string>
     */
    private function sectionsFor(array $flags, Collection $recommendations): array
    {
        return collect($flags)
            ->map(fn (string $flag): ?string => match ($flag) {
                'missing_title' => 'Rewrite the title to better reflect the page intent and primary keyword.',
                'missing_meta_description' => 'Add a sharper meta description aligned with the observed search intent.',
                'missing_cluster_signal' => 'Clarify the topic angle with headings that reinforce the cluster intent.',
                'low_authority' => 'Add stronger proof points, concrete details, and supporting subtopics to increase page authority.',
                'orphan_high' => 'Add internal linking cues and contextual references to reconnect the page inside the site.',
                'overlap_high' => 'Differentiate the angle from nearby competing pages and make the intent more explicit.',
                'non_indexable' => 'Fix indexability blockers in the copy and metadata so the page can be published cleanly.',
                'unhealthy_status' => 'Refresh the page so the live version can support a healthy crawl and status code.',
                default => null,
            })
            ->merge($recommendations->map(fn (SeoRecommendation $recommendation): string => (string) $recommendation->suggested_action))
            ->filter(fn (?string $section): bool => is_string($section) && trim($section) !== '')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<int,string>  $flags
     * @return array<int,array<string,string>>
     */
    private function faqFor(array $flags, Collection $recommendations): array
    {
        $faq = [];

        if (in_array('non_indexable', $flags, true)) {
            $faq[] = [
                'question' => 'Why is this page not indexable yet?',
                'answer' => 'The observed crawl still sees an indexability blocker that should be fixed in metadata or page setup.',
            ];
        }

        if (in_array('overlap_high', $flags, true)) {
            $faq[] = [
                'question' => 'How should this page differ from similar pages?',
                'answer' => 'It should target a more explicit angle so it no longer competes with nearby pages in the same cluster.',
            ];
        }

        foreach ($recommendations as $recommendation) {
            if ($recommendation->type === 'differentiate_intent') {
                $faq[] = [
                    'question' => 'What makes this page different from nearby content?',
                    'answer' => 'The rewrite should make the target intent clearer and reduce overlap with similar pages.',
                ];
            }
        }

        return collect($faq)
            ->unique(fn (array $item): string => strtolower($item['question']))
            ->values()
            ->all();
    }

    /**
     * @return array<int,array<string,string>>
     */
    private function internalLinksFor(SeoSitePage $page, Collection $recommendations): array
    {
        return $recommendations
            ->map(function (SeoRecommendation $recommendation) use ($page): ?array {
                $meta = is_array($recommendation->meta_json) ? $recommendation->meta_json : [];
                $url = $meta['target_url'] ?? $meta['url'] ?? $meta['source_url'] ?? $page->normalized_url;

                if (! is_string($url) || trim($url) === '') {
                    return null;
                }

                return [
                    'label' => (string) ($meta['target_label'] ?? $meta['context_label'] ?? $recommendation->title),
                    'url' => $url,
                    'reason' => (string) $recommendation->type,
                ];
            })
            ->filter(fn (?array $item): bool => is_array($item))
            ->unique(fn (array $item): string => strtolower($item['url']))
            ->values()
            ->all();
    }

    /**
     * @param  array{health_score:int,flags:array<int,string>}  $health
     * @return array<int,string>
     */
    private function rationaleFor(array $health, string $state, Collection $recommendations): array
    {
        $rationale = [
            'observed_state:'.$state,
            'observed_health_score:'.(int) $health['health_score'],
        ];

        foreach ($health['flags'] as $flag) {
            $rationale[] = 'observed_flag:'.$flag;
        }

        foreach ($recommendations as $recommendation) {
            $rationale[] = 'observed_recommendation:'.$recommendation->type;
        }

        return array_values(array_unique($rationale));
    }
}
