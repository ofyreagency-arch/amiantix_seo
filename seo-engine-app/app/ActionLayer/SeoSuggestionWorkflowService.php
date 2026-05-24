<?php

declare(strict_types=1);

namespace App\ActionLayer;

use Illuminate\Support\Str;
use App\Models\SeoPage;
use App\Models\SeoSuggestion;
use Ofyre\SeoEngine\Services\Quality\SeoQualityGateService;
use Ofyre\SeoEngine\Services\Scoring\SeoIndexabilityScoringService;
use Ofyre\SeoEngine\Services\Scoring\SeoScoringService;

final class SeoSuggestionWorkflowService
{
    public function __construct(
        private readonly SeoQualityGateService $qualityGate,
        private readonly SeoScoringService $scoring,
        private readonly SeoIndexabilityScoringService $indexability,
    ) {}

    /**
     * @return array{
     *   updated_fields:array<int,string>,
     *   body_applied:bool,
     *   page_marked_for_review:bool,
     *   signal_notes_applied:bool,
     *   content_blocked_for_regression:bool
     * }
     */
    public function apply(SeoSuggestion $suggestion): array
    {
        /** @var SeoPage $page */
        $page = $suggestion->page()->firstOrFail();
        $payload = is_array($suggestion->suggestions_json) ? $suggestion->suggestions_json : [];
        $updates = [];
        $contentBlockedForRegression = false;

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
            $incomingFaq = collect($payload['faq'])
                ->filter(fn (mixed $item): bool => is_array($item) && filled($item['question'] ?? null))
                ->map(fn (array $item): array => [
                    'question' => (string) ($item['question'] ?? ''),
                    'answer' => (string) ($item['answer'] ?? ''),
                ])
                ->values()
                ->all();

            if ($incomingFaq !== []) {
                $updates['faq_json'] = $this->mergeFaq($page->faq_json ?? [], $incomingFaq);
            }
        }

        if (is_array($payload['internal_links'] ?? null) && $payload['internal_links'] !== []) {
            $incomingLinks = collect($payload['internal_links'])
                ->filter(fn (mixed $item): bool => is_array($item) && filled($item['url'] ?? null))
                ->map(fn (array $item): array => [
                    'label' => (string) ($item['label'] ?? $item['text'] ?? $item['url']),
                    'url' => (string) ($item['url'] ?? ''),
                    'reason' => $item['reason'] ?? null,
                ])
                ->values()
                ->all();

            if ($incomingLinks !== []) {
                $updates['internal_links_json'] = $this->mergeInternalLinks($page->internal_links_json ?? [], $incomingLinks);
            }
        }

        if (is_array($payload['schema'] ?? null)) {
            $updates['schema_json'] = $payload['schema'];
        }

        if (array_key_exists('content', $updates) && ! $this->contentUpdateIsSafe($page, $updates)) {
            unset($updates['content']);
            $contentBlockedForRegression = true;
        }

