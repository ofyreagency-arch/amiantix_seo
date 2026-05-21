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
}
