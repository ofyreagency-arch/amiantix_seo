<?php

declare(strict_types=1);

namespace App\Understanding;

use App\Exceptions\SiteProfileNotReadyException;
use App\Models\SeoSite;

final class SiteProfileGate
{
    public function assertReady(?SeoSite $site = null): void
    {
        if (! $this->isRequired()) {
            return;
        }

        $status = $this->currentStatus($site);

        if ($status !== 'ready') {
            throw SiteProfileNotReadyException::forSite(
                $site?->site_id ?? (string) config('seo-engine.site.id', 'unknown'),
                $status,
            );
        }
    }

    public function isRequired(): bool
    {
        return (bool) config('seo-engine.require_site_profile', true);
    }

    public function currentStatus(?SeoSite $site = null): string
    {
        if ($site) {
            return $site->siteProfileStatus();
        }

        return trim((string) data_get(config('seo-engine.site.profile'), 'status', 'pending'));
    }

    /**
     * @return array<string,mixed>|null
     */
    public function currentProfile(): ?array
    {
        $profile = config('seo-engine.site.profile');

        return is_array($profile) ? $profile : null;
    }
}
