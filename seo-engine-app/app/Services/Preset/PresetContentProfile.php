<?php

declare(strict_types=1);

namespace App\Services\Preset;

use Ofyre\SeoEngine\Contracts\NicheContentProvider;

class PresetContentProfile implements NicheContentProvider
{
    public function __construct(
        private readonly PresetManager $presets,
    ) {}

    public function fallbackPayload(string $keyword, string $cluster, array $blueprint, array $context = []): array
    {
        if ($this->presets->siteProfileDrivesGeneration()) {
            throw new \RuntimeException('La génération de secours est désactivée quand le profil métier pilote la rédaction.');
        }

        return $this->presets->resolveContentProfile()->fallbackPayload($keyword, $cluster, $blueprint, $context);
    }

    public function extraSection(string $keyword, array $blueprint, array $context = []): string
    {
        if ($this->presets->siteProfileDrivesGeneration()) {
            return '';
        }

        return $this->presets->resolveContentProfile()->extraSection($keyword, $blueprint, $context);
    }

    public function ensureContentDepth(string $content, array $blueprint, array $context = []): string
    {
        if ($this->presets->siteProfileDrivesGeneration() || ($context['preserve_ai_narrative'] ?? false)) {
            return $content;
        }

        return $this->presets->resolveContentProfile()->ensureContentDepth($content, $blueprint, $context);
    }
}
