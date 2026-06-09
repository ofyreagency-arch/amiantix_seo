<?php

declare(strict_types=1);

namespace App\Understanding;

use Illuminate\Support\Str;

final class EditorialTopicClassifier
{
    /** @var array<int,string> */
    private const CTA_PHRASES = [
        'parlons de',
        'parlez-nous',
        'parlez nous',
        'contactez',
        'écrivez à',
        'ecrivez a',
        'nous répondons',
        'nous repondons',
        'prendre rendez',
        'prenez rendez',
        'demandez une démo',
        'demandez une demo',
        'demandez un devis',
        'inscrivez-vous',
        'inscrivez vous',
        'restez informé',
        'restez informe',
        'recevez la newsletter',
        'une question, un besoin',
        'pas trouvé',
        'pas trouve',
        'écrire à l',
        'ecrire a l',
    ];

    /** @var array<int,string> */
    private const LEGAL_PHRASES = [
        'politique de confidential',
        'mentions lég',
        'mentions leg',
        'conditions génér',
        'conditions gener',
        'données personnelles',
        'donnees personnelles',
        'exercer vos droits',
        'droits rgpd',
        'cgu',
        'cgv',
    ];

    /** @var array<int,string> */
    private const SUPPORT_PHRASES = [
        'questions fréquentes sur',
        'questions frequentes sur',
        'centre d aide',
        'centre d\'aide',
        'support client',
        'service client',
    ];

    /** @var array<int,string> */
    private const MARKETING_SLOGAN_PHRASES = [
        'chaîne technique complète',
        'chaine technique complete',
        'transformez vos',
        'un outil pensé',
        'un outil pense',
        'pilotée par l',
        'pilotee par l',
        'sans ressaisie',
        'démo gratuite',
        'demo gratuite',
        'abonnez-vous',
    ];

    /** @var array<int,string> */
    private const NON_SEO_PATH_SEGMENTS = [
        'contact',
        'devis',
        'demo',
        'demonstration',
        'rdv',
        'rendez-vous',
        'newsletter',
        'confidentialite',
        'confidentialité',
        'mentions-legales',
        'mentions-legale',
        'conditions-generales',
        'donnees-personnelles',
        'données-personnelles',
        'cgu',
        'cgv',
        'faq',
        'support',
        'aide',
        'login',
        'connexion',
        'inscription',
        'register',
        'privacy',
        'legal',
        'cookies',
    ];

    public function isNonSeoPath(?string $path): bool
    {
        $normalized = strtolower(trim((string) $path, '/'));
        if ($normalized === '') {
            return false;
        }

        $segments = array_values(array_filter(explode('/', $normalized)));

        foreach ($segments as $segment) {
            if (in_array($segment, self::NON_SEO_PATH_SEGMENTS, true)) {
                return true;
            }
        }

        return false;
    }

    public function isSearchableEditorialTopic(string $text): bool
    {
        $normalized = mb_strtolower(trim($text));

        if ($normalized === '' || mb_strlen($normalized) < 8) {
            return false;
        }

        if ($this->containsAny($normalized, self::CTA_PHRASES)) {
            return false;
        }

        if ($this->containsAny($normalized, self::LEGAL_PHRASES)) {
            return false;
        }

        if ($this->containsAny($normalized, self::SUPPORT_PHRASES)) {
            return false;
        }

        if ($this->containsAny($normalized, self::MARKETING_SLOGAN_PHRASES)) {
            return false;
        }

        if (preg_match('/^(guide|blog|faq|contact|accueil|home)\b/iu', $normalized) === 1) {
            return false;
        }

        if (preg_match('/\b(nous|votre|notre|gratuit|cliquez|découvrez|decouvrez)\b/iu', $normalized) === 1) {
            return false;
        }

        preg_match_all('/[\p{L}\p{N}\']+/u', $normalized, $matches);
        $wordCount = count($matches[0] ?? []);

        return $wordCount >= 2;
    }

