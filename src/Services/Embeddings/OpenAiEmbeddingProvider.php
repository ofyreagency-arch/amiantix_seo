<?php

declare(strict_types=1);

namespace Ofyre\SeoEngine\Services\Embeddings;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Ofyre\SeoEngine\Contracts\EmbeddingProvider;
use RuntimeException;
use Throwable;

class OpenAiEmbeddingProvider implements EmbeddingProvider
{
    public function embed(string $text): array
    {
        $apiKey = config('services.openai.api_key');

        if (! is_string($apiKey) || trim($apiKey) === '') {
            throw new RuntimeException('Missing OPENAI_API_KEY for SEO embeddings.');
        }

        try {
            $response = Http::withToken($apiKey)
                ->acceptJson()
                ->connectTimeout((int) config('services.openai.connect_timeout', 30))
                ->timeout((int) config('services.openai.request_timeout', 180))
                ->retry((int) config('services.openai.retry_attempts', 3), (int) config('services.openai.retry_delay_ms', 2000), function (Throwable $exception): bool {
                    return $exception instanceof ConnectionException || $exception instanceof RequestException;
                })
                ->post('https://api.openai.com/v1/embeddings', [
                    'model' => $this->model(),
                    'input' => $text,
                ]);
        } catch (Throwable $exception) {
            throw new RuntimeException('Unable to generate SEO embeddings: '.$exception->getMessage(), previous: $exception);
        }

        if (! $response->successful()) {
            throw new RuntimeException('OpenAI embeddings request failed with status '.$response->status().'.');
        }

        $embedding = $response->json('data.0.embedding');

        if (! is_array($embedding) || $embedding === []) {
            throw new RuntimeException('OpenAI embeddings response was empty.');
        }

        return array_map(static fn (mixed $value): float => (float) $value, $embedding);
    }

    public function model(): string
    {
        return (string) config('seo-engine.embeddings.model', 'text-embedding-3-small');
    }

    public function dimensions(): int
    {
        return (int) config('seo-engine.embeddings.dimensions', 1536);
    }
}
