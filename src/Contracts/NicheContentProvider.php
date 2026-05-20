<?php

declare(strict_types=1);

namespace Ofyre\SeoEngine\Contracts;

interface NicheContentProvider
{
    /**
     * @param  array<string,mixed>  $blueprint
     * @param  array<string,mixed>  $context
     * @return array{title:string,meta_description:string,h1:string,content:string,faq:array<int,array<string,string>>,schema?:array<int,array<string,mixed>>}
     */
    public function fallbackPayload(string $keyword, string $cluster, array $blueprint, array $context = []): array;

    /**
     * @param  array<string,mixed>  $blueprint
     * @param  array<string,mixed>  $context
     */
    public function extraSection(string $keyword, array $blueprint, array $context = []): string;

    /**
     * @param  array<string,mixed>  $blueprint
     * @param  array<string,mixed>  $context
     */
    public function ensureContentDepth(string $content, array $blueprint, array $context = []): string;
}
