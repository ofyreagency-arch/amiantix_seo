<?php

declare(strict_types=1);

namespace Ofyre\SeoEngine\Services\Scoring;

use Illuminate\Support\Str;
use Ofyre\SeoEngine\Contracts\ContentSignalProvider;

class SeoIndexabilityScoringService
{
    public function __construct(
        private readonly ?ContentSignalProvider $signals = null,
    ) {}
    public function score(object $page): int
    {
        $score = 100;
        $content = (string) ($page->content ?? '');
        $isPublished = (string) ($page->status ?? '') === 'published';
        $wordCount = str_word_count(Str::ascii(strip_tags($content)));
        $faqCount = count($page->faq_json ?? []);
        $schemaCount = count($page->schema_json ?? []);
        $linkCount = count($page->internal_links_json ?? []);

        if ($wordCount < 1300) {
            $score -= 20;
        }

        if ($faqCount < 5) {
            $score -= 12;
        }

        if ($schemaCount < 2) {
            $score -= 8;
        }

        if (! ($page->image_path ?? null) || ! ($page->image_alt ?? null) || ($page->image_status ?? null) !== 'approved') {
            $score -= 8;
        }

        if ($linkCount < 5) {
            $score -= 12;
        }

        if ($isPublished && ($page->internal_inbound_count ?? 0) < 2) {
            $score -= 12;
        }

        if (($page->cluster_links_count ?? 0) < 2) {
            $score -= 8;
        }

        if (($page->topical_score ?? 0) < 82) {
            $score -= 12;
        }

        if (($page->spam_risk ?? null) === 'medium') {
            $score -= 8;
        }

        if (($page->spam_risk ?? null) === 'high') {
            $score -= 24;
        }

        $normalizedContent = Str::lower(Str::ascii($content));
        foreach ($this->signals?->requiredContentMarkers() ?? [] as $marker) {
            if (! str_contains($normalizedContent, $marker['marker'])) {
                $score -= $marker['score_penalty'];
            }
        }

        $signals = $page->search_console_json ?? [];
        if ($isPublished && ($signals['impressions'] ?? 0) > 100 && ($signals['ctr'] ?? 0) < 0.01) {
            $score -= 5;
        }

        if ($isPublished
            && method_exists($page, 'indexationLogs')
            && $page->indexationLogs()->where('is_indexed', false)->latest('inspected_at')->limit(3)->count() >= 3) {
            $score -= 10;
        }

        return max(0, min(100, $score));
    }

    public function imageQualityScore(object $page): int
    {
        $score = 100;

        if (! ($page->image_path ?? null)) {
            $score -= 45;
        }

        $imageAlt = (string) ($page->image_alt ?? '');
        if ($imageAlt === '') {
            $score -= 25;
        }

        $requiredAltTerms = collect(config('seo-engine.quality.image_alt_required_terms', []))
            ->map(static fn (mixed $term): string => Str::lower(Str::ascii((string) $term)))
            ->filter()
            ->values();

        if ($requiredAltTerms->isNotEmpty() && ! $requiredAltTerms->contains(fn (string $term): bool => str_contains(Str::lower(Str::ascii($imageAlt)), $term))) {
            $score -= 25;
        }

        $imagePrompt = (string) ($page->image_prompt ?? '');
        if ($imagePrompt === '') {
            $score -= 15;
        }

        $requiredPromptTerms = collect(config('seo-engine.quality.image_prompt_required_terms', []))
            ->map(static fn (mixed $term): string => Str::lower(Str::ascii((string) $term)))
            ->filter()
            ->values();

        if ($requiredPromptTerms->isNotEmpty() && ! $requiredPromptTerms->contains(fn (string $term): bool => str_contains(Str::lower(Str::ascii($imagePrompt)), $term))) {
            $score -= 15;
        }

        if (! ($page->image_path ?? null) && $imagePrompt === '') {
            $score -= 15;
        }

        return max(0, min(100, $score));
    }
}
