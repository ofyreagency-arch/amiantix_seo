<?php

declare(strict_types=1);

namespace App\SeoPresets\SiteAware;

use Ofyre\SeoEngine\Contracts\ImagePromptProvider;

final class SiteAwareImagePromptProvider implements ImagePromptProvider
{
    public function promptFor(string $keyword, ?string $cluster): string
    {
        $profile = SiteProfilePromptContext::profile() ?? [];
        $industry = (string) data_get($profile, 'business.industry', 'activité professionnelle');
        $siteName = (string) data_get($profile, 'generation_directives.site_name', config('seo-engine.site.name', 'site'));

        return "Photo professionnelle réaliste pour {$siteName}, secteur {$industry}, sujet {$keyword}, sans texte, sans watermark.";
    }
}
