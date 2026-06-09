<?php

declare(strict_types=1);

namespace App\SeoPresets\SiteAware;

use Illuminate\Support\Str;
use Ofyre\SeoEngine\Contracts\NicheBlueprintProvider;

final class SiteAwareBlueprintProvider implements NicheBlueprintProvider
{
    public function resolve(string $keyword, ?string $cluster = null): array
    {
        $profile = SiteProfilePromptContext::profile() ?? [];
        $services = is_array($profile['services'] ?? null) ? $profile['services'] : [];
        $vocabulary = is_array($profile['vocabulary'] ?? null) ? $profile['vocabulary'] : [];
        $industry = (string) data_get($profile, 'business.industry', 'activité locale');
        $summary = (string) data_get($profile, 'business.summary', $industry);

        return [
            'topic' => $keyword,
            'family' => $industry,
            'archetype' => 'site_aware_guide',
            'hero_angle' => 'Répondre à une question concrète des clients de '.$summary,
            'composition' => $this->composition($keyword, $services),
            'services' => $services,
            'vocabulary' => $vocabulary['core_terms'] ?? [],
            'geography' => $profile['geography'] ?? [],
            'audience' => $profile['audience'] ?? [],
            'faq' => $this->faqBlueprint($keyword, $services),
        ];
    }

    public function expectedEditorialSections(array $profile): array
    {
        $services = is_array($profile['services'] ?? null) ? $profile['services'] : [];

        $sections = [
            'Contexte métier et enjeux pour le client',
            'Comment ce sujet se relie aux services du site',
            'Points de vigilance et bonnes pratiques terrain',
            'Étapes concrètes pour avancer',
        ];

        foreach (array_slice($services, 0, 2) as $service) {
            if (! is_array($service)) {
                continue;
            }
            $sections[] = 'Lien avec le service : '.(string) ($service['name'] ?? '');
        }

        return array_values(array_unique($sections));
    }

    public function expectedSignals(array $profile): array
    {
        $terms = data_get($profile, 'vocabulary.core_terms', []);

        return is_array($terms) ? array_slice($terms, 0, 8) : [];
    }

    /**
     * @param  array<int,array<string,mixed>>  $services
     * @return array<int,string>
     */
    private function composition(string $keyword, array $services): array
    {
        $blocks = [
            'introduction contextualisée sur '.$keyword,
            'enjeux métier pour l audience cible',
            'articulation avec les offres du site',
        ];

        foreach (array_slice($services, 0, 3) as $service) {
            if (! is_array($service)) {
                continue;
            }
            $blocks[] = 'service : '.(string) ($service['name'] ?? '');
        }

        $blocks[] = 'checklist opérationnelle';
        $blocks[] = 'conclusion orientée action';

        return $blocks;
    }

    /**
     * @param  array<int,array<string,mixed>>  $services
     * @return array<int,array<string,string>>
     */
    private function faqBlueprint(string $keyword, array $services): array
    {
        $items = [
            ['question' => 'Pourquoi '.$keyword.' est-il important dans notre activité ?', 'answer' => ''],
            ['question' => 'Quand faut-il agir concrètement ?', 'answer' => ''],
        ];

        foreach (array_slice($services, 0, 3) as $service) {
            if (! is_array($service)) {
                continue;
            }
            $name = (string) ($service['name'] ?? '');
            if ($name === '') {
                continue;
            }
            $items[] = [
                'question' => 'Comment '.$name.' aide sur '.$keyword.' ?',
                'answer' => '',
            ];
        }

        return $items;
    }
}
