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
        $reasons = [];

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
            'contact',
            'devis',
            'demo',
            'essai',
            'trial',
            'rdv',
            'appointment',
            'consultation',
            'prendre contact',
        ])) {
            $reasons[] = 'Page de conversion détectée';

            return [
                'intent_type' => 'CONVERSION_PAGE',
                'business_value_score' => 78,
                'confidence' => 88,
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
            'faq',
            'support',
            'aide',
            'help',
            'documentation',
            'doc',
            'sav',
        ])) {
            $reasons[] = 'Page support détectée';

            return [
                'intent_type' => 'SUPPORT_PAGE',
                'business_value_score' => 35,
                'confidence' => 78,
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
            'privacy',
            'confidentialite',
            'confidentialité',
            'mentions legales',
            'mentions légales',
        ])) {
            $reasons[] = 'Page sans intention business SEO détectée';

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
}
