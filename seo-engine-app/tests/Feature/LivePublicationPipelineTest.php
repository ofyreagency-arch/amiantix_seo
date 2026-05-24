<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\SeoPage;
use App\Models\SeoSite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LivePublicationPipelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_engine_published_page_can_be_pushed_live(): void
    {
        $site = SeoSite::query()->create([
            'site_id' => 'amiantix',
            'name' => 'Amiantix',
            'url' => 'https://amiantix.test',
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
            'status' => 'published',
            'published_at' => now(),
            'title' => 'Danger Sante Amiante',
            'content' => '<p>Contenu.</p>',
        ]);

        $response = $this
            ->withSession(['admin_authenticated' => true])
            ->post(route('admin.pages.publish-live', [$site->site_id, $page->id]));

        $response->assertRedirect(route('admin.pages.show', [$site->site_id, $page->id]));

        $page->refresh();

        $this->assertTrue($page->published_live);
        $this->assertNotNull($page->published_live_at);
        $this->assertSame('https://amiantix.test/danger-sante-amiante', $page->live_url);
    }

    public function test_public_route_serves_only_live_pages(): void
    {
        $site = SeoSite::query()->create([
            'site_id' => 'amiantix',
            'name' => 'Amiantix',
            'url' => 'https://amiantix.test',
            'niche' => 'amiante',
            'locale' => 'fr',
            'preset' => 'amiantix',
            'api_token_hash' => hash('sha256', 'token'),
            'is_active' => true,
        ]);

        SeoPage::query()->create([
            'site_id' => $site->site_id,
            'keyword' => 'danger sante amiante',
            'slug' => 'danger-sante-amiante',
            'status' => 'published',
            'published_at' => now(),
            'published_live' => true,
            'published_live_at' => now(),
            'live_url' => 'https://amiantix.test/danger-sante-amiante',
            'title' => 'Danger Sante Amiante',
            'h1' => 'Danger Sante Amiante',
            'meta_description' => 'Description utile',
            'content' => '## Contenu public',
        ]);

        SeoPage::query()->create([
            'site_id' => $site->site_id,
            'keyword' => 'page moteur seulement',
            'slug' => 'page-moteur-seulement',
            'status' => 'published',
            'published_at' => now(),
            'title' => 'Page moteur seulement',
            'content' => '## Brouillon live absent',
        ]);

        $this->get('https://amiantix.test/danger-sante-amiante')
            ->assertOk()
            ->assertSee('Danger Sante Amiante');

        $this->get('https://amiantix.test/page-moteur-seulement')
            ->assertNotFound();
    }

    public function test_public_sitemap_only_lists_live_indexable_pages(): void
    {
        $site = SeoSite::query()->create([
            'site_id' => 'amiantix',
            'name' => 'Amiantix',
            'url' => 'https://amiantix.test',
            'niche' => 'amiante',
            'locale' => 'fr',
            'preset' => 'amiantix',
            'api_token_hash' => hash('sha256', 'token'),
            'is_active' => true,
        ]);

        SeoPage::query()->create([
            'site_id' => $site->site_id,
            'keyword' => 'page live',
            'slug' => 'page-live',
            'status' => 'published',
            'published_at' => now(),
            'published_live' => true,
            'published_live_at' => now(),
            'live_url' => 'https://amiantix.test/page-live',
            'title' => 'Page live',
            'content' => '<p>Contenu.</p>',
        ]);

        SeoPage::query()->create([
            'site_id' => $site->site_id,
            'keyword' => 'page moteur',
            'slug' => 'page-moteur',
            'status' => 'published',
            'published_at' => now(),
            'title' => 'Page moteur',
            'content' => '<p>Contenu.</p>',
        ]);

        SeoPage::query()->create([
            'site_id' => $site->site_id,
            'keyword' => 'page noindex',
            'slug' => 'page-noindex',
            'status' => 'published',
            'published_at' => now(),
            'published_live' => true,
            'published_live_at' => now(),
            'live_url' => 'https://amiantix.test/page-noindex',
            'forced_noindex' => true,
            'title' => 'Page noindex',
            'content' => '<p>Contenu.</p>',
        ]);

        $this->get('https://amiantix.test/sitemap.xml')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee('https://amiantix.test/page-live', false)
            ->assertDontSee('https://amiantix.test/page-moteur', false)
            ->assertDontSee('https://amiantix.test/page-noindex', false);
    }

    public function test_engine_published_page_can_be_pushed_live_via_webhook_api_target(): void
    {
        Http::fake([
            'https://client.test/api/praeviseo/publish' => Http::response([
                'live_url' => 'https://client.test/blog/danger-sante-amiante',
            ], 200),
        ]);

        $site = SeoSite::query()->create([
            'site_id' => 'amiantix',
            'name' => 'Amiantix',
            'url' => 'https://amiantix.test',
            'niche' => 'amiante',
            'locale' => 'fr',
            'preset' => 'amiantix',
            'api_token_hash' => hash('sha256', 'token'),
            'is_active' => true,
            'webhook_url' => 'https://client.test/api/praeviseo/publish',
            'settings_json' => [
                'publication' => [
                    'mode' => 'webhook_api',
                    'webhook_url' => 'https://client.test/api/praeviseo/publish',
                ],
            ],
        ]);

        $page = SeoPage::query()->create([
            'site_id' => $site->site_id,
            'keyword' => 'danger sante amiante',
            'slug' => 'danger-sante-amiante',
            'status' => 'published',
            'published_at' => now(),
            'title' => 'Danger Sante Amiante',
            'content' => '<p>Contenu.</p>',
        ]);

        $response = $this
            ->withSession(['admin_authenticated' => true])
            ->post(route('admin.pages.publish-live', [$site->site_id, $page->id]));

        $response->assertRedirect(route('admin.pages.show', [$site->site_id, $page->id]));
        $response->assertSessionHas('success', 'Page publiée en live sur le site public.');

        Http::assertSent(function ($request): bool {
            $payload = $request->data();

            return $request->url() === 'https://client.test/api/praeviseo/publish'
                && ($payload['source'] ?? null) === 'praeviseo'
                && ($payload['page']['slug'] ?? null) === 'danger-sante-amiante';
        });

        $page->refresh();
        $site->refresh();

        $this->assertTrue($page->published_live);
        $this->assertNotNull($page->published_live_at);
        $this->assertSame('https://client.test/blog/danger-sante-amiante', $page->live_url);
        $this->assertSame('ok', data_get($site->settings_json, 'publication.last_push_status'));
    }

    public function test_publish_live_warns_when_webhook_target_is_missing(): void
    {
        $site = SeoSite::query()->create([
            'site_id' => 'amiantix',
            'name' => 'Amiantix',
            'url' => 'https://amiantix.test',
            'niche' => 'amiante',
            'locale' => 'fr',
            'preset' => 'amiantix',
            'api_token_hash' => hash('sha256', 'token'),
            'is_active' => true,
            'settings_json' => [
                'publication' => [
                    'mode' => 'webhook_api',
                    'webhook_url' => null,
                ],
            ],
        ]);

        $page = SeoPage::query()->create([
            'site_id' => $site->site_id,
            'keyword' => 'danger sante amiante',
            'slug' => 'danger-sante-amiante',
            'status' => 'published',
            'published_at' => now(),
            'title' => 'Danger Sante Amiante',
            'content' => '<p>Contenu.</p>',
        ]);

        $response = $this
            ->withSession(['admin_authenticated' => true])
            ->post(route('admin.pages.publish-live', [$site->site_id, $page->id]));

        $response->assertRedirect(route('admin.pages.show', [$site->site_id, $page->id]));
        $response->assertSessionHas('warning', 'Aucun endpoint de publication CMS/API n est configuré pour ce site.');

        $page->refresh();

        $this->assertFalse((bool) $page->published_live);
        $this->assertNull($page->published_live_at);
    }
}
