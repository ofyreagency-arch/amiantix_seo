<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Copilot\BusinessCopilotModificationPlanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessCopilotModificationPlannerTest extends TestCase
{
    use RefreshDatabase;

    public function test_gain_estimates_differ_by_impressions_and_position(): void
    {
        $planner = app(BusinessCopilotModificationPlanner::class);

        $low = $planner->estimateGain('refresh_page', 23, 9.0, 2.0, 'Faq');
        $high = $planner->estimateGain('refresh_page', 180, 11.0, 1.8, 'Diagnostic amiante');

        $this->assertNotSame($low['visitors'], $high['visitors']);
        $this->assertStringContainsString('affichages/mois', $low['basis']);
        $this->assertStringContainsString('visiteurs/mois', $low['display']);
    }

    public function test_plan_for_gsc_includes_sections_topics_and_faq(): void
    {
        $planner = app(BusinessCopilotModificationPlanner::class);

        $plan = $planner->planForGsc(
            'amiantix',
            'near_top_10',
            'Faq',
            'Faq',
            null,
            'faq',
            null,
            null,
        );

        $this->assertNotSame('', $plan['action_label']);
        $this->assertNotSame('', $plan['action_detail']);
        $this->assertNotEmpty($plan['sections']);
        $this->assertNotEmpty($plan['topics']);
        $this->assertNotEmpty($plan['faq']);
        $this->assertNotSame('', $plan['content_summary']);
    }
}
