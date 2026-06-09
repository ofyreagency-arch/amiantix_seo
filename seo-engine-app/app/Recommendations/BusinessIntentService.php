<?php

declare(strict_types=1);

namespace App\Recommendations;

class BusinessIntentService
{
    /**
     * @param  array<string,mixed>  $page
     * @return array{
     *   intent_type:string,
     *   business_value_score:int,
     *   confidence:int,
     *   reasons:array<int,string>
     * }
     */
    public function classify(array $page): array
    {
        $haystack = $this->normalizedHaystack($page);
        $path = $this->normalizedPath($page);
        $reasons = [];

        if ($this->pathMatches($path, [
            'mentions-legales',
            'mentions-legale',
            'confidentialite',
            'confidentialité',
            'donnees-personnelles',
            'données-personnelles',
            'conditions-generales',
            'conditions-generale',
            'cgu',
            'cgv',
            'privacy',
            'legal',
            'cookies',
        ])) {
            $reasons[] = 'Page légale ou conformité détectée via URL';

            return [
                'intent_type' => 'LEGAL_PAGE',
                'business_value_score' => 0,
                'confidence' => 96,
                'reasons' => $reasons,
            ];
        }

        if ($this->pathMatches($path, [
            'contact',
            'devis',
            'rdv',
            'rendez-vous',
            'appointment',
        ]) || $this->containsAny($haystack, [
            'parlons de vos',
            'parlez-nous',
            'écrire à l équipe',
            'ecrire a l equipe',
            'nous répondons sous',
            'nous repondons sous',
            'prendre contact',
        ])) {
            $reasons[] = 'Page contact ou prise de rendez-vous détectée';

            return [
                'intent_type' => 'CONTACT_PAGE',
                'business_value_score' => 5,
                'confidence' => 94,
                'reasons' => $reasons,
            ];
        }

        if ($this->pathMatches($path, [
            'newsletter',
            'demo',
            'demonstration',
            'essai',
            'trial',
        ]) || $this->containsAny($haystack, [
            'inscrivez-vous',
            'inscrivez vous',
            'restez informé',
            'restez informe',
            'double opt-in',
            'demandez une démo',
            'demandez une demo',
        ])) {
            $reasons[] = 'Page CTA commerciale détectée';

            return [
                'intent_type' => 'CTA_PAGE',
                'business_value_score' => 10,
                'confidence' => 90,
                'reasons' => $reasons,
            ];
        }

        if ($this->pathMatches($path, [
            'faq',
            'support',
            'aide',
            'help',
            'documentation',
            'doc',
            'sav',
        ])) {
            $reasons[] = 'Page support détectée via URL';

            return [
                'intent_type' => 'SUPPORT_PAGE',
                'business_value_score' => 20,
                'confidence' => 90,
                'reasons' => $reasons,
            ];
        }

        if ($this->containsAny($haystack, [
            'logiciel',
            'expertise',
            'diagnostic',
            'repérage',
            'desamiantage',
            'amiante',
            'prestation',
            'service',
            'solution',
            'offre',
            'tarif',
            'prix',
        ])) {
            $reasons[] = 'Lexique service/solution détecté';

            return [
                'intent_type' => 'MONEY_PAGE',
                'business_value_score' => 95,
                'confidence' => 84,
                'reasons' => $reasons,
            ];
        }

        if ($this->containsAny($haystack, [
            'blog',
            'article',
            'guide',
            'veille',
            'reglementaire',
            'réglementaire',
            'analyse',
            'definition',
            'définition',
            'actualite',
            'actualité',
            'conseil',
        ])) {
            $reasons[] = 'Page éditoriale orientée acquisition détectée';

            return [
                'intent_type' => 'TRAFFIC_PAGE',
                'business_value_score' => 68,
                'confidence' => 76,
                'reasons' => $reasons,
            ];
        }

        if ($this->containsAny($haystack, [
            'login',
            'connexion',
            'register',
            'inscription',
            'mot de passe',
            'forgot-password',
            'reset-password',
            'desinscription',
            'désinscription',
        ])) {
            $reasons[] = 'Page compte ou authentification détectée';

            return [
                'intent_type' => 'NONE',
                'business_value_score' => 0,
                'confidence' => 92,
                'reasons' => $reasons,
            ];
        }

        $reasons[] = 'Aucune intention business forte détectée';

        return [
            'intent_type' => 'TRAFFIC_PAGE',
            'business_value_score' => 50,
            'confidence' => 42,
            'reasons' => $reasons,
        ];
    }

    /**
     * @param  array<string,mixed>  $page
     */
    private function normalizedHaystack(array $page): string
    {
        $parts = array_filter([
            (string) ($page['url'] ?? ''),
            (string) ($page['path'] ?? ''),
            (string) ($page['title'] ?? ''),
            (string) ($page['meta_description'] ?? ''),
            (string) ($page['primary_h1'] ?? ''),
            (string) ($page['cluster'] ?? ''),
        ], static fn (string $value): bool => trim($value) !== '');

        return mb_strtolower(implode(' ', $parts));
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

    private function normalizedPath(array $page): string
    {
        $path = trim((string) ($page['path'] ?? ''));

        if ($path === '' && isset($page['url'])) {
            $path = (string) (parse_url((string) $page['url'], PHP_URL_PATH) ?? '');
        }

        return mb_strtolower(trim($path, '/'));
    }

    /**
     * @param  array<int,string>  $segments
     */
    private function pathMatches(string $path, array $segments): bool
    {
        if ($path === '') {
            return false;
        }

        $parts = array_values(array_filter(explode('/', $path)));

        foreach ($parts as $part) {
            if (in_array($part, $segments, true)) {
                return true;
            }
        }

        return false;
    }
}
