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
        if (! $this->overrides->rewriteAllowed($page)) {
            return $this->suggestions->persist($page, [
                'source' => 'rewrite_blocked',
                'signals_json' => ['cluster' => $page->cluster ?? null],
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

        return $this->suggestions->persist($page, [
            'source' => 'rewrite_engine:'.$mode,
            'signals_json' => [
                'seo_score' => $page->seo_score ?? null,
                'indexability_score' => $page->indexability_score ?? null,
                'spam_risk' => $page->spam_risk ?? null,
                'cluster' => $page->cluster ?? null,
            ],
            'suggestions_json' => $suggestions,
            'status' => 'pending',
        ]);
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
