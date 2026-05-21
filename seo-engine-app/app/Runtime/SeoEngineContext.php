<?php

declare(strict_types=1);

namespace App\Runtime;

use App\Models\SeoSite;

class SeoEngineContext
{
    private bool $loaded = false;

    private string $siteId;
    private string $name;
    private string $url;
    private string $niche;
    private string $locale;
    private string $preset;
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
        $this->preset              = $site->resolvedPreset();
        $this->gscSiteUrl          = $site->gsc_site_url;
        $this->gscCredentialsPath  = $site->gsc_credentials_path;

        // Inject per-site SEO + GSC config so the engine reads the correct site context.
        config([
            'seo-engine.site.id' => $this->siteId,
            'seo-engine.site.name' => $this->name,
            'seo-engine.site.url' => $this->url,
            'seo-engine.site.niche' => $this->niche,
            'seo-engine.site.locale' => $this->locale,
            'seo-engine.site.preset' => $this->preset,
        ]);

        if ($this->gscSiteUrl) {
            config([
                'seo-engine.search_console.site_url' => $this->gscSiteUrl,
                'services.google_search_console.site_url' => $this->gscSiteUrl,
            ]);
        }
        if ($this->gscCredentialsPath) {
            config([
                'seo-engine.search_console.credentials' => $this->gscCredentialsPath,
                'services.google_search_console.credentials' => $this->gscCredentialsPath,
            ]);
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

    public function preset(): string
    {
        return $this->loaded
            ? $this->preset
            : (string) config('seo-engine.site.preset', 'generic');
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
