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
        $audience = is_array($profile['audience'] ?? null) ? $profile['audience'] : [];
        $geography = is_array($profile['geography'] ?? null) ? $profile['geography'] : [];

        return [
            'topic' => $keyword,
            'family' => $industry,
            'archetype' => 'field_expert_guide',
            'hero_angle' => 'Répondre comme un praticien du métier à une situation réelle autour de '.$keyword,
            'composition' => $this->composition($keyword, $services, $industry),
            'services' => $services,
            'vocabulary' => $vocabulary['core_terms'] ?? [],
            'geography' => $geography,
            'audience' => $audience,
            'cases' => $this->fieldCases($keyword, $industry, $services, $geography),
            'mistakes' => $this->commonMistakes($keyword, $industry),
            'field_scenarios' => $this->fieldScenarios($keyword, $industry, $services),
            'arbitrages' => $this->clientArbitrages($industry),
            'faq' => $this->faqBlueprint($keyword, $services, $industry),
        ];
    }

    public function expectedEditorialSections(array $profile): array
    {
        $keyword = (string) ($profile['topic'] ?? 'le sujet');
        $industry = (string) ($profile['family'] ?? data_get(SiteProfilePromptContext::profile(), 'business.industry', 'activité'));

        return [
            'ouverture : situation terrain concrète autour de '.$keyword,
            'diagnostic rapide : ce qui bloque ou fait perdre du temps',
            'erreurs fréquentes et conséquences sur le terrain',
            'exemple chiffré crédible (délai, volume, budget indicatif)',
            'arbitrage client (coût, urgence, conformité, prestataire)',
            'lien naturel avec une prestation réelle du site si pertinent',
            'conclusion opérationnelle pour un décideur '.$industry,
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
    private function composition(string $keyword, array $services, string $industry): array
    {
        $blocks = [
            'ouverture narrative : une situation '.$industry.' reconnaissable autour de '.$keyword,
            'diagnostic rapide : ce qui bloque ou fait perdre du temps sur le terrain',
            'erreurs fréquentes et conséquences concrètes',
            'exemple chiffré crédible (délai, volume, budget indicatif)',
            'arbitrage client (coût, urgence, conformité, prestataire)',
        ];

        foreach (array_slice($services, 0, 2) as $service) {
            if (! is_array($service)) {
                continue;
            }
            $blocks[] = 'articulation terrain avec : '.(string) ($service['name'] ?? '');
        }

        $blocks[] = 'conclusion opérationnelle pour le décideur métier';

        return $blocks;
    }

    /**
     * @param  array<int,array<string,mixed>>  $services
     * @param  array<string,mixed>  $geography
     * @return array<int,string>
     */
    private function fieldCases(string $keyword, string $industry, array $services, array $geography): array
    {
        $serviceName = (string) data_get($services, '0.name', 'prestation locale');
        $region = (string) data_get($geography, 'regions.0', 'secteur local');

        return [
            'Un client '.$industry.' appelle pour '.$keyword.' avec un délai serré : il sous-estime souvent la préparation documentaire et découvre un blocage seulement 48 h avant l\'intervention.',
            'Sur '.$keyword.', une équipe terrain arrive avec un cadrage incomplet : le surcoût vient moins de la prestation que du temps perdu à requalifier le périmètre sur place.',
            'Dans un contexte '.$region.', '.$serviceName.' aide à structurer '.$keyword.' quand plusieurs acteurs (client, prestataire, responsable technique) ne partagent pas la même version des informations.',
        ];
    }

    /**
     * @return array<int,string>
     */
    private function commonMistakes(string $keyword, string $industry): array
    {
        return [
            'Traiter '.$keyword.' comme une formalité sans cadrer le contexte réel du chantier ou de l\'intervention.',
            'Confondre urgence client et faisabilité terrain, ce qui provoque replanification et surcoût.',
            'Rédiger ou décider sans chiffres (délais, surfaces, effectifs, volumes) alors que le métier '.$industry.' s\'arbitre avec des ordres de grandeur concrets.',
            'Ignorer les conséquences opérationnelles : retard, reprise, non-conformité, insatisfaction client ou re-intervention.',
        ];
    }

    /**
     * @param  array<int,array<string,mixed>>  $services
     * @return array<int,string>
     */
    private function fieldScenarios(string $keyword, string $industry, array $services): array
    {
        $service = (string) data_get($services, '0.name', 'intervention');

        return [
            'Chantier sous pression : '.$keyword.' à traiter avant reprise d\'activité',
            'Dossier incomplet : le client pensait être prêt, l\'équipe découvre un angle mort sur le terrain',
            'Arbitrage prestataire : internaliser ou confier '.$keyword.' selon délai, compétence et '.$service,
        ];
    }

    /**
     * @return array<int,string>
     */
    private function clientArbitrages(string $industry): array
    {
        return [
            'Coût immédiat vs risque différé : accepter un raccourci documentaire ou sécuriser le dossier avant intervention.',
            'Urgence client vs faisabilité terrain : lancer tout de suite ou phaser pour éviter une reprise coûteuse.',
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
                'question' => 'Quel est le premier réflexe terrain quand on traite '.$keyword.' ?',
                'answer' => '',
            ],
            [
                'question' => 'Quelles erreurs font le plus souvent déraper un dossier '.$industry.' ?',
                'answer' => '',
            ],
            [
                'question' => 'Comment estimer un délai ou un budget crédible sur ce sujet ?',
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
