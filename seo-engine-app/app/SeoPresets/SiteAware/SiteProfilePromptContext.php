<?php

declare(strict_types=1);

namespace App\SeoPresets\SiteAware;

use Illuminate\Support\Str;

final class SiteProfilePromptContext
{
    /**
     * @return array<string,mixed>|null
     */
    public static function profile(): ?array
    {
        $profile = config('seo-engine.site.profile');

        return is_array($profile) ? $profile : null;
    }

    public static function block(): string
    {
        $profile = self::profile();

        if (! $profile) {
            return '';
        }

        $directives = is_array($profile['generation_directives'] ?? null) ? $profile['generation_directives'] : [];
        $language = (string) ($directives['language'] ?? 'fr');

        $lines = [
            'Contexte métier du site (obligatoire) :',
            '- Résumé activité : '.(string) data_get($profile, 'business.summary', ''),
            '- Secteur : '.(string) data_get($profile, 'business.industry', ''),
            '- Positionnement : '.(string) data_get($profile, 'business.positioning', ''),
            '- Services : '.json_encode(self::compactServices($profile['services'] ?? []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            '- Vocabulaire métier à utiliser : '.json_encode(array_slice((array) data_get($profile, 'vocabulary.core_terms', []), 0, 12), JSON_UNESCAPED_UNICODE),
            '- Termes interdits : '.json_encode(data_get($profile, 'vocabulary.forbidden_generic', []), JSON_UNESCAPED_UNICODE),
            '- Pages principales : '.json_encode(self::compactPages($profile['main_pages'] ?? []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            '- Zone géographique : '.json_encode($profile['geography'] ?? [], JSON_UNESCAPED_UNICODE),
            '- Audience : '.json_encode($profile['audience'] ?? [], JSON_UNESCAPED_UNICODE),
            '- Langue obligatoire : '.$language,
        ];

        if ($language === 'fr') {
            $lines[] = '- Interdiction absolue de rédiger en anglais.';
        }

        $lines[] = '- Une seule voix rédactionnelle : pas de blocs collés, pas de sections template SEO, pas d enrichissement post-rédaction.';
        $lines[] = '- Interdiction de sections génériques type SaaS, "Field example", "Zoom terrain N", checklist fictive ou contenu hors métier.';
        $lines[] = '- Raconter des situations terrain avec chiffres, erreurs fréquentes et arbitrages client.';
        $lines[] = '- Mentionner les services du site seulement quand le récit métier le justifie.';

        return implode("\n", $lines);
    }

    /**
     * @param  array<int,mixed>  $services
     * @return array<int,array{name:string,description:string}>
     */
    private static function compactServices(array $services): array
    {
        $compact = [];

        foreach (array_slice($services, 0, 6) as $service) {
            if (! is_array($service)) {
                continue;
            }

            $name = trim((string) ($service['name'] ?? ''));

            if ($name === '') {
                continue;
            }

            $compact[] = [
                'name' => $name,
                'description' => Str::limit(trim((string) ($service['description'] ?? '')), 180),
            ];
        }

        return $compact;
    }

    /**
     * @param  array<int,mixed>  $pages
     * @return array<int,array{path:string,title:string,role:string}>
     */
    private static function compactPages(array $pages): array
    {
        $compact = [];

        foreach (array_slice($pages, 0, 8) as $page) {
            if (! is_array($page)) {
                continue;
            }

            $path = trim((string) ($page['path'] ?? ''));

            if ($path === '') {
                continue;
            }

            $compact[] = [
                'path' => $path,
                'title' => Str::limit(trim((string) ($page['title'] ?? '')), 80),
                'role' => trim((string) ($page['role'] ?? '')),
            ];
        }

        return $compact;
    }
}
