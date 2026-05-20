<?php

declare(strict_types=1);

namespace Ofyre\SeoEngine\Contracts;

interface VectorStore
{
    public function find(string $entityType, string $entityKey): ?object;

    /**
     * @param  array<int,float>  $embedding
     * @param  array<string,mixed>  $meta
     */
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
    ): object;

    /**
     * @param  array<int,string>  $entityKeys
     * @return iterable<int,object>
     */
    public function forEntityKeys(string $entityType, array $entityKeys): iterable;
}
