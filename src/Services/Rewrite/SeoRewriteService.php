<?php

declare(strict_types=1);

namespace Ofyre\SeoEngine\Services\Rewrite;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Ofyre\SeoEngine\Contracts\NicheBlueprintProvider;
use Ofyre\SeoEngine\Contracts\NicheContentProvider;
use Ofyre\SeoEngine\Contracts\PromptProfileProvider;
use Ofyre\SeoEngine\Contracts\RewriteAccessDecider;
use Ofyre\SeoEngine\Contracts\SeoSuggestionPersister;

class SeoRewriteService
{
    private const MODES = ['enrich', 'rewrite', 'de-duplicate', 'improve-ctr', 'improve-indexability'];

    public function __construct(
        private readonly RewriteAccessDecider $overrides,
        private readonly PromptProfileProvider $prompts,
        private readonly SeoSuggestionPersister $suggestions,
        private readonly NicheBlueprintProvider $blueprints,
        private readonly NicheContentProvider $content,
    ) {}

    public function createSuggestion(object $page, string $mode): mixed
    {
        $context = $this->rewriteSignalContext($page);
        $weakSections = $this->detectWeakSections((string) ($page->content ?? ''));
        $page = $this->pageWithRewriteContext($page, $context, $weakSections);

        if (! $this->overrides->rewriteAllowed($page)) {
            return $this->suggestions->persist($page, [
                'source' => 'rewrite_blocked',
                'signals_json' => [
                    'cluster' => $page->cluster ?? null,
                    'rewrite_context' => $this->signalContextSummary($context),
                ],
                'suggestions_json' => [
                    'blocked' => true,
                    'reason' => 'Rewrite disabled by human override.',
                ],
                'status' => 'rejected',
            ]);
        }

        if (! in_array($mode, self::MODES, true)) {
            $mode = 'enrich';
        }

        $suggestions = $this->rewriteWithAi($page, $mode) ?? $this->prompts->fallbackRewrite($page, $mode);
        $suggestions = $this->mergeSignalContextIntoSuggestions($suggestions, $context, $weakSections);
        $suggestions = $this->ensureProposedContent($page, $suggestions, $mode);

        return $this->suggestions->replacePending($page, 'rewrite_engine:'.$mode, [
            'source' => 'rewrite_engine:'.$mode,
            'signals_json' => [
                'seo_score' => $page->seo_score ?? null,
                'indexability_score' => $page->indexability_score ?? null,
                'spam_risk' => $page->spam_risk ?? null,
                'cluster' => $page->cluster ?? null,
                'rewrite_context' => $this->signalContextSummary($context),
            ],
            'suggestions_json' => $suggestions,
            'status' => 'pending',
        ]);
    }

    /**
     * @return array{
     *     sections:array<int,string>,
     *     rationale:array<int,string>,
     *     faq:array<int,array<string,mixed>>,
     *     internal_links:array<int,array<string,mixed>>,
     *     sources:array<string,int>,
     *     pending_count:int
     * }
     */
    private function rewriteSignalContext(object $page): array
    {
        $relationLoaded = method_exists($page, 'relationLoaded') && $page->relationLoaded('suggestions');
        $related = $relationLoaded
            ? collect($page->suggestions ?? [])
            : (method_exists($page, 'suggestions') ? $page->suggestions()->where('status', 'pending')->get() : collect());

        $pending = collect($related)
            ->filter(fn (mixed $suggestion): bool => is_object($suggestion))
            ->filter(fn (object $suggestion): bool => ($suggestion->status ?? null) === 'pending')
            ->values();

        $sections = $pending
            ->flatMap(fn (object $suggestion): array => is_array($suggestion->suggestions_json['sections'] ?? null)
                ? $suggestion->suggestions_json['sections']
                : [])
            ->filter(fn (mixed $section): bool => is_string($section) && trim($section) !== '')
            ->unique()
            ->values()
            ->all();

        $rationale = $pending
            ->flatMap(fn (object $suggestion): array => is_array($suggestion->suggestions_json['rationale'] ?? null)
                ? $suggestion->suggestions_json['rationale']
                : [])
            ->filter(fn (mixed $item): bool => is_string($item) && trim($item) !== '')
            ->unique()
            ->values()
            ->all();

        $faq = $pending
            ->flatMap(fn (object $suggestion): array => is_array($suggestion->suggestions_json['faq'] ?? null)
                ? $suggestion->suggestions_json['faq']
                : [])
            ->filter(fn (mixed $item): bool => is_array($item) && isset($item['question']))
            ->unique(fn (array $item): string => Str::lower((string) ($item['question'] ?? '')))
            ->values()
            ->all();

        $internalLinks = $pending
            ->flatMap(fn (object $suggestion): array => is_array($suggestion->suggestions_json['internal_links'] ?? null)
                ? $suggestion->suggestions_json['internal_links']
                : [])
            ->filter(fn (mixed $item): bool => is_array($item) && isset($item['url']))
            ->unique(fn (array $item): string => Str::lower((string) ($item['url'] ?? '')))
            ->values()
            ->all();

        $sources = $pending
            ->groupBy(fn (object $suggestion): string => (string) ($suggestion->source ?? 'unknown'))
            ->map(fn ($items): int => count($items))
            ->all();

        return [
            'sections' => $sections,
            'rationale' => $rationale,
            'faq' => $faq,
            'internal_links' => $internalLinks,
            'sources' => $sources,
            'pending_count' => $pending->count(),
        ];
    }

