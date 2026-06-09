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
                ."- One continuous editorial voice from opening to close — no stitched blocks or template sections.\n"
                ."- Open with a recognizable real-world situation (job site, client case, urgent field call).\n"
                ."- Mix short narrative paragraphs with technical precision; avoid bullet-only sections without context.\n"
                ."- Include at least 2 credible numeric examples (timelines, areas, headcount, indicative budgets, volumes).\n"
                ."- Name frequent field mistakes and their concrete consequences (delay, overrun, non-compliance, exposure).\n"
                ."- Show real client trade-offs (cost vs risk, urgency vs compliance, in-house vs specialist).\n"
                ."FORBIDDEN:\n"
                ."- Numbered generic headings: \"Field zoom 1\", \"Example 2\", \"Case study 3\", \"Key point 4\".\n"
                ."- Systematic tables, checklists, FAQ blocks, or resource lists unless truly indispensable.\n"
                ."- Identical repetitive section templates or duplicated bullet lists.\n"
                ."- SEO packaging: \"our guide\", \"discover\", \"learn how\", \"in this article\".\n"
                ."- Empty lists, premium fluff, or SaaS vocabulary.\n";
        }

        return "Écriture métier (obligatoire) :\n"
            ."- Rédiger comme un praticien expérimenté du métier du client, pas comme un rédacteur SEO.\n"
            ."- Une seule voix rédactionnelle du début à la fin — pas de blocs collés ni de sections modèle.\n"
            ."- Ouvrir sur une situation réelle reconnaissable (chantier, intervention, dossier client, urgence terrain).\n"
            ."- Alterner narration courte et précision technique ; éviter les suites de listes à puces sans contexte.\n"
            ."- Inclure au moins 2 passages avec chiffres crédibles (délais, surfaces, effectifs, budgets indicatifs, volumes).\n"
            ."- Nommer des erreurs fréquentes du métier et leurs conséquences concrètes (retard, surcoût, non-conformité, risque).\n"
            ."- Montrer des arbitrages réels (coût vs risque, urgence vs conformité, interne vs prestataire).\n"
            ."INTERDIT :\n"
            ."- Titres génériques numérotés : \"Zoom terrain 1\", \"Exemple 1\", \"Cas pratique 2\", \"Point clé 3\".\n"
            ."- Tableaux systématiques, checklists, blocs FAQ ou listes de ressources sauf si vraiment indispensables.\n"
            ."- Structures répétitives identiques d'une section à l'autre ou listes à puces dupliquées.\n"
            ."- Packaging SEO : \"notre guide\", \"découvrez\", \"apprenez\", \"dans cet article\".\n"
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
            'Guide pratique',
            'Guide décisionnel',
            'dans cet article',
            'dans ce passage que les erreurs',
            'Après cette séquence déjà dense',
            'Le passage précédent allant',
            'Le lecteur attend ensuite un passage vers',
            'Checklist operationnelle avant intervention',
            'Checklist opérationnelle avant intervention',
            'Erreurs frequentes et blocages evitables',
            'Erreurs fréquentes et blocages évitables',
            'Routine documentaire et mises a jour utiles',
            'Routine documentaire et mises à jour utiles',
            'Ressources et pages utiles a croiser',
            'Ressources et pages utiles à croiser',
            'Cette checklist donne de l air au contenu',
            'Les nommer clairement aide a differencier un contenu expert',
        ];
    }

    /**
     * @return array<int,string>
     */
    public static function forbiddenMetaPatterns(): array
    {
        return [
            '/\bdécouvrez\b/iu',
            '/\bapprenez\b/iu',
            '/\bnotre guide\b/iu',
            '/\bcliquez\b/iu',
            '/\bdiscover\b/i',
            '/\blearn how\b/i',
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
            '/\bcontexte et obligations\b/iu',
            '/\btableau de priorisation\b/iu',
            '/\bquestions terrain qui reviennent\b/iu',
            '/\bressources et pages utiles\b/iu',
        ];
    }

    /**
     * @param  array<string,mixed>  $payload
     */
    public static function assertFieldExpertPayload(array $payload): void
    {
        self::assertFieldExpertContent((string) ($payload['content'] ?? ''));
        self::assertFieldExpertMeta(
            (string) ($payload['title'] ?? ''),
            (string) ($payload['meta_description'] ?? ''),
            (string) ($payload['h1'] ?? ''),
        );

        if (isset($payload['faq']) && is_array($payload['faq'])) {
            self::assertFieldExpertFaq($payload['faq']);
        }
    }

    public static function assertFieldExpertContent(string $content): void
    {
        $plain = trim(strip_tags($content));

        if ($plain === '') {
            throw new \RuntimeException('Le contenu généré est vide.');
        }

        foreach (self::forbiddenPhrases() as $phrase) {
            if (stripos($content, $phrase) !== false) {
                throw new \RuntimeException('Contenu générique interdit détecté : '.$phrase);
            }
        }

        foreach (self::forbiddenHeadingPatterns() as $pattern) {
            if (preg_match($pattern, $plain) === 1) {
                throw new \RuntimeException('Structure éditoriale générique interdite (titres ou sections template SEO).');
            }
        }

        if (preg_match_all('/\d+/', $plain, $matches) < 2) {
            throw new \RuntimeException('Le contenu doit inclure au moins deux repères chiffrés crédibles (délai, surface, budget, volume, effectif).');
        }

        if (preg_match_all('/<table\b/i', $content, $tables) > 1) {
            throw new \RuntimeException('Trop de tableaux structurants : préférer la narration métier.');
        }

        if (preg_match_all('/<li[^>]*>(.*?)<\/li>/is', $content, $listMatches)) {
            $normalized = array_map(
                static fn (string $item): string => mb_strtolower(trim(strip_tags($item))),
                $listMatches[1],
            );
            $counts = array_count_values(array_filter($normalized));

            foreach ($counts as $count) {
                if ($count >= 3) {
                    throw new \RuntimeException('Liste répétitive détectée : même puce réutilisée plusieurs fois.');
                }
            }
        }

        if (self::wordCount($plain) < 900) {
            throw new \RuntimeException('Le contenu est trop court pour une expertise terrain crédible.');
        }
    }

    private static function wordCount(string $plain): int
    {
        preg_match_all('/[\p{L}\p{N}\']+/u', $plain, $matches);

        return count($matches[0] ?? []);
    }

    public static function assertFieldExpertMeta(string $title, string $metaDescription, string $h1): void
    {
        foreach ([$title, $metaDescription, $h1] as $field) {
            foreach (self::forbiddenMetaPatterns() as $pattern) {
                if (preg_match($pattern, $field) === 1) {
                    throw new \RuntimeException('Formulation SEO générique interdite dans le titre ou la meta.');
                }
            }
        }
    }

    /**
     * @param  array<int,mixed>  $faq
     */
    public static function assertFieldExpertFaq(array $faq): void
    {
        if ($faq === []) {
            return;
        }

        if (count($faq) > 4) {
            throw new \RuntimeException('FAQ trop longue : maximum 4 questions naturelles.');
        }

        foreach ($faq as $item) {
            if (! is_array($item)) {
                continue;
            }

            $question = mb_strtolower(trim((string) ($item['question'] ?? '')));

            if ($question === '') {
                throw new \RuntimeException('FAQ invalide : question vide.');
            }

            if (preg_match('/\b(article|contenu|seo|profondeur|blog)\b/u', $question) === 1) {
                throw new \RuntimeException('FAQ artificielle détectée : question méta ou hors terrain client.');
            }
        }
    }
}
