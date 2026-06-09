<?php

declare(strict_types=1);

namespace App\SeoPresets\SiteAware;

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
        $audience = is_array($profile['audience'] ?? null) ? $profile['audience'] : [];
        $geography = is_array($profile['geography'] ?? null) ? $profile['geography'] : [];

        $terms = array_map('strval', (array) ($vocabulary['core_terms'] ?? []));
        $nicheProfile = NicheEditorialRegistry::resolve($industry, $keyword, $terms);

        return [
            'topic' => $keyword,
            'family' => $industry,
            'niche' => $nicheProfile['niche'] ?? 'generic',
            'archetype' => 'field_expert_guide',
            'hero_angle' => (string) ($nicheProfile['hero_angle'] ?? ('Répondre comme une publication métier de référence sur '.$keyword)),
            'voice_note' => (string) ($nicheProfile['voice_note'] ?? ''),
            'composition' => $this->composition($keyword, $services, $industry, $nicheProfile),
            'depth_topics' => $this->depthTopics($keyword, $industry, $vocabulary, $nicheProfile),
            'services' => $services,
            'vocabulary' => $vocabulary['core_terms'] ?? [],
            'geography' => $geography,
            'audience' => $audience,
            'cases' => $this->fieldCases($keyword, $industry, $services, $geography, $nicheProfile),
            'mistakes' => $this->commonMistakes($keyword, $industry, $nicheProfile),
            'field_scenarios' => $this->fieldScenarios($keyword, $industry, $services, $nicheProfile),
            'arbitrages' => $this->clientArbitrages($industry),
            'faq' => $this->faqBlueprint($keyword, $services, $industry),
        ];
    }

    public function expectedEditorialSections(array $profile): array
    {
        $keyword = (string) ($profile['topic'] ?? 'le sujet');
        $industry = (string) ($profile['family'] ?? data_get(SiteProfilePromptContext::profile(), 'business.industry', 'activité'));
        $nicheProfile = NicheEditorialRegistry::resolve(
            $industry,
            $keyword,
            array_map('strval', (array) data_get($profile, 'vocabulary', [])),
        );
        $composition = (array) ($nicheProfile['composition'] ?? []);

        if ($composition !== []) {
            return array_values(array_map(
                static fn (string $block): string => $block.' (adapter au sujet '.$keyword.')',
                $composition,
            ));
        }

        return [
            'cadrage : enjeu métier de '.$keyword.' pour les décideurs '.$industry,
            'cadre, acteurs et responsabilités propres au secteur',
            'documents, preuves et points de contrôle utiles',
            'erreurs fréquentes et conséquences opérationnelles',
            'cas d\'usage pertinents pour ce métier uniquement',
            'arbitrages du décideur (coût, délai, conformité, prestataire)',
            'synthèse opérationnelle actionnable',
        ];
    }

    public function expectedSignals(array $profile): array
    {
        $terms = data_get($profile, 'vocabulary', data_get(SiteProfilePromptContext::profile(), 'vocabulary.core_terms', []));

        if (! is_array($terms)) {
            return [(string) ($profile['topic'] ?? '')];
        }

        $topic = trim((string) ($profile['topic'] ?? ''));

        return array_values(array_unique(array_filter([
            $topic,
            ...array_slice($terms, 0, 6),
        ])));
    }

    /**
     * @param  array<int,array<string,mixed>>  $services
     * @return array<int,string>
     */
    /**
     * @param  array<string,mixed>  $nicheProfile
     * @return array<int,string>
     */
    private function composition(string $keyword, array $services, string $industry, array $nicheProfile): array
    {
        $blocks = (array) ($nicheProfile['composition'] ?? []);

        if ($blocks === []) {
            $blocks = [
                'cadrage métier : pourquoi '.$keyword.' compte pour un décideur '.$industry,
                'cadre et chaîne de responsabilité propre au secteur',
                'documents et preuves à mobiliser selon le contexte',
                'erreurs fréquentes et conséquences opérationnelles',
                'cas d\'usage contextualisés pour '.$keyword,
                'arbitrages du décideur',
            ];
        }

        foreach (array_slice($services, 0, 2) as $service) {
            if (! is_array($service)) {
                continue;
            }
            $blocks[] = 'lien métier avec : '.(string) ($service['name'] ?? '');
        }

        $blocks[] = 'synthèse opérationnelle';

        return $blocks;
    }

    /**
     * @param  array<string,mixed>  $vocabulary
     * @return array<int,string>
     */
    /**
     * @param  array<string,mixed>  $vocabulary
     * @param  array<string,mixed>  $nicheProfile
     * @return array<int,string>
     */
    private function depthTopics(string $keyword, string $industry, array $vocabulary, array $nicheProfile): array
    {
        return array_values(array_unique(array_filter((array) ($nicheProfile['depth_topics'] ?? []))));
    }

    /**
     * @param  array<int,array<string,mixed>>  $services
     * @param  array<string,mixed>  $geography
     * @return array<int,string>
     */
    /**
     * @param  array<int,array<string,mixed>>  $services
     * @param  array<string,mixed>  $geography
     * @param  array<string,mixed>  $nicheProfile
     * @return array<int,string>
     */
    private function fieldCases(string $keyword, string $industry, array $services, array $geography, array $nicheProfile): array
    {
        $cases = (array) ($nicheProfile['field_scenarios'] ?? []);

        if ($cases !== []) {
            return array_values(array_map(
                static fn (string $case): string => $case.' — lien possible avec '.$keyword,
                $cases,
            ));
        }

        $serviceName = (string) data_get($services, '0.name', 'prestation locale');
        $region = (string) data_get($geography, 'regions.0', 'secteur local');

        return [
            'Un décideur '.$industry.' traite '.$keyword.' sans cadrage complet : le blocage apparaît souvent tard dans la préparation.',
            'Sur '.$keyword.', plusieurs acteurs partagent des versions différentes du périmètre.',
            'Dans un contexte '.$region.', '.$serviceName.' aide à structurer '.$keyword.' quand les parties ne s\'alignent pas.',
        ];
    }

    /**
     * @return array<int,string>
     */
    /**
     * @param  array<string,mixed>  $nicheProfile
     * @return array<int,string>
     */
    private function commonMistakes(string $keyword, string $industry, array $nicheProfile): array
    {
        $mistakes = (array) ($nicheProfile['mistakes'] ?? []);

        if ($mistakes !== []) {
            return $mistakes;
        }

        return [
            'Traiter '.$keyword.' comme une formalité sans cadrer le contexte réel.',
            'Confondre urgence affichée et faisabilité réelle.',
            'Décider sans repères vérifiables propres au métier '.$industry.'.',
        ];
    }

    /**
     * @param  array<int,array<string,mixed>>  $services
     * @return array<int,string>
     */
    /**
     * @param  array<int,array<string,mixed>>  $services
     * @param  array<string,mixed>  $nicheProfile
     * @return array<int,string>
     */
    private function fieldScenarios(string $keyword, string $industry, array $services, array $nicheProfile): array
    {
        $scenarios = (array) ($nicheProfile['field_scenarios'] ?? []);

        if ($scenarios !== []) {
            return $scenarios;
        }

        $service = (string) data_get($services, '0.name', 'intervention');

        return [
            'Dossier incomplet avant intervention sur '.$keyword,
            'Plusieurs acteurs sans version unique des informations',
            'Arbitrage : internaliser ou confier '.$keyword.' selon délai, compétence et '.$service,
        ];
    }

    /**
     * @return array<int,string>
     */
    private function clientArbitrages(string $industry): array
    {
        return [
            'Coût immédiat vs risque différé : raccourcir le cadrage documentaire ou sécuriser le dossier avant intervention.',
            'Urgence affichée vs faisabilité terrain : lancer tout de suite ou phaser pour éviter une reprise coûteuse.',
            'Interne vs prestataire : garder la maîtrise opérationnelle ou gagner en vitesse avec un spécialiste '.$industry.'.',
        ];
    }

    /**
     * @param  array<int,array<string,mixed>>  $services
     * @return array<int,array<string,string>>
     */
    private function faqBlueprint(string $keyword, array $services, string $industry): array
    {
        $serviceName = (string) data_get($services, '0.name', '');

        $items = [
            [
                'question' => 'Quel est le premier réflexe métier quand on traite '.$keyword.' ?',
                'answer' => '',
            ],
            [
                'question' => 'Quels documents ou repérages vérifier avant de lancer les travaux ?',
                'answer' => '',
            ],
            [
                'question' => 'Quelles erreurs font le plus souvent déraper un dossier '.$industry.' ?',
                'answer' => '',
            ],
            [
                'question' => 'Comment estimer un délai ou une fourchette de budget crédible sur ce sujet ?',
                'answer' => '',
            ],
            [
                'question' => 'Qui est responsable de quoi entre donneur d\'ordre, diagnostiqueur et entreprises ?',
                'answer' => '',
            ],
        ];

        if ($serviceName !== '') {
            $items[] = [
                'question' => 'Dans quels cas faire appel à '.$serviceName.' sur '.$keyword.' ?',
                'answer' => '',
            ];
        }

        $items[] = [
            'question' => 'Comment arbitrer entre urgence client et faisabilité réelle ?',
            'answer' => '',
        ];

        return $items;
    }
}
