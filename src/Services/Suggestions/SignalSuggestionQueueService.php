<?php

declare(strict_types=1);

namespace Ofyre\SeoEngine\Services\Suggestions;

use Illuminate\Support\Str;
use Ofyre\SeoEngine\Contracts\SemanticLinkRepository;
use Ofyre\SeoEngine\Contracts\SignalSuggestionFormatter;
use Ofyre\SeoEngine\Contracts\SeoPageRepository;
use Ofyre\SeoEngine\Contracts\SeoSuggestionPersister;

final class SignalSuggestionQueueService
{
    private const SOURCE = 'signal_queue:auto';

    public function __construct(
        private readonly SeoPageRepository $pages,
        private readonly SemanticLinkRepository $semanticLinks,
        private readonly SeoSuggestionPersister $suggestions,
        private readonly ?SignalSuggestionFormatter $formatter = null,
    ) {}

    /**
     * @return array{pages:int,queued:int,cleared:int}
     */
    public function queue(?string $slug = null, int $limit = 100): array
    {
        $queued = 0;
        $cleared = 0;
        $pages = 0;

        foreach ($this->candidatePages($slug, $limit) as $page) {
            $pages++;

            $draft = $this->draftForPage($page);

            if ($draft === null) {
                $cleared += $this->suggestions->discardPending($page, self::SOURCE);

                continue;
            }

            $this->suggestions->replacePending($page, self::SOURCE, $draft);
            $queued++;
        }

        return [
            'pages' => $pages,
            'queued' => $queued,
            'cleared' => $cleared,
        ];
    }

    /**
     * @return iterable<int,object>
     */
    private function candidatePages(?string $slug, int $limit): iterable
    {
        if ($slug !== null && $slug !== '') {
            $page = $this->pages->findBySlug($slug);

            return $page ? [$page] : [];
        }

        return collect($this->pages->publishedPages())
            ->take($limit);
    }

    /**
     * @return array<string,mixed>|null
     */
    private function draftForPage(object $page): ?array
    {
        $slug = (string) ($page->slug ?? '');

        if ($slug === '') {
            return null;
        }

        $internalLinks = $this->semanticLinks->internalLinkSuggestions(
            $slug,
            (int) config('seo-engine.embeddings.max_internal_link_suggestions', 4)
        );
        $cannibalizationRisks = $this->semanticLinks->cannibalizationRisks(
            $slug,
            (int) config('seo-engine.embeddings.max_cannibalization_risks', 4)
        );
        $queryOpportunities = $this->semanticLinks->queryPageMatches(
            $slug,
            (int) config('seo-engine.embeddings.max_query_opportunities', 6)
        );

        if ($internalLinks === [] && $cannibalizationRisks === [] && $queryOpportunities === []) {
            return null;
        }

        $sections = collect()
            ->merge($this->sectionsFromInternalLinks($internalLinks))
            ->merge($this->sectionsFromCannibalization($cannibalizationRisks))
            ->merge($this->sectionsFromQueries($queryOpportunities))
            ->filter(fn (mixed $section): bool => is_string($section) && trim($section) !== '')
            ->unique()
            ->take(5)
            ->values()
            ->all();

        $faq = $this->faqFromQueries($queryOpportunities);
        $rationale = collect()
            ->merge($this->rationaleFromInternalLinks($internalLinks))
            ->merge($this->rationaleFromCannibalization($cannibalizationRisks))
            ->merge($this->rationaleFromQueries($queryOpportunities))
            ->filter(fn (mixed $item): bool => is_string($item) && trim($item) !== '')
            ->unique()
            ->take(8)
            ->values()
            ->all();

        $internalLinkPayload = collect($internalLinks)
            ->map(fn (array $link): array => [
                'label' => (string) ($link['label'] ?? ''),
                'url' => (string) ($link['url'] ?? ''),
                'reason' => (string) ($link['reason'] ?? 'semantic_link'),
            ])
            ->filter(fn (array $link): bool => $link['label'] !== '' && $link['url'] !== '')
            ->unique(fn (array $link): string => Str::lower($link['url']))
            ->values()
            ->all();

        if ($sections === [] && $faq === [] && $internalLinkPayload === []) {
            return null;
        }

        return [
            'source' => self::SOURCE,
            'signals_json' => [
                'internal_link_suggestions' => $internalLinks,
                'cannibalization_risks' => $cannibalizationRisks,
                'query_opportunities' => $queryOpportunities,
            ],
            'suggestions_json' => [
                'mode' => 'signal_queue',
                'title' => null,
                'meta_description' => null,
                'h1' => null,
                'sections' => $sections,
                'faq' => $faq,
                'internal_links' => $internalLinkPayload,
                'rationale' => $rationale,
                'signals_summary' => [
                    'internal_links' => count($internalLinks),
                    'cannibalization_risks' => count($cannibalizationRisks),
                    'query_opportunities' => count($queryOpportunities),
                ],
            ],
            'status' => 'pending',
        ];
    }

