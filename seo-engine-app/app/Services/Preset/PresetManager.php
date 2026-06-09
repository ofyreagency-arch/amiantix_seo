<?php

declare(strict_types=1);

namespace App\Services\Preset;

use App\Presets\Signals\AmiantixContentSignalProvider;
use App\Presets\Signals\GenericContentSignalProvider;
use App\Runtime\SeoEngineContext;
use App\SeoPresets\Amiantix\AmiantixBlueprintProvider;
use App\SeoPresets\Amiantix\AmiantixContentProfile;
use App\SeoPresets\Amiantix\AmiantixImagePromptProvider;
use App\SeoPresets\Amiantix\AmiantixInternalLinkProvider;
use App\SeoPresets\Amiantix\AmiantixPromptProfile;
use App\SeoPresets\SiteAware\SiteAwareBlueprintProvider;
use App\SeoPresets\SiteAware\SiteAwareContentProfile;
use App\SeoPresets\SiteAware\SiteAwareContentSignalProvider;
use App\SeoPresets\SiteAware\SiteAwareImagePromptProvider;
use App\SeoPresets\SiteAware\SiteAwareInternalLinkProvider;
use App\SeoPresets\SiteAware\SiteAwarePromptProfile;
use InvalidArgumentException;
use Ofyre\SeoEngine\Contracts\ContentSignalProvider;
use Ofyre\SeoEngine\Contracts\ImagePromptProvider;
use Ofyre\SeoEngine\Contracts\InternalLinkProvider;
use Ofyre\SeoEngine\Contracts\NicheBlueprintProvider;
use Ofyre\SeoEngine\Contracts\NicheContentProvider;
use Ofyre\SeoEngine\Contracts\PromptProfileProvider;

class PresetManager
{
    public function __construct(
        private readonly SeoEngineContext $context,
    ) {}

    public function currentPreset(): string
    {
        return $this->normalizePreset($this->context->preset());
    }

    public function resolveBlueprintProvider(): NicheBlueprintProvider
    {
        return app($this->contractMap($this->currentPreset())['blueprint']);
    }

    public function resolvePromptProfile(): PromptProfileProvider
    {
        return app($this->contractMap($this->currentPreset())['prompt']);
    }

    public function resolveContentProfile(): NicheContentProvider
    {
        return app($this->contractMap($this->currentPreset())['content']);
    }

    public function resolveInternalLinkProvider(): InternalLinkProvider
    {
        return app($this->contractMap($this->currentPreset())['internal_links']);
    }

    public function resolveImagePromptProvider(): ImagePromptProvider
    {
        return app($this->contractMap($this->currentPreset())['image_prompt']);
    }

    public function resolveContentSignalProvider(): ContentSignalProvider
    {
        return app($this->contractMap($this->currentPreset())['content_signals']);
    }

    public function availablePresets(): array
    {
        return [
            'generic' => 'Générique',
            'amiantix' => 'Amiantix',
        ];
    }

    private function contractMap(string $preset): array
    {
        return match ($preset) {
            'amiantix' => [
                'blueprint' => AmiantixBlueprintProvider::class,
                'prompt' => AmiantixPromptProfile::class,
                'content' => AmiantixContentProfile::class,
                'internal_links' => AmiantixInternalLinkProvider::class,
                'image_prompt' => AmiantixImagePromptProvider::class,
                'content_signals' => AmiantixContentSignalProvider::class,
            ],
            'generic' => [
                'blueprint' => SiteAwareBlueprintProvider::class,
                'prompt' => SiteAwarePromptProfile::class,
                'content' => SiteAwareContentProfile::class,
                'internal_links' => SiteAwareInternalLinkProvider::class,
                'image_prompt' => SiteAwareImagePromptProvider::class,
                'content_signals' => SiteAwareContentSignalProvider::class,
            ],
            default => throw new InvalidArgumentException('Unknown SEO preset: '.$preset),
        };
    }

    private function normalizePreset(?string $preset): string
    {
        $value = trim((string) $preset);

        if ($value === '') {
            return 'generic';
        }

        return array_key_exists($value, $this->availablePresets()) ? $value : 'generic';
    }
}
