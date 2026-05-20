<?php

declare(strict_types=1);

namespace App\SeoBridge\Persisters;

use App\Models\SeoPage;
use App\Models\SeoSuggestion;
use Ofyre\SeoEngine\Contracts\SeoSuggestionPersister;

class DatabaseSeoSuggestionPersister implements SeoSuggestionPersister
{
    public function persist(object $page, array $payload): mixed
    {
        return SeoSuggestion::query()->create($this->normalize($page, $payload));
    }

    public function replacePending(object $page, string $source, array $payload): mixed
    {
        SeoSuggestion::query()
            ->where('seo_page_id', (int) $page->id)
            ->where('source', $source)
            ->where('status', 'pending')
            ->delete();

        return $this->persist($page, $payload);
    }

    public function discardPending(object $page, string $source): int
    {
        return SeoSuggestion::query()
            ->where('seo_page_id', (int) $page->id)
            ->where('source', $source)
            ->where('status', 'pending')
            ->delete();
    }

    private function normalize(object $page, array $payload): array
    {
        $pageId = (int) ($page->id ?? 0);

        if ($pageId <= 0 && $page instanceof SeoPage) {
            $pageId = $page->getKey();
        }

        return [
            'seo_page_id' => $pageId,
            'source' => (string) ($payload['source'] ?? 'runtime'),
            'signals_json' => $payload['signals_json'] ?? [],
            'suggestions_json' => $payload['suggestions_json'] ?? [],
            'status' => (string) ($payload['status'] ?? 'pending'),
        ];
    }
}
