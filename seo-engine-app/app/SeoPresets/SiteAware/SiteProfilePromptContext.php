<?php

declare(strict_types=1);

namespace App\SeoPresets\SiteAware;

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
            '- Services : '.json_encode($profile['services'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            '- Vocabulaire métier à utiliser : '.json_encode(data_get($profile, 'vocabulary.core_terms', []), JSON_UNESCAPED_UNICODE),
            '- Termes interdits : '.json_encode(data_get($profile, 'vocabulary.forbidden_generic', []), JSON_UNESCAPED_UNICODE),
            '- Pages principales : '.json_encode($profile['main_pages'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            '- Zone géographique : '.json_encode($profile['geography'] ?? [], JSON_UNESCAPED_UNICODE),
            '- Audience : '.json_encode($profile['audience'] ?? [], JSON_UNESCAPED_UNICODE),
            '- Langue obligatoire : '.$language,
        ];

        if ($language === 'fr') {
            $lines[] = '- Interdiction absolue de rédiger en anglais.';
        }

        $lines[] = '- Interdiction de sections génériques type SaaS, "Field example", "Zoom terrain N", checklist fictive ou contenu hors métier.';
        $lines[] = '- Chaque section doit raconter une situation terrain, avec chiffres, erreurs fréquentes et arbitrages client.';
        $lines[] = '- Chaque section doit refléter les services réels et le vocabulaire du site.';

        return implode("\n", $lines);
    }
}
