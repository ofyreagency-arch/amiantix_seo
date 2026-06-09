<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Copilot\BusinessCopilotModificationPlanner;
use App\Models\SeoSite;
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

    public function test_plan_for_gsc_uses_niche_faq_without_generic_fallback(): void
    {
        SeoSite::query()->create([
            'site_id' => 'amiantix',
            'name' => 'Amiantix',
            'url' => 'https://amiantix.com',
            'niche' => 'amiante',
            'locale' => 'fr',
            'preset' => 'amiantix',
            'api_token_hash' => hash('sha256', 'token'),
            'is_active' => true,
        ]);

        $planner = app(BusinessCopilotModificationPlanner::class);

        $plan = $planner->planForGsc(
            'amiantix',
            'near_top_10',
            'FAQ amiante',
            'FAQ',
            null,
            'faq',
            'délai repérage amiante',
            null,
        );

        $faqBlob = mb_strtolower(implode(' ', $plan['faq']));

        $this->assertNotSame('', $plan['action_label']);
        $this->assertNotSame('', $plan['action_detail']);
        $this->assertNotEmpty($plan['sections']);
        $this->assertNotEmpty($plan['topics']);
        $this->assertNotEmpty($plan['faq']);
        $this->assertStringNotContainsString('combien de temps pour traiter', $faqBlob);
        $this->assertStringNotContainsString('bloc de preuves et cas pratiques', mb_strtolower(implode(' ', $plan['sections'])));
        if ($plan['title_change'] !== null) {
            $this->assertStringNotContainsString('titre plus concret orienté bénéfice', mb_strtolower((string) $plan['title_change']));
        }
        $this->assertNotSame('', $plan['content_summary']);
    }
}
