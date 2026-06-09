<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\SeoSite;
use App\Models\SeoSiteCrawl;
use App\ObservedSite\BusinessPageRelevanceFilter;
use App\ObservedSite\SiteCrawlerService;
use App\Runtime\PremiumAutomationLoopService;
use App\Understanding\SiteOnboardingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RunObservedSiteCrawlJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 1200;

    public int $tries = 1;

    public function __construct(
        public readonly int $crawlId,
        public readonly bool $runOnboarding = false,
    ) {
        $this->onQueue('observed-crawls');
    }

    public function handle(
        SiteCrawlerService $service,
        PremiumAutomationLoopService $premiumLoop,
        SiteOnboardingService $onboarding,
        BusinessPageRelevanceFilter $businessPages,
    ): void
    {
        $crawl = SeoSiteCrawl::query()->find($this->crawlId);

        if (! $crawl) {
            return;
        }

        $site = SeoSite::query()->where('site_id', $crawl->site_id)->first();

        if (! $site) {
            $crawl->forceFill([
                'status' => 'failed',
                'completed_at' => now(),
                'meta_json' => [
                    ...((array) ($crawl->meta_json ?? [])),
                    'error' => 'Site introuvable pour ce crawl.',
                ],
            ])->save();

            return;
        }

        try {
            $service->crawlQueued($site, $crawl);
            $crawl = $crawl->fresh();
            $businessPages->markExcludedTechnicalPages($site->fresh());

            if ($this->runOnboarding || data_get($crawl->meta_json, 'trigger') === 'site_onboarding') {
                $onboarding->completeAfterCrawl($site->fresh(), $crawl);
            }

            $premiumLoop->runForSite($site->fresh(['googleConnection', 'latestObservedCrawl']));
        } catch (\Throwable $exception) {
            $crawl->forceFill([
                'status' => 'failed',
                'completed_at' => now(),
                'meta_json' => [
                    ...((array) ($crawl->meta_json ?? [])),
                    'error' => $exception->getMessage(),
                ],
            ])->save();

            throw $exception;
        }
    }
}
