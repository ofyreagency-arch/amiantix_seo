<?php

declare(strict_types=1);

namespace App\SeoPresets\Amiantix;

use Ofyre\SeoEngine\Contracts\PromptProfileProvider;

class AmiantixPromptProfile implements PromptProfileProvider
{
    private \Ofyre\SeoEngine\Examples\AmiantixPreset\AmiantixPromptProfile $inner;

    public function __construct()
    {
        $this->inner = new \Ofyre\SeoEngine\Examples\AmiantixPreset\AmiantixPromptProfile();
    }

    public function generationPrompt(string $keyword, string $cluster, array $blueprint, array $editorialSections, array $expectedSignals): string
    {
        return $this->inner->generationPrompt($keyword, $cluster, $blueprint, $editorialSections, $expectedSignals);
    }

    public function improvementPrompt(object $page, array $blueprint, array $audit, array $editorialSections, array $expectedSignals): string
    {
        return $this->inner->improvementPrompt($page, $blueprint, $audit, $editorialSections, $expectedSignals);
    }

    public function rewritePrompt(object $page, string $mode): string
    {
        return $this->inner->rewritePrompt($page, $mode);
    }

    public function fallbackRewrite(object $page, string $mode): array
    {
        return $this->inner->fallbackRewrite($page, $mode);
    }
}
