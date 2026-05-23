<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\SeoRecommendation;
use App\Models\SeoOverride;
use App\Models\SeoPage;
use App\Models\SeoSitePage;
use App\Models\SeoSuggestion;
use App\ObservedSite\ObservedRewriteBridgeService;
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

    public function test_observed_rewrite_bridge_feeds_runtime_rewrite_with_observed_context(): void
    {
        $page = SeoPage::query()->create([
            'site_id' => 'rewrite-site',
            'keyword' => 'diagnostic amiante paris',
            'slug' => 'diagnostic-amiante-paris',
            'cluster' => 'diagnostic',
            'status' => 'published',
            'title' => 'Diagnostic amiante Paris',
            'content' => '<p>contenu</p>',
            'seo_score' => 58,
        ]);

        $sitePage = SeoSitePage::query()->create([
            'site_id' => 'rewrite-site',
            'normalized_url' => 'https://rewrite.test/diagnostic-amiante-paris',
            'url_hash' => sha1('https://rewrite.test/diagnostic-amiante-paris'),
            'path' => '/diagnostic-amiante-paris',
            'title' => 'Diagnostic amiante Paris',
            'meta_description' => null,
            'canonical_url' => 'https://rewrite.test/diagnostic-amiante-paris',
            'indexability_state' => 'noindex',
            'last_status_code' => 200,
            'latest_word_count' => 180,
            'internal_inlinks' => 0,
            'internal_outlinks' => 1,
            'authority_score' => 0.12,
            'orphan_score' => 0.82,
            'overlap_score' => 0.18,
            'pillar_likelihood' => 0.25,
            'cluster_label' => 'diagnostic',
            'discovered_at' => now(),
            'last_seen_at' => now(),
        ]);

        SeoRecommendation::query()->create([
            'site_id' => 'rewrite-site',
            'site_page_id' => $sitePage->id,
            'type' => 'add_internal_links',
            'priority' => 10,
            'estimated_impact' => 'high',
            'difficulty' => 'low',
            'cluster' => 'diagnostic',
            'title' => 'Reconnect orphan page: Diagnostic amiante Paris',
            'reasoning' => 'Observed graph says the page is isolated.',
            'suggested_action' => 'Add contextual internal links from stronger cluster pages.',
            'status' => 'pending',
            'meta_json' => [
                'context_label' => 'Diagnostic amiante Paris',
                'url' => 'https://rewrite.test/guide-diagnostic-amiante',
            ],
            'generated_at' => now(),
        ]);

        $context = app(ObservedRewriteBridgeService::class)->syncForPage($page);

        $this->assertTrue($context['matched']);
        $this->assertTrue($context['queued']);
        $this->assertSame('warning', $context['state']);
        $this->assertContains('non_indexable', $context['flags']);
        $this->assertDatabaseHas('seo_suggestions', [
            'seo_page_id' => $page->id,
            'source' => 'observed_rewrite:auto',
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
        $this->assertContains('observed_rewrite:auto', array_keys($suggestion->suggestions_json['signals_summary']['sources']));
        $this->assertContains('observed_flag:non_indexable', $suggestion->suggestions_json['rationale']);
        $this->assertContains(
            'Add contextual internal links from stronger cluster pages.',
            $suggestion->suggestions_json['sections']
        );
    }

    public function test_rewrite_fallback_preserves_a_rich_existing_article_instead_of_collapsing_it_into_a_short_patch(): void
    {
        $page = SeoPage::query()->create([
            'site_id' => 'rewrite-site',
            'keyword' => 'diagnostic amiante paris',
            'slug' => 'diagnostic-amiante-paris',
            'cluster' => 'diagnostics',
            'title' => 'Diagnostic amiante Paris',
            'content' => implode('', [
                '<section><h2>Contexte et obligations</h2><p>'.str_repeat('Contexte documentaire et obligations terrain. ', 25).'</p></section>',
                '<section><h2>Processus d intervention et coordination</h2><p>'.str_repeat('Workflow, coordination, hypothese de travaux et preparation. ', 25).'</p></section>',
                '<section><h2>Documents et preuves a conserver</h2><p>'.str_repeat('Pieces, traces, diffusion et validations documentaires. ', 25).'</p></section>',
                '<section><h2>Matrice de controle documentaire et terrain</h2><p>Controle.</p><table><tr><td>amiante</td></tr></table></section>',
                '<section><h2>Passer du constat a une intervention maitrisée</h2><p>'.str_repeat('Decision, arbitrage et verification finale. ', 25).'</p></section>',
            ]),
            'seo_score' => 72,
            'indexability_score' => 88,
            'spam_risk' => 'low',
            'internal_links_json' => [],
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

        $this->assertStringContainsString('<h2>Contexte et obligations</h2>', (string) $suggestion->suggestions_json['proposed_content']);
        $this->assertStringContainsString('<h2>Processus d intervention et coordination</h2>', (string) $suggestion->suggestions_json['proposed_content']);
        $this->assertStringContainsString('<table>', (string) $suggestion->suggestions_json['proposed_content']);
        $this->assertStringNotContainsString('<h2>Passe de réécriture</h2>', (string) $suggestion->suggestions_json['proposed_content']);
    }

    private function rewriteService(RewriteAccessDecider $access, PromptProfileProvider $prompts): SeoRewriteService
    {
        return new class(
            $access,
            $prompts,
            new DatabaseSeoSuggestionPersister(),
            app(\Ofyre\SeoEngine\Contracts\NicheBlueprintProvider::class),
            app(\Ofyre\SeoEngine\Contracts\NicheContentProvider::class),
        ) extends SeoRewriteService
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

            public function generationCorePrompt(string $keyword, string $cluster, array $blueprint, array $editorialSections, array $expectedSignals): string
            {
                return 'generation-core';
            }

            public function generationFaqPrompt(string $keyword, string $cluster, array $blueprint, string $title, string $metaDescription, string $h1, string $content): string
            {
                return 'generation-faq';
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
