<?php

declare(strict_types=1);

namespace Ofyre\SeoEngine\Services\Embeddings;

use Illuminate\Support\Str;
use Ofyre\SeoEngine\Contracts\SemanticLinkPolicyProvider;

class ConfigDrivenSemanticLinkPolicy implements SemanticLinkPolicyProvider
{
    public function evaluate(object $sourcePage, object $targetPage, float $similarityScore): array
    {
        $threshold = (float) config('seo-engine.embeddings.internal_link_threshold', 0.84);
        $adjusted = $similarityScore;
        $reasons = ['semantic_similarity'];

        $sourceCluster = (string) ($sourcePage->cluster ?? '');
        $targetCluster = (string) ($targetPage->cluster ?? '');

        if ($sourceCluster !== '' && $sourceCluster === $targetCluster) {
            $adjusted += (float) config('seo-engine.embeddings.policy.same_cluster_bonus', 0.04);
            $reasons[] = 'same_cluster';
        } elseif ($sourceCluster !== '' && $targetCluster !== '') {
            $adjusted -= (float) config('seo-engine.embeddings.policy.cross_cluster_penalty', 0.03);
            $reasons[] = 'cross_cluster';
        }

        $sourceIntent = $this->intentFamily($sourcePage);
        $targetIntent = $this->intentFamily($targetPage);

        if ($sourceIntent !== null && $sourceIntent === $targetIntent) {
            $adjusted += (float) config('seo-engine.embeddings.policy.same_intent_bonus', 0.05);
            $reasons[] = 'same_intent';
        }

        if ($this->isGeneric($targetPage) && ! $this->isGeneric($sourcePage)) {
            $adjusted -= (float) config('seo-engine.embeddings.policy.generic_target_penalty', 0.02);
            $reasons[] = 'generic_target';
        }

        if ($this->isPillar($targetPage)) {
            $adjusted += (float) config('seo-engine.embeddings.policy.pillar_target_bonus', 0.02);
            $reasons[] = 'pillar_target';
        }

        $strongTargetThreshold = (int) config('seo-engine.embeddings.policy.strong_target_inbound_threshold', 6);
        if ((int) ($targetPage->internal_inbound_count ?? 0) >= $strongTargetThreshold) {
            $adjusted -= (float) config('seo-engine.embeddings.policy.strong_target_penalty', 0.02);
            $reasons[] = 'strongly_linked_target';
        }

        $adjusted = max(0.0, min(1.0, $adjusted));

        return [
            'accepted' => $adjusted >= $threshold,
            'score' => round($adjusted, 4),
            'reasons' => array_values(array_unique($reasons)),
            'meta' => [
                'raw_similarity_score' => round($similarityScore, 4),
                'intent_match' => $sourceIntent !== null && $sourceIntent === $targetIntent,
                'source_intent' => $sourceIntent,
                'target_intent' => $targetIntent,
                'cluster_match' => $sourceCluster !== '' && $sourceCluster === $targetCluster,
                'target_is_generic' => $this->isGeneric($targetPage),
                'target_is_pillar' => $this->isPillar($targetPage),
                'target_inbound_count' => (int) ($targetPage->internal_inbound_count ?? 0),
            ],
        ];
    }

    protected function intentFamily(object $page): ?string
    {
        $tokens = $this->tokensFor($page);

        foreach ((array) config('seo-engine.embeddings.policy.intent_families', []) as $family => $keywords) {
            $normalizedKeywords = collect((array) $keywords)
                ->map(fn (mixed $value): string => Str::lower(Str::ascii((string) $value)))
                ->filter();

            if ($normalizedKeywords->contains(fn (string $keyword): bool => $tokens->contains($keyword))) {
                return (string) $family;
            }
        }

        return null;
    }

    protected function isGeneric(object $page): bool
    {
        $tokens = $this->tokensFor($page);
        $genericTerms = collect((array) config('seo-engine.embeddings.policy.generic_terms', []))
            ->map(fn (mixed $value): string => Str::lower(Str::ascii((string) $value)))
            ->filter();

        return $genericTerms->contains(fn (string $term): bool => $tokens->contains($term));
    }

    protected function isPillar(object $page): bool
    {
        $tokens = $this->tokensFor($page);
        $pillarTerms = collect((array) config('seo-engine.embeddings.policy.pillar_terms', []))
            ->map(fn (mixed $value): string => Str::lower(Str::ascii((string) $value)))
            ->filter();

        return $pillarTerms->contains(fn (string $term): bool => $tokens->contains($term));
    }

    /**
     * @return \Illuminate\Support\Collection<int,string>
     */
    protected function tokensFor(object $page)
    {
        return collect(preg_split('/[^a-z0-9]+/i', Str::lower(Str::ascii(
            trim(((string) ($page->slug ?? '')).' '.((string) ($page->keyword ?? '')).' '.((string) ($page->title ?? '')))
        ))))
            ->filter(fn (?string $value): bool => is_string($value) && $value !== '');
    }
}
