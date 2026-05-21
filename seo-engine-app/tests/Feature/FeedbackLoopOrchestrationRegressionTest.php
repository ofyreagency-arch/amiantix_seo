<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\SeoPage;
use App\Models\SeoSuggestion;
use App\SeoBridge\Feedback\DatabaseSeoFeedbackLoopDriver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Ofyre\SeoEngine\Contracts\SeoPageRepository;
use Ofyre\SeoEngine\Services\Console\SeoFeedbackLoopRunner;
use Ofyre\SeoEngine\Services\Scoring\SeoScoringService;
use Ofyre\SeoEngine\Services\SearchConsole\SearchConsoleService;
use Tests\TestCase;

class FeedbackLoopOrchestrationRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_feedback_loop_runner_creates_useful_suggestions_and_clears_recovered_noise(): void
    {
        $strong = SeoPage::query()->create([
            'site_id' => 'runner-site',
            'keyword' => 'diagnostic amiante paris',
            'slug' => 'diagnostic-amiante-paris',
            'status' => 'published',
            'title' => 'Diagnostic amiante Paris avant travaux',
            'meta_description' => 'Diagnostic amiante a Paris : reperage, DTA, obligations, delais et travaux pour preparer un chantier conforme et mieux orienter la decision.',
            'content' => $this->strongContent(),
            'faq_json' => array_fill(0, 5, ['question' => 'Q', 'answer' => 'R']),
            'schema_json' => [['@type' => 'Article'], ['@type' => 'FAQPage']],
            'image_path' => 'images/amiante.png',
            'image_alt' => 'Diagnostic amiante avant travaux',
            'image_prompt' => 'Illustration amiante avant travaux',
            'topical_score' => 90,
            'spam_risk' => 'low',
            'is_indexed' => true,
        ]);

        $weak = SeoPage::query()->create([
            'site_id' => 'runner-site',
            'keyword' => 'reperage amiante prix',
            'slug' => 'reperage-amiante-prix',
            'status' => 'published',
            'title' => 'Repérage amiante prix',
            'meta_description' => 'Courte meta.',
            'content' => '<h2>Prix</h2><p>reperage amiante prix</p>',
            'faq_json' => [],
            'schema_json' => [],
            'topical_score' => 60,
            'spam_risk' => 'low',
            'is_indexed' => false,
        ]);

        SeoSuggestion::query()->create([
            'seo_page_id' => $strong->id,
            'source' => 'feedback_loop:auto',
            'signals_json' => ['legacy' => true],
            'suggestions_json' => ['mode' => 'feedback_loop'],
            'status' => 'pending',
        ]);

        $pages = new class([$strong, $weak]) implements SeoPageRepository
        {
            /**
             * @param  array<int,object>  $pages
             */
            public function __construct(private readonly array $pages) {}

            public function findBySlug(string $slug): ?object
            {
                foreach ($this->pages as $page) {
                    if (($page->slug ?? null) === $slug) {
                        return $page;
                    }
                }

                return null;
            }

            public function publishedPages(): iterable
            {
                return $this->pages;
            }

            public function pagesForScoreRefresh(?string $slug = null): iterable
            {
                return $this->pages;
            }
        };

        $searchConsole = Mockery::mock(SearchConsoleService::class);
        $searchConsole->shouldReceive('pageMetrics')->once()->with(Mockery::on(
            fn ($page): bool => $page->id === $strong->id
        ))->andReturn([
            'impressions' => 210,
            'ctr' => 0.042,
            'position' => 5.8,
            'queries' => ['diagnostic amiante paris'],
            'indexed' => true,
            'coverage' => ['index_verdict:PASS'],
        ]);
        $searchConsole->shouldReceive('pageMetrics')->once()->with(Mockery::on(
            fn ($page): bool => $page->id === $weak->id
        ))->andReturn([
            'impressions' => 160,
            'ctr' => 0.009,
            'position' => 13.8,
            'queries' => ['reperage amiante prix'],
            'indexed' => false,
            'coverage' => ['index_verdict:FAIL'],
        ]);

        $scoring = Mockery::mock(SeoScoringService::class);
        $scoring->shouldReceive('audit')->once()->with(
            Mockery::on(fn ($page): bool => $page->id === $strong->id),
            Mockery::type('array')
        )->andReturn([
            'score' => 91,
            'issues' => [],
            'recommendations' => [],
        ]);
        $scoring->shouldReceive('audit')->once()->with(
            Mockery::on(fn ($page): bool => $page->id === $weak->id),
            Mockery::type('array')
        )->andReturn([
            'score' => 54,
            'issues' => ['not_indexed', 'low_ctr', 'page_two_position'],
            'recommendations' => ['Renforcer le contenu.', 'Ameliorer le title.', 'Verifier l indexation.'],
        ]);

        $runner = new SeoFeedbackLoopRunner(
            $pages,
            $searchConsole,
            $scoring,
            app(DatabaseSeoFeedbackLoopDriver::class),
        );

        $created = $runner->run();

        $this->assertSame(1, $created);
        $this->assertDatabaseCount('seo_suggestions', 1);

        $suggestion = SeoSuggestion::query()->sole();
        $this->assertSame($weak->id, $suggestion->seo_page_id);
        $this->assertSame('feedback_loop:auto', $suggestion->source);
        $this->assertContains('not_indexed', $suggestion->suggestions_json['rationale']);
        $this->assertContains('low_ctr', $suggestion->suggestions_json['rationale']);
    }

    private function strongContent(): string
    {
        return str_repeat('<h2>Obligations</h2><h3>Detail</h3><a href="/guide">Guide</a><p>diagnostic amiante reperage dta travaux chantier obligations processus risques conformite</p>', 60);
    }
}
