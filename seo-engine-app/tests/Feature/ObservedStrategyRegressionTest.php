<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\SeoRecommendation;
use App\Models\SeoSite;
use App\Models\SeoSitePage;
use App\Models\SeoStrategyItem;
use App\Recommendations\RecommendationEngineService;
use App\Understanding\SiteUnderstandingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ObservedStrategyRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_strategy_generation_excludes_auth_and_utility_false_positives_on_amiantix(): void
    {
        $site = SeoSite::query()->create([
            'site_id' => 'amiantix',
            'name' => 'Amiantix',
            'url' => 'https://amiantix.com',
            'locale' => 'fr',
            'preset' => 'amiantix',
            'api_token_hash' => hash('sha256', 'token'),
        ]);

        $servicePage = SeoSitePage::query()->create([
            'site_id' => $site->site_id,
            'normalized_url' => 'https://amiantix.com/expertise-amiante',
            'url_hash' => sha1('https://amiantix.com/expertise-amiante'),
            'path' => '/expertise-amiante',
            'title' => 'Expertise amiante',
            'cluster_label' => 'expertise-amiante',
            'indexability_state' => 'indexable',
            'latest_word_count' => 220,
            'authority_score' => 0.14,
        ]);

        $source = SeoSitePage::query()->create([
            'site_id' => $site->site_id,
            'normalized_url' => 'https://amiantix.com/login',
            'url_hash' => sha1('https://amiantix.com/login'),
            'path' => '/login',
            'title' => 'Connexion',
            'indexability_state' => 'indexable',
        ]);

        $target = SeoSitePage::query()->create([
            'site_id' => $site->site_id,
            'normalized_url' => 'https://amiantix.com/forgot-password',
            'url_hash' => sha1('https://amiantix.com/forgot-password'),
            'path' => '/forgot-password',
            'title' => 'Mot de passe oublié',
            'indexability_state' => 'indexable',
        ]);

        $understanding = Mockery::mock(SiteUnderstandingService::class);
        $understanding->shouldReceive('analyze')
            ->once()
            ->andReturn([
                'orphan_pages' => [
                    [
                        'id' => $servicePage->id,
                        'url' => 'https://amiantix.com/expertise-amiante',
                        'title' => 'Expertise amiante',
                        'cluster' => 'expertise-amiante',
                        'orphan_score' => 0.87,
                        'inlinks' => 1,
                    ],
                    [
                        'id' => 501,
                        'url' => 'https://amiantix.com/login',
                        'title' => 'Connexion',
                        'orphan_score' => 0.99,
                        'inlinks' => 0,
                    ],
                ],
                'weak_pages' => [
                    [
                        'id' => $servicePage->id,
                        'url' => 'https://amiantix.com/expertise-amiante',
                        'title' => 'Expertise amiante',
                        'cluster' => 'expertise-amiante',
                        'word_count' => 220,
                        'authority_score' => 0.14,
                        'indexability_state' => 'indexable',
                        'missing_h1' => false,
                    ],
                    [
                        'id' => 502,
                        'url' => 'https://amiantix.com/newsletter/desinscription',
                        'title' => 'Désinscription newsletter',
                        'cluster' => 'newsletter',
                        'word_count' => 90,
                        'authority_score' => 0.08,
                        'indexability_state' => 'noindex',
                        'missing_h1' => true,
                    ],
                ],
                'overlaps' => [
                    [
                        'source_id' => $source->id,
                        'target_id' => $target->id,
                        'score' => 0.93,
                    ],
                    [
                        'source_id' => $target->id,
                        'target_id' => $source->id,
                        'score' => 0.93,
                    ],
                ],
                'content_gaps' => [
                    [
                        'cluster' => 'diagnostic-amiante',
                        'page_count' => 1,
                        'avg_word_count' => 220,
                        'avg_authority' => 0.14,
                        'reason' => 'undercovered_cluster',
                    ],
                ],
            ]);

        $this->app->instance(SiteUnderstandingService::class, $understanding);

        $saved = app(RecommendationEngineService::class)->generate($site);

        $this->assertCount(2, $saved);
        $this->assertSame(2, SeoRecommendation::query()->where('site_id', $site->site_id)->count());
        $this->assertSame(2, SeoStrategyItem::query()->where('site_id', $site->site_id)->count());

        $titles = $saved->pluck('title')->all();

        $this->assertContains('Strengthen weak page: Expertise amiante', $titles);
        $this->assertContains('Expand cluster: diagnostic-amiante', $titles);

        $this->assertNotContains('Reconnect orphan page: Connexion', $titles);
        $this->assertNotContains('Strengthen weak page: Désinscription newsletter', $titles);
        $this->assertNotContains('Resolve overlap: Connexion vs Mot de passe oublié', $titles);

        $refreshRecommendation = SeoRecommendation::query()
            ->where('site_id', $site->site_id)
            ->where('type', 'refresh_page')
            ->firstOrFail();

        $meta = is_array($refreshRecommendation->meta_json) ? $refreshRecommendation->meta_json : [];

        $this->assertSame('SEO_PAGE', data_get($meta, 'page_classification.page_type'));
        $this->assertSame('MONEY_PAGE', data_get($meta, 'business_intent.intent_type'));
        $this->assertTrue((bool) data_get($meta, 'eligibility.eligible'));
        $this->assertGreaterThan(0, (int) data_get($meta, 'scoring.recommendation_score'));
        $this->assertStringContainsString('Impact estimé', $refreshRecommendation->reasoning);

        $strategyItem = SeoStrategyItem::query()
            ->where('site_id', $site->site_id)
            ->where('title', 'Strengthen weak page: Expertise amiante')
            ->firstOrFail();

        $this->assertContains('Expertise amiante', $strategyItem->keywords_json);
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }
}
