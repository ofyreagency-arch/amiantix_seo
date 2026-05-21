<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\SeoPage;
use App\Models\SeoSuggestion;
use App\SeoBridge\Feedback\DatabaseSeoFeedbackLoopDriver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeedbackLoopRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_feedback_loop_persists_a_pending_suggestion_for_low_scores(): void
    {
        $page = SeoPage::query()->create([
            'site_id' => 'feedback-site',
            'keyword' => 'diagnostic amiante paris',
            'slug' => 'diagnostic-amiante-paris',
            'status' => 'published',
            'title' => 'Diagnostic amiante Paris',
            'seo_score' => 54,
        ]);

        $driver = app(DatabaseSeoFeedbackLoopDriver::class);

        $first = $driver->proposeForPage($page, [
            'ctr' => 0.012,
            'position' => 14.1,
        ], [
            'score' => 62,
            'issues' => ['content_too_short', 'weak_internal_linking'],
            'recommendations' => ['Ajouter un comparatif des cas d usage.', 'Renforcer le maillage vers les pages piliers.'],
        ]);

        $second = $driver->proposeForPage($page, [
            'ctr' => 0.010,
            'position' => 12.8,
        ], [
            'score' => 61,
            'issues' => ['content_too_short'],
            'recommendations' => ['Allonger la page avec un angle plus complet.'],
        ]);

        $this->assertNotNull($first);
        $this->assertNotNull($second);
        $this->assertDatabaseCount('seo_suggestions', 1);

        $suggestion = SeoSuggestion::query()->firstOrFail();

        $this->assertSame('feedback_loop:auto', $suggestion->source);
        $this->assertSame('pending', $suggestion->status);
        $this->assertSame(['content_too_short'], $suggestion->suggestions_json['rationale']);
        $this->assertSame(['Allonger la page avec un angle plus complet.'], $suggestion->suggestions_json['sections']);
        $this->assertSame(0.010, $suggestion->signals_json['metrics']['ctr']);
        $this->assertSame(61, $suggestion->signals_json['audit']['score']);
    }

    public function test_feedback_loop_keeps_high_scoring_pages_out_of_the_queue(): void
    {
        $page = SeoPage::query()->create([
            'site_id' => 'feedback-site',
            'keyword' => 'repérage amiante',
            'slug' => 'reperage-amiante',
            'status' => 'published',
            'title' => 'Repérage amiante',
            'seo_score' => 90,
        ]);

        $driver = app(DatabaseSeoFeedbackLoopDriver::class);

        $result = $driver->proposeForPage($page, [], [
            'score' => 85,
            'issues' => ['low_ctr'],
            'recommendations' => ['Revoir le title.'],
        ]);

        $this->assertNull($result);
        $this->assertDatabaseCount('seo_suggestions', 0);
    }
}
