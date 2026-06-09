<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Copilot\BusinessCopilotService;
use App\Models\SeoRecommendation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessCopilotServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_ranks_gsc_opportunity_in_business_language(): void
    {
        $payload = app(BusinessCopilotService::class)->build(collect([
            [
                'site_id' => 'amiantix',
                'site_name' => 'Amiantix',
                'type' => 'near_top_10',
                'label' => 'Diagnostic amiante avant travaux',
                'slug' => 'diagnostic-amiante-avant-travaux',
                'page_id' => 12,
                'query' => 'diagnostic amiante avant travaux',
                'reason' => 'ignored',
                'action' => 'rafraichir la page',
                'priority_level' => 'high',
                'priority_label' => 'Priorite haute',
                'priority_score' => 640,
                'action_state' => 'ready',
                'action_state_label' => 'Actionnable maintenant',
                'pending_suggestion' => false,
                'metrics' => [
                    'impressions' => 800,
                    'ctr' => 1.8,
                    'position' => 11.2,
                ],
            ],
        ]), collect());

        $top = $payload['top_action'];
        $this->assertNotNull($top);
        $this->assertSame(1, $top['rank']);
        $this->assertStringContainsString('Actualiser', (string) $top['headline']);
        $this->assertStringContainsString('diagnostic amiante avant travaux', (string) $top['subject']);
        $this->assertGreaterThan(0, (int) $top['monthly_gain_visitors']);
        $this->assertStringContainsString('visiteurs/mois', (string) $top['gain_display']);
        $this->assertStringNotContainsString('CTR', (string) $top['problem_plain']);
        $this->assertStringNotContainsString('impressions', strtolower((string) $top['why_plain']));
    }

    public function test_prefers_higher_gain_action_first(): void
    {
        $payload = app(BusinessCopilotService::class)->build(collect([
            [
                'site_id' => 'demo',
                'site_name' => 'Demo',
                'type' => 'low_ctr',
                'label' => 'Page faible clic',
                'slug' => 'page-faible',
                'page_id' => 1,
                'query' => null,
                'reason' => '',
                'action' => 'relancer le CTR',
                'priority_score' => 300,
                'action_state' => 'ready',
                'pending_suggestion' => false,
                'metrics' => ['impressions' => 120, 'ctr' => 0.8, 'position' => 14],
            ],
            [
                'site_id' => 'demo',
                'site_name' => 'Demo',
                'type' => 'near_top_10',
                'label' => 'Page proche top 10',
                'slug' => 'page-top10',
                'page_id' => 2,
                'query' => 'devis plomberie lyon',
                'reason' => '',
                'action' => 'rafraichir la page',
                'priority_score' => 500,
                'action_state' => 'ready',
                'pending_suggestion' => false,
                'metrics' => ['impressions' => 900, 'ctr' => 2.1, 'position' => 10.8],
            ],
        ]), collect());

        $this->assertSame('devis plomberie lyon', $payload['top_action']['subject'] ?? null);
        $this->assertCount(2, $payload['daily_priority']);
    }

    public function test_maps_recommendation_to_business_action(): void
    {
        $recommendation = new SeoRecommendation([
            'site_id' => 'cabinet',
            'site_page_id' => 4,
            'type' => 'create_page',
            'priority' => 15,
            'estimated_impact' => 'high',
            'difficulty' => 'high',
            'title' => 'Expand cluster: mise en demeure loyer',
            'reasoning' => 'Technical reasoning',
            'suggested_action' => 'Create a dedicated supporting page.',
            'status' => 'pending',
            'meta_json' => [
                'context_label' => 'mise en demeure impayé loyer',
                'impact_estimate' => [
                    'monthly_gain_min' => 40,
                    'monthly_gain_max' => 90,
                ],
            ],
        ]);
        $recommendation->id = 99;

        $payload = app(BusinessCopilotService::class)->build(collect(), collect([$recommendation]));

        $this->assertSame('Créer ce contenu', $payload['top_action']['headline'] ?? null);
        $this->assertTrue($payload['top_action']['apply_ready'] ?? false);
        $this->assertSame('generate', $payload['top_action']['apply_workflow'] ?? null);
    }

    public function test_marks_unmapped_gsc_page_as_manual_apply(): void
    {
        $payload = app(BusinessCopilotService::class)->build(collect([
            [
                'site_id' => 'amiantix',
                'site_name' => 'Amiantix',
                'type' => 'near_top_10',
                'label' => 'Faq',
                'slug' => 'faq',
                'page_id' => null,
                'query' => null,
                'reason' => '',
                'action' => 'rafraichir la page',
                'priority_score' => 500,
                'action_state' => 'ready',
                'pending_suggestion' => false,
                'metrics' => ['impressions' => 23, 'ctr' => 1.2, 'position' => 9],
            ],
        ]), collect());

        $this->assertSame('rewrite', $payload['top_action']['apply_workflow'] ?? null);
        $this->assertFalse($payload['top_action']['apply_ready'] ?? true);
        $this->assertStringContainsString('/publications', (string) ($payload['top_action']['apply_href'] ?? ''));
        $this->assertStringContainsString('slug=faq', (string) ($payload['top_action']['apply_href'] ?? ''));
    }
}
