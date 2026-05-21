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

    public function test_strategy_generation_deduplicates_and_contextualizes_observed_actions(): void
    {
        $site = SeoSite::query()->create([
            'site_id' => 'amiantix',
            'name' => 'Amiantix',
            'url' => 'https://amiantix.com',
            'locale' => 'fr',
            'preset' => 'amiantix',
            'api_token_hash' => hash('sha256', 'token'),
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
            'normalized_url' => 'https://amiantix.com/mot-de-passe-oublie',
            'url_hash' => sha1('https://amiantix.com/mot-de-passe-oublie'),
            'path' => '/mot-de-passe-oublie',
            'title' => 'Mot de passe oublié',
            'indexability_state' => 'indexable',
        ]);

        $understanding = Mockery::mock(SiteUnderstandingService::class);
        $understanding->shouldReceive('analyze')
            ->once()
            ->andReturn([
                'orphan_pages' => [
                    [
                        'id' => 101,
                        'url' => 'https://amiantix.com/newsletter',
                        'title' => 'Newsletter expertise amiante',
                        'orphan_score' => 0.99,
                        'inlinks' => 0,
                    ],
                    [
                        'id' => 101,
                        'url' => 'https://amiantix.com/newsletter',
                        'title' => 'Newsletter expertise amiante',
                        'orphan_score' => 0.99,
                        'inlinks' => 0,
                    ],
                ],
                'weak_pages' => [
                    [
                        'id' => 102,
                        'url' => 'https://amiantix.com/desinscription',
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

        $this->assertCount(4, $saved);
        $this->assertSame(4, SeoRecommendation::query()->where('site_id', $site->site_id)->count());
        $this->assertSame(4, SeoStrategyItem::query()->where('site_id', $site->site_id)->count());

        $titles = $saved->pluck('title')->all();

        $this->assertContains('Reconnect orphan page: Newsletter expertise amiante', $titles);
        $this->assertContains('Strengthen weak page: Désinscription newsletter', $titles);
        $this->assertContains('Resolve overlap: Connexion vs Mot de passe oublié', $titles);
        $this->assertContains('Expand cluster: diagnostic-amiante', $titles);

        $overlapRecommendation = SeoRecommendation::query()
            ->where('site_id', $site->site_id)
            ->where('type', 'differentiate_intent')
            ->firstOrFail();

        $this->assertStringContainsString('Connexion', $overlapRecommendation->reasoning);
        $this->assertStringContainsString('Mot de passe oublié', $overlapRecommendation->reasoning);

        $strategyItem = SeoStrategyItem::query()
            ->where('site_id', $site->site_id)
            ->where('title', 'Resolve overlap: Connexion vs Mot de passe oublié')
            ->firstOrFail();

        $this->assertContains('Connexion', $strategyItem->keywords_json);
        $this->assertContains('Mot de passe oublié', $strategyItem->keywords_json);
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }
}
