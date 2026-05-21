<?php

declare(strict_types=1);

namespace Tests\Support;

use Ofyre\SeoEngine\Contracts\EmbeddingProvider;

class FakeEmbeddingProvider implements EmbeddingProvider
{
    public function embed(string $text): array
    {
        $text = mb_strtolower($text);

        return match (true) {
            str_contains($text, 'query: diagnostic amiante paris') || str_contains($text, 'diagnostic amiante paris') => [0.99, 0.01, 0.0],
            str_contains($text, 'title: repérage amiante paris') => [0.7, 0.3, 0.0],
            str_contains($text, 'title: diagnostic amiante') => [1.0, 0.0, 0.0],
            str_contains($text, 'title: désamiantage bâtiment') || str_contains($text, 'title: desamiantage batiment') => [0.1, 0.95, 0.0],
            str_contains($text, 'repérage') && str_contains($text, 'amiante') => [0.7, 0.3, 0.0],
            str_contains($text, 'diagnostic') && str_contains($text, 'amiante') => [1.0, 0.0, 0.0],
            str_contains($text, 'désamiantage') || str_contains($text, 'desamiantage') => [0.1, 0.95, 0.0],
            default => [0.2, 0.2, 0.6],
        };
    }

    public function model(): string
    {
        return 'fake-embedding-model';
    }

    public function dimensions(): int
    {
        return 3;
    }
}
