<?php

declare(strict_types=1);

namespace App\Copilot;

use App\Models\SeoPage;
use App\Models\SeoSite;
use App\Models\SeoSitePage;
use App\Models\SeoSitePageSnapshot;
use App\Services\Publication\ObservedNativePublicationGuard;
use App\Services\Publication\PreviewPublicationEligibility;

final class ActionPreviewService
{
    public function __construct(
        private readonly BusinessCopilotModificationPlanner $modificationPlanner,
        private readonly ActionApplyContextService $applyContext,
        private readonly PageModificationEvidenceService $pageEvidence,
        private readonly PreviewPublicationEligibility $confirmPublishEligibility,
        private readonly ObservedNativePublicationGuard $nativeGuard,
    ) {}

    /**
     * @return array<string,mixed>|null
     */
    public function build(string $siteId, string $slug, ?string $query = null): ?array
    {
        $siteId = trim($siteId);
        $slug = ltrim(trim($slug), '/');

        if ($siteId === '' || $slug === '') {
            return null;
        }

        $site = SeoSite::query()->where('site_id', $siteId)->first();

        if (! $site) {
            return null;
        }

        $seoPage = SeoPage::query()
            ->where('site_id', $siteId)
            ->where('slug', $slug)
            ->first();

        $observedPage = $this->resolveObservedPage($siteId, $slug, $seoPage);

        if (! $seoPage && ! $observedPage) {
            return null;
        }

        $label = trim((string) ($observedPage?->title ?: $seoPage?->title ?: $slug));
        $subject = $query !== null && trim($query) !== '' ? trim($query) : $label;
        $workflow = 'rewrite';
        $pageId = $seoPage?->id;

        $plan = $this->modificationPlanner->planForGsc(
            $siteId,
            'near_top_10',
            $subject,
            $label,
            $pageId,
            $slug,
            $query,
            null,
        );

        $modificationPlan = [
            'sections' => $plan['sections'],
            'topics' => $plan['topics'],
            'faq' => $plan['faq'],
            'content_summary' => $plan['content_summary'],
            'title_change' => $plan['title_change'],
        ];

        $applyReady = $this->applyContext->canAutoApply($workflow, $siteId, $pageId, $slug);

        $applyContext = $this->applyContext->resolve(
            $siteId,
            $slug,
            $pageId,
            $workflow,
            $applyReady,
            $subject,
            $label,
            (string) $site->url,
            $modificationPlan,
        );

        $snapshot = $this->latestSnapshot($observedPage);
        $evidence = $this->pageEvidence->gather($siteId, $pageId, $slug, $query, $subject);
        $current = $this->currentState($observedPage, $snapshot, $seoPage, $evidence);
        $proposed = $this->proposedState($modificationPlan, $current);

        $preview = [
            'site_id' => $siteId,
            'site_name' => (string) $site->name,
            'slug' => $slug,
            'query' => $query,
            'apply_context' => $applyContext,
            'modification_plan' => $modificationPlan,
            'apply_ready' => $applyReady,
            'apply_workflow' => $workflow,
            'apply_href' => $applyReady
                ? '/publications?'.http_build_query(['focus' => 'content', 'site' => $siteId, 'slug' => $slug, 'action' => 'rewrite'])
                : null,
            'current' => $current,
            'proposed' => $proposed,
            'diff' => $this->diffSummary($current, $proposed),
        ];

        return array_merge($preview, $this->confirmPublishEligibility->forPreview($site, $preview));
    }

    private function resolveObservedPage(string $siteId, string $slug, ?SeoPage $seoPage): ?SeoSitePage
    {
        if ($seoPage?->observed_site_page_id) {
            return SeoSitePage::query()
                ->where('site_id', $siteId)
                ->whereKey($seoPage->observed_site_page_id)
                ->first();
        }

        return $this->nativeGuard->resolveObservedPageBySlug($siteId, $slug);
    }

