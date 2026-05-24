<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\SeoPresets\Amiantix\AmiantixBlueprintProvider;
use App\SeoPresets\Amiantix\AmiantixContentProfile;
use App\Models\SeoPage;
use App\Models\SeoSearchConsoleMetric;
use App\Models\SeoSite;
use App\Models\SeoSuggestion;
use App\Runtime\SeoEngineContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Ofyre\SeoEngine\Services\Console\SeoGeneratePageRunner;
use Ofyre\SeoEngine\Services\Rewrite\SeoRewriteService;
use Tests\TestCase;

class AdminPageWorkflowRuntimeTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_show_displays_live_workflow_and_active_suggestion(): void
    {
        $this->withoutVite();

        $site = SeoSite::query()->create([
            'site_id' => 'workflow-site',
            'name' => 'Workflow Site',
            'url' => 'https://workflow-site.test',
            'niche' => 'amiante',
            'locale' => 'fr',
            'preset' => 'amiantix',
            'api_token_hash' => hash('sha256', 'token'),
            'is_active' => true,
        ]);

        $page = SeoPage::query()->create([
            'site_id' => $site->site_id,
            'keyword' => 'diagnostic amiante paris',
            'slug' => 'diagnostic-amiante-paris',
            'status' => 'draft',
            'title' => 'Diagnostic amiante Paris',
            'content' => '<p>Contenu initial.</p>',
            'seo_score' => 58,
            'quality_score' => 100,
            'indexability_score' => 64,
            'image_quality_score' => 30,
        ]);

        SeoSearchConsoleMetric::query()->create([
            'site_id' => $site->site_id,
            'seo_page_id' => $page->id,
            'metric_date' => now()->toDateString(),
            'window_days' => 28,
            'url' => 'https://workflow-site.test/diagnostic-amiante-paris',
            'impressions' => 42,
            'clicks' => 2,
            'ctr' => 0.0476,
            'position' => 11.2,
            'is_indexed' => true,
        ]);

        SeoSuggestion::query()->create([
            'seo_page_id' => $page->id,
            'source' => 'rewrite_engine:enrich',
            'signals_json' => ['seo_score' => 58],
            'suggestions_json' => [
                'title' => 'Diagnostic amiante Paris : obligations et coordination',
                'sections' => [
                    'Ajouter une checklist opérationnelle avant intervention.',
                    'Préciser les contextes copropriété et ERP.',
                ],
                'rationale' => [
                    'Le contenu est déjà bon sur le fond, mais il manque encore un dernier cran de lisibilité et de workflow.',
                ],
                'signals_summary' => [
                    'rewrite_target_plan' => [
                        [
                            'heading' => 'Documents et preuves a conserver',
                            'phase' => 'proof',
                            'patch_intent' => 'expand_and_structure',
                            'replacement_mode' => 'replace_only_if_patch_adds_structure',
                            'instruction' => 'developper et structurer cette section avec des listes, sous-parties ou tableaux utiles',
                            'reasons' => ['too_short', 'missing_structure'],
                        ],
                    ],
                ],
            ],
            'status' => 'pending',
        ]);

        config()->set('seo-engine.site.preset', 'amiantix');
        config()->set('seo-engine.site.niche', 'amiante');

        $response = $this
            ->withSession(['admin_authenticated' => true])
            ->get(route('admin.pages.show', [$site->site_id, $page->id]));

        $response->assertOk();
        $response->assertSee('Diagnostic de publication moteur');
        $response->assertSee('Workflow éditorial');
        $response->assertSee('Source observée');
        $response->assertSee('Suggestion active');
        $response->assertSee('Appliquer à la page');
        $response->assertSee('Plan de patch ciblé');
        $response->assertSee('Pourquoi ça baisse');
        $response->assertSee('1 section(s) faible(s) tirent la page vers le bas.');
        $response->assertSee('Documents et preuves a conserver');
        $response->assertSee('phase proof');
        $response->assertSee('expand_and_structure');
        $response->assertSee('replace_only_if_patch_adds_structure');
        $response->assertSee('too_short');
        $response->assertSee('missing_structure');
    }

    public function test_generate_creates_a_new_page_when_a_keyword_slug_collides_with_an_existing_blog(): void
    {
        $site = SeoSite::query()->create([
            'site_id' => 'workflow-site',
            'name' => 'Workflow Site',
            'url' => 'https://workflow-site.test',
            'niche' => 'amiante',
            'locale' => 'fr',
            'preset' => 'amiantix',
            'api_token_hash' => hash('sha256', 'token'),
            'is_active' => true,
        ]);

        config()->set('seo-engine.site.preset', 'amiantix');
        config()->set('seo-engine.site.niche', 'amiante');

        $this->withSession(['admin_authenticated' => true])
            ->post(route('admin.pages.generate', $site->site_id), [
                'keyword' => 'Diagnostic Amiante Paris',
                'status' => 'draft',
            ])
            ->assertRedirect();

        $this->withSession(['admin_authenticated' => true])
            ->post(route('admin.pages.generate', $site->site_id), [
                'keyword' => 'Diagnostic amiante paris!',
                'status' => 'draft',
            ])
            ->assertRedirect();

        $pages = SeoPage::query()
            ->where('site_id', $site->site_id)
            ->orderBy('id')
            ->get(['keyword', 'slug']);

        $this->assertCount(2, $pages);
        $this->assertSame('diagnostic-amiante-paris', $pages[0]->slug);
        $this->assertSame('diagnostic-amiante-paris-2', $pages[1]->slug);
    }

    public function test_generate_marks_a_page_as_fallback_and_surfaces_the_ai_error(): void
    {
        Http::fake([
            'https://api.openai.com/v1/responses' => Http::failedConnection('cURL error 60: SSL certificate problem'),
        ]);

        config()->set('services.openai.api_key', 'test-key');
        config()->set('services.openai.model', 'gpt-4o-mini');

        $site = SeoSite::query()->create([
            'site_id' => 'workflow-site',
            'name' => 'Workflow Site',
            'url' => 'https://workflow-site.test',
            'niche' => 'amiante',
            'locale' => 'fr',
            'preset' => 'amiantix',
            'api_token_hash' => hash('sha256', 'token'),
            'is_active' => true,
        ]);

        app(SeoEngineContext::class)->loadFromSite($site);

        $result = app(SeoGeneratePageRunner::class)->run('Danger sante amiante', 'draft', false);
        $page = $result['page'];

        $this->assertInstanceOf(SeoPage::class, $page);
        $this->assertSame('fallback', $page->generation_source);
        $this->assertStringStartsWith('Connexion OpenAI impossible', (string) $page->generation_error);
        $this->assertSame('network_error', $page->generation_trace_json['error_type'] ?? null);
    }

    public function test_generate_marks_a_page_as_ai_when_openai_payload_is_complete(): void
    {
        Http::fake([
            'https://api.openai.com/v1/responses' => Http::response([
                'output' => [[
                    'content' => [[
                        'text' => json_encode([
                            'title' => 'Danger Sante Amiante : obligations et coordination',
                            'meta_description' => 'Une meta orientée coordination, preuves et arbitrages terrain.',
                            'h1' => 'Danger Sante Amiante : ce qu il faut cadrer',
                            'content' => '<section><h2>Contexte</h2><p>Contenu expert terrain.</p></section>',
                            'faq' => [
                                ['question' => 'Q1', 'answer' => 'A1'],
                                ['question' => 'Q2', 'answer' => 'A2'],
                                ['question' => 'Q3', 'answer' => 'A3'],
                                ['question' => 'Q4', 'answer' => 'A4'],
                                ['question' => 'Q5', 'answer' => 'A5'],
                            ],
                            'schema' => [
                                ['@context' => 'https://schema.org', '@type' => 'Article'],
                                ['@context' => 'https://schema.org', '@type' => 'FAQPage'],
                            ],
                        ], JSON_THROW_ON_ERROR),
                    ]],
                ]],
            ], 200),
        ]);

        config()->set('services.openai.api_key', 'test-key');
        config()->set('services.openai.model', 'gpt-4o-mini');

        $site = SeoSite::query()->create([
            'site_id' => 'workflow-site',
            'name' => 'Workflow Site',
            'url' => 'https://workflow-site.test',
            'niche' => 'amiante',
            'locale' => 'fr',
            'preset' => 'amiantix',
            'api_token_hash' => hash('sha256', 'token'),
            'is_active' => true,
        ]);

        app(SeoEngineContext::class)->loadFromSite($site);

        $result = app(SeoGeneratePageRunner::class)->run('Danger sante amiante', 'draft', false);
        $page = $result['page'];

        $this->assertInstanceOf(SeoPage::class, $page);
        $this->assertContains($page->generation_source, ['ai', 'hybrid']);
        $this->assertNull($page->generation_error);
        $this->assertContains('title', $page->generation_trace_json['returned_keys'] ?? []);
    }

    public function test_generate_persists_missing_keys_when_openai_returns_a_partial_payload(): void
    {
        Http::fake([
            'https://api.openai.com/v1/responses' => Http::response([
                'output' => [[
                    'content' => [[
                        'text' => json_encode([
                            'title' => 'Plan de retrait amiante en copropriete',
                            'meta_description' => 'Meta partielle',
                            'h1' => 'Plan de retrait amiante',
                            'content' => '<section><h2>Contexte</h2><p>Contenu.</p></section>',
                        ], JSON_THROW_ON_ERROR),
                    ]],
                ]],
            ], 200),
        ]);

        config()->set('services.openai.api_key', 'test-key');
        config()->set('services.openai.model', 'gpt-4o-mini');

        $site = SeoSite::query()->create([
            'site_id' => 'workflow-site',
            'name' => 'Workflow Site',
            'url' => 'https://workflow-site.test',
            'niche' => 'amiante',
            'locale' => 'fr',
            'preset' => 'amiantix',
            'api_token_hash' => hash('sha256', 'token'),
            'is_active' => true,
        ]);

        app(SeoEngineContext::class)->loadFromSite($site);

        $page = app(SeoGeneratePageRunner::class)->run('Plan de retrait amiante en copropriete', 'draft', false)['page'];

        $this->assertSame('hybrid', $page->generation_source);
        $this->assertStringContainsString('étape faq', (string) $page->generation_error);
        $this->assertContains('title', $page->generationReturnedKeys());
        $this->assertContains('content', $page->generationReturnedKeys());
        $this->assertSame([], $page->generationMissingKeys());
        $this->assertNotEmpty($page->generation_trace_json['steps']['faq']['response_excerpt'] ?? null);
        $this->assertStringContainsString('Plan de retrait amiante', (string) $page->title);
        $this->assertStringStartsWith('<section><h2>Contexte</h2><p>Contenu.</p></section>', (string) $page->content);
        $this->assertCount(5, $page->faq_json ?? []);
    }

    public function test_generate_keeps_ai_content_when_openai_returns_structured_content_array(): void
    {
        Http::fake([
            'https://api.openai.com/v1/responses' => Http::response([
                'output' => [[
                    'content' => [[
                        'text' => json_encode([
                            'title' => 'Plan de retrait amiante en copropriete',
                            'meta_description' => 'Meta complète',
                            'h1' => 'Plan de retrait amiante',
                            'content' => [
                                [
                                    'H2' => 'Contexte et obligations',
                                    'paragraph' => 'Le phasage doit être cadré avant intervention.',
                                    'items' => ['Repérage', 'Coordination', 'Preuves'],
                                ],
                                [
                                    'H2' => 'Blocages fréquents',
                                    'paragraph' => 'Les accès et versions documentaires doivent être alignés.',
                                ],
                            ],
                            'faq' => [
                                ['question' => 'Quand faut-il cadrer ?', 'answer' => 'Avant diffusion du scénario travaux.'],
                                ['question' => 'Qui coordonne ?', 'answer' => 'Le donneur d ordre avec les acteurs du chantier.'],
                                ['question' => 'Pourquoi tracer ?', 'answer' => 'Pour éviter les zones grises documentaires.'],
                                ['question' => 'Que vérifier ?', 'answer' => 'Zones, hypothèses et accès.'],
                                ['question' => 'Quel risque majeur ?', 'answer' => 'Le décalage entre hypothèse et terrain.'],
                            ],
                            'schema' => [['@type' => 'Article'], ['@type' => 'FAQPage']],
                        ], JSON_THROW_ON_ERROR),
                    ]],
                ]],
            ], 200),
        ]);

        $site = SeoSite::query()->create([
            'site_id' => 'workflow-site',
            'name' => 'Workflow Site',
            'url' => 'https://workflow-site.test',
            'niche' => 'amiante',
            'preset' => 'amiantix',
            'locale' => 'fr',
            'api_token_hash' => hash('sha256', 'token'),
            'is_active' => true,
        ]);

        app(SeoEngineContext::class)->loadFromSite($site);

        $page = app(SeoGeneratePageRunner::class)->run('Plan de retrait amiante en copropriete', 'draft', false)['page'];

        $this->assertSame('hybrid', $page->generation_source);
        $this->assertStringContainsString('<h2>Contexte et obligations</h2>', (string) $page->content);
        $this->assertStringContainsString('<ul><li>Repérage</li><li>Coordination</li><li>Preuves</li></ul>', (string) $page->content);
        $this->assertNull($page->generation_error);
    }

    public function test_generate_normalizes_nested_structured_content_fragments_without_crashing(): void
    {
        Http::fake([
            'https://api.openai.com/v1/responses' => Http::sequence()
                ->push([
                    'output' => [[
                        'content' => [[
                            'type' => 'output_text',
                            'text' => json_encode([
                                'title' => 'Coordination amiante en appel d offre',
                                'meta_description' => 'Meta',
                                'h1' => 'Coordination amiante en appel d offre',
                                'content' => [
                                    [
                                        'H2' => 'Contexte',
                                        'paragraph' => [
                                            ['text' => 'Premier bloc.'],
                                            ['text' => 'Deuxieme bloc.'],
                                        ],
                                        'items' => [
                                            ['text' => 'Piece DCE'],
                                            ['text' => 'Planning'],
                                        ],
                                    ],
                                ],
                            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        ]],
                    ]],
                ])
                ->push([
                    'output' => [[
                        'content' => [[
                            'type' => 'output_text',
                            'text' => json_encode([
                                'faq' => [
                                    ['question' => 'Q1', 'answer' => 'R1'],
                                    ['question' => 'Q2', 'answer' => 'R2'],
                                    ['question' => 'Q3', 'answer' => 'R3'],
                                    ['question' => 'Q4', 'answer' => 'R4'],
                                    ['question' => 'Q5', 'answer' => 'R5'],
                                ],
                            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        ]],
                    ]],
                ]),
        ]);

        $site = SeoSite::query()->create([
            'site_id' => 'workflow-site',
            'name' => 'Workflow Site',
            'url' => 'https://workflow-site.test',
            'niche' => 'amiante',
            'locale' => 'fr',
            'api_token_hash' => hash('sha256', 'token'),
            'is_active' => true,
        ]);

        app(SeoEngineContext::class)->loadFromSite($site);

        $page = app(SeoGeneratePageRunner::class)->run('Coordination amiante appel d offre', 'draft', false)['page'];

        $this->assertContains($page->generation_source, ['ai', 'hybrid']);
        $this->assertStringContainsString('<h2>Contexte</h2>', (string) $page->content);
        $this->assertStringContainsString('<p>Premier bloc.'."\n".'Deuxieme bloc.</p>', (string) $page->content);
        $this->assertStringContainsString('<ul><li>Piece DCE</li><li>Planning</li></ul>', (string) $page->content);
    }

    public function test_applying_a_suggestion_updates_page_fields_and_marks_it_applied(): void
    {
        $site = SeoSite::query()->create([
            'site_id' => 'workflow-site',
            'name' => 'Workflow Site',
            'url' => 'https://workflow-site.test',
            'niche' => 'amiante',
            'locale' => 'fr',
            'preset' => 'amiantix',
            'api_token_hash' => hash('sha256', 'token'),
            'is_active' => true,
        ]);

        $page = SeoPage::query()->create([
            'site_id' => $site->site_id,
            'keyword' => 'diagnostic amiante paris',
            'slug' => 'diagnostic-amiante-paris',
            'status' => 'draft',
            'title' => 'Ancien titre',
            'meta_description' => 'Ancienne meta',
            'content' => '<p>Contenu initial.</p>',
        ]);

        $suggestion = SeoSuggestion::query()->create([
            'seo_page_id' => $page->id,
            'source' => 'rewrite_engine:enrich',
            'signals_json' => ['seo_score' => 58],
            'suggestions_json' => [
                'title' => 'Diagnostic amiante Paris : obligations, preuves et coordination',
                'meta_description' => 'Une version plus claire et plus utile pour la publication.',
                'h1' => 'Diagnostic amiante Paris : ce qu il faut cadrer avant intervention',
                'faq' => [
                    ['question' => 'Quand faut-il agir ?', 'answer' => 'Avant la diffusion d un ordre d intervention.'],
                ],
                'internal_links' => [
                    ['url' => '/diagnostic-amiante', 'text' => 'Diagnostic amiante'],
                ],
                'sections' => [
                    'Ajouter une checklist opérationnelle avant intervention.',
                ],
            ],
            'status' => 'pending',
        ]);

        $response = $this
            ->withSession(['admin_authenticated' => true])
            ->post(route('admin.pages.suggestions.apply', [$site->site_id, $page->id, $suggestion->id]));

        $response->assertRedirect(route('admin.pages.show', [$site->site_id, $page->id]));

        $page->refresh();
        $suggestion->refresh();

        $this->assertSame('review', $page->status);
        $this->assertSame('Diagnostic amiante Paris : obligations, preuves et coordination', $page->title);
        $this->assertSame('Une version plus claire et plus utile pour la publication.', $page->meta_description);
        $this->assertSame('Diagnostic amiante Paris : ce qu il faut cadrer avant intervention', $page->h1);
        $this->assertSame('applied', $suggestion->status);
        $this->assertNotNull($suggestion->applied_at);
        $this->assertSame('Quand faut-il agir ?', $page->faq_json[0]['question']);
        $this->assertSame('Diagnostic amiante', $page->internal_links_json[0]['label']);
    }

    public function test_applying_a_regressive_suggestion_preserves_a_strong_article_body_and_faq_depth(): void
    {
        $site = SeoSite::query()->create([
            'site_id' => 'workflow-site',
            'name' => 'Workflow Site',
            'url' => 'https://workflow-site.test',
            'niche' => 'amiante',
            'locale' => 'fr',
            'preset' => 'amiantix',
            'api_token_hash' => hash('sha256', 'token'),
            'is_active' => true,
        ]);

        $blueprint = app(AmiantixBlueprintProvider::class)->resolve('diagnostic amiante paris', 'diagnostics');
        $payload = app(AmiantixContentProfile::class)->fallbackPayload('diagnostic amiante paris', 'diagnostics', $blueprint);

        $page = SeoPage::query()->create([
            'site_id' => $site->site_id,
            'keyword' => 'diagnostic amiante paris',
            'slug' => 'diagnostic-amiante-paris',
            'cluster' => 'diagnostics',
            'status' => 'draft',
            'title' => $payload['title'],
            'h1' => $payload['h1'],
            'meta_description' => $payload['meta_description'],
            'content' => $payload['content'],
            'faq_json' => $payload['faq'],
            'schema_json' => [
                ['@context' => 'https://schema.org', '@type' => 'Article'],
                ['@context' => 'https://schema.org', '@type' => 'FAQPage'],
            ],
            'internal_links_json' => [
                ['url' => '/diagnostic-amiante', 'label' => 'Diagnostic amiante'],
                ['url' => '/reperage-amiante-avant-travaux', 'label' => 'Repérage amiante avant travaux'],
                ['url' => '/ss4-amiante', 'label' => 'SS4 amiante'],
                ['url' => '/dta-amiante', 'label' => 'DTA amiante'],
                ['url' => '/coordination-amiante', 'label' => 'Coordination amiante'],
            ],
            'image_prompt' => 'Illustration editoriale amiante chantier coordination documentaire',
        ]);

        $originalContent = (string) $page->content;
        $originalFaqCount = count($page->faq_json ?? []);
        $originalTitle = (string) $page->title;
        $originalMeta = (string) $page->meta_description;
        $originalLinkCount = count($page->internal_links_json ?? []);

        $suggestion = SeoSuggestion::query()->create([
            'seo_page_id' => $page->id,
            'source' => 'rewrite_engine:enrich',
            'signals_json' => ['seo_score' => 84],
            'suggestions_json' => [
                'title' => 'Diagnostic amiante Paris : version plus claire',
                'meta_description' => 'Meta plus concise.',
                'content' => '<section><h2>Contexte</h2><p>Texte bref.</p></section><section><h2>FAQ</h2><p>Réponse rapide.</p></section>',
                'faq' => [
                    ['question' => 'Q1', 'answer' => 'R1'],
                    ['question' => 'Q2', 'answer' => 'R2'],
                    ['question' => 'Q3', 'answer' => 'R3'],
                ],
                'internal_links' => [
                    ['url' => '/diagnostic-amiante-prix', 'text' => 'Diagnostic amiante prix'],
                ],
            ],
            'status' => 'pending',
        ]);

        $response = $this
            ->withSession(['admin_authenticated' => true])
            ->post(route('admin.pages.suggestions.apply', [$site->site_id, $page->id, $suggestion->id]));

        $response->assertRedirect(route('admin.pages.show', [$site->site_id, $page->id]));
        $response->assertSessionHas('warning');
        $response->assertSessionHas('success', 'Suggestion approuvée : aucun patch éditorial n a été appliqué pour protéger l article actuel.');

        $page->refresh();
        $suggestion->refresh();

        $this->assertSame('review', $page->status);
        $this->assertSame($originalTitle, (string) $page->title);
        $this->assertSame($originalMeta, (string) $page->meta_description);
        $this->assertSame($originalContent, (string) $page->content);
        $this->assertSame($originalFaqCount, count($page->faq_json ?? []));
        $this->assertContains('Content patch skipped because it would degrade the current article quality.', $page->review_issues_json ?? []);
        $this->assertSame('applied', $suggestion->status);
        $this->assertNotNull($suggestion->applied_at);
        $this->assertCount($originalLinkCount, $page->internal_links_json ?? []);
    }

    public function test_rewrite_suggestion_accepts_structured_content_without_triggering_array_to_string_error(): void
    {
        Http::fake([
            'https://api.openai.com/v1/responses' => Http::response([
                'output' => [[
                    'content' => [[
                        'text' => json_encode([
                            'title' => 'Titre réécrit',
                            'meta_description' => 'Meta réécrite',
                            'content' => [
                                [
                                    'H2' => 'Priorité 1',
                                    'paragraph' => 'Ajouter un cadrage documentaire plus concret.',
                                ],
                                [
                                    'H2' => 'Priorité 2',
                                    'paragraph' => 'Clarifier les rôles MOA, MOE et SPS.',
                                ],
                            ],
                            'sections' => [
                                'Ajouter un cadrage documentaire plus concret.',
                                'Clarifier les rôles MOA, MOE et SPS.',
                            ],
                            'rationale' => [
                                'Le contenu doit mieux différencier les blocages terrain.',
                            ],
                            'faq' => [
                                ['question' => 'Quand faut-il agir ?', 'answer' => 'Avant diffusion des hypothèses.'],
                            ],
                            'internal_links' => [
                                ['url' => '/diagnostic-amiante-copropriete', 'label' => 'Diagnostic amiante copropriete'],
                            ],
                        ], JSON_THROW_ON_ERROR),
                    ]],
                ]],
            ], 200),
        ]);

        $site = SeoSite::query()->create([
            'site_id' => 'workflow-site',
            'name' => 'Workflow Site',
            'url' => 'https://workflow-site.test',
            'niche' => 'amiante',
            'preset' => 'amiantix',
            'locale' => 'fr',
            'api_token_hash' => hash('sha256', 'token'),
            'is_active' => true,
        ]);

        app(SeoEngineContext::class)->loadFromSite($site);

        $page = SeoPage::query()->create([
            'site_id' => $site->site_id,
            'keyword' => 'plan de retrait amiante en copropriete',
            'slug' => 'plan-de-retrait-amiante-en-copropriete',
            'cluster' => 'copropriete',
            'status' => 'draft',
            'title' => 'Ancien titre',
            'meta_description' => 'Ancienne meta',
            'content' => '<p>Contenu initial.</p>',
        ]);

        $suggestion = app(SeoRewriteService::class)->createSuggestion($page, 'enrich');

        $this->assertNotNull($suggestion);
        $this->assertStringContainsString('<h2>Priorité 1</h2>', (string) $suggestion->suggestions_json['proposed_content']);
        $this->assertStringContainsString('Ajouter un cadrage documentaire plus concret.', (string) $suggestion->suggestions_json['proposed_content']);
    }

    public function test_page_show_displays_rewrite_target_plan_from_fresh_rewrite_session_payload(): void
    {
        $this->withoutVite();

        $site = SeoSite::query()->create([
            'site_id' => 'workflow-site',
            'name' => 'Workflow Site',
            'url' => 'https://workflow-site.test',
            'niche' => 'amiante',
            'preset' => 'amiantix',
            'locale' => 'fr',
            'api_token_hash' => hash('sha256', 'token'),
            'is_active' => true,
        ]);

        $page = SeoPage::query()->create([
            'site_id' => $site->site_id,
            'keyword' => 'plan de retrait amiante en copropriete',
            'slug' => 'plan-de-retrait-amiante-en-copropriete',
            'cluster' => 'copropriete',
            'status' => 'draft',
            'title' => 'Ancien titre',
            'meta_description' => 'Ancienne meta',
            'content' => '<p>Contenu initial.</p>',
        ]);

        $response = $this
            ->withSession([
                'admin_authenticated' => true,
                'rewrite_suggestion' => [
                    'title' => 'Titre réécrit',
                    'rationale' => ['Renforcer une section faible avant publication.'],
                    'signals_summary' => [
                        'rewrite_target_plan' => [
                            [
                                'heading' => 'Documents et preuves a conserver',
                                'phase' => 'proof',
                                'patch_intent' => 'expand_and_structure',
                                'replacement_mode' => 'replace_only_if_patch_adds_structure',
                                'instruction' => 'developper et structurer cette section avec des listes, sous-parties ou tableaux utiles',
                                'reasons' => ['too_short', 'missing_structure'],
                            ],
                        ],
                    ],
                ],
            ])
            ->get(route('admin.pages.show', [$site->site_id, $page->id]));

        $response->assertOk();
        $response->assertSee('Suggestion créée');
        $response->assertSee('Plan de patch ciblé');
        $response->assertSee('Pourquoi ça baisse');
        $response->assertSee('1 section(s) faible(s).');
        $response->assertSee('Documents et preuves a conserver');
        $response->assertSee('phase proof');
        $response->assertSee('expand_and_structure');
        $response->assertSee('replace_only_if_patch_adds_structure');
        $response->assertSee('too_short');
        $response->assertSee('missing_structure');
    }

    public function test_review_page_can_be_published_when_quality_gates_are_green(): void
    {
        $site = SeoSite::query()->create([
            'site_id' => 'workflow-site',
            'name' => 'Workflow Site',
            'url' => 'https://workflow-site.test',
            'niche' => 'amiante',
            'locale' => 'fr',
            'preset' => 'amiantix',
            'api_token_hash' => hash('sha256', 'token'),
            'is_active' => true,
        ]);

        $blueprint = app(AmiantixBlueprintProvider::class)->resolve('diagnostic amiante paris', 'diagnostics');
        $payload = app(AmiantixContentProfile::class)->fallbackPayload('diagnostic amiante paris', 'diagnostics', $blueprint);
        $publishableContent = $payload['content'].'<section><h2>Ressources complémentaires</h2><p><a href="/reglementation-amiante">Reglementation amiante</a> <a href="/reperage-amiante-avant-travaux">Repérage amiante avant travaux</a> <a href="/coordination-amiante">Coordination amiante</a></p></section>';

        $page = SeoPage::query()->create([
            'site_id' => $site->site_id,
            'keyword' => 'diagnostic amiante paris',
            'slug' => 'diagnostic-amiante-paris',
            'cluster' => 'diagnostics',
            'status' => 'review',
            'title' => $payload['title'],
            'h1' => $payload['h1'],
            'meta_description' => $payload['meta_description'],
            'content' => $publishableContent,
            'faq_json' => $payload['faq'],
            'schema_json' => [
                ['@context' => 'https://schema.org', '@type' => 'Article'],
                ['@context' => 'https://schema.org', '@type' => 'FAQPage'],
            ],
            'internal_links_json' => [
                ['url' => '/diagnostic-amiante', 'label' => 'Diagnostic amiante'],
                ['url' => '/reperage-amiante-avant-travaux', 'label' => 'Repérage amiante avant travaux'],
                ['url' => '/ss4-amiante', 'label' => 'SS4 amiante'],
                ['url' => '/dta-amiante', 'label' => 'DTA amiante'],
                ['url' => '/coordination-amiante', 'label' => 'Coordination amiante'],
            ],
            'seo_score' => 82,
            'quality_score' => 100,
            'indexability_score' => 76,
            'image_status' => 'approved',
            'image_quality_score' => 100,
            'image_path' => 'seo/test-image.jpg',
            'image_alt' => 'Illustration amiante chantier et coordination documentaire',
            'image_prompt' => 'Illustration editoriale amiante chantier coordination documentaire',
            'spam_risk' => 'low',
            'duplicate_risk_score' => 20,
            'cluster_links_count' => 2,
        ]);

        config()->set('seo-engine.site.preset', 'amiantix');
        config()->set('seo-engine.site.niche', 'amiante');

        $response = $this
            ->withSession(['admin_authenticated' => true])
            ->post(route('admin.pages.publish', [$site->site_id, $page->id]));

        $response->assertRedirect(route('admin.pages.show', [$site->site_id, $page->id]));

        $page->refresh();

        $this->assertSame('published', $page->status);
        $this->assertNotNull($page->published_at);
    }

    public function test_page_show_refreshes_stale_scores_before_rendering(): void
    {
        $this->withoutVite();

        $site = SeoSite::query()->create([
            'site_id' => 'workflow-site',
            'name' => 'Workflow Site',
            'url' => 'https://workflow-site.test',
            'niche' => 'amiante',
            'locale' => 'fr',
            'preset' => 'amiantix',
            'api_token_hash' => hash('sha256', 'token'),
            'is_active' => true,
        ]);

        $blueprint = app(AmiantixBlueprintProvider::class)->resolve('danger sante amiante', 'reglementation');
        $payload = app(AmiantixContentProfile::class)->fallbackPayload('danger sante amiante', 'reglementation', $blueprint);
        $publishableContent = $payload['content'].'<section><h2>Ressources complémentaires</h2><p><a href="/reglementation-amiante">Reglementation amiante</a> <a href="/reperage-amiante-avant-travaux">Repérage amiante avant travaux</a> <a href="/coordination-amiante">Coordination amiante</a></p></section>';

        $page = SeoPage::query()->create([
            'site_id' => $site->site_id,
            'keyword' => 'danger sante amiante',
            'slug' => 'danger-sante-amiante',
            'cluster' => 'reglementation',
            'status' => 'review',
            'title' => $payload['title'],
            'h1' => $payload['h1'],
            'meta_description' => $payload['meta_description'],
            'content' => $publishableContent,
            'faq_json' => $payload['faq'],
            'schema_json' => [
                ['@context' => 'https://schema.org', '@type' => 'Article'],
                ['@context' => 'https://schema.org', '@type' => 'FAQPage'],
            ],
            'internal_links_json' => [
                ['url' => '/reglementation-amiante', 'label' => 'Reglementation amiante'],
                ['url' => '/reperage-amiante-avant-travaux', 'label' => 'Repérage amiante avant travaux'],
                ['url' => '/dta-amiante', 'label' => 'DTA amiante'],
                ['url' => '/ss4-amiante', 'label' => 'SS4 amiante'],
                ['url' => '/coordination-amiante', 'label' => 'Coordination amiante'],
            ],
            'image_status' => 'approved',
            'image_path' => 'seo/test-image.jpg',
            'image_alt' => 'Illustration amiante chantier et coordination documentaire',
            'image_prompt' => 'Illustration editoriale amiante chantier coordination documentaire',
            'seo_score' => 12,
            'quality_score' => 100,
            'topical_score' => 100,
            'indexability_score' => 5,
            'image_quality_score' => 30,
            'spam_risk' => 'high',
            'review_issues_json' => ['High spam risk or excessive genericness detected.'],
            'cluster_links_count' => 2,
        ]);

        config()->set('seo-engine.site.preset', 'amiantix');
        config()->set('seo-engine.site.niche', 'amiante');

        $response = $this
            ->withSession(['admin_authenticated' => true])
            ->get(route('admin.pages.show', [$site->site_id, $page->id]));

        $response->assertOk();

        $page->refresh();

        $this->assertSame('low', $page->spam_risk);
        $this->assertGreaterThanOrEqual(70, $page->seo_score);
        $this->assertGreaterThanOrEqual(65, $page->indexability_score);
        $this->assertNotContains('High spam risk or excessive genericness detected.', $page->review_issues_json ?? []);
        $response->assertDontSee('Risque spam détecté');
    }

    public function test_page_show_displays_the_real_generation_source_and_error(): void
    {
        $this->withoutVite();

        $site = SeoSite::query()->create([
            'site_id' => 'workflow-site',
            'name' => 'Workflow Site',
            'url' => 'https://workflow-site.test',
            'niche' => 'amiante',
            'locale' => 'fr',
            'preset' => 'amiantix',
            'api_token_hash' => hash('sha256', 'token'),
            'is_active' => true,
        ]);

        $page = SeoPage::query()->create([
            'site_id' => $site->site_id,
            'keyword' => 'danger sante amiante',
            'slug' => 'danger-sante-amiante',
            'cluster' => 'reglementation',
            'status' => 'draft',
            'title' => 'Danger Sante Amiante',
            'content' => '<p>Contenu fallback.</p>',
            'generation_source' => 'fallback',
            'generation_error' => 'Connexion OpenAI impossible : certificat SSL invalide.',
        ]);

        $response = $this
            ->withSession(['admin_authenticated' => true])
            ->get(route('admin.pages.show', [$site->site_id, $page->id]));

        $response->assertOk();
        $response->assertSee('Source de génération');
        $response->assertSee('Fallback preset');
        $response->assertSee('Connexion OpenAI impossible');
    }

    public function test_package_status_command_is_available(): void
    {
        $this->artisan('seo:package-status')
            ->assertSuccessful();
    }

    public function test_openai_ssl_doctor_command_is_available(): void
    {
        $this->artisan('seo:doctor-openai-ssl')
            ->assertSuccessful()
            ->expectsOutputToContain('Diagnostic SSL OpenAI');
    }

    public function test_page_show_refresh_uses_site_preset_context(): void
    {
        $this->withoutVite();

        config()->set('seo-engine.site.preset', 'generic');
        config()->set('seo-engine.site.niche', 'general');

        $site = SeoSite::query()->create([
            'site_id' => 'workflow-site',
            'name' => 'Workflow Site',
            'url' => 'https://workflow-site.test',
            'niche' => 'amiante',
            'locale' => 'fr',
            'preset' => 'amiantix',
            'api_token_hash' => hash('sha256', 'token'),
            'is_active' => true,
        ]);

        $blueprint = app(AmiantixBlueprintProvider::class)->resolve('danger sante amiante', 'reglementation');
        $payload = app(AmiantixContentProfile::class)->fallbackPayload('danger sante amiante', 'reglementation', $blueprint);
        $publishableContent = $payload['content'].'<section><h2>Ressources complémentaires</h2><p><a href="/reglementation-amiante">Reglementation amiante</a> <a href="/reperage-amiante-avant-travaux">Repérage amiante avant travaux</a> <a href="/coordination-amiante">Coordination amiante</a></p></section>';

        $page = SeoPage::query()->create([
            'site_id' => $site->site_id,
            'keyword' => 'danger sante amiante',
            'slug' => 'danger-sante-amiante',
            'cluster' => 'reglementation',
            'status' => 'review',
            'title' => $payload['title'],
            'h1' => $payload['h1'],
            'meta_description' => $payload['meta_description'],
            'content' => $publishableContent,
            'faq_json' => $payload['faq'],
            'schema_json' => [
                ['@context' => 'https://schema.org', '@type' => 'Article'],
                ['@context' => 'https://schema.org', '@type' => 'FAQPage'],
            ],
            'internal_links_json' => [
                ['url' => '/reglementation-amiante', 'label' => 'Reglementation amiante'],
                ['url' => '/reperage-amiante-avant-travaux', 'label' => 'Repérage amiante avant travaux'],
                ['url' => '/dta-amiante', 'label' => 'DTA amiante'],
                ['url' => '/ss4-amiante', 'label' => 'SS4 amiante'],
                ['url' => '/coordination-amiante', 'label' => 'Coordination amiante'],
            ],
            'image_status' => 'approved',
            'image_path' => 'seo/test-image.jpg',
            'image_alt' => 'Illustration amiante chantier et coordination documentaire',
            'image_prompt' => 'Illustration editoriale amiante chantier coordination documentaire',
            'seo_score' => 12,
            'quality_score' => 12,
            'topical_score' => 12,
            'indexability_score' => 12,
            'image_quality_score' => 30,
            'spam_risk' => 'high',
            'review_issues_json' => ['High spam risk or excessive genericness detected.'],
            'cluster_links_count' => 2,
        ]);

        $response = $this
            ->withSession(['admin_authenticated' => true])
            ->get(route('admin.pages.show', [$site->site_id, $page->id]));

        $response->assertOk();

        $page->refresh();

        $this->assertSame('low', $page->spam_risk);
        $this->assertSame(100, $page->topical_score);
        $this->assertSame(100, $page->quality_score);
    }

    public function test_publish_refreshes_stale_scores_before_blocking_a_green_page(): void
    {
        $site = SeoSite::query()->create([
            'site_id' => 'workflow-site',
            'name' => 'Workflow Site',
            'url' => 'https://workflow-site.test',
            'niche' => 'amiante',
            'locale' => 'fr',
            'preset' => 'amiantix',
            'api_token_hash' => hash('sha256', 'token'),
            'is_active' => true,
        ]);

        $blueprint = app(AmiantixBlueprintProvider::class)->resolve('danger sante amiante', 'reglementation');
        $payload = app(AmiantixContentProfile::class)->fallbackPayload('danger sante amiante', 'reglementation', $blueprint);
        $publishableContent = $payload['content'].'<section><h2>Ressources complémentaires</h2><p><a href="/reglementation-amiante">Reglementation amiante</a> <a href="/reperage-amiante-avant-travaux">Repérage amiante avant travaux</a> <a href="/coordination-amiante">Coordination amiante</a></p></section>';

        $page = SeoPage::query()->create([
            'site_id' => $site->site_id,
            'keyword' => 'danger sante amiante',
            'slug' => 'danger-sante-amiante',
            'cluster' => 'reglementation',
            'status' => 'review',
            'title' => $payload['title'],
            'h1' => $payload['h1'],
            'meta_description' => $payload['meta_description'],
            'content' => $publishableContent,
            'faq_json' => $payload['faq'],
            'schema_json' => [
                ['@context' => 'https://schema.org', '@type' => 'Article'],
                ['@context' => 'https://schema.org', '@type' => 'FAQPage'],
            ],
            'internal_links_json' => [
                ['url' => '/reglementation-amiante', 'label' => 'Reglementation amiante'],
                ['url' => '/reperage-amiante-avant-travaux', 'label' => 'Repérage amiante avant travaux'],
                ['url' => '/dta-amiante', 'label' => 'DTA amiante'],
                ['url' => '/ss4-amiante', 'label' => 'SS4 amiante'],
                ['url' => '/coordination-amiante', 'label' => 'Coordination amiante'],
            ],
            'image_status' => 'approved',
            'image_path' => 'seo/test-image.jpg',
            'image_alt' => 'Illustration amiante chantier et coordination documentaire',
            'image_prompt' => 'Illustration editoriale amiante chantier coordination documentaire',
            'seo_score' => 12,
            'quality_score' => 100,
            'topical_score' => 100,
            'indexability_score' => 5,
            'image_quality_score' => 30,
            'spam_risk' => 'high',
            'review_issues_json' => ['High spam risk or excessive genericness detected.'],
            'cluster_links_count' => 2,
        ]);

        config()->set('seo-engine.site.preset', 'amiantix');
        config()->set('seo-engine.site.niche', 'amiante');

        $response = $this
            ->withSession(['admin_authenticated' => true])
            ->post(route('admin.pages.publish', [$site->site_id, $page->id]));

        $response->assertRedirect(route('admin.pages.show', [$site->site_id, $page->id]));

        $page->refresh();

        $this->assertSame('published', $page->status);
        $this->assertNotNull($page->published_at);
        $this->assertSame('low', $page->spam_risk);
    }

    public function test_quick_fix_can_generate_and_approve_ai_image(): void
    {
        Storage::fake('public');
        Http::fake([
            'https://api.openai.com/v1/images/generations' => Http::response([
                'data' => [
                    ['b64_json' => base64_encode('fake-image-binary')],
                ],
            ], 200),
        ]);

        config()->set('services.openai.api_key', 'test-key');
        config()->set('services.openai.image_model', 'gpt-image-1');

        $site = SeoSite::query()->create([
            'site_id' => 'workflow-site',
            'name' => 'Workflow Site',
            'url' => 'https://workflow-site.test',
            'niche' => 'amiante',
            'locale' => 'fr',
            'preset' => 'amiantix',
            'api_token_hash' => hash('sha256', 'token'),
            'is_active' => true,
        ]);

        $page = SeoPage::query()->create([
            'site_id' => $site->site_id,
            'keyword' => 'diagnostic amiante paris',
            'slug' => 'diagnostic-amiante-paris',
            'cluster' => 'diagnostics',
            'status' => 'review',
            'title' => 'Diagnostic amiante Paris',
            'content' => '<p>Contenu initial.</p>',
            'faq_json' => [
                ['question' => 'Q1', 'answer' => 'A1'],
                ['question' => 'Q2', 'answer' => 'A2'],
                ['question' => 'Q3', 'answer' => 'A3'],
                ['question' => 'Q4', 'answer' => 'A4'],
                ['question' => 'Q5', 'answer' => 'A5'],
            ],
            'seo_score' => 82,
            'quality_score' => 100,
            'indexability_score' => 60,
            'image_status' => 'missing',
        ]);

        Http::assertNothingSent();

        $this->withSession(['admin_authenticated' => true])
            ->post(route('admin.pages.quick-fix', [$site->site_id, $page->id]), ['action' => 'generate_image'])
            ->assertRedirect(route('admin.pages.show', [$site->site_id, $page->id]));

        Http::assertSent(function ($request) {
            $payload = $request->data();

            return $request->url() === 'https://api.openai.com/v1/images/generations'
                && ! array_key_exists('response_format', $payload)
                && ($payload['model'] ?? null) === 'gpt-image-1';
        });

        $page->refresh();

        $this->assertSame('generated', $page->image_status);
        $this->assertNotEmpty($page->image_prompt);
        $this->assertNotEmpty($page->image_alt);
        $this->assertNotEmpty($page->image_path);
        Storage::disk('public')->assertExists($page->image_path);

        $this->withSession(['admin_authenticated' => true])
            ->post(route('admin.pages.quick-fix', [$site->site_id, $page->id]), ['action' => 'approve_image'])
            ->assertRedirect(route('admin.pages.show', [$site->site_id, $page->id]));

        $page->refresh();

        $this->assertSame('approved', $page->image_status);
    }
}
