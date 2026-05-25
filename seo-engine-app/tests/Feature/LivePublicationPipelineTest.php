<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\SeoPage;
use App\Models\SeoSearchConsoleMetric;
use App\Models\SeoSite;
use App\Models\SeoSitePage;
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

    public function test_engine_published_page_can_be_pushed_live_via_laravel_bridge_target(): void
    {
        Http::fake([
            'https://client.test/api/praeviseo/bridge/publish' => Http::response([
                'live_url' => 'https://client.test/ressources/danger-sante-amiante',
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
            'webhook_url' => 'https://client.test/api/praeviseo/bridge/publish',
            'settings_json' => [
                'publication' => [
                    'mode' => 'laravel_bridge',
                    'webhook_url' => 'https://client.test/api/praeviseo/bridge/publish',
                    'shared_secret' => 'bridge-secret',
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
            $siteHeader = $request->header('X-Praeviseo-Site-Id');
            $timestampHeader = $request->header('X-Praeviseo-Timestamp');
            $signatureHeader = $request->header('X-Praeviseo-Signature');

            return $request->url() === 'https://client.test/api/praeviseo/bridge/publish'
                && (($siteHeader[0] ?? null) === 'amiantix')
                && ! empty($timestampHeader[0] ?? null)
                && ! empty($signatureHeader[0] ?? null);
        });

        $page->refresh();

        $this->assertTrue($page->published_live);
        $this->assertSame('https://client.test/ressources/danger-sante-amiante', $page->live_url);
    }

    public function test_engine_published_page_can_be_pushed_live_via_symfony_bridge_target(): void
    {
        Http::fake([
            'https://client.test/api/praeviseo/bridge/publish' => Http::response([
                'live_url' => 'https://client.test/conseils/danger-sante-amiante',
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
            'webhook_url' => 'https://client.test/api/praeviseo/bridge/publish',
            'settings_json' => [
                'publication' => [
                    'mode' => 'symfony_bridge',
                    'webhook_url' => 'https://client.test/api/praeviseo/bridge/publish',
                    'shared_secret' => 'bridge-secret',
                    'path_prefix' => 'conseils',
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
            return $request->url() === 'https://client.test/api/praeviseo/bridge/publish'
                && (($request->header('X-Praeviseo-Site-Id')[0] ?? null) === 'amiantix');
        });

        $page->refresh();

        $this->assertTrue($page->published_live);
        $this->assertSame('https://client.test/conseils/danger-sante-amiante', $page->live_url);
    }

    public function test_engine_published_page_can_be_pushed_live_via_wordpress_bridge_target(): void
    {
        Http::fake([
            'https://client.test/wp-json/praeviseo/v1/publish' => Http::response([
                'live_url' => 'https://client.test/ressources/danger-sante-amiante',
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
            'webhook_url' => 'https://client.test/wp-json/praeviseo/v1/publish',
            'settings_json' => [
                'publication' => [
                    'mode' => 'wordpress_bridge',
                    'webhook_url' => 'https://client.test/wp-json/praeviseo/v1/publish',
                    'shared_secret' => 'bridge-secret',
                    'path_prefix' => 'ressources',
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

        $page->refresh();

        $this->assertTrue($page->published_live);
        $this->assertSame('https://client.test/ressources/danger-sante-amiante', $page->live_url);
    }

    public function test_bridge_connect_endpoint_sets_up_laravel_bridge_from_connection_code(): void
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
                    'mode' => 'laravel_bridge',
                    'connect_code' => 'ABCD-EFGH-IJKL',
                    'bridge_status' => 'pending',
                ],
            ],
        ]);

        $response = $this->postJson('/api/bridge/connect', [
            'connection_code' => 'abcd-efgh-ijkl',
            'app_url' => 'https://client.test',
            'bridge' => 'laravel_bridge',
            'publication_prefix' => 'ressources',
        ]);

        $response->assertOk()
            ->assertJsonPath('status', 'connected')
            ->assertJsonPath('site_id', 'amiantix')
            ->assertJsonPath('publication_mode', 'laravel_bridge')
            ->assertJsonPath('publication_endpoint', 'https://client.test/api/praeviseo/bridge/publish')
            ->assertJsonPath('publication_prefix', 'ressources');

        $site->refresh();

        $this->assertSame('https://client.test/api/praeviseo/bridge/publish', $site->publicationWebhookUrl());
        $this->assertSame('connected', $site->publicationBridgeStatus());
        $this->assertSame('ressources', $site->publicationPathPrefix());
        $this->assertNotNull($site->publicationSharedSecret());
    }

    public function test_bridge_connect_endpoint_sets_up_symfony_bridge_from_connection_code(): void
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
                    'mode' => 'symfony_bridge',
                    'connect_code' => 'WXYZ-QRST-UVWX',
                    'bridge_status' => 'pending',
                ],
            ],
        ]);

        $response = $this->postJson('/api/bridge/connect', [
            'connection_code' => 'WXYZ-QRST-UVWX',
            'app_url' => 'https://client.test',
            'bridge' => 'symfony_bridge',
            'publication_prefix' => 'conseils',
        ]);

        $response->assertOk()
            ->assertJsonPath('status', 'connected')
            ->assertJsonPath('publication_mode', 'symfony_bridge')
            ->assertJsonPath('publication_prefix', 'conseils');

        $site->refresh();

        $this->assertSame('https://client.test/api/praeviseo/bridge/publish', $site->publicationWebhookUrl());
        $this->assertSame('connected', $site->publicationBridgeStatus());
        $this->assertSame('conseils', $site->publicationPathPrefix());
    }

    public function test_bridge_connect_endpoint_sets_up_wordpress_bridge_from_connection_code(): void
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
                    'mode' => 'wordpress_bridge',
                    'connect_code' => 'WORD-PRES-SSEO',
                    'bridge_status' => 'pending',
                ],
            ],
        ]);

        $response = $this->postJson('/api/bridge/connect', [
            'connection_code' => 'WORD-PRES-SSEO',
            'app_url' => 'https://client.test',
            'bridge' => 'wordpress_bridge',
            'publication_prefix' => 'ressources',
        ]);

        $response->assertOk()
            ->assertJsonPath('status', 'connected')
            ->assertJsonPath('publication_mode', 'wordpress_bridge')
            ->assertJsonPath('publication_endpoint', 'https://client.test/wp-json/praeviseo/v1/publish')
            ->assertJsonPath('publication_prefix', 'ressources');

        $site->refresh();

        $this->assertSame('https://client.test/wp-json/praeviseo/v1/publish', $site->publicationWebhookUrl());
        $this->assertSame('connected', $site->publicationBridgeStatus());
        $this->assertSame('ressources', $site->publicationPathPrefix());
        $this->assertNotNull($site->publicationSharedSecret());
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

    public function test_page_show_surfaces_true_live_monitoring_signals(): void
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
            'published_live' => true,
            'published_live_at' => now(),
            'live_url' => 'https://amiantix.test/danger-sante-amiante',
            'title' => 'Danger Sante Amiante',
            'content' => '<p>Contenu.</p>',
        ]);

        SeoSitePage::query()->create([
            'site_id' => $site->site_id,
            'normalized_url' => 'https://amiantix.test/danger-sante-amiante',
            'url_hash' => sha1('https://amiantix.test/danger-sante-amiante'),
            'path' => '/danger-sante-amiante',
            'canonical_url' => 'https://amiantix.test/danger-sante-amiante',
            'indexability_state' => 'indexable',
            'last_status_code' => 200,
            'discovered_at' => now(),
            'last_seen_at' => now(),
        ]);

        SeoSearchConsoleMetric::query()->create([
            'site_id' => $site->site_id,
            'seo_page_id' => $page->id,
            'metric_date' => now()->toDateString(),
            'window_days' => 28,
            'url' => 'https://amiantix.test/danger-sante-amiante',
            'clicks' => 4,
            'impressions' => 18,
            'ctr' => 0.2222,
            'position' => 8.4,
            'is_indexed' => true,
        ]);

        SeoSearchConsoleMetric::query()->create([
            'site_id' => $site->site_id,
            'seo_page_id' => $page->id,
            'metric_date' => now()->toDateString(),
            'window_days' => 28,
            'query' => 'danger amiante',
            'url' => 'https://amiantix.test/danger-sante-amiante',
            'clicks' => 1,
            'impressions' => 3,
            'ctr' => 0.3333,
            'position' => 9.2,
            'is_indexed' => true,
        ]);

        $response = $this
            ->withSession(['admin_authenticated' => true])
            ->get(route('admin.pages.show', [$site->site_id, $page->id]));

        $response->assertOk();
        $response->assertSee('Monitoring post-publication');
        $response->assertSee('URL publique');
        $response->assertSee('https://amiantix.test/danger-sante-amiante');
        $response->assertSee('HTTP réel');
        $response->assertSee('Surveillance active');
        $response->assertSee('Queries observées');
    }
}
