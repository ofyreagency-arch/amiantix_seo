<?php

declare(strict_types=1);

namespace App\SeoBridge\VectorStore;

use App\Models\SeoVector;
use Ofyre\SeoEngine\Contracts\VectorStore;

class MysqlVectorStore implements VectorStore
{
    public function find(string $entityType, string $entityKey): ?object
    {
        return SeoVector::query()
            ->where('entity_type', $entityType)
            ->where('entity_key', $entityKey)
            ->first();
    }

    public function upsert(
        string $entityType,
        string $entityKey,
        ?int $entityId,
        string $sourceText,
        string $sourceHash,
        string $embeddingModel,
        string $embeddingVersion,
        array $embedding,
        array $meta = [],
    ): object {
        return tap(
            SeoVector::query()->firstOrNew([
                'entity_type' => $entityType,
                'entity_key' => $entityKey,
            ]),
            function (SeoVector $vector) use ($entityId, $sourceText, $sourceHash, $embeddingModel, $embeddingVersion, $embedding, $meta): void {
                $vector->fill([
                    'entity_id' => $entityId,
                    'source_text' => $sourceText,
                    'source_hash' => $sourceHash,
                    'embedding_model' => $embeddingModel,
                    'embedding_version' => $embeddingVersion,
                    'embedding_json' => array_values(array_map(static fn (mixed $value): float => (float) $value, $embedding)),
                    'meta_json' => $meta,
                ])->save();
            }
        );
    }

    public function forEntityKeys(string $entityType, array $entityKeys): iterable
    {
        return SeoVector::query()
            ->where('entity_type', $entityType)
            ->whereIn('entity_key', $entityKeys)
            ->get();
    }
}
