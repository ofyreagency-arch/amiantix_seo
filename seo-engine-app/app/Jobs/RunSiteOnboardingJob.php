<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\SeoSite;
use App\Understanding\SiteOnboardingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class RunSiteOnboardingJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $siteId,
    ) {}

    public function handle(SiteOnboardingService $onboarding): void
    {
        $site = SeoSite::query()->where('site_id', $this->siteId)->first();

        if (! $site) {
            return;
        }

        $onboarding->start($site);
    }
}
