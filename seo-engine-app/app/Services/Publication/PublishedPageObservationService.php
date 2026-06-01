<?php

declare(strict_types=1);

namespace App\Services\Publication;

use App\Models\SeoPage;
use App\Models\SeoSite;
use App\Models\SeoSitePage;
use App\ObservedSite\SeoPageObservedLinkService;
use App\ObservedSite\SiteCrawlerService;
use Illuminate\Support\Facades\Log;

class PublishedPageObservationService
{
    public function __construct(
        private readonly SiteCrawlerService $crawler,
        private readonly SeoPageObservedLinkService $observedLinks,
    ) {}

    public function followLivePublication(SeoSite $site, SeoPage $page): ?SeoSitePage
    {
        $liveUrl = trim((string) ($page->live_url ?? ''));

        if ($liveUrl === '') {
            return null;
        }

        if (trim((string) ($page->canonical_url ?? '')) === '') {
            $page->forceFill(['canonical_url' => $liveUrl])->save();
            $page = $page->refresh();
        }

        try {
            $observed = $this->crawler->observeUrl($site, $liveUrl);
        } catch (\Throwable $exception) {
            Log::warning('published_page_observation_failed', [
                'site_id' => $site->site_id,
                'page_id' => $page->id,
                'live_url' => $liveUrl,
                'message' => $exception->getMessage(),
            ]);

            return $this->observedLinks->syncPage($page->fresh());
        }

        if ($observed === null) {
            return $this->observedLinks->syncPage($page->fresh());
        }

        return $this->observedLinks->syncPage($page->fresh());
    }
}
