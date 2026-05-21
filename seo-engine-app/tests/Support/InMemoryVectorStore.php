<?php

declare(strict_types=1);

namespace Tests\Support;

use Ofyre\SeoEngine\Contracts\VectorStore;

class InMemoryVectorStore implements VectorStore
{
    /** @var array<string,object> */
    private array $items = [];

    public function find(string $entityType, string $entityKey): ?object
    {
        return $this->items[$this->key($entityType, $entityKey)] ?? null;
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
        $object = (object) [
            'entity_type' => $entityType,
            'entity_key' => $entityKey,
            'entity_id' => $entityId,
            'source_text' => $sourceText,
            'source_hash' => $sourceHash,
            'embedding_model' => $embeddingModel,
            'embedding_version' => $embeddingVersion,
            'embedding_json' => array_values($embedding),
            'meta_json' => $meta,
        ];

        $this->items[$this->key($entityType, $entityKey)] = $object;

        return $object;
    }

    public function forEntityKeys(string $entityType, array $entityKeys): iterable
    {
        return collect($entityKeys)
            ->map(fn (string $entityKey): ?object => $this->find($entityType, $entityKey))
            ->filter()
            ->values()
            ->all();
    }

    private function key(string $entityType, string $entityKey): string
    {
        return $entityType.'|'.$entityKey;
    }
}
