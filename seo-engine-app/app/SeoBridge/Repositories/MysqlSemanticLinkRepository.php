<?php

declare(strict_types=1);

namespace App\SeoBridge\Repositories;

use App\Models\SeoSemanticLink;
use Ofyre\SeoEngine\Contracts\SemanticLinkRepository;

class MysqlSemanticLinkRepository implements SemanticLinkRepository
{
    public function replaceInternalLinkSuggestions(string $sourceKey, array $suggestions): int
    {
        return $this->replace('internal_link', $sourceKey, $suggestions);
    }

    public function internalLinkSuggestions(string $sourceKey, int $limit = 4): array
    {
        return $this->fetch('internal_link', $sourceKey, $limit);
    }

    public function replaceCannibalizationRisks(string $sourceKey, array $risks): int
    {
        return $this->replace('cannibalization', $sourceKey, $risks);
    }

    public function cannibalizationRisks(string $sourceKey, int $limit = 5): array
    {
        return $this->fetch('cannibalization', $sourceKey, $limit);
    }

    public function replaceQueryPageMatches(string $sourceKey, array $matches): int
    {
        return $this->replace('query_match', $sourceKey, $matches);
    }

    public function queryPageMatches(string $sourceKey, int $limit = 6): array
    {
        return $this->fetch('query_match', $sourceKey, $limit);
    }

    private function replace(string $relationType, string $sourceKey, array $items): int
    {
        SeoSemanticLink::query()
            ->where('relation_type', $relationType)
            ->where('source_key', $sourceKey)
            ->delete();

        foreach ($items as $item) {
            SeoSemanticLink::query()->create([
                'relation_type' => $relationType,
                'source_key' => $sourceKey,
                'source_id' => isset($item['source_id']) ? (int) $item['source_id'] : null,
                'target_key' => (string) ($item['target_key'] ?? ''),
                'target_id' => isset($item['target_id']) ? (int) $item['target_id'] : null,
                'label' => (string) ($item['label'] ?? ''),
                'url' => (string) ($item['url'] ?? ''),
                'reason' => (string) ($item['reason'] ?? ''),
                'similarity_score' => (float) ($item['similarity_score'] ?? 0),
                'meta_json' => $item['meta'] ?? [],
            ]);
        }

        return count($items);
    }

    private function fetch(string $relationType, string $sourceKey, int $limit): array
    {
        return SeoSemanticLink::query()
            ->where('relation_type', $relationType)
            ->where('source_key', $sourceKey)
            ->orderByDesc('similarity_score')
            ->limit($limit)
            ->get()
            ->map(static fn (SeoSemanticLink $link): array => [
                'label' => $link->label,
                'url' => $link->url,
                'reason' => $link->reason,
                'similarity_score' => (float) $link->similarity_score,
                'meta' => $link->meta_json ?? [],
            ])
            ->all();
    }
}
