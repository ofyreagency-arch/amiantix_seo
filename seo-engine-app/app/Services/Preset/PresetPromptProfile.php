<?php

declare(strict_types=1);

namespace App\Services\Preset;

use App\SeoPresets\SiteAware\SiteProfilePromptContext;
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

    public function generationCorePrompt(string $keyword, string $cluster, array $blueprint, array $editorialSections, array $expectedSignals): string
    {
        $profile = $this->presets->resolvePromptProfile();
        $base = $profile->generationCorePrompt($keyword, $cluster, $blueprint, $editorialSections, $expectedSignals);

        if ($this->presets->siteProfileDrivesGeneration()) {
            return $base;
        }

        $context = SiteProfilePromptContext::block();

        return $context !== '' ? $context."\n\n".$base : $base;
    }

    public function generationFaqPrompt(string $keyword, string $cluster, array $blueprint, string $title, string $metaDescription, string $h1, string $content): string
    {
        return $this->presets->resolvePromptProfile()->generationFaqPrompt($keyword, $cluster, $blueprint, $title, $metaDescription, $h1, $content);
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
