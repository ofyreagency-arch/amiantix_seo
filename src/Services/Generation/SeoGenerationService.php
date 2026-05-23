<?php

declare(strict_types=1);

namespace Ofyre\SeoEngine\Services\Generation;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Ofyre\SeoEngine\Contracts\ImagePromptProvider;
use Ofyre\SeoEngine\Contracts\InternalLinkProvider;
use Ofyre\SeoEngine\Contracts\NicheBlueprintProvider;
use Ofyre\SeoEngine\Contracts\NicheContentProvider;
use Ofyre\SeoEngine\Contracts\PromptProfileProvider;
use Throwable;

class SeoGenerationService
{
    public function __construct(
        protected readonly NicheBlueprintProvider $blueprints,
        protected readonly PromptProfileProvider $prompts,
        protected readonly NicheContentProvider $content,
        protected readonly InternalLinkProvider $internalLinking,
        protected readonly ImagePromptProvider $imagePrompts,
    ) {}

    /**
     * @return array{cluster:string,blueprint:array<string,mixed>,payload:array<string,mixed>,generation_source:string,generation_error:?string,generation_trace:array<string,mixed>}
     */
    public function generatePayload(string $keyword): array
    {
        $cluster = $this->internalLinking->clusterForKeyword($keyword);
        $blueprint = $this->blueprints->resolve($keyword, $cluster);
        $aiResult = $this->generateWithAi($keyword, $cluster, $blueprint);
        $fallbackPayload = $this->fallbackPayload($keyword, $cluster, $blueprint);
        $errorType = (string) ($aiResult['trace']['error_type'] ?? '');

        if ($aiResult['payload'] === null) {
            $source = 'fallback';
            $payload = $fallbackPayload;
        } elseif ($errorType === 'partial_generation') {
            $source = 'hybrid';
            $payload = $this->mergePartialPayloadWithFallback($aiResult['payload'], $fallbackPayload);
        } else {
            $source = 'ai';
            $payload = $aiResult['payload'];
        }

        [$payload, $enriched] = $this->ensurePremiumDepth($payload, $blueprint, $keyword, $cluster);

        if ($source === 'ai' && $enriched) {
            $source = 'hybrid';
        }

        return [
            'cluster' => $cluster,
            'blueprint' => $blueprint,
            'payload' => $payload,
            'generation_source' => $source,
            'generation_error' => $aiResult['error'],
            'generation_trace' => $aiResult['trace'],
        ];
    }

    /**
     * @param  array<string,mixed>  $audit
     * @return array{cluster:string,blueprint:array<string,mixed>,payload:array<string,mixed>}
     */
    public function improvePayload(object $page, array $audit = []): array
    {
        $cluster = (string) ($page->cluster ?: $this->internalLinking->clusterForKeyword((string) $page->keyword));
        $blueprint = $this->blueprints->resolve((string) $page->keyword, $cluster);
        $payload = $this->improveWithAi($page, $audit);

        if (! $payload) {
            $payload = $this->fallbackPayload((string) $page->keyword, $cluster, $blueprint);
            $payload['content'] = (string) $page->content."\n".$this->content->extraSection((string) $page->keyword, $blueprint, [
                'cluster' => $cluster,
                'page' => $page,
            ]);
            $payload['faq'] = array_values(array_merge($page->faq_json ?? [], $payload['faq']));
        }

        return [
            'cluster' => $cluster,
            'blueprint' => $blueprint,
            'payload' => $this->ensurePremiumDepth($payload, $blueprint, (string) $page->keyword, $cluster)[0],
        ];
    }

