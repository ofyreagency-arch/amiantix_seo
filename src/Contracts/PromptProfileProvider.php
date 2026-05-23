<?php

declare(strict_types=1);

namespace Ofyre\SeoEngine\Contracts;

interface PromptProfileProvider
{
    /**
     * @param  array<string,mixed>  $blueprint
     */
    public function generationPrompt(string $keyword, string $cluster, array $blueprint, array $editorialSections, array $expectedSignals): string;

    /**
     * @param  array<string,mixed>  $blueprint
     */
    public function generationCorePrompt(string $keyword, string $cluster, array $blueprint, array $editorialSections, array $expectedSignals): string;

    /**
     * @param  array<string,mixed>  $blueprint
     */
    public function generationFaqPrompt(string $keyword, string $cluster, array $blueprint, string $title, string $metaDescription, string $h1, string $content): string;

    /**
     * @param  array<string,mixed>  $blueprint
     * @param  array<string,mixed>  $audit
     */
    public function improvementPrompt(object $page, array $blueprint, array $audit, array $editorialSections, array $expectedSignals): string;

    public function rewritePrompt(object $page, string $mode): string;

    /**
     * @return array<string,mixed>
     */
    public function fallbackRewrite(object $page, string $mode): array;
}
