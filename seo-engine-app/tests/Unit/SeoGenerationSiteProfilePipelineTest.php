<?php

declare(strict_types=1);

namespace Tests\Unit;

use Ofyre\SeoEngine\Contracts\ImagePromptProvider;
use Ofyre\SeoEngine\Contracts\InternalLinkProvider;
use Ofyre\SeoEngine\Contracts\NicheBlueprintProvider;
use Ofyre\SeoEngine\Contracts\NicheContentProvider;
use Ofyre\SeoEngine\Contracts\PromptProfileProvider;
use Ofyre\SeoEngine\Services\Generation\SeoGenerationService;
use Tests\TestCase;

class SeoGenerationSiteProfilePipelineTest extends TestCase
{
    public function test_site_profile_mode_skips_post_ai_content_enrichment(): void
    {
        config()->set('seo-engine.require_site_profile', true);
        $this->assertTrue((bool) config('seo-engine.require_site_profile'));

        $spy = new SpyNicheContentProvider();

        $service = new class(
            $this->createMock(NicheBlueprintProvider::class),
            $this->createMock(PromptProfileProvider::class),
            $spy,
            $this->createMock(InternalLinkProvider::class),
            $this->createMock(ImagePromptProvider::class),
        ) extends SeoGenerationService {
            public function exposeEnsurePremiumDepth(array $payload, array $blueprint, string $keyword, string $cluster): array
            {
                return $this->ensurePremiumDepth($payload, $blueprint, $keyword, $cluster);
            }

            public function flagFromConfig(): bool
            {
                return (bool) config('seo-engine.require_site_profile', false);
            }
        };

        $this->assertTrue($service->flagFromConfig());

        [$result] = $service->exposeEnsurePremiumDepth(
            [
                'title' => 'Titre',
                'meta_description' => 'Meta',
                'h1' => 'H1',
                'content' => '<p>Contenu IA inchangé avec 12 m² et 48 h de délai.</p>',
                'faq' => [],
            ],
            ['topic' => 'test'],
            'fuite eau',
            'urgence',
        );

        $this->assertSame(0, $spy->ensureContentDepthCalls);
        $this->assertSame(0, $spy->fallbackPayloadCalls);
        $this->assertSame('<p>Contenu IA inchangé avec 12 m² et 48 h de délai.</p>', $result['content']);
        $this->assertArrayHasKey('schema', $result);
    }
}

final class SpyNicheContentProvider implements NicheContentProvider
{
    public int $ensureContentDepthCalls = 0;

    public int $fallbackPayloadCalls = 0;

    public function fallbackPayload(string $keyword, string $cluster, array $blueprint, array $context = []): array
    {
        $this->fallbackPayloadCalls++;

        return ['faq' => []];
    }

    public function extraSection(string $keyword, array $blueprint, array $context = []): string
    {
        return '';
    }

    public function ensureContentDepth(string $content, array $blueprint, array $context = []): string
    {
        $this->ensureContentDepthCalls++;

        return $content.'<p>bloc injecté</p>';
    }
}
