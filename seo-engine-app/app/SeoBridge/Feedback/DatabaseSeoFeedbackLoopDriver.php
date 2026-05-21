<?php

declare(strict_types=1);

namespace App\SeoBridge\Feedback;

use App\Models\SeoPage;
use App\SeoBridge\Persisters\DatabaseSeoSuggestionPersister;
use Ofyre\SeoEngine\Contracts\SeoFeedbackLoopDriver;

class DatabaseSeoFeedbackLoopDriver implements SeoFeedbackLoopDriver
{
    public function __construct(
        private readonly DatabaseSeoSuggestionPersister $suggestions,
    ) {}

    public function proposeForPage(object $page, array $metrics, array $audit): mixed
    {
        if (! $page instanceof SeoPage) {
            $page = SeoPage::query()->find((int) ($page->id ?? 0));
        }

        if (! $page) {
            return null;
        }

        if ($this->pageHasRecovered($metrics, $audit)) {
            $this->suggestions->discardPending($page, 'feedback_loop:auto');

            return null;
        }

        return $this->suggestions->replacePending($page, 'feedback_loop:auto', [
            'source' => 'feedback_loop:auto',
            'signals_json' => [
                'metrics' => $metrics,
                'audit' => $audit,
            ],
            'suggestions_json' => [
                'mode' => 'feedback_loop',
                'title' => null,
                'meta_description' => null,
                'h1' => null,
                'sections' => $audit['recommendations'] ?? [],
                'faq' => [],
                'internal_links' => [],
                'rationale' => $audit['issues'] ?? [],
            ],
            'status' => 'pending',
        ]);
    }

    private function pageHasRecovered(array $metrics, array $audit): bool
    {
        if (($audit['score'] ?? 100) >= 85) {
            return true;
        }

        $indexed = (bool) ($metrics['indexed'] ?? false);
        $impressions = (float) ($metrics['impressions'] ?? 0);
        $ctr = (float) ($metrics['ctr'] ?? 0);
        $position = (float) ($metrics['position'] ?? 100);

        return $indexed
            && $impressions >= 100
            && $ctr >= 0.03
            && $position <= 10.0;
    }
}
