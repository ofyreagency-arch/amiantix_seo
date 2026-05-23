<?php

declare(strict_types=1);

namespace Ofyre\SeoEngine\Services\Rewrite;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
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
    ) {}

    public function createSuggestion(object $page, string $mode): mixed
    {
        $context = $this->rewriteSignalContext($page);
        $page = $this->pageWithRewriteContext($page, $context);

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
        $suggestions = $this->mergeSignalContextIntoSuggestions($suggestions, $context);
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
    private function pageWithRewriteContext(object $page, array $context): object
    {
        $clone = clone $page;
        $clone->rewrite_signal_context = $context;
        $clone->rewrite_signal_summary = $this->signalContextSummary($context);

        return $clone;
    }

    /**
     * @param  array<string,mixed>  $suggestions
     * @param  array<string,mixed>  $context
     * @return array<string,mixed>
     */
    private function mergeSignalContextIntoSuggestions(array $suggestions, array $context): array
    {
        $suggestions['sections'] = collect($suggestions['sections'] ?? [])
            ->merge($context['sections'])
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
            ],
        );

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
        $existing = trim($this->normalizeSuggestedContent($suggestions['content'] ?? $suggestions['proposed_content'] ?? ''));

        if ($existing !== '') {
            $suggestions['proposed_content'] = $existing;

            return $suggestions;
        }

        $sections = collect($suggestions['sections'] ?? [])
            ->filter(fn (mixed $item): bool => is_string($item) && trim($item) !== '')
            ->values();

        if ($sections->isEmpty()) {
            return $suggestions;
        }

        $opening = match ($mode) {
            'rewrite' => 'Cette passe propose une réécriture complète de la page avec un angle plus net et plus utile.',
            'de-duplicate' => 'Cette passe clarifie l intention de recherche et différencie la page des contenus proches.',
            'improve-ctr' => 'Cette passe renforce surtout la promesse éditoriale visible dans le titre et la meta.',
            'improve-indexability' => 'Cette passe vise une page plus propre à publier et plus facile à indexer.',
            default => 'Cette passe enrichit la page existante en gardant son angle principal tout en la rendant plus utile.',
        };

        $body = $sections
            ->map(function (string $section, int $index): string {
                $heading = 'Amélioration prioritaire '.($index + 1);

                return '<section><h2>'.$heading.'</h2><p>'.$section.'</p></section>';
            })
            ->implode('');

        $faq = collect($suggestions['faq'] ?? [])
            ->filter(fn (mixed $item): bool => is_array($item) && isset($item['question']))
            ->take(3)
            ->map(fn (array $item): string => '<section><h3>'.((string) ($item['question'] ?? 'Question')).'</h3><p>'.((string) ($item['answer'] ?? '')).'</p></section>')
            ->implode('');

        $suggestions['proposed_content'] = '<section><h2>Passe de réécriture</h2><p>'.$opening.'</p><p>'.((string) ($page->meta_description ?? $page->keyword ?? '')).'</p></section>'.$body.$faq;

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
}