    /**
     * @param  array<int,array{label:string,url:string,reason:string,similarity_score:float,meta:array<string,mixed>}>  $internalLinks
     * @return array<int,string>
     */
    private function sectionsFromInternalLinks(array $internalLinks): array
    {
        if ($this->formatter === null || $internalLinks === []) {
            return [];
        }

        $labels = collect($internalLinks)
            ->take(3)
            ->pluck('label')
            ->filter(fn (mixed $label): bool => is_string($label) && trim($label) !== '')
            ->values()
            ->all();

        if ($labels === []) {
            return [];
        }

        $section = $this->formatter->internalLinkSection($labels);

        return $section !== null ? [$section] : [];
    }

    /**
     * @param  array<int,array{label:string,url:string,reason:string,similarity_score:float,meta:array<string,mixed>}>  $risks
     * @return array<int,string>
     */
    private function sectionsFromCannibalization(array $risks): array
    {
        if ($this->formatter === null) {
            return [];
        }

        return collect($risks)
            ->take(2)
            ->map(function (array $risk): ?string {
                $label = (string) ($risk['label'] ?? '');
                $action = str_replace('_', ' ', (string) (($risk['meta']['recommended_action'] ?? null) ?: $risk['reason'] ?? 'review_cluster_overlap'));

                return $this->formatter->cannibalizationSection($label, $action);
            })
            ->filter(fn (mixed $s): bool => is_string($s) && $s !== '')
            ->values()
            ->all();
    }

    /**
     * @param  array<int,array{label:string,url:string,reason:string,similarity_score:float,meta:array<string,mixed>}>  $matches
     * @return array<int,string>
     */
    private function sectionsFromQueries(array $matches): array
    {
        if ($this->formatter === null) {
            return [];
        }

        return collect($matches)
            ->take(2)
            ->map(function (array $match): ?string {
                $query = $this->queryLabel($match);
                $action = str_replace('_', ' ', (string) ($match['reason'] ?? 'refresh_existing_page'));

                return $this->formatter->querySection($query, $action);
            })
            ->filter(fn (mixed $s): bool => is_string($s) && $s !== '')
            ->values()
            ->all();
    }

    /**
     * @param  array<int,array{label:string,url:string,reason:string,similarity_score:float,meta:array<string,mixed>}>  $matches
     * @return array<int,array{question:string,answer:string}>
     */
    private function faqFromQueries(array $matches): array
    {
        if ($this->formatter === null) {
            return [];
        }

        return collect($matches)
            ->take(3)
            ->map(function (array $match): ?array {
                $question = $this->formatter->questionFromQuery($this->queryLabel($match));

                return $this->formatter->queryFaqItem($question);
            })
            ->filter(fn (mixed $item): bool => is_array($item) && isset($item['question']))
            ->unique(fn (array $item): string => Str::lower((string) $item['question']))
            ->values()
            ->all();
    }

    /**
     * @param  array<int,array{label:string,url:string,reason:string,similarity_score:float,meta:array<string,mixed>}>  $internalLinks
     * @return array<int,string>
     */
    private function rationaleFromInternalLinks(array $internalLinks): array
    {
        if ($this->formatter === null) {
            return [];
        }

        return collect($internalLinks)
            ->take(3)
            ->map(fn (array $link): ?string => $this->formatter->internalLinkRationale(
                (string) ($link['label'] ?? ''),
                (float) ($link['similarity_score'] ?? 0.0),
            ))
            ->filter(fn (mixed $s): bool => is_string($s) && $s !== '')
            ->values()
            ->all();
    }

    /**
     * @param  array<int,array{label:string,url:string,reason:string,similarity_score:float,meta:array<string,mixed>}>  $risks
     * @return array<int,string>
     */
    private function rationaleFromCannibalization(array $risks): array
    {
        if ($this->formatter === null) {
            return [];
        }

        return collect($risks)
            ->take(3)
            ->map(function (array $risk): ?string {
                $label = (string) ($risk['label'] ?? '');
                $action = str_replace('_', ' ', (string) (($risk['meta']['recommended_action'] ?? null) ?: $risk['reason'] ?? 'review_cluster_overlap'));

                return $this->formatter->cannibalizationRationale($label, $action);
            })
            ->filter(fn (mixed $s): bool => is_string($s) && $s !== '')
            ->values()
            ->all();
    }

    /**
     * @param  array<int,array{label:string,url:string,reason:string,similarity_score:float,meta:array<string,mixed>}>  $matches
     * @return array<int,string>
     */
    private function rationaleFromQueries(array $matches): array
    {
        if ($this->formatter === null) {
            return [];
        }

        return collect($matches)
            ->take(4)
            ->map(function (array $match): ?string {
                $query = $this->queryLabel($match);
                $impressions = (int) ($match['meta']['impressions'] ?? 0);
                $position = round((float) ($match['meta']['position'] ?? 0), 1);

                return $this->formatter->queryRationale($query, $impressions, $position, (string) ($match['reason'] ?? 'monitor_query'));
            })
            ->filter(fn (mixed $s): bool => is_string($s) && $s !== '')
            ->values()
            ->all();
    }

    /**
     * @param  array{label:string,url:string,reason:string,similarity_score:float,meta:array<string,mixed>}  $match
     */
    private function queryLabel(array $match): string
    {
        $query = trim((string) ($match['meta']['query'] ?? ''));

        if ($query !== '') {
            return $query;
        }

        $label = (string) ($match['label'] ?? '');

        return $label !== '' ? $label : ($this->formatter?->fallbackQueryLabel() ?? '');
    }
}
