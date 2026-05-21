<?php

declare(strict_types=1);

namespace Tests\Unit;

use Ofyre\SeoEngine\Services\Embeddings\DefaultCannibalizationActionDecider;
use Tests\TestCase;

class DefaultCannibalizationActionDeciderTest extends TestCase
{
    public function test_it_preserves_existing_cannibalization_actions(): void
    {
        $decider = new DefaultCannibalizationActionDecider();

        $this->assertSame(
            'consolidate_weaker_page',
            $decider->decide(true, true, 'diagnostic', 'diagnostic', 300, 100, 0.98)
        );

        $this->assertSame(
            'differentiate_angle',
            $decider->decide(true, true, 'diagnostic', 'diagnostic', 80, 70, 0.94)
        );

        $this->assertSame(
            'clarify_search_intent',
            $decider->decide(false, true, 'diagnostic', 'diagnostic', 80, 70, 0.91)
        );

        $this->assertSame(
            'review_cluster_overlap',
            $decider->decide(true, false, 'diagnostic', 'travaux', 80, 70, 0.90)
        );
    }
}
