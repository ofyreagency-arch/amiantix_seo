<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\SeoOverride;
use App\Models\SeoPage;
use App\Models\SeoSuggestion;
use App\SeoBridge\Persisters\DatabaseSeoSuggestionPersister;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Ofyre\SeoEngine\Contracts\PromptProfileProvider;
use Ofyre\SeoEngine\Contracts\RewriteAccessDecider;
use Ofyre\SeoEngine\Services\Rewrite\SeoRewriteService;
use Tests\TestCase;

class RewriteSignalRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_rewrite_is_blocked_when_a_human_override_disables_it(): void
    {
        $page = SeoPage::query()->create([
            'site_id' => 'rewrite-site',
            'keyword' => 'diagnostic amiante paris',
            'slug' => 'diagnostic-amiante-paris',
            'cluster' => 'diagnostic',
            'title' => 'Diagnostic amiante Paris',
            'content' => '<p>contenu</p>',
            'seo_score' => 42,
        ]);

        SeoOverride::query()->create([
            'seo_page_id' => $page->id,
            'rewrite_allowed' => false,
            'forced_noindex' => false,
        ]);

        $service = $this->rewriteService(
            new class implements RewriteAccessDecider
            {
                public function rewriteAllowed(object $page): bool
                {
                    return false;
                }
            },
            $this->promptProfile(),
        );

        $suggestion = $service->createSuggestion($page, 'rewrite');

        $this->assertSame('rewrite_blocked', $suggestion->source);
        $this->assertSame('rejected', $suggestion->status);
        $this->assertTrue($suggestion->suggestions_json['blocked']);
        $this->assertStringContainsString('No pending engine signals', $suggestion->signals_json['rewrite_context']);
    }

    public function test_rewrite_fallback_merges_pending_feedback_and_signal_queue_context(): void
    {
        $page = SeoPage::query()->create([
            'site_id' => 'rewrite-site',
            'keyword' => 'diagnostic amiante paris',
            'slug' => 'diagnostic-amiante-paris',
            'cluster' => 'diagnostic',
            'title' => 'Diagnostic amiante Paris',
            'content' => '<p>contenu</p>',
            'seo_score' => 41,
            'indexability_score' => 44,
            'spam_risk' => 'low',
            'internal_links_json' => [],
        ]);

        SeoSuggestion::query()->create([
            'seo_page_id' => $page->id,
            'source' => 'feedback_loop:auto',
            'signals_json' => ['audit' => true],
            'suggestions_json' => [
                'mode' => 'feedback_loop',
                'sections' => ['Ajouter une section sur les délais de repérage.'],
                'faq' => [],
                'internal_links' => [],
                'rationale' => ['page_two_position', 'low_ctr'],
            ],
            'status' => 'pending',
        ]);

        SeoSuggestion::query()->create([
            'seo_page_id' => $page->id,
            'source' => 'signal_queue:auto',
            'signals_json' => ['semantic' => true],
            'suggestions_json' => [
                'mode' => 'signal_queue',
                'sections' => ['Clarifier la différence entre diagnostic et repérage.'],
                'faq' => [
                    [
                        'question' => 'Quand faut-il faire un repérage amiante ?',
                        'answer' => 'Avant certains travaux ou démolitions.',
                    ],
                ],
                'internal_links' => [
                    [
                        'label' => 'Guide repérage amiante',
                        'url' => 'https://example.test/guide-reperage-amiante',
                        'reason' => 'pillar_target',
                    ],
                ],
                'rationale' => ['review_wrong_ranking_page'],
            ],
            'status' => 'pending',
        ]);

        $service = $this->rewriteService(
            new class implements RewriteAccessDecider
            {
                public function rewriteAllowed(object $page): bool
                {
                    return true;
                }
            },
            $this->promptProfile(),
        );

        $suggestion = $service->createSuggestion($page->fresh(['suggestions']), 'improve-indexability');

        $this->assertSame('rewrite_engine:improve-indexability', $suggestion->source);
        $this->assertSame('pending', $suggestion->status);
        $this->assertContains('Ajouter une section sur les délais de repérage.', $suggestion->suggestions_json['sections']);
        $this->assertContains('Clarifier la différence entre diagnostic et repérage.', $suggestion->suggestions_json['sections']);
        $this->assertContains('page_two_position', $suggestion->suggestions_json['rationale']);
        $this->assertContains('review_wrong_ranking_page', $suggestion->suggestions_json['rationale']);
        $this->assertCount(1, $suggestion->suggestions_json['internal_links']);
        $this->assertCount(1, $suggestion->suggestions_json['faq']);
        $this->assertSame(2, $suggestion->suggestions_json['signals_summary']['pending_rewrite_signals']);
        $this->assertSame([
            'feedback_loop:auto' => 1,
            'signal_queue:auto' => 1,
        ], $suggestion->suggestions_json['signals_summary']['sources']);
        $this->assertStringContainsString('feedback_loop:auto=1', $suggestion->signals_json['rewrite_context']);
        $this->assertStringContainsString('signal_queue:auto=1', $suggestion->signals_json['rewrite_context']);
    }

    public function test_invalid_rewrite_mode_falls_back_to_enrich_without_losing_signal_context(): void
    {
        $page = SeoPage::query()->create([
            'site_id' => 'rewrite-site',
            'keyword' => 'diagnostic amiante paris',
            'slug' => 'diagnostic-amiante-paris',
            'cluster' => 'diagnostic',
            'title' => 'Diagnostic amiante Paris',
            'content' => '<p>contenu</p>',
            'seo_score' => 55,
        ]);

        SeoSuggestion::query()->create([
            'seo_page_id' => $page->id,
            'source' => 'feedback_loop:auto',
            'signals_json' => ['audit' => true],
            'suggestions_json' => [
                'mode' => 'feedback_loop',
                'sections' => ['Ajouter un comparatif opérationnel.'],
                'faq' => [],
                'internal_links' => [],
                'rationale' => ['content_too_short'],
            ],
            'status' => 'pending',
        ]);

        $service = $this->rewriteService(
            new class implements RewriteAccessDecider
            {
                public function rewriteAllowed(object $page): bool
                {
                    return true;
                }
            },
            $this->promptProfile(),
        );

        $suggestion = $service->createSuggestion($page->fresh(['suggestions']), 'totally-invalid-mode');

        $this->assertSame('rewrite_engine:enrich', $suggestion->source);
        $this->assertContains('Ajouter un comparatif opérationnel.', $suggestion->suggestions_json['sections']);
        $this->assertSame(1, $suggestion->suggestions_json['signals_summary']['pending_rewrite_signals']);
    }

    public function test_rewrite_replaces_existing_pending_suggestion_for_the_same_mode_instead_of_accumulating_noise(): void
    {
        $page = SeoPage::query()->create([
            'site_id' => 'rewrite-site',
            'keyword' => 'diagnostic amiante paris',
            'slug' => 'diagnostic-amiante-paris',
            'cluster' => 'diagnostic',
            'title' => 'Diagnostic amiante Paris',
            'content' => '<p>contenu</p>',
            'seo_score' => 55,
        ]);

        SeoSuggestion::query()->create([
            'seo_page_id' => $page->id,
            'source' => 'rewrite_engine:enrich',
            'signals_json' => ['legacy' => true],
            'suggestions_json' => ['mode' => 'enrich', 'sections' => ['Ancienne suggestion']],
            'status' => 'pending',
        ]);

        $service = $this->rewriteService(
            new class implements RewriteAccessDecider
            {
                public function rewriteAllowed(object $page): bool
                {
                    return true;
                }
            },
            $this->promptProfile(),
        );

        $suggestion = $service->createSuggestion($page, 'enrich');

        $this->assertSame('rewrite_engine:enrich', $suggestion->source);
        $this->assertDatabaseCount('seo_suggestions', 1);
        $this->assertDatabaseMissing('seo_suggestions', [
            'seo_page_id' => $page->id,
            'source' => 'rewrite_engine:enrich',
            'status' => 'pending',
            'id' => 1,
        ]);
    }

    public function test_rewrite_ignores_non_pending_suggestions_and_deduplicates_signal_payloads(): void
    {
        $page = SeoPage::query()->create([
            'site_id' => 'rewrite-site',
            'keyword' => 'diagnostic amiante paris',
            'slug' => 'diagnostic-amiante-paris',
            'cluster' => 'diagnostic',
            'title' => 'Diagnostic amiante Paris',
            'content' => '<p>contenu</p>',
            'seo_score' => 48,
        ]);

        SeoSuggestion::query()->create([
            'seo_page_id' => $page->id,
            'source' => 'feedback_loop:auto',
            'signals_json' => ['audit' => true],
            'suggestions_json' => [
                'sections' => ['Ajouter une FAQ réglementaire.', 'Ajouter une FAQ réglementaire.'],
                'faq' => [
                    ['question' => 'Quand faut-il faire un repérage amiante ?', 'answer' => 'Avant certains travaux.'],
                ],
                'internal_links' => [
                    ['label' => 'Guide amiante', 'url' => 'https://example.test/guide-amiante', 'reason' => 'semantic_link'],
                ],
                'rationale' => ['low_ctr', 'low_ctr'],
            ],
            'status' => 'pending',
        ]);

        SeoSuggestion::query()->create([
            'seo_page_id' => $page->id,
            'source' => 'signal_queue:auto',
            'signals_json' => ['semantic' => true],
            'suggestions_json' => [
                'sections' => ['Ajouter une FAQ réglementaire.'],
                'faq' => [
                    ['question' => 'Quand faut-il faire un repérage amiante ?', 'answer' => 'Avant certains travaux.'],
                ],
                'internal_links' => [
                    ['label' => 'Guide amiante bis', 'url' => 'https://example.test/guide-amiante', 'reason' => 'pillar_target'],
                ],
                'rationale' => ['review_wrong_ranking_page'],
            ],
            'status' => 'pending',
        ]);

        SeoSuggestion::query()->create([
            'seo_page_id' => $page->id,
            'source' => 'feedback_loop:auto',
            'signals_json' => ['old' => true],
            'suggestions_json' => [
                'sections' => ['Section appliquée qui ne doit plus compter.'],
                'rationale' => ['already_applied'],
            ],
            'status' => 'applied',
        ]);

        $service = $this->rewriteService(
            new class implements RewriteAccessDecider
            {
                public function rewriteAllowed(object $page): bool
                {
                    return true;
                }
            },
            $this->promptProfile(),
        );

        $suggestion = $service->createSuggestion($page->fresh(['suggestions']), 'rewrite');

        $this->assertSame('rewrite_engine:rewrite', $suggestion->source);
        $this->assertCount(2, $suggestion->suggestions_json['sections']);
        $this->assertContains('Ajouter une FAQ réglementaire.', $suggestion->suggestions_json['sections']);
        $this->assertNotContains('Section appliquée qui ne doit plus compter.', $suggestion->suggestions_json['sections']);
        $this->assertCount(1, $suggestion->suggestions_json['faq']);
        $this->assertCount(1, $suggestion->suggestions_json['internal_links']);
        $this->assertCount(4, $suggestion->suggestions_json['rationale']);
        $this->assertContains('low_ctr', $suggestion->suggestions_json['rationale']);
        $this->assertContains('review_wrong_ranking_page', $suggestion->suggestions_json['rationale']);
        $this->assertNotContains('already_applied', $suggestion->suggestions_json['rationale']);
        $this->assertSame(2, $suggestion->suggestions_json['signals_summary']['pending_rewrite_signals']);
    }

    private function rewriteService(RewriteAccessDecider $access, PromptProfileProvider $prompts): SeoRewriteService
    {
        return new class($access, $prompts, new DatabaseSeoSuggestionPersister()) extends SeoRewriteService
        {
            protected function rewriteWithAi(object $page, string $mode): ?array
            {
                return null;
            }
        };
    }

    private function promptProfile(): PromptProfileProvider
    {
        return new class implements PromptProfileProvider
        {
            public function generationPrompt(string $keyword, string $cluster, array $blueprint, array $editorialSections, array $expectedSignals): string
            {
                return 'generation';
            }

            public function improvementPrompt(object $page, array $blueprint, array $audit, array $editorialSections, array $expectedSignals): string
            {
                return 'improvement';
            }

            public function rewritePrompt(object $page, string $mode): string
            {
                return 'rewrite:'.$mode;
            }

            public function fallbackRewrite(object $page, string $mode): array
            {
                return [
                    'mode' => $mode,
                    'title' => null,
                    'meta_description' => null,
                    'h1' => null,
                    'sections' => ['Base rewrite for '.$mode],
                    'faq' => [],
                    'internal_links' => [],
                    'rationale' => ['Base rewrite rationale'],
                    'signals_summary' => [
                        'prompt_received_context' => $page->rewrite_signal_context['pending_count'] ?? 0,
                    ],
                ];
            }
        };
    }
}
