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
        $this->assertSiteProfileReady();

        $cluster = $this->internalLinking->clusterForKeyword($keyword);
        $blueprint = $this->blueprints->resolve($keyword, $cluster);
        $fallbackPayload = $this->requiresSiteProfile() ? [] : $this->fallbackPayload($keyword, $cluster, $blueprint);
        $coreResult = $this->generateCoreWithAi($keyword, $cluster, $blueprint);
        $steps = ['core' => $coreResult['trace']];
        $errors = [];

        if ($coreResult['payload'] === null) {
            if ($this->requiresSiteProfile()) {
                throw new \RuntimeException(
                    'La génération IA a échoué. Aucun contenu générique ne sera produit tant que le profil métier est requis.'
                );
            }

            $source = 'fallback';
            $payload = $fallbackPayload;
            $errors[] = $coreResult['error'];
            $steps['faq'] = [
                'step' => 'faq',
                'skipped' => true,
                'reason' => 'core_failed',
            ];
        } else {
            $payload = $this->requiresSiteProfile()
                ? $coreResult['payload']
                : $this->mergePartialPayloadWithFallback($coreResult['payload'], $fallbackPayload);
            $source = (($coreResult['trace']['error_type'] ?? null) === 'partial_generation') ? 'hybrid' : 'ai';

            if ($coreResult['error']) {
                $errors[] = $coreResult['error'];
            }

            if ($this->requiresSiteProfile() && is_array($payload)) {
                [$payload, $expandTrace] = $this->expandShortFieldContent($payload, $keyword);

                if (is_array($expandTrace)) {
                    $steps['expand'] = $expandTrace;
                }
            }

            if ($this->requiresSiteProfile()) {
                $payload['faq'] = [];

                $faqResult = $this->generateFaqWithAi(
                    $keyword,
                    $cluster,
                    $blueprint,
                    (string) ($payload['title'] ?? ''),
                    (string) ($payload['meta_description'] ?? ''),
                    (string) ($payload['h1'] ?? ''),
                    (string) ($payload['content'] ?? '')
                );

                $steps['faq'] = $faqResult['trace'];

                if ($faqResult['payload'] !== null && is_array($faqResult['payload']['faq'] ?? null)) {
                    $payload['faq'] = array_slice(array_values($faqResult['payload']['faq']), 0, 4);
                }

                if ($faqResult['error']) {
                    $errors[] = $faqResult['error'];
                }
            } else {
                $faqResult = $this->generateFaqWithAi(
                    $keyword,
                    $cluster,
                    $blueprint,
                    (string) ($payload['title'] ?? ''),
                    (string) ($payload['meta_description'] ?? ''),
                    (string) ($payload['h1'] ?? ''),
                    (string) ($payload['content'] ?? '')
                );

                $steps['faq'] = $faqResult['trace'];

                if ($faqResult['payload'] !== null && is_array($faqResult['payload']['faq'] ?? null) && count($faqResult['payload']['faq']) >= 5) {
                    $payload['faq'] = array_values($faqResult['payload']['faq']);
                } else {
                    $source = 'hybrid';
                    if ($faqResult['error']) {
                        $errors[] = $faqResult['error'];
                    }
                }
            }
        }

        [$payload, $enriched] = $this->ensurePremiumDepth($payload, $blueprint, $keyword, $cluster);

        if ($this->requiresSiteProfile()) {
            $source = 'ai';
        } elseif ($source === 'ai' && $enriched) {
            $source = 'hybrid';
        }

        return [
            'cluster' => $cluster,
            'blueprint' => $blueprint,
            'payload' => $payload,
            'generation_source' => $source,
            'generation_error' => $this->combineGenerationErrors($errors),
            'generation_trace' => $this->mergeGenerationTrace($steps, $payload),
        ];
    }

    /**
     * @param  array<string,mixed>  $audit
     * @return array{cluster:string,blueprint:array<string,mixed>,payload:array<string,mixed>}
     */
    public function improvePayload(object $page, array $audit = []): array
    {
        $this->assertSiteProfileReady();

        $cluster = (string) ($page->cluster ?: $this->internalLinking->clusterForKeyword((string) $page->keyword));
        $blueprint = $this->blueprints->resolve((string) $page->keyword, $cluster);
        $payload = $this->improveWithAi($page, $audit);

        if (! $payload) {
            if ($this->requiresSiteProfile()) {
                throw new \RuntimeException(
                    'L amélioration IA a échoué. Aucun contenu générique ne sera produit tant que le profil métier est requis.'
                );
            }

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
        $schema = [
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
        ];

        $faq = is_array($page->faq_json ?? null) ? $page->faq_json : [];

        if ($faq !== []) {
            $schema[] = [
                '@context' => 'https://schema.org',
                '@type' => 'FAQPage',
                'mainEntity' => array_map(static fn (array $item): array => [
                    '@type' => 'Question',
                    'name' => $item['question'] ?? '',
                    'acceptedAnswer' => [
                        '@type' => 'Answer',
                        'text' => $item['answer'] ?? '',
                    ],
                ], $faq),
            ];
        }

        return $schema;
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
     * @param  array<string,mixed>  $blueprint
     * @return array{payload:?array<string,mixed>,error:?string,trace:array<string,mixed>}
     */
    protected function generateCoreWithAi(string $keyword, string $cluster, array $blueprint): array
    {
        return $this->askAiResult(
            $this->prompts->generationCorePrompt(
                $keyword,
                $cluster,
                $blueprint,
                $this->blueprints->expectedEditorialSections($blueprint),
                $this->blueprints->expectedSignals($blueprint),
            ),
            $keyword,
            ['title', 'meta_description', 'h1', 'content'],
            'core'
        );
    }

    /**
     * @param  array<string,mixed>  $blueprint
     * @return array{payload:?array<string,mixed>,error:?string,trace:array<string,mixed>}
     */
    protected function generateFaqWithAi(
        string $keyword,
        string $cluster,
        array $blueprint,
        string $title,
        string $metaDescription,
        string $h1,
        string $content
    ): array {
        return $this->askAiResult(
            $this->prompts->generationFaqPrompt(
                $keyword,
                $cluster,
                $blueprint,
                $title,
                $metaDescription,
                $h1,
                $content
            ),
            $keyword,
            ['faq'],
            'faq'
        );
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
    protected function askAiResult(string $prompt, ?string $keyword = null, array $expectedKeys = ['title', 'meta_description', 'h1', 'content', 'faq', 'schema'], string $step = 'generation'): array
    {
        $apiKey = config('services.openai.api_key');
        $startedAt = microtime(true);
        $timeoutSeconds = (int) config('services.openai.request_timeout', 180);
        $connectTimeoutSeconds = (int) config('services.openai.connect_timeout', 30);
        $retryAttempts = (int) config('services.openai.retry_attempts', 3);
        $retryDelayMs = (int) config('services.openai.retry_delay_ms', 2000);

        Log::info('SEO generation started.', [
            'keyword' => $keyword,
            'step' => $step,
            'prompt_length' => mb_strlen($prompt),
            'expected_keys' => $expectedKeys,
            'timeout_seconds' => $timeoutSeconds,
            'connect_timeout_seconds' => $connectTimeoutSeconds,
            'retry_attempts' => $retryAttempts,
            'retry_delay_ms' => $retryDelayMs,
        ]);

        if (! $apiKey) {
            Log::warning('SEO generation skipped: missing OPENAI_API_KEY.', [
                'keyword' => $keyword,
                'step' => $step,
                'duration_ms' => $this->elapsedMs($startedAt),
                'error_type' => 'missing_api_key',
            ]);

            return [
                'payload' => null,
                'error' => 'OPENAI_API_KEY manquante : génération AI indisponible.',
                'trace' => [
                    'step' => $step,
                    'error_type' => 'missing_api_key',
                    'expected_keys' => $expectedKeys,
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
                    'max_output_tokens' => (int) config('services.openai.max_output_tokens', 8192),
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
                'step' => $step,
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
                    'step' => $step,
                    'error_type' => $isTimeout ? 'timeout' : 'network_error',
                    'exception_message' => $exception->getMessage(),
                    'expected_keys' => $expectedKeys,
                ],
            ];
        }

        $openAiDurationMs = $this->elapsedMs($openAiStartedAt);

        if (! $response->successful()) {
            Log::warning('SEO generation OpenAI HTTP error.', [
                'keyword' => $keyword,
                'step' => $step,
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
                    'step' => $step,
                    'error_type' => 'openai_http_error',
                    'http_status' => $response->status(),
                    'response_excerpt' => Str::limit($response->body(), 500),
                    'expected_keys' => $expectedKeys,
                ],
            ];
        }

        $text = $response->json('output.0.content.0.text');

        if (! is_string($text) || trim($text) === '') {
            Log::warning('SEO generation returned empty payload.', [
                'keyword' => $keyword,
                'step' => $step,
                'duration_ms' => $this->elapsedMs($startedAt),
                'openai_duration_ms' => $openAiDurationMs,
                'error_type' => 'empty_generation',
            ]);

            return [
                'payload' => null,
                'error' => 'OpenAI a renvoyé une réponse vide.',
                'trace' => [
                    'step' => $step,
                    'error_type' => 'empty_generation',
                    'expected_keys' => $expectedKeys,
                ],
            ];
        }

        $decoded = json_decode($text, true);

        if (! is_array($decoded)) {
            Log::warning('SEO generation returned invalid JSON.', [
                'keyword' => $keyword,
                'step' => $step,
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
                    'step' => $step,
                    'error_type' => 'invalid_json',
                    'json_error' => json_last_error_msg(),
                    'response_excerpt' => Str::limit($text, 500),
                    'response_length' => mb_strlen($text),
                    'expected_keys' => $expectedKeys,
                ],
            ];
        }

        $decoded = $this->normalizeAiPayload($decoded);

        if (! $this->isCompletePayload($decoded, $expectedKeys)) {
            $missingKeys = $this->missingPayloadKeys($decoded, $expectedKeys);

            Log::warning('SEO generation returned partial payload.', [
                'keyword' => $keyword,
                'step' => $step,
                'duration_ms' => $this->elapsedMs($startedAt),
                'openai_duration_ms' => $openAiDurationMs,
                'error_type' => 'partial_generation',
                'keys' => array_keys($decoded),
                'missing_keys' => $missingKeys,
                'response_length' => mb_strlen($text),
            ]);

            return [
                'payload' => $decoded,
                'error' => 'OpenAI a renvoyé un payload partiel sur l étape '.$step.'. Clés manquantes : '.implode(', ', $missingKeys).'.',
                'trace' => [
                    'step' => $step,
                    'error_type' => 'partial_generation',
                    'expected_keys' => $expectedKeys,
                    'returned_keys' => array_values(array_map('strval', array_keys($decoded))),
                    'missing_keys' => $missingKeys,
                    'response_excerpt' => Str::limit($text, 500),
                    'response_length' => mb_strlen($text),
                ],
            ];
        }

        preg_match_all('/<h2\b/i', (string) ($decoded['content'] ?? ''), $matches);
        $sectionCount = count($matches[0]);

        Log::info('SEO generation completed.', [
            'keyword' => $keyword,
            'step' => $step,
            'duration_ms' => $this->elapsedMs($startedAt),
            'openai_duration_ms' => $openAiDurationMs,
            'prompt_length' => mb_strlen($prompt),
            'response_length' => mb_strlen($text),
            'timeout_seconds' => $timeoutSeconds,
            'connect_timeout_seconds' => $connectTimeoutSeconds,
            'expected_keys' => $expectedKeys,
            'returned_keys' => array_keys($decoded),
            'section_count' => $sectionCount,
        ]);

        return [
            'payload' => $decoded,
            'error' => null,
            'trace' => [
                'step' => $step,
                'error_type' => null,
                'expected_keys' => $expectedKeys,
                'returned_keys' => array_values(array_map('strval', array_keys($decoded))),
                'missing_keys' => [],
                'response_length' => mb_strlen($text),
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

        if (! $this->requiresSiteProfile()) {
            $payload['content'] = $this->content->ensureContentDepth((string) ($payload['content'] ?? ''), $blueprint, [
                'keyword' => $keyword,
                'cluster' => $cluster,
                'internal_links' => $links,
                'preserve_ai_narrative' => false,
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
        }

        if (! isset($payload['schema']) || ! is_array($payload['schema']) || $payload['schema'] === []) {
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

    protected function isCompletePayload(mixed $payload, array $expectedKeys = ['title', 'meta_description', 'h1', 'content', 'faq', 'schema']): bool
    {
        if (! is_array($payload)) {
            return false;
        }

        foreach ($expectedKeys as $key) {
            if (! array_key_exists($key, $payload)) {
                return false;
            }
        }

        $typeChecks = [
            'title' => is_string($payload['title'] ?? null),
            'meta_description' => is_string($payload['meta_description'] ?? null),
            'h1' => is_string($payload['h1'] ?? null),
            'content' => is_string($payload['content'] ?? null),
            'faq' => is_array($payload['faq'] ?? null),
            'schema' => is_array($payload['schema'] ?? null),
        ];

        foreach ($expectedKeys as $key) {
            if (($typeChecks[$key] ?? false) !== true) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, string>
     */
    protected function missingPayloadKeys(array $payload, array $expectedKeys = ['title', 'meta_description', 'h1', 'content', 'faq', 'schema']): array
    {
        $missing = [];

        foreach ($expectedKeys as $key) {
            if (! array_key_exists($key, $payload) || $payload[$key] === null) {
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

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function normalizeAiPayload(array $payload): array
    {
        if (array_key_exists('content', $payload)) {
            $payload['content'] = $this->normalizeAiContent($payload['content']);
        }

        if (array_key_exists('faq', $payload) && is_array($payload['faq'])) {
            $payload['faq'] = collect($payload['faq'])
                ->filter(fn (mixed $item): bool => is_array($item))
                ->map(fn (array $item): array => [
                    'question' => (string) ($item['question'] ?? ''),
                    'answer' => (string) ($item['answer'] ?? ''),
                ])
                ->filter(fn (array $item): bool => $item['question'] !== '' && $item['answer'] !== '')
                ->values()
                ->all();
        }

        return $payload;
    }

    protected function normalizeAiContent(mixed $content): string
    {
        if (is_string($content)) {
            return $content;
        }

        if (! is_array($content)) {
            return '';
        }

        $sections = collect($content)
            ->map(function (mixed $section): string {
                if (is_string($section)) {
                    return trim($section);
                }

                if (! is_array($section)) {
                    return '';
                }

                $heading = $this->normalizeAiTextFragment($section['H2'] ?? $section['h2'] ?? $section['title'] ?? '');
                $paragraph = $this->normalizeAiTextFragment($section['paragraph'] ?? $section['content'] ?? $section['text'] ?? '');

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

                if (is_array($section['items'] ?? null)) {
                    $items = collect($section['items'])
                        ->map(fn (mixed $item): string => $this->normalizeAiTextFragment($item))
                        ->filter(fn (string $item): bool => $item !== '')
                        ->map(fn (string $item): string => '<li>'.$item.'</li>')
                        ->implode('');

                    if ($items !== '') {
                        $html .= '<ul>'.$items.'</ul>';
                    }
                }

                $html .= '</section>';

                return $html;
            })
            ->filter(fn (string $section): bool => $section !== '')
            ->implode('');

        return $sections;
    }

    protected function normalizeAiTextFragment(mixed $value): string
    {
        if (is_string($value)) {
            return trim($value);
        }

        if (is_scalar($value)) {
            return trim((string) $value);
        }

        if (! is_array($value)) {
            return '';
        }

        $preferredKeys = ['text', 'content', 'paragraph', 'value', 'label', 'title'];

        foreach ($preferredKeys as $key) {
            if (array_key_exists($key, $value)) {
                $normalized = $this->normalizeAiTextFragment($value[$key]);

                if ($normalized !== '') {
                    return $normalized;
                }
            }
        }

        $fragments = collect($value)
            ->map(fn (mixed $item): string => $this->normalizeAiTextFragment($item))
            ->filter(fn (string $item): bool => $item !== '')
            ->values()
            ->all();

        return trim(implode("\n", $fragments));
    }

    /**
     * @param  array<string, array<string, mixed>>  $steps
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function mergeGenerationTrace(array $steps, array $payload): array
    {
        $returnedKeys = [];
        $missingKeys = [];
        $errorType = null;

        foreach ($steps as $step) {
            if ($errorType === null && is_string($step['error_type'] ?? null) && $step['error_type'] !== '') {
                $errorType = $step['error_type'];
            }

            if (is_array($step['returned_keys'] ?? null)) {
                $returnedKeys = array_merge($returnedKeys, array_map('strval', $step['returned_keys']));
            }

            if (is_array($step['missing_keys'] ?? null)) {
                $missingKeys = array_merge($missingKeys, array_map('strval', $step['missing_keys']));
            }
        }

        $missingKeys = array_values(array_unique(array_filter($missingKeys, static fn (string $key): bool => ! array_key_exists($key, $payload))));

        return [
            'steps' => $steps,
            'error_type' => $errorType,
            'returned_keys' => array_values(array_unique($returnedKeys)),
            'missing_keys' => $missingKeys,
        ];
    }

    /**
     * @param  array<int, string|null>  $errors
     */
    protected function combineGenerationErrors(array $errors): ?string
    {
        $filtered = array_values(array_filter(array_map(
            static fn (mixed $error): ?string => is_string($error) && trim($error) !== '' ? trim($error) : null,
            $errors
        )));

        if ($filtered === []) {
            return null;
        }

        return implode(' | ', array_values(array_unique($filtered)));
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

    protected function requiresSiteProfile(): bool
    {
        return (bool) config('seo-engine.require_site_profile', false);
    }

    protected function assertSiteProfileReady(): void
    {
        if (! $this->requiresSiteProfile()) {
            return;
        }

        $status = trim((string) data_get(config('seo-engine.site.profile'), 'status', 'pending'));

        if ($status !== 'ready') {
            throw new \RuntimeException(sprintf(
                'Le profil métier du site n est pas prêt (statut: %s). La génération est bloquée.',
                $status !== '' ? $status : 'pending',
            ));
        }
    }

    /**
     * @param  array<string,mixed>  $payload
     * @return array{0:array<string,mixed>,1:?array<string,mixed>}
     */
    protected function expandShortFieldContent(array $payload, string $keyword): array
    {
        $content = (string) ($payload['content'] ?? '');

        if (! $this->fieldExpertContentTooShort($content)) {
            return [$payload, null];
        }

        $prompt = "Approfondis cet article métier sans changer de voix ni ajouter de sections template.\n"
            ."Objectif : au moins 1000 mots, HTML avec <h2> et <p>, une seule narration continue.\n"
            ."Ajoute situations terrain, chiffres crédibles, erreurs fréquentes et arbitrages client.\n"
            ."Interdit : checklist opérationnelle, ressources à croiser, phrases de consigne interne, blocs collés.\n"
            .'Mot-clé : '.$keyword."\n"
            .'Titre actuel : '.(string) ($payload['title'] ?? '')."\n"
            ."Contenu actuel :\n".$content."\n"
            .'Retourner uniquement un JSON avec : title, meta_description, h1, content.';

        $result = $this->askAiResult(
            $prompt,
            $keyword,
            ['title', 'meta_description', 'h1', 'content'],
            'expand',
        );

        if (! is_array($result['payload'] ?? null)) {
            return [$payload, $result['trace']];
        }

        return [array_replace($payload, $result['payload']), $result['trace']];
    }

    protected function fieldExpertContentTooShort(string $content): bool
    {
        return $this->fieldExpertWordCount($content) < 800;
    }

    protected function fieldExpertWordCount(string $content): int
    {
        $plain = trim(strip_tags($content));
        preg_match_all('/[\p{L}\p{N}\']+/u', $plain, $matches);

        return count($matches[0] ?? []);
    }
}
