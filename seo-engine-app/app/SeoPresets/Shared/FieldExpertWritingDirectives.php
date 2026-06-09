<?php

declare(strict_types=1);

namespace App\SeoPresets\Shared;

final class FieldExpertWritingDirectives
{
    public static function promptBlock(string $language = 'fr'): string
    {
        if ($language === 'en') {
            return "Field-expert writing (mandatory):\n"
                ."- Institutional editorial voice for the site: neutral, expert, decision-maker oriented — not SEO copy, not an invented first-person narrator.\n"
                ."- Never use first-person singular (I, my, me): do not invent a consultant, inspector, or eyewitness persona.\n"
                ."- Describe situations in third person (the client, the contractor, teams) or with professional \"we\" without autobiography.\n"
                ."- One continuous editorial voice from opening to close — no stitched blocks or template sections.\n"
                ."- Prioritize domain depth: regulatory frame, actors, responsibilities, documents, use cases (condo, demolition, occupied site…) as relevant.\n"
                ."- Numbers allowed: orders of magnitude, regulatory ranges, typical timelines — never precise amounts presented as a lived client case unless sourced.\n"
                ."- Hypothetical examples only when explicitly labeled (\"on a typical job site…\", \"in a common scenario…\").\n"
                ."- Name frequent field mistakes and their concrete consequences (delay, overrun, non-compliance, exposure).\n"
                ."- Show real client trade-offs (cost vs risk, urgency vs compliance, in-house vs specialist).\n"
                ."FORBIDDEN:\n"
                ."- Autobiographical storytelling: \"I remember\", \"I was recently involved\", \"my team\", \"a client called me\".\n"
                ."- Implicit fictional numbered scenarios presented as lived experience.\n"
                ."- The same scaffold on every article: urgency → blockage → mistake → numbered example → trade-off → conclusion.\n"
                ."- Numbered generic headings: \"Field zoom 1\", \"Example 2\", \"Case study 3\", \"Key point 4\".\n"
                ."- Systematic tables, checklists, FAQ blocks, or resource lists unless truly indispensable.\n"
                ."- SEO packaging: \"our guide\", \"discover\", \"learn how\", \"in this article\".\n"
                ."- Empty lists, premium fluff, or SaaS vocabulary.\n";
        }

        return "Écriture métier (obligatoire) :\n"
            ."- Voix éditoriale institutionnelle du site : neutre, experte, orientée décideurs métier — pas un rédacteur SEO, pas un narrateur personnel inventé.\n"
            ."- Interdit absolu de la première personne du singulier (je, j', mon, ma, mes) : ne pas inventer un consultant, diagnostiqueur ou témoin.\n"
            ."- Décrire les situations à la troisième personne (le donneur d'ordre, le syndic, l'entreprise, les équipes) ou avec « on » métier sans autobiographie.\n"
            ."- Une seule voix rédactionnelle du début à la fin — pas de blocs collés ni de sections modèle.\n"
            ."- Prioriser profondeur métier : cadre réglementaire, acteurs, responsabilités, documents, cas d'usage (copropriété, ERP, démolition…) selon le sujet.\n"
            ."- Alterner explication structurée et précision technique ; éviter les suites de listes à puces sans contexte.\n"
            ."- Chiffres autorisés : ordres de grandeur, fourchettes réglementaires, délais types — jamais de montants précis présentés comme un cas client vécu si ce n'est pas sourcé.\n"
            ."- Exemples hypothétiques possibles uniquement s'ils sont explicitement présentés comme tels (« sur un chantier type… », « dans un scénario courant… »).\n"
            ."- Nommer des erreurs fréquentes du métier et leurs conséquences concrètes (retard, surcoût, non-conformité, risque).\n"
            ."- Montrer des arbitrages réels (coût vs risque, urgence vs conformité, interne vs prestataire).\n"
            ."INTERDIT :\n"
            ."- Récit autobiographique : « je me souviens », « j'ai récemment », « mon équipe », « un client m'appelle ».\n"
            ."- Scénario fictionnel chiffré implicite (500 m², 15 000 €, 20 000 €) présenté comme expérience vécue.\n"
            ."- Structure répétitive systématique : urgence → blocage → erreur → exemple chiffré → arbitrage → conclusion sur chaque article.\n"
            ."- Titres génériques numérotés : \"Zoom terrain 1\", \"Exemple 1\", \"Cas pratique 2\", \"Point clé 3\".\n"
            ."- Tableaux systématiques, checklists, blocs FAQ ou listes de ressources sauf si vraiment indispensables.\n"
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
            'je me souviens',
            'J ai récemment',
            'J\'ai récemment',
            'mon équipe',
            'Mon équipe',
            'un client m appelle',
            'un client m\'appelle',
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

        if (preg_match_all('/\b(je|j\'|j’|mon|ma|mes)\b/iu', $plain, $firstPerson) >= 2) {
            throw new \RuntimeException('Voix narrative interdite : la première personne du singulier invente un témoin. Utiliser une voix institutionnelle ou la troisième personne.');
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

        if (self::wordCount($plain) < self::minWordCount()) {
            throw new \RuntimeException('Le contenu est trop court pour une expertise métier crédible (minimum '.self::minWordCount().' mots).');
        }
    }

    public static function minWordCount(): int
    {
        return max(1200, (int) config('seo-engine.quality.min_word_count', 1300));
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

        if (count($faq) > 6) {
            throw new \RuntimeException('FAQ trop longue : maximum 6 questions naturelles.');
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