    /**
     * @param  array<string,mixed>  $context
     */
    private function pageWithRewriteContext(object $page, array $context, array $weakSections = []): object
    {
        $clone = clone $page;
        $clone->rewrite_signal_context = $context;
        $clone->rewrite_signal_summary = $this->signalContextSummary($context);
        $clone->rewrite_weak_sections = $weakSections;

        return $clone;
    }

    /**
     * @param  array<string,mixed>  $suggestions
     * @param  array<string,mixed>  $context
     * @return array<string,mixed>
     */
    private function mergeSignalContextIntoSuggestions(array $suggestions, array $context, array $weakSections = []): array
    {
        $suggestions['sections'] = collect($suggestions['sections'] ?? [])
            ->merge($context['sections'])
            ->merge($weakSections)
            ->filter(fn (mixed $section): bool => is_string($section) && trim($section) !== '')
            ->unique()
            ->values()
            ->all();

        $suggestions['rationale'] = collect($suggestions['rationale'] ?? [])
            ->merge($context['rationale'])
            ->push($this->signalContextSummary($context))
            ->filter(fn (mixed $item): bool => is_string($item) && trim($item) !== '')
            ->unique()
            ->values()
            ->all();

        $suggestions['faq'] = collect($suggestions['faq'] ?? [])
            ->merge($context['faq'])
            ->filter(fn (mixed $item): bool => is_array($item) && isset($item['question']))
            ->unique(fn (array $item): string => Str::lower((string) ($item['question'] ?? '')))
            ->values()
            ->all();

        $suggestions['internal_links'] = collect($suggestions['internal_links'] ?? [])
            ->merge($context['internal_links'])
            ->filter(fn (mixed $item): bool => is_array($item) && isset($item['url']))
            ->unique(fn (array $item): string => Str::lower((string) ($item['url'] ?? '')))
            ->values()
            ->all();

        $suggestions['signals_summary'] = array_merge(
            is_array($suggestions['signals_summary'] ?? null) ? $suggestions['signals_summary'] : [],
            [
                'pending_rewrite_signals' => (int) ($context['pending_count'] ?? 0),
                'sources' => $context['sources'] ?? [],
                'weak_sections' => $weakSections,
            ],
        );

        if ($weakSections !== []) {
            $suggestions['rationale'][] = 'Target the currently weak sections before compacting the full article.';
            $suggestions['rationale'] = collect($suggestions['rationale'])
                ->filter(fn (mixed $item): bool => is_string($item) && trim($item) !== '')
                ->unique()
                ->values()
                ->all();
        }

        return $suggestions;
    }

    /**
     * @param  array<string,mixed>  $context
     */
    private function signalContextSummary(array $context): string
    {
        $count = (int) ($context['pending_count'] ?? 0);
        $sources = collect($context['sources'] ?? [])
            ->map(fn (int $total, string $source): string => $source.'='.$total)
            ->values()
            ->implode(', ');

        if ($count === 0) {
            return 'No pending engine signals were attached to this rewrite.';
        }

        return 'Rewrite informed by '.$count.' pending engine signal(s)'.($sources !== '' ? ' ['.$sources.'].' : '.');
    }

