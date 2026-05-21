<?php

declare(strict_types=1);

namespace Tests\Unit;

use Ofyre\SeoEngine\Services\Embeddings\ConfigDrivenSemanticLinkPolicy;
use Tests\TestCase;

class ConfigDrivenSemanticLinkPolicyTest extends TestCase
{
    public function test_it_preserves_same_cluster_same_intent_and_pillar_heuristics(): void
    {
        config()->set('seo-engine.embeddings.internal_link_threshold', 0.84);
        config()->set('seo-engine.embeddings.policy.same_cluster_bonus', 0.04);
        config()->set('seo-engine.embeddings.policy.cross_cluster_penalty', 0.03);
        config()->set('seo-engine.embeddings.policy.same_intent_bonus', 0.05);
        config()->set('seo-engine.embeddings.policy.generic_target_penalty', 0.02);
        config()->set('seo-engine.embeddings.policy.pillar_target_bonus', 0.02);
        config()->set('seo-engine.embeddings.policy.strong_target_penalty', 0.02);
        config()->set('seo-engine.embeddings.policy.strong_target_inbound_threshold', 6);
        config()->set('seo-engine.embeddings.policy.intent_families', [
            'diagnostic' => ['diagnostic', 'repérage'],
        ]);
        config()->set('seo-engine.embeddings.policy.generic_terms', ['amiante']);
        config()->set('seo-engine.embeddings.policy.pillar_terms', ['diagnostic']);

        $policy = new ConfigDrivenSemanticLinkPolicy();

        $source = (object) [
            'slug' => 'diagnostic-amiante-paris',
            'keyword' => 'diagnostic amiante paris',
            'title' => 'Diagnostic amiante Paris',
            'cluster' => 'diagnostic',
            'internal_inbound_count' => 1,
        ];

        $target = (object) [
            'slug' => 'diagnostic-amiante',
            'keyword' => 'diagnostic amiante',
            'title' => 'Diagnostic amiante',
            'cluster' => 'diagnostic',
            'internal_inbound_count' => 2,
        ];

        $result = $policy->evaluate($source, $target, 0.82);

        $this->assertTrue($result['accepted']);
        $this->assertSame(['semantic_similarity', 'same_cluster', 'same_intent', 'pillar_target'], $result['reasons']);
        $this->assertTrue($result['meta']['cluster_match']);
        $this->assertTrue($result['meta']['intent_match']);
        $this->assertTrue($result['meta']['target_is_pillar']);
        $this->assertGreaterThan(0.84, $result['score']);
    }
}
