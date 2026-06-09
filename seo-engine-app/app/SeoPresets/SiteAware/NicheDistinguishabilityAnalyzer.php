<?php

declare(strict_types=1);

namespace App\SeoPresets\SiteAware;

final class NicheDistinguishabilityAnalyzer
{
    /**
     * @param  array<int,string>  $keywordsToStrip
     * @return array<string,mixed>
     */
    public static function fingerprint(string $niche, string $content, array $keywordsToStrip = []): array
    {
        $plain = mb_strtolower(trim(strip_tags($content)));
        $keywordPattern = implode('|', array_map(
            static fn (string $word): string => preg_quote(mb_strtolower(trim($word)), '/'),
            array_filter($keywordsToStrip),
        ));

        if ($keywordPattern !== '') {
            $plain = preg_replace('/\b('.$keywordPattern.')\b/u', ' ', $plain) ?? $plain;
        }

        preg_match_all('/<h2\b[^>]*>(.*?)<\/h2>/is', $content, $h2Matches);
        $h2 = array_map(
            static fn (string $heading): string => mb_strtolower(trim(strip_tags($heading))),
            $h2Matches[1] ?? [],
        );

        preg_match_all('/[\p{L}\p{N}\']{4,}/u', $plain, $tokenMatches);
        $tokens = $tokenMatches[0] ?? [];
        $frequencies = array_count_values($tokens);
        arsort($frequencies);
        $topTerms = array_slice(array_keys($frequencies), 0, 20);

        $profile = NicheEditorialRegistry::resolve($niche, implode(' ', $keywordsToStrip));
        $signatureHits = [];
        foreach ((array) ($profile['signature_terms'] ?? []) as $term) {
            if ($term !== '' && str_contains($plain, mb_strtolower($term))) {
                $signatureHits[] = $term;
            }
        }

        return [
            'niche' => $niche,
            'top_terms' => $topTerms,
            'h2_headings' => $h2,
            'signature_hits' => $signatureHits,
            'signature_ratio' => count($signatureHits) / max(1, count((array) ($profile['signature_terms'] ?? []))),
        ];
    }

    /**
     * @param  array<string,array<string,mixed>>  $fingerprints
     * @return array<string,mixed>
     */
    public static function compare(array $fingerprints): array
    {
        $niches = array_keys($fingerprints);
        $pairs = [];
        $maxOverlap = 0.0;

        for ($i = 0; $i < count($niches); $i++) {
            for ($j = $i + 1; $j < count($niches); $j++) {
                $left = $niches[$i];
                $right = $niches[$j];
                $overlap = self::jaccard(
                    (array) ($fingerprints[$left]['top_terms'] ?? []),
                    (array) ($fingerprints[$right]['top_terms'] ?? []),
                );
                $h2Overlap = self::jaccard(
                    (array) ($fingerprints[$left]['h2_headings'] ?? []),
                    (array) ($fingerprints[$right]['h2_headings'] ?? []),
                );
                $maxOverlap = max($maxOverlap, $overlap, $h2Overlap);
                $pairs[] = [
                    'left' => $left,
                    'right' => $right,
                    'term_overlap' => round($overlap, 3),
                    'h2_overlap' => round($h2Overlap, 3),
                    'too_similar' => $overlap >= 0.55 || $h2Overlap >= 0.5,
                ];
            }
        }

        $weakSignatures = [];
        foreach ($fingerprints as $niche => $fingerprint) {
            if (($fingerprint['signature_ratio'] ?? 0) < 0.2) {
                $weakSignatures[] = $niche;
            }
        }

        return [
            'pairs' => $pairs,
            'max_term_overlap' => round(max(array_map(static fn (array $pair): float => (float) ($pair['term_overlap'] ?? 0), $pairs) ?: [0]), 3),
            'weak_signature_niches' => $weakSignatures,
            'distinct_enough' => $pairs !== []
                && ! in_array(true, array_column($pairs, 'too_similar'), true)
                && $weakSignatures === [],
        ];
    }

    /**
     * @param  array<int,string>  $left
     * @param  array<int,string>  $right
     */
    private static function jaccard(array $left, array $right): float
    {
        $left = array_values(array_unique(array_filter($left)));
        $right = array_values(array_unique(array_filter($right)));

        if ($left === [] && $right === []) {
            return 0.0;
        }

        $intersection = array_intersect($left, $right);
        $union = array_unique([...$left, ...$right]);

        return count($intersection) / max(1, count($union));
    }
}
