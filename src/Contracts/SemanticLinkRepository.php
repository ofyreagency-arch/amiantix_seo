<?php

declare(strict_types=1);

namespace Ofyre\SeoEngine\Contracts;

interface SemanticLinkRepository
{
    /**
     * @param  array<int,array{
     *     target_key:string,
     *     target_id:?int,
     *     label:string,
     *     url:string,
     *     reason:string,
     *     similarity_score:float,
     *     meta?:array<string,mixed>
     * }>  $suggestions
     */
    public function replaceInternalLinkSuggestions(string $sourceKey, array $suggestions): int;

    /**
     * @return array<int,array{label:string,url:string,reason:string,similarity_score:float,meta:array<string,mixed>}>
     */
    public function internalLinkSuggestions(string $sourceKey, int $limit = 4): array;

    /**
     * @param  array<int,array{
     *     target_key:string,
     *     target_id:?int,
     *     label:string,
     *     url:string,
     *     reason:string,
     *     similarity_score:float,
     *     meta?:array<string,mixed>
     * }>  $risks
     */
    public function replaceCannibalizationRisks(string $sourceKey, array $risks): int;

    /**
     * @return array<int,array{label:string,url:string,reason:string,similarity_score:float,meta:array<string,mixed>}>
     */
    public function cannibalizationRisks(string $sourceKey, int $limit = 5): array;

    /**
     * @param  array<int,array{
     *     source_key:string,
     *     source_id:?int,
     *     target_key:string,
     *     label:string,
     *     url:string,
     *     reason:string,
     *     similarity_score:float,
     *     meta?:array<string,mixed>
     * }>  $matches
     */
    public function replaceQueryPageMatches(string $sourceKey, array $matches): int;

    /**
     * @return array<int,array{label:string,url:string,reason:string,similarity_score:float,meta:array<string,mixed>}>
     */
    public function queryPageMatches(string $sourceKey, int $limit = 6): array;
}
