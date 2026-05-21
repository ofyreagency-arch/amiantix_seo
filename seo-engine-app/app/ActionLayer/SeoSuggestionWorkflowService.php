<?php

declare(strict_types=1);

namespace App\ActionLayer;

use App\Models\SeoPage;
use App\Models\SeoSuggestion;

final class SeoSuggestionWorkflowService
{
    /**
     * @return array{
     *   updated_fields:array<int,string>,
     *   body_applied:bool,
     *   page_marked_for_review:bool,
     *   signal_notes_applied:bool
     * }
     */
    public function apply(SeoSuggestion $suggestion): array
    {
        /** @var SeoPage $page */
        $page = $suggestion->page()->firstOrFail();
        $payload = is_array($suggestion->suggestions_json) ? $suggestion->suggestions_json : [];
        $updates = [];

        foreach (['title', 'meta_description', 'h1'] as $field) {
            $value = trim((string) ($payload[$field] ?? ''));

            if ($value !== '') {
                $updates[$field] = $value;
            }
        }

        $content = trim((string) ($payload['content'] ?? $payload['proposed_content'] ?? ''));
        if ($content !== '') {
            $updates['content'] = $content;
        }

        if (is_array($payload['faq'] ?? null) && $payload['faq'] !== []) {
            $updates['faq_json'] = collect($payload['faq'])
                ->filter(fn (mixed $item): bool => is_array($item) && filled($item['question'] ?? null))
                ->map(fn (array $item): array => [
                    'question' => (string) ($item['question'] ?? ''),
                    'answer' => (string) ($item['answer'] ?? ''),
                ])
                ->values()
                ->all();
        }

        if (is_array($payload['internal_links'] ?? null) && $payload['internal_links'] !== []) {
            $updates['internal_links_json'] = collect($payload['internal_links'])
                ->filter(fn (mixed $item): bool => is_array($item) && filled($item['url'] ?? null))
                ->map(fn (array $item): array => [
                    'label' => (string) ($item['label'] ?? $item['text'] ?? $item['url']),
                    'url' => (string) ($item['url'] ?? ''),
                    'reason' => $item['reason'] ?? null,
                ])
                ->values()
                ->all();
        }

        if (is_array($payload['schema'] ?? null)) {
            $updates['schema_json'] = $payload['schema'];
        }

        $signalNotesApplied = false;
        if ($updates === []) {
            $reviewNotes = collect($page->review_issues_json ?? [])
                ->map(fn (mixed $item): string => is_array($item) ? (string) ($item['message'] ?? json_encode($item)) : (string) $item)
                ->merge($this->signalReviewNotes($suggestion))
                ->filter(fn (string $item): bool => trim($item) !== '')
                ->unique()
                ->values()
                ->all();

            if ($reviewNotes !== []) {
                $updates['review_issues_json'] = $reviewNotes;
                $signalNotesApplied = true;
            }
        }

        $pageMarkedForReview = false;
        if ($updates !== []) {
            if ($page->status === 'draft') {
                $updates['status'] = 'review';
                $pageMarkedForReview = true;
            }

            $page->update($updates);
        }

        $suggestion->update([
            'status' => 'applied',
            'applied_at' => now(),
        ]);

        return [
            'updated_fields' => array_keys($updates),
            'body_applied' => array_key_exists('content', $updates),
            'page_marked_for_review' => $pageMarkedForReview,
            'signal_notes_applied' => $signalNotesApplied,
        ];
    }

    /**
     * @return array<int,string>
     */
    private function signalReviewNotes(SeoSuggestion $suggestion): array
    {
        $payload = is_array($suggestion->suggestions_json) ? $suggestion->suggestions_json : [];

        return collect()
            ->merge($payload['recommendations'] ?? [])
            ->merge($payload['sections'] ?? [])
            ->merge($payload['rationale'] ?? [])
            ->filter(fn (mixed $item): bool => is_string($item) && trim($item) !== '')
            ->take(8)
            ->values()
            ->all();
    }
}
