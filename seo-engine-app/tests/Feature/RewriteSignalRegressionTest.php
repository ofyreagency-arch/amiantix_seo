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
                '<section><h2>Contexte et obligations</h2><p>'.str_repeat('Contexte documentaire et obligations terrain. ', 70).'</p></section>',
                '<section><h2>Processus d intervention et coordination</h2><p>'.str_repeat('Workflow, coordination, hypothese de travaux et preparation. ', 70).'</p></section>',
                '<section><h2>Documents et preuves a conserver</h2><p>'.str_repeat('Pieces, traces, diffusion et validations documentaires. ', 70).'</p></section>',
                '<section><h2>Matrice de controle documentaire et terrain</h2><p>Controle.</p><table><tr><td>amiante</td></tr></table></section>',
                '<section><h2>Passer du constat a une intervention maitrisée</h2><p>'.str_repeat('Decision, arbitrage et verification finale. ', 70).'</p></section>',
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

    public function test_rewrite_merge_helper_preserves_rich_content_and_appends_only_new_structured_sections(): void
    {
        $service = new class(
            new class implements RewriteAccessDecider
            {
                public function rewriteAllowed(object $page): bool
                {
                    return true;
                }
            },
            $this->promptProfile(),
            new DatabaseSeoSuggestionPersister(),
            app(\Ofyre\SeoEngine\Contracts\NicheBlueprintProvider::class),
            app(\Ofyre\SeoEngine\Contracts\NicheContentProvider::class),
        ) extends SeoRewriteService
        {
            protected function rewriteWithAi(object $page, string $mode): ?array
            {
                return [
                    'title' => null,
                    'meta_description' => null,
                    'h1' => null,
                    'proposed_content' => '<section><h2>Checklist operationnelle avant intervention</h2><p>Verifier les hypotheses de travaux, la diffusion des pieces et les acces sensibles.</p></section>',
                    'sections' => ['Ajouter une checklist opérationnelle avant intervention.'],
                    'faq' => [],
                    'internal_links' => [],
                    'rationale' => ['Patch local plus utile qu une réécriture totale.'],
                ];
            }
        };

        $current = implode('', [
            '<section><h2>Contexte et obligations</h2><p>'.str_repeat('Contexte documentaire et obligations terrain. ', 20).'</p></section>',
            '<section><h2>Processus d intervention et coordination</h2><p>'.str_repeat('Workflow, coordination, hypothese de travaux et preparation. ', 20).'</p></section>',
        ]);
        $patch = implode('', [
            '<section><h2>Checklist operationnelle avant intervention</h2><p>Verifier les hypotheses de travaux, la diffusion des pieces et les acces sensibles.</p></section>',
            '<section><h2>Contexte et obligations</h2><p>Cette section ne doit pas etre dupliquee.</p></section>',
        ]);

        $ref = new \ReflectionClass($service);
        $method = $ref->getMethod('mergeSuggestedNarrativePatch');
        $method->setAccessible(true);
        $merged = $method->invoke($service, $current, $patch);

        $this->assertStringContainsString('<h2>Contexte et obligations</h2>', (string) $merged);
        $this->assertStringContainsString('<h2>Checklist operationnelle avant intervention</h2>', (string) $merged);
        $this->assertSame(1, substr_count((string) $merged, '<h2>Contexte et obligations</h2>'));
    }

    public function test_rewrite_surfaces_detected_weak_sections_instead_of_only_global_enrich_flags(): void
    {
        $page = SeoPage::query()->create([
            'site_id' => 'rewrite-site',
            'keyword' => 'diagnostic amiante paris',
            'slug' => 'diagnostic-amiante-paris',
            'cluster' => 'diagnostics',
            'title' => 'Diagnostic amiante Paris',
            'content' => implode('', [
                '<section><h2>Contexte et obligations</h2><p>'.str_repeat('Contexte documentaire et obligations terrain. ', 50).'</p></section>',
                '<section><h2>Documents et preuves a conserver</h2><p>Pieces a verifier.</p></section>',
                '<section><h2>Matrice de controle documentaire et terrain</h2><p>Controle rapide.</p></section>',
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

        $this->assertContains('Documents et preuves a conserver', $suggestion->suggestions_json['sections']);
        $this->assertContains('Matrice de controle documentaire et terrain', $suggestion->suggestions_json['sections']);
        $this->assertSame(
            ['Documents et preuves a conserver', 'Matrice de controle documentaire et terrain'],
            $suggestion->suggestions_json['signals_summary']['weak_sections']
        );
        $this->assertSame(
            ['too_short', 'missing_structure'],
            $suggestion->suggestions_json['signals_summary']['weak_section_reasons']['Documents et preuves a conserver']
        );
        $this->assertSame(
            'developper et structurer cette section avec des listes, sous-parties ou tableaux utiles',
            $suggestion->suggestions_json['signals_summary']['weak_section_instructions']['Documents et preuves a conserver']
        );
        $this->assertSame(
            ['too_short', 'missing_structure'],
            $suggestion->suggestions_json['signals_summary']['weak_section_reasons']['Matrice de controle documentaire et terrain']
        );
        $this->assertSame(
            'developper et structurer cette section avec des listes, sous-parties ou tableaux utiles',
            $suggestion->suggestions_json['signals_summary']['weak_section_instructions']['Matrice de controle documentaire et terrain']
        );
        $this->assertContains(
            'Target the currently weak sections before compacting the full article.',
            $suggestion->suggestions_json['rationale']
        );
    }

    public function test_rewrite_merge_helper_replaces_an_existing_weak_section_when_the_patch_targets_the_same_heading(): void
    {
        $service = new class(
            new class implements RewriteAccessDecider
            {
                public function rewriteAllowed(object $page): bool
                {
                    return true;
                }
            },
            $this->promptProfile(),
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

        $current = implode('', [
            '<section><h2>Contexte et obligations</h2><p>'.str_repeat('Contexte documentaire et obligations terrain. ', 20).'</p></section>',
            '<section><h2>Documents et preuves a conserver</h2><p>Pieces a verifier.</p></section>',
        ]);
        $patch = '<section><h2>Documents et preuves a conserver</h2><ul><li>Tracer les versions diffusees.</li><li>Conserver les validations et reserves formelles.</li></ul><p>Le patch renforce la section existante au lieu de la dupliquer.</p></section>';

        $ref = new \ReflectionClass($service);
        $detect = $ref->getMethod('detectWeakSections');
        $detect->setAccessible(true);
        $weakSections = $detect->invoke($service, $current);

        $merge = $ref->getMethod('mergeSuggestedNarrativePatch');
        $merge->setAccessible(true);
        $merged = $merge->invoke($service, $current, $patch, $weakSections);

        $this->assertSame(['Documents et preuves a conserver'], $weakSections);
        $this->assertSame(1, substr_count((string) $merged, '<h2>Documents et preuves a conserver</h2>'));
        $this->assertStringContainsString('Tracer les versions diffusees.', (string) $merged);
        $this->assertStringNotContainsString('<p>Pieces a verifier.</p>', (string) $merged);
    }

    public function test_rewrite_merge_helper_replaces_a_weak_section_when_the_patch_heading_is_close_but_reworded(): void
    {
        $service = new class(
            new class implements RewriteAccessDecider
            {
                public function rewriteAllowed(object $page): bool
                {
                    return true;
                }
            },
            $this->promptProfile(),
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

        $current = implode('', [
            '<section><h2>Contexte et obligations</h2><p>'.str_repeat('Contexte documentaire et obligations terrain. ', 20).'</p></section>',
            '<section><h2>Documents et preuves a conserver</h2><p>Pieces a verifier.</p></section>',
        ]);
        $patch = '<section><h2>Preuves documentaires et validations a conserver</h2><ul><li>Tracer les versions diffusees.</li><li>Conserver les validations et reserves formelles.</li></ul><p>Le patch renforce la section existante avec un heading reformule.</p></section>';

        $ref = new \ReflectionClass($service);
        $detect = $ref->getMethod('detectWeakSections');
        $detect->setAccessible(true);
        $weakSections = $detect->invoke($service, $current);

        $merge = $ref->getMethod('mergeSuggestedNarrativePatch');
        $merge->setAccessible(true);
        $merged = $merge->invoke($service, $current, $patch, $weakSections);

        $this->assertSame(['Documents et preuves a conserver'], $weakSections);
        $this->assertSame(1, substr_count((string) $merged, '<h2>Contexte et obligations</h2>'));
        $this->assertSame(1, substr_count((string) $merged, '<h2>Preuves documentaires et validations a conserver</h2>'));
        $this->assertStringContainsString('Tracer les versions diffusees.', (string) $merged);
        $this->assertStringNotContainsString('<p>Pieces a verifier.</p>', (string) $merged);
        $this->assertStringNotContainsString('<h2>Documents et preuves a conserver</h2>', (string) $merged);
    }

    public function test_rewrite_merge_helper_does_not_replace_a_weak_section_with_a_close_heading_from_the_wrong_phase(): void
    {
        $service = new class(
            new class implements RewriteAccessDecider
            {
                public function rewriteAllowed(object $page): bool
                {
                    return true;
                }
            },
            $this->promptProfile(),
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

        $current = implode('', [
            '<section><h2>Contexte et obligations</h2><p>'.str_repeat('Contexte documentaire et obligations terrain. ', 20).'</p></section>',
            '<section><h2>Documents et preuves a conserver</h2><p>Pieces a verifier.</p></section>',
        ]);
        $patch = '<section><h2>Questions sur les preuves a conserver</h2><ul><li>Cette section appartient a la phase FAQ.</li></ul><p>Elle ne doit pas remplacer la phase preuve.</p></section>';
        $blueprint = app(\Ofyre\SeoEngine\Contracts\NicheBlueprintProvider::class)->resolve('diagnostic amiante paris', 'diagnostics');

        $ref = new \ReflectionClass($service);
        $detect = $ref->getMethod('detectWeakSections');
        $detect->setAccessible(true);
        $weakSections = $detect->invoke($service, $current);

        $merge = $ref->getMethod('mergeSuggestedNarrativePatch');
        $merge->setAccessible(true);
        $merged = $merge->invoke($service, $current, $patch, $weakSections, $blueprint);

        $this->assertSame(['Documents et preuves a conserver'], $weakSections);
        $this->assertSame(1, substr_count((string) $merged, '<h2>Documents et preuves a conserver</h2>'));
        $this->assertSame(1, substr_count((string) $merged, '<h2>Questions sur les preuves a conserver</h2>'));
        $this->assertStringContainsString('<p>Pieces a verifier.</p>', (string) $merged);
    }

    public function test_rewrite_merge_helper_keeps_a_structurally_weak_section_when_the_patch_does_not_fix_the_missing_structure(): void
    {
        $service = new class(
            new class implements RewriteAccessDecider
            {
                public function rewriteAllowed(object $page): bool
                {
                    return true;
                }
            },
            $this->promptProfile(),
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

        $current = implode('', [
            '<section><h2>Contexte et obligations</h2><p>'.str_repeat('Contexte documentaire et obligations terrain. ', 20).'</p></section>',
            '<section><h2>Documents et preuves a conserver</h2><p>Pieces a verifier.</p></section>',
        ]);
        $patch = '<section><h2>Documents et preuves a conserver</h2><p>Ajouter quelques précisions documentaires reste utile.</p></section>';
        $blueprint = app(\Ofyre\SeoEngine\Contracts\NicheBlueprintProvider::class)->resolve('diagnostic amiante paris', 'diagnostics');

        $ref = new \ReflectionClass($service);
        $detect = $ref->getMethod('detectWeakSections');
        $detect->setAccessible(true);
        $weakSections = $detect->invoke($service, $current);

        $profiles = $ref->getMethod('detectWeakSectionProfiles');
        $profiles->setAccessible(true);
        $weakProfiles = $profiles->invoke($service, $current);

        $merge = $ref->getMethod('mergeSuggestedNarrativePatch');
        $merge->setAccessible(true);
        $merged = $merge->invoke($service, $current, $patch, $weakSections, $blueprint, $weakProfiles);

        $this->assertSame(['Documents et preuves a conserver'], $weakSections);
        $this->assertSame(1, substr_count((string) $merged, '<h2>Documents et preuves a conserver</h2>'));
        $this->assertStringContainsString('<p>Pieces a verifier.</p>', (string) $merged);
        $this->assertStringNotContainsString('<p>Ajouter quelques précisions documentaires reste utile.</p>', (string) $merged);
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
