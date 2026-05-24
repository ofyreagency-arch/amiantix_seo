<?php

declare(strict_types=1);

namespace App\Services\Publication;

use App\Models\SeoPage;
use App\Models\SeoSite;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class SeoLivePublicationService
{
    public function publish(SeoPage $page, SeoSite $site): SeoPage
    {
        if (! $this->supportsLivePublication()) {
            return $page->refresh();
        }

        $page->forceFill([
            'published_live' => true,
            'published_live_at' => $page->published_live_at ?? now(),
            'live_url' => $this->liveUrlFor($page, $site),
        ])->save();

        return $page->refresh();
    }

    public function liveUrlFor(SeoPage $page, SeoSite $site): string
    {
        return rtrim((string) $site->url, '/').$page->canonicalPath();
    }

    public function resolveSiteByHost(string $host): ?SeoSite
    {
        $normalizedHost = $this->normalizeHost($host);

        return SeoSite::query()
            ->active()
            ->get()
            ->first(function (SeoSite $site) use ($normalizedHost): bool {
                $siteHost = $this->normalizeHost((string) parse_url((string) $site->url, PHP_URL_HOST));

                return $siteHost !== '' && $siteHost === $normalizedHost;
            });
    }

    public function livePagesQuery(SeoSite $site): Builder
    {
        $query = SeoPage::query()
            ->where('site_id', $site->site_id);

        if (! $this->supportsLivePublication()) {
            return $query->whereRaw('1 = 0');
        }

        return $query->publishedLive();
    }

    public function supportsLivePublication(): bool
    {
        return Schema::hasColumns('seo_pages', [
            'published_live',
            'published_live_at',
            'live_url',
        ]);
    }

    private function normalizeHost(string $host): string
    {
        return ltrim(strtolower(trim($host)), '.');
    }
}
