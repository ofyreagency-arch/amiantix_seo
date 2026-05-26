<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\SeoPage;
use App\Models\SeoSite;
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
}
