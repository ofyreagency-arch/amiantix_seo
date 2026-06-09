<?php

declare(strict_types=1);

namespace App\Services\Publication;

use App\Models\SeoPage;
use App\Models\SeoSite;
use App\Models\SeoSitePage;

final class ObservedNativePublicationGuard
{
    public function isHomepageSlug(string $slug): bool
    {
        $normalized = strtolower(trim($slug, '/'));

        return $normalized === '' || in_array($normalized, ['accueil', 'home', 'index'], true);
    }

    public function isHomepagePath(string $path): bool
    {
        return trim($path, '/') === '';
    }

    public function isHomepage(string $slug, ?string $targetPath = null): bool
    {
        if ($this->isHomepageSlug($slug)) {
            return true;
        }

        return $targetPath !== null && $this->isHomepagePath($targetPath);
    }

    public function homepageBlockedReason(): string
    {
        return 'La page d accueil demande une validation humaine obligatoire avant toute publication native. Validez le plan ici, puis finalisez la publication avec votre référent PraeviSEO ou depuis le studio éditorial.';
    }

    public function isNativeObservedSlug(string $siteId, string $slug): bool
    {
        $observed = $this->resolveObservedPageBySlug($siteId, $slug);

        if (! $observed) {
            return false;
        }

        return $this->isNativeObservedPath($siteId, (string) $observed->path);
    }

    public function isNativeObservedPage(SeoPage $page): bool
    {
        $observed = $page->relationLoaded('observedPage')
            ? $page->observedPage
            : ($page->observed_site_page_id
                ? SeoSitePage::query()->find($page->observed_site_page_id)
                : null);

        if (! $observed) {
            return false;
        }

        return $this->isNativeObservedPath((string) $page->site_id, (string) $observed->path);
    }

    public function isNativeObservedPath(string $siteId, string $path): bool
    {
        $site = SeoSite::query()->where('site_id', $siteId)->first();

        if (! $site) {
            return false;
        }

        $prefix = trim((string) $site->publicationPathPrefix(), '/');
        $observedPath = ltrim(trim($path), '/');

        if ($observedPath === '') {
            return true;
        }

        if ($prefix !== '' && ($observedPath === $prefix || str_starts_with($observedPath, $prefix.'/'))) {
            return false;
        }

        return true;
    }

    public function resolveObservedPageBySlug(string $siteId, string $slug): ?SeoSitePage
    {
        if ($siteId === '') {
            return null;
        }

        if ($this->isHomepageSlug($slug)) {
            return SeoSitePage::query()
                ->where('site_id', $siteId)
                ->where(function ($query): void {
                    $query->where('path', '/')->orWhere('path', '');
                })
                ->orderByDesc('last_seen_at')
                ->first();
        }

        return SeoSitePage::query()
            ->where('site_id', $siteId)
            ->where('path', '/'.ltrim($slug, '/'))
            ->orderByDesc('last_seen_at')
            ->first();
    }
}