    /**
     * @return array<string,mixed>|null
     */
    protected function rewriteWithAi(object $page, string $mode): ?array
    {
        $apiKey = config('services.openai.api_key');

        if (! $apiKey) {
            Log::warning('SEO rewrite skipped: missing OPENAI_API_KEY.', [
                'page_slug' => $page->slug ?? null,
                'mode' => $mode,
            ]);

            return null;
        }

        $response = Http::withToken((string) $apiKey)
            ->acceptJson()
            ->timeout(90)
            ->post('https://api.openai.com/v1/responses', [
                'model' => config('services.openai.model', 'gpt-4o-mini'),
                'input' => $this->prompts->rewritePrompt($page, $mode),
                'text' => [
                    'format' => [
                        'type' => 'json_object',
                    ],
                ],
            ]);

        if (! $response->successful()) {
            Log::warning('SEO rewrite OpenAI HTTP error.', [
                'page_slug' => $page->slug ?? null,
                'mode' => $mode,
                'http_status' => $response->status(),
                'response_excerpt' => Str::limit($response->body(), 500),
            ]);

            return null;
        }

        $text = $response->json('output.0.content.0.text');

        if (! is_string($text) || trim($text) === '') {
            Log::warning('SEO rewrite returned empty payload.', [
                'page_slug' => $page->slug ?? null,
                'mode' => $mode,
            ]);

            return null;
        }

        $decoded = json_decode($text, true);

        if (! is_array($decoded)) {
            Log::warning('SEO rewrite returned invalid JSON.', [
                'page_slug' => $page->slug ?? null,
                'mode' => $mode,
                'json_error' => json_last_error_msg(),
                'response_excerpt' => Str::limit($text, 300),
            ]);

            return null;
        }

        return $decoded;
    }

    /**
     * @param  array<string,mixed>  $suggestions
     * @return array<string,mixed>
     */
    private function ensureProposedContent(object $page, array $suggestions, string $mode): array
    {
        $cluster = (string) ($page->cluster ?? '');
        $blueprint = $this->blueprints->resolve((string) ($page->keyword ?? ''), $cluster !== '' ? $cluster : null);
        $currentContent = trim((string) ($page->content ?? ''));
        $suggestedContent = trim($this->normalizeSuggestedContent($suggestions['content'] ?? $suggestions['proposed_content'] ?? ''));
        $sections = collect($suggestions['sections'] ?? [])
            ->filter(fn (mixed $item): bool => is_string($item) && trim($item) !== '')
            ->values()
            ->all();

        $baseContent = $suggestedContent;

        if ($baseContent === '' || $this->shouldPreserveExistingNarrative($currentContent, $baseContent, $mode)) {
            $baseContent = $currentContent;

            if ($currentContent !== '' && $suggestedContent !== '') {
                $baseContent = $this->mergeSuggestedNarrativePatch(
                    $currentContent,
                    $suggestedContent,
                    $this->detectWeakSections($currentContent)
                );
            }
        }

        if ($baseContent === '') {
            $baseContent = (string) ($this->content->fallbackPayload(
                (string) ($page->keyword ?? ''),
                $cluster,
                $blueprint,
                [
                    'page' => $page,
                    'rewrite_mode' => $mode,
                    'rewrite_sections' => $sections,
                    'rewrite_rationale' => $suggestions['rationale'] ?? [],
                    'internal_links' => $suggestions['internal_links'] ?? $page->internal_links_json ?? [],
                ]
            )['content'] ?? '');
        }

        if ($suggestedContent === '' && $baseContent !== '' && $sections !== []) {
            $baseContent .= $this->renderRewriteFocusSection($mode, $sections);
        }

        if ($baseContent === '') {
            return $suggestions;
        }

        $suggestions['proposed_content'] = $this->content->ensureContentDepth($baseContent, $blueprint, [
            'keyword' => (string) ($page->keyword ?? ''),
            'cluster' => $cluster,
            'page' => $page,
            'rewrite_mode' => $mode,
            'rewrite_sections' => $sections,
            'rewrite_rationale' => $suggestions['rationale'] ?? [],
            'internal_links' => $suggestions['internal_links'] ?? $page->internal_links_json ?? [],
        ]);

        return $suggestions;
    }

