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
        if (($audit['score'] ?? 100) >= 85) {
            return null;
        }

        if (! $page instanceof SeoPage) {
            $page = SeoPage::query()->find((int) ($page->id ?? 0));
        }

        if (! $page) {
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
}
