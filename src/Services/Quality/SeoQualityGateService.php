<?php

declare(strict_types=1);

namespace Ofyre\SeoEngine\Services\Quality;

use Illuminate\Support\Str;
use Ofyre\SeoEngine\Contracts\ContentSignalProvider;
use Ofyre\SeoEngine\Contracts\NicheBlueprintProvider;

class SeoQualityGateService
{
    public function __construct(
        private readonly NicheBlueprintProvider $blueprints,
        private readonly ?ContentSignalProvider $signals = null,
    ) {}

    /**
     * @param  array<string,mixed>  $payload
     * @return array{
     *     accepted:bool,
     *     decision:string,
     *     topical_score:int,
     *     quality_score:int,
     *     spam_risk:string,
     *     issues:array<int,string>,
     *     failed_rules:array<int,string>,
     *     warnings:array<int,string>,
     *     recommendations:array<int,string>,
     *     thresholds:array<string,int|string>
     * }
     */
    public function reviewPayload(string $keyword, array $payload, ?string $cluster = null): array
    {
        $blueprint = $this->blueprints->resolve($keyword, $cluster);
        $text = Str::lower(Str::ascii($keyword.' '.($payload['title'] ?? '').' '.($payload['meta_description'] ?? '').' '.strip_tags((string) ($payload['content'] ?? ''))));
        $blockedSignals = config('seo-engine.quality.blocked_signals', ['crypto', 'casino', 'trading', 'viral', 'dropshipping', 'marketing automation']);
        $issues = [];
        $failedRules = [];
        $warnings = [];
        $recommendations = [];

        $expectedSignals = $this->blueprints->expectedSignals($blueprint);
        $topicalMatches = collect($expectedSignals)->filter(fn (string $signal): bool => str_contains($text, Str::lower(Str::ascii($signal))))->count();
        $blockedMatches = collect($blockedSignals)->filter(fn (string $signal): bool => str_contains($text, $signal))->values()->all();
        $wordCount = str_word_count(Str::ascii(strip_tags((string) ($payload['content'] ?? ''))));
        $faqCount = count($payload['faq'] ?? []);
        $h2Count = preg_match_all('/<h2[^>]*>/i', (string) ($payload['content'] ?? ''));
        $h3Count = preg_match_all('/<h3[^>]*>/i', (string) ($payload['content'] ?? ''));
        $hasTable = str_contains((string) ($payload['content'] ?? ''), '<table');
        $expectedSections = $this->blueprints->expectedEditorialSections($blueprint);
        $sectionCoverage = collect($expectedSections)
            ->filter(fn (string $section): bool => str_contains((string) ($payload['content'] ?? ''), $section))
            ->count();
        $professionSpecificCoverage = collect($blueprint['risk_terms'] ?? [])
            ->filter(fn (string $term): bool => str_contains($text, Str::lower(Str::ascii($term))))
            ->count();

        $minWordCount = (int) config('seo-engine.quality.min_word_count', 1300);
        $minFaqCount = (int) config('seo-engine.quality.min_faq_count', 5);
        $minH2Count = (int) config('seo-engine.quality.min_h2_count', 6);
        $minH3Count = (int) config('seo-engine.quality.min_h3_count', 5);
        $minTopicalScore = (int) config('seo-engine.quality.min_topical_score', 82);
        $minQualityScore = (int) config('seo-engine.quality.min_quality_score', 82);
        $minSignals = (int) config('seo-engine.quality.min_profession_specific_signals', 6);
        $minBlueprintSections = max(8, (int) ceil(count($expectedSections) * 0.72));

        $topicalScore = min(100, 38 + ($topicalMatches * 5) + ($professionSpecificCoverage * 5));
        $qualityScore = 22;

        if ($wordCount >= $minWordCount) {
            $qualityScore += 24;
        } else {
            $failedRules[] = 'quality_content_too_short';
            $issues[] = 'Content too short.';
            $recommendations[] = $this->signals?->recommendationFor('quality_content_too_short')
                ?? 'Aim for at least '.$minWordCount.' words covering the main topic angles.';
        }

        if ($faqCount >= $minFaqCount) {
            $qualityScore += 14;
        } else {
            $failedRules[] = 'quality_missing_faq_depth';
            $issues[] = 'FAQ too short or missing.';
            $recommendations[] = $this->signals?->recommendationFor('quality_missing_faq_depth')
                ?? 'Add at least '.$minFaqCount.' FAQ entries covering the main search intents.';
        }

        if ($h2Count >= $minH2Count && $h3Count >= $minH3Count) {
            $qualityScore += 14;
        } else {
            $failedRules[] = 'quality_missing_heading_depth';
            $issues[] = 'Insufficient heading structure.';
            $recommendations[] = $this->signals?->recommendationFor('quality_missing_heading_depth')
                ?? 'Add multiple H2/H3 headings covering the main topic angles.';
        }

        if ($hasTable) {
            $qualityScore += 10;
        } else {
            $failedRules[] = 'quality_missing_table';
            $issues[] = 'Missing structured table.';
            $recommendations[] = $this->signals?->recommendationFor('quality_missing_table')
                ?? 'Add a summary table adapted to the subject.';
        }

        if ($sectionCoverage >= $minBlueprintSections) {
            $qualityScore += 12;
        } else {
            $failedRules[] = 'quality_missing_blueprint_sections';
            $issues[] = 'Incomplete editorial coverage.';
            $recommendations[] = $this->signals?->recommendationFor('quality_missing_blueprint_sections')
                ?? 'Add the missing structural sections to cover the subject in depth.';
        }

        if ($professionSpecificCoverage >= $minSignals) {
            $qualityScore += 12;
        } else {
            $failedRules[] = 'quality_low_profession_depth';
            $issues[] = 'Insufficient topic-specific depth.';
            $recommendations[] = $this->signals?->recommendationFor('quality_low_profession_depth')
                ?? 'Add subject-specific terms, real cases and field constraints.';
        }

        foreach ($blockedMatches as $blocked) {
            $failedRules[] = 'blocked_topic:'.$blocked;
            $issues[] = 'Blocked topic detected: '.$blocked.'.';
            $recommendations[] = 'Remove out-of-scope references and refocus the content.';
        }

        foreach ($this->signals?->genericPhraseWarnings() ?? [] as $phraseWarning) {
            if (str_contains($text, (string) ($phraseWarning['phrase'] ?? ''))) {
                $warnings[] = (string) ($phraseWarning['warning'] ?? '');
            }
        }

        $qualityScore = min(100, $qualityScore);
        $spamRisk = $blockedMatches !== [] || $topicalScore < 78 || $professionSpecificCoverage < 4
            ? 'high'
            : ($qualityScore < $minQualityScore ? 'medium' : 'low');
        $accepted = $topicalScore >= $minTopicalScore && $qualityScore >= $minQualityScore && $spamRisk !== 'high';

        if ($topicalScore < $minTopicalScore) {
            $failedRules[] = 'topical_score_below_threshold';
            $issues[] = 'Topical coverage below threshold.';
            $recommendations[] = $this->signals?->recommendationFor('topical_score_below_threshold')
                ?? 'Strengthen niche signals and topic-specific semantics.';
        }

        if ($professionSpecificCoverage < 5) {
            $failedRules[] = 'profession_specificity_below_threshold';
            $issues[] = 'Content is too close to a generic SEO template.';
            $recommendations[] = $this->signals?->recommendationFor('profession_specificity_below_threshold')
                ?? 'Add subject-specific terms, real cases and field constraints.';
        }

        if ($spamRisk === 'medium') {
            $warnings[] = $this->signals?->recommendationFor('spam_risk_medium')
                ?? 'Strengthen content depth and uniqueness.';
        }

        if ($spamRisk === 'high') {
            $failedRules[] = 'spam_risk_high';
            $issues[] = 'High spam risk or excessive genericness detected.';
            $recommendations[] = $this->signals?->recommendationFor('spam_risk_high')
                ?? 'Rewrite with a more specific angle and reduce repetition.';
        }

        return [
            'accepted' => $accepted,
            'decision' => $accepted ? 'accepted' : ($spamRisk === 'high' ? 'rejected' : 'needs_review'),
            'topical_score' => $topicalScore,
            'quality_score' => $qualityScore,
            'spam_risk' => $spamRisk,
            'issues' => array_values(array_unique($issues)),
            'failed_rules' => array_values(array_unique($failedRules)),
            'warnings' => array_values(array_unique($warnings)),
            'recommendations' => array_values(array_unique($recommendations)),
            'thresholds' => [
                'min_topical_score' => $minTopicalScore,
                'min_quality_score' => $minQualityScore,
                'min_word_count' => $minWordCount,
                'min_faq_count' => $minFaqCount,
                'min_h2_count' => $minH2Count,
                'min_h3_count' => $minH3Count,
                'min_profession_specific_signals' => $minSignals,
                'min_blueprint_sections' => $minBlueprintSections,
            ],
        ];
    }

    /**
     * @return array{
     *     accepted:bool,
     *     decision:string,
     *     topical_score:int,
     *     quality_score:int,
     *     spam_risk:string,
     *     issues:array<int,string>,
     *     failed_rules:array<int,string>,
     *     warnings:array<int,string>,
     *     recommendations:array<int,string>,
     *     thresholds:array<string,int|string>
     * }
     */
    public function reviewPage(object $page): array
    {
        return $this->reviewPayload((string) ($page->keyword ?? ''), [
            'title' => (string) ($page->title ?? ''),
            'meta_description' => (string) ($page->meta_description ?? ''),
            'content' => (string) ($page->content ?? ''),
            'faq' => $page->faq_json ?? [],
        ], $page->cluster ?? null);
    }
}
