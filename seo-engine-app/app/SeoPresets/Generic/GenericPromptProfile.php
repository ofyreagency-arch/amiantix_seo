<?php

declare(strict_types=1);

namespace App\SeoPresets\Generic;

use Ofyre\SeoEngine\Contracts\PromptProfileProvider;
use Ofyre\SeoEngine\Examples\GenericBusinessPreset\GenericBusinessPromptProfile;

class GenericPromptProfile implements PromptProfileProvider
{
    private GenericBusinessPromptProfile $inner;

    public function __construct()
    {
        $this->inner = new GenericBusinessPromptProfile();
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
