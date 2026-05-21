<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\SeoPage;
use App\Models\SeoSuggestion;
use App\SeoBridge\Persisters\DatabaseSeoSuggestionPersister;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Ofyre\SeoEngine\Contracts\SemanticLinkRepository;
use Ofyre\SeoEngine\Contracts\SeoPageRepository;
use Ofyre\SeoEngine\Services\Console\SeoSignalSuggestionQueueRunner;
use Ofyre\SeoEngine\Services\Suggestions\SignalSuggestionQueueService;
use Tests\TestCase;

class SignalSuggestionSourceIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_signal_queue_clears_only_its_own_pending_suggestions_and_keeps_feedback_items(): void
    {
        $page = SeoPage::query()->create([
            'site_id' => 'signal-isolation',
            'keyword' => 'diagnostic amiante paris',
            'slug' => 'diagnostic-amiante-paris',
            'status' => 'published',
            'title' => 'Diagnostic amiante Paris',
        ]);

        SeoSuggestion::query()->create([
            'seo_page_id' => $page->id,
            'source' => 'feedback_loop:auto',
            'signals_json' => ['audit' => true],
            'suggestions_json' => ['mode' => 'feedback_loop'],
            'status' => 'pending',
        ]);

        SeoSuggestion::query()->create([
            'seo_page_id' => $page->id,
            'source' => 'signal_queue:auto',
            'signals_json' => ['semantic' => true],
            'suggestions_json' => ['mode' => 'signal_queue'],
            'status' => 'pending',
        ]);

        $pages = new class([$page]) implements SeoPageRepository
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

        $semantic = new class implements SemanticLinkRepository
        {
            public function replaceInternalLinkSuggestions(string $sourceKey, array $suggestions): int
            {
                return count($suggestions);
            }

            public function internalLinkSuggestions(string $sourceKey, int $limit = 4): array
            {
                return [];
            }

            public function replaceCannibalizationRisks(string $sourceKey, array $risks): int
            {
                return count($risks);
            }

            public function cannibalizationRisks(string $sourceKey, int $limit = 5): array
            {
                return [];
            }

            public function replaceQueryPageMatches(string $sourceKey, array $matches): int
            {
                return count($matches);
            }

            public function queryPageMatches(string $sourceKey, int $limit = 6): array
            {
                return [];
            }
        };

        $runner = new SeoSignalSuggestionQueueRunner(
            new SignalSuggestionQueueService(
                $pages,
                $semantic,
                app(DatabaseSeoSuggestionPersister::class),
                null,
            ),
        );

        $summary = $runner->run($page->slug, 1);

        $this->assertSame([
            'pages' => 1,
            'queued' => 0,
            'cleared' => 1,
        ], $summary);

        $this->assertDatabaseCount('seo_suggestions', 1);
        $this->assertDatabaseHas('seo_suggestions', [
            'seo_page_id' => $page->id,
            'source' => 'feedback_loop:auto',
            'status' => 'pending',
        ]);
        $this->assertDatabaseMissing('seo_suggestions', [
            'seo_page_id' => $page->id,
            'source' => 'signal_queue:auto',
            'status' => 'pending',
        ]);
    }
}
