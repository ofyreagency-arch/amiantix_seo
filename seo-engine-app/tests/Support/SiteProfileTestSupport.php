<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\SeoSite;

trait SiteProfileTestSupport
{
    /**
     * @param  array<string,mixed>  $overrides
     */
    protected function seedReadySiteProfile(SeoSite $site, array $overrides = []): array
    {
        $profile = array_replace_recursive([
            'version' => 'v1',
            'status' => 'ready',
            'generated_at' => now()->toIso8601String(),
            'business' => [
                'summary' => 'Entreprise spécialisée dans les diagnostics réglementaires.',
                'industry' => $site->niche !== '' ? $site->niche : 'activité locale',
                'positioning' => $site->name,
            ],
            'services' => [
                [
                    'name' => 'Diagnostic réglementaire',
                    'description' => 'Intervention terrain et rapport conforme.',
                    'source_url' => rtrim((string) $site->url, '/').'/services/diagnostic',
                    'headings' => ['Diagnostic', 'Rapport'],
                    'intent' => 'MONEY_PAGE',
                ],
            ],
            'vocabulary' => [
                'core_terms' => ['diagnostic', 'conformite', 'rapport', 'intervention'],
                'forbidden_generic' => ['Field example', 'SaaS knowledge base'],
                'tone' => 'expert métier',
            ],
            'main_pages' => [
                [
                    'url' => rtrim((string) $site->url, '/'),
                    'title' => $site->name,
                    'path' => '/',
                    'role' => 'pillar',
                    'cluster' => $site->niche,
                ],
            ],
            'geography' => [
                'scope' => 'regional',
                'regions' => ['Paris'],
                'evidence' => ['test'],
            ],
            'audience' => [
                'segments' => [
                    ['label' => 'Professionnels', 'needs' => ['conformité'], 'signals' => ['entreprise']],
                ],
            ],
            'generation_directives' => [
                'language' => str_starts_with(strtolower((string) $site->locale), 'en') ? 'en' : 'fr',
                'locale' => $site->locale ?: 'fr',
                'site_name' => $site->name,
                'site_url' => $site->url,
                'niche' => $site->niche,
                'forbid_english' => ! str_starts_with(strtolower((string) $site->locale), 'en'),
                'forbid_saas_template' => true,
                'forbid_generic_sections' => true,
            ],
        ], $overrides);

        $site->saveSiteProfile($profile);

        config([
            'seo-engine.require_site_profile' => true,
            'seo-engine.site.id' => $site->site_id,
            'seo-engine.site.name' => $site->name,
            'seo-engine.site.url' => $site->url,
            'seo-engine.site.niche' => $site->niche,
            'seo-engine.site.locale' => $site->locale,
            'seo-engine.site.preset' => $site->resolvedPreset(),
            'seo-engine.site.profile' => $profile,
        ]);

        return $profile;
    }
}