    private function latestSnapshot(?SeoSitePage $observedPage): ?SeoSitePageSnapshot
    {
        if (! $observedPage) {
            return null;
        }

        if ($observedPage->last_snapshot_id) {
            return SeoSitePageSnapshot::query()->find($observedPage->last_snapshot_id);
        }

        return SeoSitePageSnapshot::query()
            ->where('site_page_id', $observedPage->id)
            ->orderByDesc('observed_at')
            ->first();
    }

    /**
     * @param  array<string,mixed>  $evidence
     * @return array<string,mixed>
     */
    private function currentState(
        ?SeoSitePage $observedPage,
        ?SeoSitePageSnapshot $snapshot,
        ?SeoPage $seoPage,
        array $evidence,
    ): array {
        $h2Headings = $evidence['h2_headings'] ?? [];

        if ($h2Headings === [] && $snapshot) {
            $h2Headings = array_values(array_filter((array) ($snapshot->h2_json ?? [])));
        }

        $contentExcerpt = '';

        if ($snapshot?->content_text) {
            $contentExcerpt = mb_substr(trim((string) $snapshot->content_text), 0, 420);
        } elseif ($seoPage?->content) {
            $contentExcerpt = mb_substr(trim(strip_tags((string) $seoPage->content)), 0, 420);
        }

        return [
            'source' => $observedPage ? 'observed_crawl' : ($seoPage ? 'studio' : 'unknown'),
            'title' => (string) ($snapshot?->title ?: $observedPage?->title ?: $seoPage?->title ?: ''),
            'meta_description' => (string) ($snapshot?->meta_description ?: $seoPage?->meta_description ?: ''),
            'h2_headings' => array_slice($h2Headings, 0, 8),
            'word_count' => (int) ($snapshot?->word_count ?? $evidence['word_count'] ?? $observedPage?->latest_word_count ?? 0),
            'content_excerpt' => $contentExcerpt,
            'observed_at' => $snapshot?->observed_at?->toIso8601String(),
            'live_url' => (string) ($observedPage?->normalized_url ?: $seoPage?->live_url ?: ''),
        ];
    }

    /**
     * @param  array{sections:array<int,string>,topics:array<int,string>,faq:array<int,string>,content_summary:string,title_change:?string}  $plan
     * @param  array<string,mixed>  $current
     * @return array<string,mixed>
     */
    private function proposedState(array $plan, array $current): array
    {
        $proposedH2 = array_values(array_unique([
            ...($current['h2_headings'] ?? []),
            ...array_map(
                fn (string $section): string => preg_replace('/^Section (?:manquante|à ajouter)\s*:\s*/iu', '', $section) ?: $section,
                $plan['sections'],
            ),
        ]));

        return [
            'title' => $plan['title_change'] ?: ($current['title'] ?? ''),
            'meta_description' => null,
            'h2_headings' => array_slice($proposedH2, 0, 10),
            'sections_to_add' => $plan['sections'],
            'topics_to_cover' => $plan['topics'],
            'faq_to_add' => $plan['faq'],
            'content_summary' => $plan['content_summary'],
            'title_change' => $plan['title_change'],
        ];
    }

    /**
     * @param  array<string,mixed>  $current
     * @param  array<string,mixed>  $proposed
     * @return array<string,mixed>
     */
    private function diffSummary(array $current, array $proposed): array
    {
        $currentH2 = $current['h2_headings'] ?? [];
        $proposedH2 = $proposed['h2_headings'] ?? [];
        $addedH2 = array_values(array_diff($proposedH2, $currentH2));

        return [
            'sections_added_count' => count($proposed['sections_to_add'] ?? []),
            'faq_added_count' => count($proposed['faq_to_add'] ?? []),
            'topics_added_count' => count($proposed['topics_to_cover'] ?? []),
            'headings_added_count' => count($addedH2),
            'headings_added' => array_slice($addedH2, 0, 6),
            'title_will_change' => ($proposed['title_change'] ?? null) !== null
                && ($proposed['title_change'] ?? '') !== ($current['title'] ?? ''),
        ];
    }
}