    public function isSearchableVocabularyTerm(string $term): bool
    {
        $normalized = mb_strtolower(trim($term));

        if ($normalized === '' || mb_strlen($normalized) < 4) {
            return false;
        }

        if ($this->containsAny($normalized, array_merge(
            self::CTA_PHRASES,
            self::LEGAL_PHRASES,
            self::SUPPORT_PHRASES,
            ['amiantix', 'newsletter', 'blog', 'contact', 'demo', 'gratuit', 'logiciel', 'saas'],
        ))) {
            return false;
        }

        return true;
    }

    public function isExcludedServiceName(string $name, string $intentType = '', ?string $path = null): bool
    {
        if ($path !== null && $this->isNonSeoPath($path)) {
            return true;
        }

        if (in_array(strtoupper($intentType), ['CONTACT_PAGE', 'LEGAL_PAGE', 'SUPPORT_PAGE', 'CTA_PAGE', 'NONE'], true)) {
            return true;
        }

        return ! $this->isSearchableEditorialTopic($name);
    }

    /**
     * @param  array<string,mixed>  $profile
     * @return array<int,string>
     */
    public function buildEditorialTopics(array $profile): array
    {
        $industry = trim((string) data_get($profile, 'business.industry', ''));
        $terms = is_array($profile['vocabulary']['core_terms'] ?? null) ? $profile['vocabulary']['core_terms'] : [];
        $services = is_array($profile['services'] ?? null) ? $profile['services'] : [];

        $topics = [];

        foreach ($terms as $term) {
            $term = trim((string) $term);
            if (! $this->isSearchableVocabularyTerm($term)) {
                continue;
            }

            $topics[] = $term;

            if ($industry !== '' && ! str_contains(mb_strtolower($term), mb_strtolower($industry))) {
                $topics[] = trim($industry.' '.$term);
                $topics[] = trim($term.' '.$industry);
            }
        }

        foreach ($services as $service) {
            if (! is_array($service)) {
                continue;
            }

            foreach ($service['headings'] ?? [] as $heading) {
                $heading = trim((string) $heading);
                if ($this->isSearchableEditorialTopic($heading)) {
                    $topics[] = $heading;
                }
            }

            $description = trim((string) ($service['description'] ?? ''));
            if ($description !== '') {
                $snippet = Str::limit($description, 90, '');
                if ($this->isSearchableEditorialTopic($snippet)) {
                    $topics[] = $snippet;
                }
            }
        }

        foreach ($this->problemFramings($industry, $terms) as $topic) {
            $topics[] = $topic;
        }

        return array_values(array_unique(array_filter(
            $topics,
            fn (string $topic): bool => $this->isSearchableEditorialTopic($topic),
        )));
    }

    /**
     * @param  array<int,mixed>  $terms
     * @return array<int,string>
     */
    private function problemFramings(string $industry, array $terms): array
    {
        $topics = [];
        $anchors = array_values(array_filter(array_map(
            fn (mixed $term): string => trim((string) $term),
            $terms,
        ), fn (string $term): bool => $this->isSearchableVocabularyTerm($term)));

        foreach (array_slice($anchors, 0, 6) as $anchor) {
            if (mb_strlen($anchor) < 5) {
                continue;
            }

            $topics[] = 'délai '.$anchor;
            $topics[] = 'coût '.$anchor;
            $topics[] = 'erreurs '.$anchor;
            $topics[] = $anchor.' avant travaux';
            $topics[] = 'obligation '.$anchor;
        }

        if ($industry !== '') {
            $topics[] = 'diagnostic '.$industry.' avant travaux';
            $topics[] = 'erreurs courantes '.$industry;
            $topics[] = 'coordination chantier '.$industry;
        }

        return $topics;
    }

    /**
     * @param  array<int,string>  $needles
     */
    private function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($haystack, mb_strtolower($needle))) {
                return true;
            }
        }

        return false;
    }
}
