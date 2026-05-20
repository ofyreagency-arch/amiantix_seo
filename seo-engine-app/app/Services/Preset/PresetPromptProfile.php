<?php

declare(strict_types=1);

namespace App\Services\Preset;

use Ofyre\SeoEngine\Contracts\PromptProfileProvider;

class PresetPromptProfile implements PromptProfileProvider
{
    public function __construct(
        private readonly PresetManager $presets,
    ) {}

    public function generationPrompt(string $keyword, string $cluster, array $blueprint, array $editorialSections, array $expectedSignals): string
    {
        return $this->presets->resolvePromptProfile()->generationPrompt($keyword, $cluster, $blueprint, $editorialSections, $expectedSignals);
    }

    public function improvementPrompt(object $page, array $blueprint, array $audit, array $editorialSections, array $expectedSignals): string
    {
        return $this->presets->resolvePromptProfile()->improvementPrompt($page, $blueprint, $audit, $editorialSections, $expectedSignals);
    }

    public function rewritePrompt(object $page, string $mode): string
    {
        return $this->presets->resolvePromptProfile()->rewritePrompt($page, $mode);
    }

    public function fallbackRewrite(object $page, string $mode): array
    {
        return $this->presets->resolvePromptProfile()->fallbackRewrite($page, $mode);
    }
}