    /**
     * @return array<int, array{question:string,answer:string}>
     */
    public function generateFaq(string $keyword): array
    {
        $cluster = $this->internalLinking->clusterForKeyword($keyword);

        return $this->fallbackPayload($keyword, $cluster, $this->blueprints->resolve($keyword, $cluster))['faq'];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function generateSchema(object $page): array
    {
        return [
            [
                '@context' => 'https://schema.org',
                '@type' => 'Article',
                'headline' => (string) ($page->title ?? ''),
                'description' => (string) ($page->meta_description ?? ''),
                'inLanguage' => $this->languageCode(),
                'mainEntityOfPage' => rtrim((string) config('seo-engine.site.url', config('app.url')), '/').$this->canonicalPathFor($page),
                'author' => [
                    '@type' => 'Organization',
                    'name' => (string) config('seo-engine.site.name', config('app.name')),
                ],
            ],
            [
                '@context' => 'https://schema.org',
                '@type' => 'FAQPage',
                'mainEntity' => array_map(static fn (array $item): array => [
                    '@type' => 'Question',
                    'name' => $item['question'] ?? '',
                    'acceptedAnswer' => [
                        '@type' => 'Answer',
                        'text' => $item['answer'] ?? '',
                    ],
                ], $page->faq_json ?? []),
            ],
        ];
    }

    /**
     * @return array<int, array{label:string,url:string,reason:string}>
     */
    public function generateInternalLinks(object $page): array
    {
        return $page->internal_links_json ?? $this->internalLinking->linksFor($page);
    }

    public function generateImagePrompt(string $keyword, ?string $cluster = null): string
    {
        return $this->imagePrompts->promptFor($keyword, $cluster);
    }

    protected function slugForKeyword(string $keyword): string
    {
        $slug = Str::slug(Str::lower($keyword));
        $prefix = trim((string) config('seo-engine.site.slug_prefix', ''));

        if ($prefix !== '' && ! str_starts_with($slug, $prefix)) {
            $slug = $prefix.$slug;
        }

        return $slug;
    }

    /**
     * @param  array<string,mixed>  $blueprint
     * @return array{payload:?array<string,mixed>,error:?string,trace:array<string,mixed>}
     */
    protected function generateWithAi(string $keyword, string $cluster, array $blueprint): array
    {
        return $this->askAiResult($this->prompts->generationPrompt(
            $keyword,
            $cluster,
            $blueprint,
            $this->blueprints->expectedEditorialSections($blueprint),
            $this->blueprints->expectedSignals($blueprint),
        ), $keyword);
    }

    /**
     * @param  array<string,mixed>  $audit
     * @return array{title?:string,meta_description?:string,h1?:string,content?:string,faq?:array<int,array<string,string>>,schema?:array<int,array<string,mixed>>}|null
     */
    protected function improveWithAi(object $page, array $audit): ?array
    {
        $blueprint = $this->blueprints->resolve((string) $page->keyword, $page->cluster ?? null);

        return $this->askAiResult($this->prompts->improvementPrompt(
            $page,
            $blueprint,
            $audit,
            $this->blueprints->expectedEditorialSections($blueprint),
            $this->blueprints->expectedSignals($blueprint),
        ), (string) $page->keyword)['payload'];
    }

    /**
     * @return array{payload:?array<string,mixed>,error:?string,trace:array<string,mixed>}
     */
    protected function askAiResult(string $prompt, ?string $keyword = null): array
    {
        $apiKey = config('services.openai.api_key');
        $startedAt = microtime(true);
        $timeoutSeconds = (int) config('services.openai.request_timeout', 180);
        $connectTimeoutSeconds = (int) config('services.openai.connect_timeout', 30);
        $retryAttempts = (int) config('services.openai.retry_attempts', 3);
        $retryDelayMs = (int) config('services.openai.retry_delay_ms', 2000);

        Log::info('SEO generation started.', [
            'keyword' => $keyword,
            'prompt_length' => mb_strlen($prompt),
            'timeout_seconds' => $timeoutSeconds,
            'connect_timeout_seconds' => $connectTimeoutSeconds,
            'retry_attempts' => $retryAttempts,
            'retry_delay_ms' => $retryDelayMs,
        ]);

        if (! $apiKey) {
            Log::warning('SEO generation skipped: missing OPENAI_API_KEY.', [
                'keyword' => $keyword,
                'duration_ms' => $this->elapsedMs($startedAt),
                'error_type' => 'missing_api_key',
            ]);

            return [
                'payload' => null,
                'error' => 'OPENAI_API_KEY manquante : génération AI indisponible.',
                'trace' => [
                    'error_type' => 'missing_api_key',
                ],
            ];
        }

        try {
            $openAiStartedAt = microtime(true);

            $response = Http::withToken((string) $apiKey)
                ->acceptJson()
                ->connectTimeout($connectTimeoutSeconds)
                ->timeout($timeoutSeconds)
                ->retry($retryAttempts, $retryDelayMs, function (Throwable $exception): bool {
                    return $exception instanceof ConnectionException || $exception instanceof RequestException;
                })
                ->post('https://api.openai.com/v1/responses', [
                    'model' => config('services.openai.model', 'gpt-4o-mini'),
                    'input' => $prompt,
                    'text' => [
                        'format' => [
                            'type' => 'json_object',
                        ],
                    ],
                ]);
        } catch (ConnectionException $exception) {
            $isTimeout = str_contains(Str::lower($exception->getMessage()), 'timed out')
                || str_contains(Str::lower($exception->getMessage()), 'curl error 28');

            Log::warning('SEO generation OpenAI connection failed.', [
                'keyword' => $keyword,
                'duration_ms' => $this->elapsedMs($startedAt),
                'error_type' => $isTimeout ? 'timeout' : 'network_error',
                'message' => $exception->getMessage(),
                'timeout_seconds' => $timeoutSeconds,
                'connect_timeout_seconds' => $connectTimeoutSeconds,
            ]);

            return [
                'payload' => null,
                'error' => $isTimeout
                    ? 'Connexion OpenAI expirée pendant la génération.'
                    : 'Connexion OpenAI impossible : '.$exception->getMessage(),
                'trace' => [
                    'error_type' => $isTimeout ? 'timeout' : 'network_error',
                    'exception_message' => $exception->getMessage(),
                ],
            ];
        }

        $openAiDurationMs = $this->elapsedMs($openAiStartedAt);

        if (! $response->successful()) {
            Log::warning('SEO generation OpenAI HTTP error.', [
                'keyword' => $keyword,
                'duration_ms' => $this->elapsedMs($startedAt),
                'openai_duration_ms' => $openAiDurationMs,
                'error_type' => 'openai_http_error',
                'http_status' => $response->status(),
                'response_excerpt' => Str::limit($response->body(), 500),
            ]);

            return [
                'payload' => null,
                'error' => 'OpenAI a répondu en erreur HTTP '.$response->status().'.',
                'trace' => [
                    'error_type' => 'openai_http_error',
                    'http_status' => $response->status(),
                    'response_excerpt' => Str::limit($response->body(), 500),
                ],
            ];
        }

        $text = $response->json('output.0.content.0.text');

        if (! is_string($text) || trim($text) === '') {
            Log::warning('SEO generation returned empty payload.', [
                'keyword' => $keyword,
                'duration_ms' => $this->elapsedMs($startedAt),
                'openai_duration_ms' => $openAiDurationMs,
                'error_type' => 'empty_generation',
            ]);

            return [
                'payload' => null,
                'error' => 'OpenAI a renvoyé une réponse vide.',
                'trace' => [
                    'error_type' => 'empty_generation',
                ],
            ];
        }

        $decoded = json_decode($text, true);

        if (! is_array($decoded)) {
            Log::warning('SEO generation returned invalid JSON.', [
                'keyword' => $keyword,
                'duration_ms' => $this->elapsedMs($startedAt),
                'openai_duration_ms' => $openAiDurationMs,
                'error_type' => 'invalid_json',
                'json_error' => json_last_error_msg(),
                'response_excerpt' => Str::limit($text, 500),
            ]);

            return [
                'payload' => null,
                'error' => 'OpenAI a renvoyé un JSON invalide : '.json_last_error_msg().'.',
                'trace' => [
                    'error_type' => 'invalid_json',
                    'json_error' => json_last_error_msg(),
                    'response_excerpt' => Str::limit($text, 500),
                ],
            ];
        }

        if (! $this->isCompletePayload($decoded)) {
            $missingKeys = $this->missingPayloadKeys($decoded);

            Log::warning('SEO generation returned partial payload.', [
                'keyword' => $keyword,
                'duration_ms' => $this->elapsedMs($startedAt),
                'openai_duration_ms' => $openAiDurationMs,
                'error_type' => 'partial_generation',
                'keys' => array_keys($decoded),
                'missing_keys' => $missingKeys,
            ]);

            return [
                'payload' => $decoded,
                'error' => 'OpenAI a renvoyé un payload partiel, fallback activé. Clés manquantes : '.implode(', ', $missingKeys).'.',
                'trace' => [
                    'error_type' => 'partial_generation',
                    'returned_keys' => array_values(array_map('strval', array_keys($decoded))),
                    'missing_keys' => $missingKeys,
                    'response_excerpt' => Str::limit($text, 500),
                ],
            ];
        }

        preg_match_all('/<h2\b/i', (string) ($decoded['content'] ?? ''), $matches);
        $sectionCount = count($matches[0]);

        Log::info('SEO generation completed.', [
            'keyword' => $keyword,
            'duration_ms' => $this->elapsedMs($startedAt),
            'openai_duration_ms' => $openAiDurationMs,
            'prompt_length' => mb_strlen($prompt),
            'timeout_seconds' => $timeoutSeconds,
            'connect_timeout_seconds' => $connectTimeoutSeconds,
            'section_count' => $sectionCount,
        ]);

        return [
            'payload' => $decoded,
            'error' => null,
            'trace' => [
                'error_type' => null,
                'returned_keys' => array_values(array_map('strval', array_keys($decoded))),
                'missing_keys' => [],
            ],
        ];
    }

    /**
     * @param  array<string,mixed>  $blueprint
     * @return array{title:string,meta_description:string,h1:string,content:string,faq:array<int,array<string,string>>,schema:array<int,array<string,mixed>>}
     */
    protected function fallbackPayload(string $keyword, string $cluster, array $blueprint): array
    {
        $links = $this->internalLinkHtml($keyword, $cluster);
        $payload = $this->content->fallbackPayload($keyword, $cluster, $blueprint, [
            'internal_links' => $links,
        ]);

        if (! isset($payload['schema']) || ! is_array($payload['schema'])) {
            $payload['schema'] = $this->generateSchema((object) [
                'title' => $payload['title'] ?? '',
                'meta_description' => $payload['meta_description'] ?? '',
                'faq_json' => $payload['faq'] ?? [],
                'slug' => $this->slugForKeyword($keyword),
            ]);
        }

        return $payload;
    }

    /**
     * @return array<int,array{label:string,url:string,reason:string}>
     */
    protected function internalLinkHtml(string $keyword, string $cluster): array
    {
        return $this->internalLinking->linksFor((object) [
            'id' => null,
            'keyword' => Str::lower($keyword),
            'cluster' => $cluster,
            'slug' => $this->slugForKeyword($keyword),
        ]);
    }

    /**
     * @param  array<string,mixed>  $payload
     * @param  array<string,mixed>  $blueprint
     * @return array{0:array<string,mixed>,1:bool}
     */
    protected function ensurePremiumDepth(array $payload, array $blueprint, string $keyword, string $cluster): array
    {
        $links = $this->internalLinkHtml($keyword, $cluster);
        $originalPayload = $payload;

        $payload['content'] = $this->content->ensureContentDepth((string) ($payload['content'] ?? ''), $blueprint, [
            'keyword' => $keyword,
            'cluster' => $cluster,
            'internal_links' => $links,
        ]);

        if (! isset($payload['faq']) || ! is_array($payload['faq']) || count($payload['faq']) < 5) {
            $fallbackFaq = $this->content->fallbackPayload($keyword, $cluster, $blueprint, [
                'internal_links' => $links,
            ])['faq'];

            $payload['faq'] = array_map(static fn (array $item): array => [
                'question' => (string) $item['question'],
                'answer' => (string) $item['answer'],
            ], $fallbackFaq);
        }

        if (! isset($payload['schema']) || ! is_array($payload['schema']) || count($payload['schema']) < 2) {
            $payload['schema'] = $this->generateSchema((object) [
                'title' => $payload['title'] ?? '',
                'meta_description' => $payload['meta_description'] ?? '',
                'faq_json' => $payload['faq'] ?? [],
                'slug' => $this->slugForKeyword($keyword),
            ]);
        }

        return [
            $payload,
            json_encode($payload) !== json_encode($originalPayload),
        ];
    }

    protected function elapsedMs(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }

    protected function isCompletePayload(mixed $payload): bool
    {
        if (! is_array($payload)) {
            return false;
        }

        foreach (['title', 'meta_description', 'h1', 'content', 'faq', 'schema'] as $key) {
            if (! array_key_exists($key, $payload)) {
                return false;
            }
        }

        return is_string($payload['title'])
            && is_string($payload['meta_description'])
            && is_string($payload['h1'])
            && is_string($payload['content'])
            && is_array($payload['faq'])
            && is_array($payload['schema']);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, string>
     */
    protected function missingPayloadKeys(array $payload): array
    {
        $missing = [];

        foreach (['title', 'meta_description', 'h1', 'content', 'faq', 'schema'] as $key) {
            if (! array_key_exists($key, $payload)) {
                $missing[] = $key;
            }
        }

        return $missing;
    }

    /**
     * @param  array<string, mixed>  $partialPayload
     * @param  array<string, mixed>  $fallbackPayload
     * @return array<string, mixed>
     */
    protected function mergePartialPayloadWithFallback(array $partialPayload, array $fallbackPayload): array
    {
        $merged = $fallbackPayload;

        foreach (['title', 'meta_description', 'h1', 'content', 'faq', 'schema'] as $key) {
            if (array_key_exists($key, $partialPayload) && $partialPayload[$key] !== null) {
                $merged[$key] = $partialPayload[$key];
            }
        }

        return $merged;
    }

    protected function canonicalPathFor(object $page): string
    {
        if (method_exists($page, 'canonicalPath')) {
            return (string) $page->canonicalPath();
        }

        $slug = ltrim((string) ($page->slug ?? ''), '/');

        return $slug === '' ? '/' : '/'.$slug;
    }

    protected function languageCode(): string
    {
        $configured = (string) config('seo-engine.site.locale', config('app.locale', 'en'));
        $normalized = str_replace('_', '-', trim($configured));

        return $normalized !== '' ? $normalized : 'en';
    }
}
