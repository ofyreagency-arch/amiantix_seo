<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\SeoPresets\Amiantix\AmiantixBlueprintProvider;
use App\SeoPresets\Amiantix\AmiantixContentProfile;
use App\Models\SeoPage;
use App\Models\SeoSearchConsoleMetric;
use App\Models\SeoSite;
use App\Models\SeoSuggestion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
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
            ],
            'status' => 'pending',
        ]);

        $response = $this
            ->withSession(['admin_authenticated' => true])
            ->get(route('admin.pages.show', [$site->site_id, $page->id]));

        $response->assertOk();
        $response->assertSee('Statut éditorial moteur');
        $response->assertSee('Workflow éditorial');
        $response->assertSee('Observed runtime');
        $response->assertSee('Suggestion active');
        $response->assertSee('Appliquer à la page');
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

        $page = SeoPage::query()->create([
            'site_id' => $site->site_id,
            'keyword' => 'diagnostic amiante paris',
            'slug' => 'diagnostic-amiante-paris',
            'cluster' => 'diagnostics',
            'status' => 'review',
            'title' => $payload['title'],
            'h1' => $payload['h1'],
            'meta_description' => $payload['meta_description'],
            'content' => $payload['content'],
            'faq_json' => $payload['faq'],
            'seo_score' => 82,
            'quality_score' => 100,
            'indexability_score' => 76,
            'image_status' => 'approved',
            'image_quality_score' => 100,
            'spam_risk' => 'low',
            'duplicate_risk_score' => 20,
        ]);

        $response = $this
            ->withSession(['admin_authenticated' => true])
            ->post(route('admin.pages.publish', [$site->site_id, $page->id]));

        $response->assertRedirect(route('admin.pages.show', [$site->site_id, $page->id]));

        $page->refresh();

        $this->assertSame('published', $page->status);
        $this->assertNotNull($page->published_at);
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

        $this->withSession(['admin_authenticated' => true])
            ->post(route('admin.pages.quick-fix', [$site->site_id, $page->id]), ['action' => 'generate_image'])
            ->assertRedirect(route('admin.pages.show', [$site->site_id, $page->id]));

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
