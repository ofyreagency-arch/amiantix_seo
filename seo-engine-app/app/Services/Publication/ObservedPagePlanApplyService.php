<?php

declare(strict_types=1);

namespace App\Services\Publication;

use App\Models\SeoPage;

final class ObservedPagePlanApplyService
{
    /**
     * @param  array{sections?:array<int,string>,topics?:array<int,string>,faq?:array<int,string>,content_summary?:string,title_change?:string|null}  $plan
     */
    public function apply(SeoPage $page, array $plan): SeoPage
    {
        $content = (string) ($page->content ?? '');
        $faq = is_array($page->faq_json) ? $page->faq_json : [];
        $existingQuestions = collect($faq)
            ->map(fn (mixed $item): string => is_array($item) ? strtolower(trim((string) ($item['question'] ?? ''))) : '')
            ->filter(fn (string $question): bool => $question !== '')
            ->all();

        foreach ($plan['sections'] ?? [] as $section) {
            $heading = $this->normalizeSectionLabel((string) $section);

            if ($heading === '' || $this->contentContainsHeading($content, $heading)) {
                continue;
            }

            $content .= sprintf(
                '<h2>%s</h2><p>%s</p>',
                e($heading),
                e($this->sectionPlaceholder($heading)),
            );
        }

        foreach ($plan['faq'] ?? [] as $question) {
            $normalized = trim((string) $question);

            if ($normalized === '' || in_array(strtolower($normalized), $existingQuestions, true)) {
                continue;
            }

            $faq[] = [
                'question' => $normalized,
                'answer' => $this->faqPlaceholder($normalized),
            ];
            $existingQuestions[] = strtolower($normalized);
        }

        $updates = [
            'content' => $content,
            'faq_json' => $faq,
            'status' => 'published',
            'published_at' => $page->published_at ?? now(),
        ];

        $titleChange = trim((string) ($plan['title_change'] ?? ''));

        if ($titleChange !== '') {
            $updates['title'] = $titleChange;
            $updates['h1'] = $titleChange;
        }

        $page->forceFill($updates)->save();

        return $page->refresh();
    }

    private function normalizeSectionLabel(string $section): string
    {
        $label = preg_replace('/^Section (?:manquante|à ajouter)\s*:\s*/iu', '', trim($section)) ?: trim($section);

        return trim($label);
    }

    private function contentContainsHeading(string $content, string $heading): bool
    {
        return stripos(strip_tags($content), $heading) !== false;
    }

    private function sectionPlaceholder(string $heading): string
    {
        return sprintf(
            'Cette section enrichit la page autour de « %s » pour mieux répondre aux recherches des internautes.',
            $heading,
        );
    }

    private function faqPlaceholder(string $question): string
    {
        return sprintf(
            'Réponse à structurer autour de « %s » pour rassurer les visiteurs et renforcer la pertinence SEO de la page.',
            rtrim($question, ' ?'),
        );
    }
}