    private function normalizeSuggestedContent(mixed $content): string
    {
        if (is_string($content)) {
            return $content;
        }

        if (! is_array($content)) {
            return '';
        }

        return collect($content)
            ->map(function (mixed $section): string {
                if (is_string($section)) {
                    return trim($section);
                }

                if (! is_array($section)) {
                    return '';
                }

                $heading = trim((string) ($section['H2'] ?? $section['h2'] ?? $section['title'] ?? ''));
                $paragraph = trim((string) ($section['paragraph'] ?? $section['content'] ?? $section['text'] ?? ''));

                if ($heading === '' && $paragraph === '') {
                    return '';
                }

                $html = '<section>';

                if ($heading !== '') {
                    $html .= '<h2>'.$heading.'</h2>';
                }

                if ($paragraph !== '') {
                    $html .= '<p>'.$paragraph.'</p>';
                }

                $html .= '</section>';

                return $html;
            })
            ->filter(fn (string $section): bool => $section !== '')
            ->implode('');
    }

    private function shouldPreserveExistingNarrative(string $currentContent, string $suggestedContent, string $mode): bool
    {
        if ($currentContent === '' || $suggestedContent === '') {
            return false;
        }

        $currentWords = $this->wordCount($currentContent);
        $suggestedWords = $this->wordCount($suggestedContent);
        $currentHeadings = $this->headingCount($currentContent);
        $suggestedHeadings = $this->headingCount($suggestedContent);
        $currentHasTable = str_contains(Str::lower($currentContent), '<table');
        $suggestedHasTable = str_contains(Str::lower($suggestedContent), '<table');

        if ($currentWords < 900 || $currentHeadings < 5) {
            return false;
        }

        if ($mode === 'improve-ctr' && $suggestedWords >= 350 && $suggestedHeadings >= 2) {
            return false;
        }

        return $suggestedWords < (int) round($currentWords * 0.65)
            || $suggestedHeadings < max(2, (int) floor($currentHeadings / 2))
            || ($currentHasTable && ! $suggestedHasTable);
    }

    /**
     * @param  array<int,string>  $sections
     */
    private function renderRewriteFocusSection(string $mode, array $sections): string
    {
        $heading = match ($mode) {
            'rewrite' => 'Points a renforcer dans la reecriture',
            'de-duplicate' => 'Points a clarifier pour differencier la page',
            'improve-ctr' => 'Promesses a rendre plus visibles',
            'improve-indexability' => 'Points a fiabiliser avant publication',
            default => 'Points a renforcer dans cette version',
        };

        $items = collect($sections)
            ->take(4)
            ->map(fn (string $section): string => '<li>'.$section.'</li>')
            ->implode('');

        if ($items === '') {
            return '';
        }

        return '<section><h2>'.$heading.'</h2><ul>'.$items.'</ul></section>';
    }

    private function mergeSuggestedNarrativePatch(string $currentContent, string $suggestedContent, array $weakSections = []): string
    {
        $currentHeadings = $this->headingsIndex($currentContent);
        $currentSections = $this->extractHtmlSections($currentContent);
        $patchSections = $this->extractHtmlSections($suggestedContent);
        $append = [];
        $replaced = false;

        foreach ($patchSections as $section) {
            $heading = $this->firstHeadingFromSection($section);
            $normalizedHeading = Str::lower(trim($heading));

            if ($heading === '') {
                continue;
            }

            $replacementIndex = $this->findWeakSectionReplacementIndex($currentSections, $heading, $weakSections);

            if ($replacementIndex !== null) {
                $currentSections[$replacementIndex] = $section;
                $replaced = true;

                continue;
            }

            if (isset($currentHeadings[$normalizedHeading])) {
                continue;
            }

            $append[] = $section;
        }

        if ($currentSections !== [] && $replaced) {
            $currentContent = implode('', $currentSections);
        }

        if ($append === []) {
            return $currentContent;
        }

        return $currentContent.implode('', $append);
    }

