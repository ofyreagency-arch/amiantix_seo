<?php

declare(strict_types=1);

namespace App\ObservedSite;

use App\Models\SeoPage;
use App\Models\SeoSitePage;

class SeoPageObservedLinkService
{
    public function __construct(
        private readonly BusinessPageRelevanceFilter $businessPages,
    ) {}

    public function observedForPage(SeoPage $page, bool $resolve = true): ?SeoSitePage
    {
        if ($page->relationLoaded('observedPage') && $page->observedPage) {
            return $page->observedPage;
        }

        if ($page->observed_site_page_id) {
            $observed = SeoSitePage::query()
                ->whereKey($page->observed_site_page_id)
                ->where('site_id', $page->site_id)
                ->first();

            if ($observed) {
                $page->setRelation('observedPage', $observed);

                return $observed;
            }
        }

        if (! $resolve) {
            return null;
        }

        return $this->syncPage($page);
    }

    public function syncPage(SeoPage $page): ?SeoSitePage
    {
        $match = $this->resolveMatch($page);

        if (! $match) {
            $page->forceFill([
                'observed_site_page_id' => null,
                'observed_page_match_rule' => null,
                'observed_page_linked_at' => null,
            ])->save();
            $page->unsetRelation('observedPage');

            return null;
        }

        $page->forceFill([
            'observed_site_page_id' => $match['page']->id,
            'observed_page_match_rule' => $match['rule'],
            'observed_page_linked_at' => now(),
        ])->save();
        $page->setRelation('observedPage', $match['page']);

        return $match['page'];
    }

    /**
     * @return array{page:SeoSitePage,rule:string}|null
     */
    public function resolveMatch(SeoPage $page): ?array
    {
        $canonicalUrl = trim((string) ($page->canonical_url ?? ''));

        if ($canonicalUrl !== '') {
            $observed = SeoSitePage::query()
                ->where('site_id', $page->site_id)
                ->where('normalized_url', $canonicalUrl)
                ->businessRelevant()
                ->first();

            if ($observed && $this->businessPages->isRelevantObservedPage($observed)) {
                return ['page' => $observed, 'rule' => 'canonical_url_exact'];
            }
        }

        $path = $page->canonicalPath();
        $observed = SeoSitePage::query()
            ->where('site_id', $page->site_id)
            ->where('path', $path)
            ->businessRelevant()
            ->orderByDesc('last_seen_at')
            ->first();

        if ($observed && $this->businessPages->isRelevantObservedPage($observed)) {
            return ['page' => $observed, 'rule' => 'path_exact'];
        }

        return null;
    }
}
