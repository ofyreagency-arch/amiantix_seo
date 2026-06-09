<?php

declare(strict_types=1);

namespace App\SeoPresets\Shared;

final class FieldExpertWritingDirectives
{
    public static function promptBlock(string $language = 'fr'): string
    {
        if ($language === 'en') {
            return "Field-expert writing (mandatory):\n"
                ."- Write as a seasoned practitioner in the client's industry, not as an SEO copywriter.\n"
                ."- Open with a recognizable real-world situation (job site, client case, urgent field call).\n"
                ."- Mix short narrative paragraphs with technical precision; avoid bullet-only sections without context.\n"
                ."- Include at least 2 credible numeric examples (timelines, areas, headcount, indicative budgets, volumes).\n"
                ."- Name frequent field mistakes and their concrete consequences (delay, overrun, non-compliance, exposure).\n"
                ."- Show real client trade-offs (cost vs risk, urgency vs compliance, in-house vs specialist).\n"
                ."FORBIDDEN:\n"
                ."- Numbered generic headings: \"Field zoom 1\", \"Example 2\", \"Case study 3\", \"Key point 4\".\n"
                ."- Identical repetitive section templates.\n"
                ."- Empty lists, premium fluff, or SaaS vocabulary.\n";
        }

        return "Écriture métier (obligatoire) :\n"
            ."- Rédiger comme un praticien expérimenté du métier du client, pas comme un rédacteur SEO.\n"
            ."- Ouvrir sur une situation réelle reconnaissable (chantier, intervention, dossier client, urgence terrain).\n"
            ."- Alterner narration courte et précision technique ; éviter les suites de listes à puces sans contexte.\n"
            ."- Inclure au moins 2 passages avec chiffres crédibles (délais, surfaces, effectifs, budgets indicatifs, volumes).\n"
            ."- Nommer des erreurs fréquentes du métier et leurs conséquences concrètes (retard, surcoût, non-conformité, risque).\n"
            ."- Montrer des arbitrages réels (coût vs risque, urgence vs conformité, interne vs prestataire).\n"
            ."INTERDIT :\n"
            ."- Titres génériques numérotés : \"Zoom terrain 1\", \"Exemple 1\", \"Cas pratique 2\", \"Point clé 3\".\n"
            ."- Structures répétitives identiques d'une section à l'autre.\n"
            ."- Paragraphes creux, formulations premium ou vocabulaire SaaS.\n";
    }

    /**
     * @return array<int,string>
     */
    public static function forbiddenPhrases(): array
    {
        return [
            'Field example',
            'SaaS knowledge base',
            'Operational context',
            'innovative solution',
            'Zoom terrain 1',
            'Zoom terrain 2',
            'Zoom terrain 3',
            'Exemple 1',
            'Exemple 2',
            'Cas pratique 1',
            'Cas pratique 2',
        ];
    }

    /**
     * @return array<int,string>
     */
    public static function forbiddenHeadingPatterns(): array
    {
        return [
            '/zoom\s+terrain\s*\d+/iu',
            '/\b(exemple|cas\s+pratique|scenario|situation|point\s+clé)\s*\d+\b/iu',
            '/\b(chapitre|section|partie)\s*\d+\s*:/iu',
        ];
    }

    public static function assertFieldExpertContent(string $content): void
    {
        foreach (self::forbiddenPhrases() as $phrase) {
            if (stripos($content, $phrase) !== false) {
                throw new \RuntimeException('Contenu générique interdit détecté : '.$phrase);
            }
        }

        $plain = strip_tags($content);

        foreach (self::forbiddenHeadingPatterns() as $pattern) {
            if (preg_match($pattern, $plain) === 1) {
                throw new \RuntimeException('Structure éditoriale générique interdite (titres numérotés type zoom/exemple).');
            }
        }
    }
}
