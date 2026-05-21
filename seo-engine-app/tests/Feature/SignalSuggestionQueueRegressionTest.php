<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\SeoPage;
use App\Models\SeoSemanticLink;
use App\Models\SeoSite;
use App\Models\SeoSuggestion;
use App\Runtime\SeoEngineContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Ofyre\SeoEngine\Services\Suggestions\SignalSuggestionQueueService;
use Tests\TestCase;

class SignalSuggestionQueueRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('seo-engine.embeddings.max_internal_link_suggestions', 4);
        config()->set('seo-engine.embeddings.max_cannibalization_risks', 4);
        config()->set('seo-engine.embeddings.max_query_opportunities', 6);
    }

    public function test_signal_queue_combines_internal_links_cannibalization_and_queries_into_one_draft(): void
    {
        $site = SeoSite::query()->create([
            'site_id' => 'signal-site',
            'name' => 'Signal Site',
            'url' => 'https://signal.test',
            'niche' => 'amiante',
            'locale' => 'fr',
            'preset' => 'generic',
            'api_token_hash' => hash('sha256', 'token'),
            'is_active' => true,
        ]);

        app(SeoEngineContext::class)->loadFromSite($site);

        $page = SeoPage::query()->create([
            'site_id' => $site->site_id,
            'keyword' => 'diagnostic amiante paris',
            'slug' => 'diagnostic-amiante-paris',
            'status' => 'published',
            'published_at' => now(),
            'title' => 'Diagnostic amiante Paris',
        ]);

        SeoSemanticLink::query()->create([
            'site_id' => $site->site_id,
            'relation_type' => 'internal_link',
            'source_key' => $page->slug,
            'source_id' => $page->id,
            'target_key' => 'guide-dta',
            'target_id' => 101,
            'label' => 'Guide DTA',
            'url' => 'https://signal.test/guide-dta',
            'reason' => 'pillar_target',
            'similarity_score' => 0.93,
            'meta_json' => ['cluster' => 'diagnostic'],
        ]);

        SeoSemanticLink::query()->create([
            'site_id' => $site->site_id,
            'relation_type' => 'cannibalization',
            'source_key' => $page->slug,
            'source_id' => $page->id,
            'target_key' => 'diagnostic-amiante-ile-de-france',
            'target_id' => 102,
            'label' => 'Diagnostic amiante Ile-de-France',
            'url' => 'https://signal.test/diagnostic-amiante-ile-de-france',
            'reason' => 'differentiate_angle',
            'similarity_score' => 0.89,
            'meta_json' => ['recommended_action' => 'differentiate_angle'],
        ]);

        SeoSemanticLink::query()->create([
            'site_id' => $site->site_id,
            'relation_type' => 'query_match',
            'source_key' => $page->slug,
            'source_id' => $page->id,
            'target_key' => 'query-diagnostic-amiante-prix',
            'target_id' => 103,
            'label' => 'diagnostic amiante prix',
            'url' => $page->canonicalPath(),
            'reason' => 'review_wrong_ranking_page',
            'similarity_score' => 0.87,
            'meta_json' => [
                'query' => 'diagnostic amiante prix',
                'impressions' => 180,
                'position' => 11.4,
            ],
        ]);

        $result = app(SignalSuggestionQueueService::class)->queue();

        $this->assertSame([
            'pages' => 1,
            'queued' => 1,
            'cleared' => 0,
        ], $result);

        $suggestion = SeoSuggestion::query()->firstOrFail();

        $this->assertSame('signal_queue:auto', $suggestion->source);
        $this->assertSame('signal_queue', $suggestion->suggestions_json['mode']);
        $this->assertNotEmpty($suggestion->suggestions_json['sections']);
        $this->assertNotEmpty($suggestion->suggestions_json['faq']);
        $this->assertNotEmpty($suggestion->suggestions_json['internal_links']);
        $this->assertNotEmpty($suggestion->suggestions_json['rationale']);
        $this->assertSame([
            'internal_links' => 1,
            'cannibalization_risks' => 1,
            'query_opportunities' => 1,
        ], $suggestion->suggestions_json['signals_summary']);
        $this->assertSame('Guide DTA', $suggestion->suggestions_json['internal_links'][0]['label']);
        $this->assertSame('diagnostic amiante prix', $suggestion->signals_json['query_opportunities'][0]['meta']['query']);
    }

    public function test_signal_queue_clears_pending_drafts_when_signals_disappear(): void
    {
        $site = SeoSite::query()->create([
            'site_id' => 'signal-site-clear',
            'name' => 'Signal Site Clear',
            'url' => 'https://signal-clear.test',
            'niche' => 'amiante',
            'locale' => 'fr',
            'preset' => 'generic',
            'api_token_hash' => hash('sha256', 'token'),
            'is_active' => true,
        ]);

        app(SeoEngineContext::class)->loadFromSite($site);

        $page = SeoPage::query()->create([
            'site_id' => $site->site_id,
            'keyword' => 'dta immeuble',
            'slug' => 'dta-immeuble',
            'status' => 'published',
            'published_at' => now(),
            'title' => 'DTA immeuble',
        ]);

        SeoSuggestion::query()->create([
            'seo_page_id' => $page->id,
            'source' => 'signal_queue:auto',
            'signals_json' => ['legacy' => true],
            'suggestions_json' => ['mode' => 'signal_queue'],
            'status' => 'pending',
        ]);

        $result = app(SignalSuggestionQueueService::class)->queue();

        $this->assertSame([
            'pages' => 1,
            'queued' => 0,
            'cleared' => 1,
        ], $result);
        $this->assertDatabaseCount('seo_suggestions', 0);
    }
}
