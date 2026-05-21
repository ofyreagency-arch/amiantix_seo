<?php

declare(strict_types=1);

namespace Ofyre\SeoEngine\Services\Scoring;

use Illuminate\Support\Str;
use Ofyre\SeoEngine\Contracts\ContentSignalProvider;

class SeoScoringService
{
    public function __construct(
        private readonly ?ContentSignalProvider $signals = null,
    ) {}
    /**
     * @param  array<string,mixed>  $searchConsoleData
     * @return array{score:int,issues:array<int,string>,recommendations:array<int,string>}
     */
    public function audit(object $page, array $searchConsoleData = []): array
    {
        $score = 100;
        $issues = [];
        $recommendations = [];
        $content = (string) ($page->content ?? '');
        $plainContent = trim(strip_tags($content));
        $wordCount = str_word_count(Str::ascii($plainContent));
        $faqCount = count($page->faq_json ?? []);
        $internalLinks = substr_count($content, 'href="/') + substr_count($content, 'href="https://');
        $headings = preg_match_all('/<h[2-3][^>]*>/i', $content);
        $titleLength = Str::length((string) ($page->title ?? ''));
        $metaLength = Str::length((string) ($page->meta_description ?? ''));

        if ($wordCount < 1300) {
            $score -= 22;
            $issues[] = 'content_too_short';
            $recommendations[] = $this->signals?->recommendationFor('content_too_short')
                ?? 'Add content depth to reach sufficient editorial quality.';
        }

        if ($faqCount < 5) {
            $score -= 14;
            $issues[] = 'missing_or_short_faq';
            $recommendations[] = $this->signals?->recommendationFor('missing_or_short_faq')
                ?? 'Add at least 5 FAQ entries covering the main search intents.';
        }

        if ($internalLinks < 3) {
            $score -= 8;
            $issues[] = 'weak_internal_linking';
            $recommendations[] = 'Add more strategic internal links to semantically close pages.';
        }

        if ($headings < 6) {
            $score -= 12;
            $issues[] = 'weak_heading_structure';
            $recommendations[] = $this->signals?->recommendationFor('weak_heading_structure')
                ?? 'Structure the page with multiple H2/H3 headings covering the main topic angles.';
        }

        if (empty($page->schema_json)) {
            $score -= 8;
            $issues[] = 'missing_schema';
            $recommendations[] = 'Add Article and FAQPage JSON-LD schema markup.';
        }

        if ($titleLength < 35 || $titleLength > 70) {
            $score -= 6;
            $issues[] = 'title_length_to_optimize';
            $recommendations[] = 'Adjust the title to 45–65 characters including the main keyword.';
        }

        if ($metaLength < 120 || $metaLength > 170) {
            $score -= 6;
            $issues[] = 'meta_description_length_to_optimize';
            $recommendations[] = 'Rewrite the meta description to 140–160 characters with a clear value proposition.';
        }

        if (! ($page->image_path ?? null) && ! ($page->image_prompt ?? null)) {
            $score -= 6;
            $issues[] = 'missing_seo_image';
            $recommendations[] = 'Generate an SEO image with a matching alt tag.';
        }

        $normalizedContent = Str::lower(Str::ascii($content));
        foreach ($this->signals?->requiredContentMarkers() ?? [] as $marker) {
            if (! str_contains($normalizedContent, $marker['marker'])) {
                $score -= $marker['score_penalty'];
                $issues[] = $marker['issue_key'];
                $recommendation = $this->signals->recommendationFor($marker['issue_key']);
                if ($recommendation !== null) {
                    $recommendations[] = $recommendation;
                }
            }
        }

        if (($page->topical_score ?? 0) > 0 && ($page->topical_score ?? 0) < 75) {
            $score -= 20;
            $issues[] = 'topic_outside_expected_scope';
            $recommendations[] = 'Reject or rewrite the page to stay within the expected editorial scope.';
        }

        if (($page->spam_risk ?? null) === 'high') {
            $score -= 20;
            $issues[] = 'high_spam_risk';
            $recommendations[] = 'Block publication and review the editorial brief.';
        }

        $ctr = $searchConsoleData['ctr'] ?? null;
        if (is_numeric($ctr) && $ctr < 0.015) {
            $score -= 10;
            $issues[] = 'low_ctr';
            $recommendations[] = 'Test a clearer title and a more result-oriented meta description.';
        }

        $position = $searchConsoleData['position'] ?? null;
        if (is_numeric($position) && $position >= 11 && $position <= 20) {
            $score -= 10;
            $issues[] = 'page_two_position';
            $recommendations[] = 'Strengthen semantic depth and add content matching Search Console queries.';
        }

        $indexed = $page->indexed ?? $page->is_indexed ?? $searchConsoleData['indexed'] ?? false;

        if (! $indexed) {
            $score -= 10;
            $issues[] = 'not_indexed';
            $recommendations[] = 'Check indexation status, sitemap, canonical tags and content quality before requesting re-indexation.';
        }

        return [
            'score' => max(0, min(100, $score)),
            'issues' => array_values(array_unique($issues)),
            'recommendations' => array_values(array_unique($recommendations)),
        ];
    }
}