    /**
     * @return array<int,string>
     */
    private function detectWeakSections(string $content): array
    {
        return collect($this->extractHtmlSections($content))
            ->map(function (string $section): ?string {
                $heading = $this->firstHeadingFromSection($section);

                if ($heading === '') {
                    return null;
                }

                $sectionWords = $this->wordCount($section);
                $normalizedHeading = Str::lower(Str::ascii($heading));
                $expectsStructuredSupport = Str::contains($normalizedHeading, [
                    'tableau',
                    'matrice',
                    'checklist',
                    'questions',
                    'faq',
                    'documents',
                    'preuves',
                    'processus',
                    'workflow',
                ]);
                $hasStructuredSupport = str_contains(Str::lower($section), '<table')
                    || str_contains(Str::lower($section), '<ul')
                    || str_contains(Str::lower($section), '<ol')
                    || str_contains(Str::lower($section), '<h3');

                if ($sectionWords < 55) {
                    return $heading;
                }

                if ($expectsStructuredSupport && ! $hasStructuredSupport && $sectionWords < 120) {
                    return $heading;
                }

                return null;
            })
            ->filter(fn (?string $heading): bool => is_string($heading) && trim($heading) !== '')
            ->values()
            ->all();
    }

    /**
     * @param  array<int,string>  $currentSections
     * @param  array<int,string>  $weakSections
     */
    private function findWeakSectionReplacementIndex(array $currentSections, string $patchHeading, array $weakSections): ?int
    {
        foreach ($currentSections as $index => $currentSection) {
            $currentHeading = $this->firstHeadingFromSection($currentSection);

            if ($currentHeading === '') {
                continue;
            }

            if (! $this->isWeakHeadingCandidate($currentHeading, $weakSections)) {
                continue;
            }

            if (! $this->headingsAreClose($currentHeading, $patchHeading)) {
                continue;
            }

            return $index;
        }

        return null;
    }

    /**
     * @param  array<int,string>  $weakSections
     */
    private function isWeakHeadingCandidate(string $heading, array $weakSections): bool
    {
        $normalizedHeading = $this->normalizeHeading($heading);

        foreach ($weakSections as $weakHeading) {
            if ($this->normalizeHeading($weakHeading) === $normalizedHeading) {
                return true;
            }
        }

        return false;
    }

    private function headingsAreClose(string $left, string $right): bool
    {
        $normalizedLeft = $this->normalizeHeading($left);
        $normalizedRight = $this->normalizeHeading($right);

        if ($normalizedLeft === '' || $normalizedRight === '') {
            return false;
        }

        if ($normalizedLeft === $normalizedRight) {
            return true;
        }

        similar_text($normalizedLeft, $normalizedRight, $similarity);

        if ($similarity >= 68.0) {
            return true;
        }

        $leftTokens = collect(explode(' ', $normalizedLeft))
            ->filter(fn (string $token): bool => $token !== '')
            ->values();
        $rightTokens = collect(explode(' ', $normalizedRight))
            ->filter(fn (string $token): bool => $token !== '')
            ->values();

        if ($leftTokens->isEmpty() || $rightTokens->isEmpty()) {
            return false;
        }

        $common = $leftTokens->intersect($rightTokens)->count();
        $coverage = $common / min($leftTokens->count(), $rightTokens->count());

        return $coverage >= 0.6;
    }

    private function normalizeHeading(string $heading): string
    {
        return Str::of($heading)
            ->lower()
            ->ascii()
            ->replaceMatches('/[^a-z0-9]+/u', ' ')
            ->squish()
            ->value();
    }

    private function wordCount(string $content): int
    {
        return str_word_count(Str::ascii(strip_tags($content)));
    }

    private function headingCount(string $content): int
    {
        preg_match_all('/<h2\b/i', $content, $matches);

        return count($matches[0] ?? []);
    }

    /**
     * @return array<string,true>
     */
    private function headingsIndex(string $content): array
    {
        preg_match_all('/<h2\b[^>]*>(.*?)<\/h2>/is', $content, $matches);

        return collect($matches[1] ?? [])
            ->map(fn (mixed $heading): string => Str::lower(trim(strip_tags((string) $heading))))
            ->filter(fn (string $heading): bool => $heading !== '')
            ->mapWithKeys(fn (string $heading): array => [$heading => true])
            ->all();
    }

    /**
     * @return array<int,string>
     */
    private function extractHtmlSections(string $content): array
    {
        preg_match_all('/<section\b[^>]*>.*?<\/section>/is', $content, $matches);

        return array_values(array_filter(
            $matches[0] ?? [],
            static fn (mixed $section): bool => is_string($section) && trim($section) !== ''
        ));
    }

    private function firstHeadingFromSection(string $section): string
    {
        if (! preg_match('/<h2\b[^>]*>(.*?)<\/h2>/is', $section, $matches)) {
            return '';
        }

        return trim(strip_tags((string) ($matches[1] ?? '')));
    }
}
