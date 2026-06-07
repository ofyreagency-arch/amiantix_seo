<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\SeoPage;
use App\Models\SeoSearchConsoleMetric;
use App\Models\SeoSemanticLink;
use App\Models\SeoSite;
use App\Models\SeoSitePage;
use App\Models\SeoSitePageSnapshot;
use App\Models\User;
use App\Models\UserAccessToken;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ClientWorkspacePublicationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_publications_endpoint_tolerates_missing_live_publication_columns(): void
    {
        $user = User::factory()->create();
        $rawToken = 'frontend-publications-token';

        UserAccessToken::query()->create([
            'user_id' => $user->id,
            'name' => 'frontend',
            'token_hash' => hash('sha256', $rawToken),
        ]);

        $site = SeoSite::query()->create([
            'site_id' => 'amiantix',
            'name' => 'Amiantix',
            'url' => 'https://amiantix.com',
            'niche' => 'amiante',
            'locale' => 'fr',
            'preset' => 'amiantix',
            'api_token_hash' => hash('sha256', 'site-token'),
            'is_active' => true,
        ]);

        $user->seoSites()->attach($site->id, ['role' => 'owner']);

        SeoPage::query()->create([
            'site_id' => $site->site_id,
            'keyword' => 'diagnostic amiante paris',
            'slug' => 'diagnostic-amiante-paris',
            'title' => 'Diagnostic amiante Paris',
            'status' => 'published',
            'published_at' => now()->subHour(),
        ]);

        Schema::table('seo_pages', function (Blueprint $table): void {
            if (Schema::hasColumn('seo_pages', 'last_observed_at')) {
                $table->dropColumn('last_observed_at');
            }

            if (Schema::hasColumn('seo_pages', 'live_url')) {
                $table->dropColumn('live_url');
            }

            if (Schema::hasColumn('seo_pages', 'published_live_at')) {
                $table->dropColumn('published_live_at');
            }

            if (Schema::hasColumn('seo_pages', 'published_live')) {
                $table->dropColumn('published_live');
            }
        });

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$rawToken)
            ->getJson('/api/client/publications');

        $response->assertOk();
        $response->assertJsonPath('stats.engine_published', 1);
        $response->assertJsonPath('stats.live_published', 0);
        $response->assertJsonPath('stats.with_live_url', 0);
        $response->assertJsonPath('items.0.slug', 'diagnostic-amiante-paris');
        $response->assertJsonPath('items.0.published_live', false);
        $response->assertJsonPath('items.0.live_url', null);
    }

    public function test_publications_endpoint_uses_latest_available_analytics_metrics_for_a_page(): void
    {
        $user = User::factory()->create();
        $rawToken = 'frontend-publications-gsc-token';

        UserAccessToken::query()->create([
            'user_id' => $user->id,
            'name' => 'frontend',
            'token_hash' => hash('sha256', $rawToken),
        ]);

        $site = SeoSite::query()->create([
            'site_id' => 'amiantix',
            'name' => 'Amiantix',
            'url' => 'https://amiantix.com',
            'niche' => 'amiante',
            'locale' => 'fr',
            'preset' => 'amiantix',
            'api_token_hash' => hash('sha256', 'site-token'),
            'is_active' => true,
        ]);

        $user->seoSites()->attach($site->id, ['role' => 'owner']);

        $page = SeoPage::query()->create([
            'site_id' => $site->site_id,
            'keyword' => 'diagnostic amiante paris',
            'slug' => 'diagnostic-amiante-paris',
            'title' => 'Diagnostic amiante Paris',
            'status' => 'published',
            'published_at' => now()->subHour(),
            'published_live' => true,
            'live_url' => 'https://amiantix.com/diagnostic-amiante-paris',
        ]);

        SeoSearchConsoleMetric::query()->create([
            'site_id' => $site->site_id,
            'seo_page_id' => $page->id,
            'metric_date' => '2026-05-25',
            'window_days' => 28,
            'query' => null,
            'url' => 'https://amiantix.com/diagnostic-amiante-paris',
            'clicks' => 3,
            'impressions' => 12,
            'ctr' => 0.25,
            'position' => 8.7,
            'payload_json' => [
                'analytics' => [
                    'url' => 'https://amiantix.com/diagnostic-amiante-paris',
                ],
            ],
        ]);

        SeoSearchConsoleMetric::query()->create([
            'site_id' => $site->site_id,
            'seo_page_id' => $page->id,
            'metric_date' => '2026-05-26',
            'window_days' => 28,
            'query' => null,
            'url' => 'https://amiantix.com/diagnostic-amiante-paris',
            'clicks' => 0,
            'impressions' => 0,
            'ctr' => 0.0,
            'position' => 0.0,
            'payload_json' => [
                'inspection' => [
                    'inspectionResult' => [
                        'indexStatusResult' => [
                            'verdict' => 'PASS',
                        ],
                    ],
                ],
            ],
        ]);

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$rawToken)
            ->getJson('/api/client/publications');

        $response->assertOk();
        $response->assertJsonPath('items.0.gsc_metrics.impressions', 12);
        $response->assertJsonPath('items.0.gsc_metrics.clicks', 3);
        $response->assertJsonPath('items.0.gsc_metrics.ctr', 25);
        $response->assertJsonPath('items.0.gsc_metrics.position', 8.7);
    }

    public function test_publications_endpoint_exposes_observed_content_signals(): void
    {
        $user = User::factory()->create();
        $rawToken = 'frontend-publications-observed-token';

        UserAccessToken::query()->create([
            'user_id' => $user->id,
            'name' => 'frontend',
            'token_hash' => hash('sha256', $rawToken),
        ]);

        $site = SeoSite::query()->create([
            'site_id' => 'amiantix',
            'name' => 'Amiantix',
            'url' => 'https://amiantix.com',
            'niche' => 'amiante',
            'locale' => 'fr',
            'preset' => 'amiantix',
            'api_token_hash' => hash('sha256', 'site-token'),
            'is_active' => true,
        ]);

        $user->seoSites()->attach($site->id, ['role' => 'owner']);

        $observed = SeoSitePage::query()->create([
            'site_id' => $site->site_id,
            'normalized_url' => 'https://amiantix.com/faq',
            'url_hash' => hash('sha256', 'https://amiantix.com/faq'),
            'path' => '/faq',
            'title' => 'Faq',
            'indexability_state' => 'indexable',
            'latest_word_count' => 820,
            'internal_inlinks' => 1,
            'internal_outlinks' => 6,
            'authority_score' => 0.44,
            'orphan_score' => 0.11,
            'overlap_score' => 0.36,
            'pillar_likelihood' => 0.73,
            'cluster_label' => 'diagnostic-amiante',
        ]);

        $page = SeoPage::query()->create([
            'site_id' => $site->site_id,
            'observed_site_page_id' => $observed->id,
            'keyword' => 'faq amiante',
            'slug' => 'faq',
            'title' => 'Faq',
            'status' => 'published',
            'published_at' => now()->subHour(),
            'published_live' => true,
            'live_url' => 'https://amiantix.com/faq',
        ]);

        SeoSitePageSnapshot::query()->create([
            'site_id' => $site->site_id,
            'site_crawl_id' => 1,
            'site_page_id' => $observed->id,
            'url' => 'https://amiantix.com/faq',
            'title' => 'Faq',
            'word_count' => 910,
            'content_hash' => hash('sha256', 'faq-v1'),
            'observed_at' => now()->subDay(),
        ]);

        SeoSemanticLink::query()->create([
            'site_id' => $site->site_id,
            'relation_type' => 'observed_internal_link',
            'source_key' => 'page:faq',
            'source_id' => $observed->id,
            'target_key' => 'page:guide-dta',
            'target_id' => null,
            'label' => 'Guide DTA',
            'similarity_score' => 0.84,
        ]);

        SeoSemanticLink::query()->create([
            'site_id' => $site->site_id,
            'relation_type' => 'observed_cannibalization',
            'source_key' => 'page:faq',
            'source_id' => $observed->id,
            'target_key' => 'page:diagnostic-amiante',
            'target_id' => null,
            'label' => 'Diagnostic amiante',
            'similarity_score' => 0.88,
        ]);

        SeoSemanticLink::query()->create([
            'site_id' => $site->site_id,
            'relation_type' => 'observed_query_match',
            'source_key' => 'query:faq amiante',
            'source_id' => null,
            'target_key' => 'page:faq',
            'target_id' => $observed->id,
            'label' => 'faq amiante',
            'similarity_score' => 0.79,
        ]);

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$rawToken)
            ->getJson('/api/client/publications');

        $response->assertOk();
        $response->assertJsonPath('items.0.observed_content.authority_score', 44);
        $response->assertJsonPath('items.0.observed_content.cluster_label', 'diagnostic-amiante');
        $response->assertJsonPath('items.0.observed_content.snapshot_word_count', 910);
        $response->assertJsonPath('items.0.observed_content.internal_link_suggestions_count', 1);
        $response->assertJsonPath('items.0.observed_content.cannibalization_count', 1);
        $response->assertJsonPath('items.0.observed_content.query_match_count', 1);
        $response->assertJsonPath('items.0.observed_content.top_internal_link_target', 'Guide DTA');
        $response->assertJsonPath('items.0.observed_content.top_cannibalization_target', 'Diagnostic amiante');
        $response->assertJsonPath('items.0.observed_content.top_query_match', 'faq amiante');
    }

    public function test_publications_endpoint_includes_draft_review_published_and_live_pages(): void
    {
        $user = User::factory()->create();
        $rawToken = 'frontend-publications-status-token';

        UserAccessToken::query()->create([
            'user_id' => $user->id,
            'name' => 'frontend',
            'token_hash' => hash('sha256', $rawToken),
        ]);

        $site = SeoSite::query()->create([
            'site_id' => 'amiantix',
            'name' => 'Amiantix',
            'url' => 'https://amiantix.com',
            'niche' => 'amiante',
            'locale' => 'fr',
            'preset' => 'amiantix',
            'api_token_hash' => hash('sha256', 'site-token'),
            'is_active' => true,
        ]);

        $user->seoSites()->attach($site->id, ['role' => 'owner']);

        SeoPage::query()->create([
            'site_id' => $site->site_id,
            'keyword' => 'blog amiante',
            'slug' => 'blog-amiante',
            'title' => 'Blog amiante',
            'status' => 'draft',
        ]);

        SeoPage::query()->create([
            'site_id' => $site->site_id,
            'keyword' => 'guide dta',
            'slug' => 'guide-dta',
            'title' => 'Guide DTA',
            'status' => 'review',
        ]);

        SeoPage::query()->create([
            'site_id' => $site->site_id,
            'keyword' => 'faq amiante',
            'slug' => 'faq-amiante',
            'title' => 'FAQ amiante',
            'status' => 'published',
            'published_at' => now()->subHour(),
        ]);

        SeoPage::query()->create([
            'site_id' => $site->site_id,
            'keyword' => 'diagnostic amiante paris',
            'slug' => 'diagnostic-amiante-paris',
            'title' => 'Diagnostic amiante Paris',
            'status' => 'published',
            'published_at' => now()->subMinutes(30),
            'published_live' => true,
            'published_live_at' => now()->subMinutes(20),
            'live_url' => 'https://amiantix.com/diagnostic-amiante-paris',
        ]);

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$rawToken)
            ->getJson('/api/client/publications');

        $response->assertOk();
        $response->assertJsonPath('stats.draft', 1);
        $response->assertJsonPath('stats.review', 1);
        $response->assertJsonPath('stats.published', 2);
        $response->assertJsonPath('stats.live_published', 1);
        $response->assertJsonCount(4, 'items');

        $items = collect($response->json('items'));

        $this->assertTrue($items->contains(fn (array $item): bool => $item['slug'] === 'blog-amiante' && $item['status'] === 'draft'));
        $this->assertTrue($items->contains(fn (array $item): bool => $item['slug'] === 'guide-dta' && $item['status'] === 'review'));
        $this->assertTrue($items->contains(fn (array $item): bool => $item['slug'] === 'faq-amiante' && $item['status'] === 'published'));
        $this->assertTrue($items->contains(fn (array $item): bool => $item['slug'] === 'diagnostic-amiante-paris' && $item['published_live'] === true));
    }

    public function test_client_can_delete_owned_publication_from_workspace(): void
    {
        $user = User::factory()->create();
        $rawToken = 'frontend-publications-delete-token';

        UserAccessToken::query()->create([
            'user_id' => $user->id,
            'name' => 'frontend',
            'token_hash' => hash('sha256', $rawToken),
            'abilities' => ['client:workspace'],
        ]);

        $site = SeoSite::query()->create([
            'site_id' => 'amiantix',
            'name' => 'Amiantix',
            'url' => 'https://amiantix.com',
            'niche' => 'amiante',
            'locale' => 'fr',
            'preset' => 'amiantix',
            'api_token_hash' => hash('sha256', 'amiantix-api-token'),
            'publication_mode' => 'laravel_bridge',
        ]);

        $user->seoSites()->attach($site->id, ['role' => 'owner']);

        $page = SeoPage::query()->create([
            'site_id' => $site->site_id,
            'keyword' => 'guide amiante',
            'slug' => 'guide-amiante',
            'title' => 'Guide amiante',
            'status' => 'draft',
        ]);

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$rawToken)
            ->deleteJson('/api/client/publications/'.$page->id);

        $response->assertOk();
        $response->assertJsonPath('status', 'ok');
        $response->assertJsonPath('deleted.slug', 'guide-amiante');
        $this->assertDatabaseMissing('seo_pages', ['id' => $page->id]);
    }
}
