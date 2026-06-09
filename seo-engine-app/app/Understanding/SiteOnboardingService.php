<?php

declare(strict_types=1);

namespace App\Understanding;

use App\Models\SeoSite;
use App\Models\SeoSiteCrawl;
use App\ObservedSite\BusinessPageRelevanceFilter;
use App\ObservedSite\ObservedPageEmbeddingService;
use App\ObservedSite\SiteCrawlerService;
use App\Jobs\RunObservedSiteCrawlJob;
use Illuminate\Support\Facades\Log;
use Throwable;

final class SiteOnboardingService
{
    public function __construct(
        private readonly SiteCrawlerService $crawler,
        private readonly ObservedPageEmbeddingService $embeddings,
        private readonly SiteUnderstandingService $understanding,
        private readonly SiteProfileBuilder $profileBuilder,
        private readonly BusinessPageRelevanceFilter $businessPages,
    ) {}

    public function start(SeoSite $site): void
    {
        $this->markStatus($site, 'pending');

        $latest = $site->latestObservedCrawl()->first();

        if ($latest && in_array((string) $latest->status, ['pending', 'running'], true)) {
            return;
        }

        $crawl = SeoSiteCrawl::query()->create([
            'site_id' => $site->site_id,
            'base_url' => rtrim((string) $site->url, '/'),
            'status' => 'pending',
            'max_pages' => 80,
            'meta_json' => ['trigger' => 'site_onboarding'],
        ]);

        RunObservedSiteCrawlJob::dispatch($crawl->id, true);
    }

    public function completeAfterCrawl(SeoSite $site, SeoSiteCrawl $crawl): void
    {
        if ((string) $crawl->status !== 'completed') {
            return;
        }

        $this->markStatus($site, 'analyzing');

        try {
            $this->businessPages->markExcludedTechnicalPages($site);
            $this->embeddings->embedSite($site->site_id, 250, true);
            $understanding = $this->understanding->analyze($site, true);
            $profile = $this->profileBuilder->build($site, (int) $crawl->id, $understanding);
            $site->saveSiteProfile($profile);
        } catch (Throwable $exception) {
            Log::warning('site_onboarding.profile_failed', [
                'site_id' => $site->site_id,
                'crawl_id' => $crawl->id,
                'error' => $exception->getMessage(),
            ]);

            $this->markStatus($site, 'insufficient_data');
        }
    }

    public function runSynchronously(SeoSite $site): array
    {
        $this->markStatus($site, 'analyzing');

        $crawl = SeoSiteCrawl::query()->create([
            'site_id' => $site->site_id,
            'base_url' => rtrim((string) $site->url, '/'),
            'status' => 'pending',
            'max_pages' => 80,
            'meta_json' => ['trigger' => 'site_onboarding_sync'],
        ]);

        $this->crawler->crawlQueued($site->fresh(), $crawl->fresh());
        $crawl = $crawl->fresh();

        $this->businessPages->markExcludedTechnicalPages($site);
        $this->embeddings->embedSite($site->site_id, 250, true);
        $understanding = $this->understanding->analyze($site, true);
        $profile = $this->profileBuilder->build($site, (int) $crawl->id, $understanding);
        $site->saveSiteProfile($profile);

        return $profile;
    }

    private function markStatus(SeoSite $site, string $status): void
    {
        $settings = $site->settings_json ?? [];
        $profile = is_array($settings['site_profile'] ?? null) ? $settings['site_profile'] : [];
        $profile['version'] = 'v1';
        $profile['status'] = $status;
        $profile['updated_at'] = now()->toIso8601String();
        $settings['site_profile'] = $profile;
        $site->forceFill(['settings_json' => $settings])->save();
    }
}
