<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\SeoSite;

class SeoEngineContext
{
    private bool $loaded = false;

    private string $siteId;
    private string $name;
    private string $url;
    private string $niche;
    private string $locale;
    private ?string $gscSiteUrl;
    private ?string $gscCredentialsPath;

    public function loadFromSite(SeoSite $site): void
    {
        $this->loaded              = true;
        $this->siteId              = $site->site_id;
        $this->name                = $site->name;
        $this->url                 = $site->url;
        $this->niche               = $site->niche;
        $this->locale              = $site->locale;
        $this->gscSiteUrl          = $site->gsc_site_url;
        $this->gscCredentialsPath  = $site->gsc_credentials_path;

        // Inject per-site GSC config so SearchConsoleService reads the right values
        if ($this->gscSiteUrl) {
            config(['seo-engine.search_console.site_url' => $this->gscSiteUrl]);
        }
        if ($this->gscCredentialsPath) {
            config(['seo-engine.search_console.credentials' => $this->gscCredentialsPath]);
        }
    }

    public function isLoaded(): bool
    {
        return $this->loaded;
    }

    public function siteId(): string
    {
        return $this->loaded
            ? $this->siteId
            : (string) config('seo-engine.site.id', 'default');
    }

    public function name(): string
    {
        return $this->loaded
            ? $this->name
            : (string) config('seo-engine.site.name', 'My Site');
    }

    public function url(): string
    {
        return $this->loaded
            ? $this->url
            : (string) config('seo-engine.site.url', config('app.url', ''));
    }

    public function niche(): string
    {
        return $this->loaded
            ? $this->niche
            : (string) config('seo-engine.site.niche', 'general');
    }

    public function locale(): string
    {
        return $this->loaded
            ? $this->locale
            : (string) config('seo-engine.site.locale', 'en');
    }

    public function gscSiteUrl(): ?string
    {
        return $this->loaded
            ? $this->gscSiteUrl
            : config('seo-engine.search_console.site_url');
    }

    public function gscCredentialsPath(): ?string
    {
        return $this->loaded
            ? $this->gscCredentialsPath
            : config('seo-engine.search_console.credentials');
    }
}