        $signalNotesApplied = false;
        if ($updates === []) {
            $reviewNotes = collect($page->review_issues_json ?? [])
                ->map(fn (mixed $item): string => is_array($item) ? (string) ($item['message'] ?? json_encode($item)) : (string) $item)
                ->merge($this->signalReviewNotes($suggestion))
                ->merge($contentBlockedForRegression ? ['Content patch skipped because it would degrade the current article quality.'] : [])
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
            if ($contentBlockedForRegression) {
                $updates['review_issues_json'] = collect($page->review_issues_json ?? [])
                    ->map(fn (mixed $item): string => is_array($item) ? (string) ($item['message'] ?? json_encode($item)) : (string) $item)
                    ->push('Content patch skipped because it would degrade the current article quality.')
                    ->filter(fn (string $item): bool => trim($item) !== '')
                    ->unique()
                    ->values()
                    ->all();
            }

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
            'content_blocked_for_regression' => $contentBlockedForRegression,
        ];
    }

    /**
     * @param  array<int,array{question:string,answer:string}>  $current
     * @param  array<int,array{question:string,answer:string}>  $incoming
     * @return array<int,array{question:string,answer:string}>
     */
    private function mergeFaq(array $current, array $incoming): array
    {
        return collect($current)
            ->merge($incoming)
            ->filter(fn (mixed $item): bool => is_array($item) && filled($item['question'] ?? null))
            ->unique(fn (array $item): string => Str::lower(trim((string) ($item['question'] ?? ''))))
            ->values()
            ->all();
    }

    /**
     * @param  array<int,array{label:string,url:string,reason:mixed}>  $current
     * @param  array<int,array{label:string,url:string,reason:mixed}>  $incoming
     * @return array<int,array{label:string,url:string,reason:mixed}>
     */
    private function mergeInternalLinks(array $current, array $incoming): array
    {
        return collect($current)
            ->merge($incoming)
            ->filter(fn (mixed $item): bool => is_array($item) && filled($item['url'] ?? null))
            ->unique(fn (array $item): string => Str::lower(trim((string) ($item['url'] ?? ''))))
            ->values()
            ->all();
    }

    /**
     * @param  array<string,mixed>  $updates
     */
    private function contentUpdateIsSafe(SeoPage $page, array $updates): bool
    {
        $before = $this->evaluateSnapshot($page);
        $candidate = clone $page;

        foreach ($updates as $key => $value) {
            $candidate->{$key} = $value;
        }

        $after = $this->evaluateSnapshot($candidate);

        if ($before['protected']) {
            if ($after['quality_score'] < $before['quality_score']) {
                return false;
            }

            if ($after['topical_score'] < $before['topical_score']) {
                return false;
            }

            if ($after['seo_score'] < ($before['seo_score'] - 3)) {
                return false;
            }

            if ($after['indexability_score'] < ($before['indexability_score'] - 2)) {
                return false;
            }

            if ($after['word_count'] < (int) floor($before['word_count'] * 0.85)) {
                return false;
            }

            if ($after['faq_count'] < $before['faq_count']) {
                return false;
            }

            if ($after['h2_count'] < max(0, $before['h2_count'] - 1)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array{
     *   seo_score:int,
     *   indexability_score:int,
     *   topical_score:int,
     *   quality_score:int,
     *   word_count:int,
     *   faq_count:int,
     *   h2_count:int,
     *   protected:bool
     * }
     */
    private function evaluateSnapshot(object $page): array
    {
        $review = $this->qualityGate->reviewPage($page);
        $candidate = clone $page;
        $candidate->topical_score = $review['topical_score'];
        $candidate->quality_score = $review['quality_score'];
        $candidate->spam_risk = $review['spam_risk'];

        $audit = $this->scoring->audit($candidate);
        $content = (string) ($candidate->content ?? '');

        return [
            'seo_score' => (int) ($audit['score'] ?? 0),
            'indexability_score' => $this->indexability->score($candidate),
            'topical_score' => (int) ($review['topical_score'] ?? 0),
            'quality_score' => (int) ($review['quality_score'] ?? 0),
            'word_count' => str_word_count(Str::ascii(strip_tags($content))),
            'faq_count' => count($candidate->faq_json ?? []),
            'h2_count' => preg_match_all('/<h2[^>]*>/i', $content),
            'protected' => $this->isProtectedArticle($candidate, $review, $audit),
        ];
    }

    /**
     * @param  array{topical_score:int,quality_score:int,spam_risk:string}  $review
     * @param  array{score:int,issues:array<int,string>,recommendations:array<int,string>}  $audit
     */
    private function isProtectedArticle(object $page, array $review, array $audit): bool
    {
        $content = (string) ($page->content ?? '');
        $wordCount = str_word_count(Str::ascii(strip_tags($content)));
        $h2Count = preg_match_all('/<h2[^>]*>/i', $content);

        return $wordCount >= 1200
            || $h2Count >= 6
            || ((int) ($review['quality_score'] ?? 0) >= 90
                && (int) ($review['topical_score'] ?? 0) >= 90
                && (int) ($audit['score'] ?? 0) >= 70);
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
