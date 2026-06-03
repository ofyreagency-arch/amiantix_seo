<?php

declare(strict_types=1);

namespace App\Recommendations;

class PageClassifierService
{
    /**
     * @param  array<string,mixed>  $page
     * @return array{
     *   page_type:string,
     *   seo_eligibility_score:int,
     *   seo_intent_score:int,
     *   classification_confidence:int,
     *   reasons:array<int,string>
     * }
     */
    public function classify(array $page): array
    {
        $haystack = $this->normalizedHaystack($page);
        $reasons = [];
        $pageType = $this->resolvePageType($haystack, $reasons);
        $wordCount = (int) ($page['word_count'] ?? $page['latest_word_count'] ?? 0);
        $indexability = (string) ($page['indexability_state'] ?? 'unknown');
        $hasCluster = trim((string) ($page['cluster'] ?? '')) !== '';
        $gscImpressions = (int) ($page['gsc_impressions'] ?? 0);

        $eligibility = match ($pageType) {
            'AUTH_PAGE', 'ACCOUNT_PAGE', 'LEGAL_PAGE', 'UTILITY_PAGE', 'SYSTEM_PAGE' => 0,
            'CONVERSION_PAGE' => 38,
            default => 62,
        };

        $intent = match ($pageType) {
            'SEO_PAGE' => 58,
            'CONVERSION_PAGE' => 32,
            default => 0,
        };

        if ($wordCount >= 600) {
            $eligibility += 18;
            $intent += 14;
            $reasons[] = 'Contenu suffisamment profond';
        } elseif ($wordCount >= 250) {
            $eligibility += 10;
            $intent += 8;
            $reasons[] = 'Contenu exploitable détecté';
        } elseif ($wordCount > 0) {
            $eligibility += 3;
            $intent += 2;
            $reasons[] = 'Contenu court détecté';
        }

        if ($indexability === 'indexable') {
            $eligibility += 12;
            $reasons[] = 'Page indexable';
        } elseif ($indexability !== 'unknown') {
            $eligibility -= 35;
            $reasons[] = 'Page non indexable';
        }

        if ($hasCluster) {
            $eligibility += 8;
            $intent += 10;
            $reasons[] = 'Cluster métier détecté';
        }

        if ($gscImpressions > 0) {
            $eligibility += min(12, (int) round(log($gscImpressions + 1, 2)));
            $intent += min(10, (int) round(log($gscImpressions + 1, 2)));
            $reasons[] = 'Présence réelle dans Google Search Console';
        }

        $eligibility = max(0, min(100, $eligibility));
        $intent = max(0, min(100, $intent));

        return [
            'page_type' => $pageType,
            'seo_eligibility_score' => $eligibility,
            'seo_intent_score' => $intent,
            'classification_confidence' => $this->confidenceFor($pageType, $reasons),
            'reasons' => array_values(array_unique($reasons)),
        ];
    }

    /**
     * @param  array<int,string>  $reasons
     */
    private function resolvePageType(string $haystack, array &$reasons): string
    {
        $map = [
            'AUTH_PAGE' => ['login', 'connexion', 'signin', 'signup', 'register', 'inscription', 'forgot-password', 'reset-password', 'mot de passe'],
            'ACCOUNT_PAGE' => ['compte', 'account', 'dashboard', 'profil', 'profile', 'mon compte'],
            'LEGAL_PAGE' => ['mentions legales', 'mentions légales', 'privacy', 'confidentialite', 'confidentialité', 'cookies', 'cgu', 'cgv', 'terms'],
            'UTILITY_PAGE' => ['desinscription', 'désinscription', 'unsubscribe', 'confirmation-email', 'verify-email', 'callback', 'webhook', 'feed', 'sitemap'],
            'SYSTEM_PAGE' => ['wp-admin', '/admin', '/api/', '/_profiler', '/health', '/status', '/cron'],
            'CONVERSION_PAGE' => ['contact', 'devis', 'quote', 'demo', 'essai', 'trial', 'rdv', 'appointment'],
        ];

        foreach ($map as $type => $needles) {
            foreach ($needles as $needle) {
                if (str_contains($haystack, mb_strtolower($needle))) {
                    $reasons[] = sprintf('Pattern %s détecté', $needle);

                    return $type;
                }
            }
        }

        $reasons[] = 'Page par défaut considérée comme SEO';

        return 'SEO_PAGE';
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
     * @param  array<int,string>  $reasons
     */
    private function confidenceFor(string $pageType, array $reasons): int
    {
        $base = match ($pageType) {
            'SEO_PAGE' => 58,
            'CONVERSION_PAGE' => 74,
            default => 86,
        };

        return max(40, min(98, $base + count($reasons) * 2));
    }
}
