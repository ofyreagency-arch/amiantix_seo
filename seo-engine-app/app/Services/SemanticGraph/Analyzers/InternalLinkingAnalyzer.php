<?php

declare(strict_types=1);

namespace App\Services\SemanticGraph\Analyzers;

use App\Models\SeoSemanticLink;
use App\Models\SeoSiteLink;
use App\Models\SeoSitePage;
use App\Services\SemanticGraph\Support\ObservedSemanticSupport;
use Ofyre\SeoEngine\Contracts\SemanticLinkPolicyProvider;

class InternalLinkingAnalyzer
{
    public function __construct(
        private readonly ObservedSemanticSupport $support,
        private readonly SemanticLinkPolicyProvider $policy,
    ) {}

    /**
     * @return array<int,array<string,mixed>>
     */
    public function analyze(string $siteId): array
    {
        $pages = SeoSitePage::query()->where('site_id', $siteId)->get()->keyBy('id');
        $existing = SeoSiteLink::query()
            ->where('site_id', $siteId)
            ->where('is_internal', true)
            ->get()
            ->mapWithKeys(fn (SeoSiteLink $link): array => [$link->source_page_id.'>'.$link->target_page_id => true]);

        $neighbors = SeoSemanticLink::query()
            ->where('site_id', $siteId)
            ->whereIn('relation_type', [
                'semantic_similarity_same_cluster',
                'semantic_similarity_same_intent',
                'semantic_similarity_cross_cluster',
                'pillar_target',
            ])
            ->orderByDesc('similarity_score')
            ->get();

        SeoSemanticLink::query()
            ->where('site_id', $siteId)
            ->where('relation_type', 'observed_internal_link')
            ->delete();

        $suggestions = [];

        foreach ($neighbors as $neighbor) {
            $sourcePage = $pages->get((int) $neighbor->source_id);
            $targetPage = $pages->get((int) $neighbor->target_id);

            if (! $sourcePage || ! $targetPage) {
                continue;
            }

            $key = $sourcePage->id.'>'.$targetPage->id;
            if (isset($existing[$key])) {
                continue;
            }

            $policy = $this->policy->evaluate(
                $this->policyPage($sourcePage),
                $this->policyPage($targetPage),
                (float) $neighbor->similarity_score
            );
            $clusterMatch = (bool) ($policy['meta']['cluster_match'] ?? false);
            $pillarTarget = (bool) ($policy['meta']['target_is_pillar'] ?? false);
            $authorityGain = round((float) $targetPage->authority_score - (float) $sourcePage->authority_score, 4);
            $priorityScore = (float) ($policy['score'] ?? $neighbor->similarity_score) + max(0.0, $authorityGain * 0.05);

            if (! (bool) ($policy['accepted'] ?? false) && $priorityScore < 0.82) {
                continue;
            }

            $reason = $pillarTarget
                ? 'pillar_target'
                : ((string) (($policy['reasons'][1] ?? $policy['reasons'][0] ?? 'semantic_similarity')));

            SeoSemanticLink::query()->create([
                'site_id' => $siteId,
                'relation_type' => 'observed_internal_link',
                'source_key' => $sourcePage->normalized_url,
                'source_id' => $sourcePage->id,
                'target_key' => $targetPage->normalized_url,
                'target_id' => $targetPage->id,
                'label' => $this->support->pageLabel($targetPage),
                'url' => $targetPage->normalized_url,
                'reason' => $reason,
                'similarity_score' => round(min(1.0, $priorityScore), 4),
                'meta_json' => [
                    'cluster_match' => $clusterMatch,
                    'pillar_target' => $pillarTarget,
                    'authority_flow' => $authorityGain,
                    'policy_reasons' => $policy['reasons'] ?? [],
                    'source_cluster' => $sourcePage->cluster_label,
                    'target_cluster' => $targetPage->cluster_label,
                ],
            ]);

            $suggestions[] = [
                'source_id' => $sourcePage->id,
                'target_id' => $targetPage->id,
                'score' => round(min(1.0, $priorityScore), 4),
                'reason' => $reason,
            ];
        }

        return $suggestions;
    }

    private function policyPage(SeoSitePage $page): object
    {
        return (object) [
            'slug' => trim((string) $page->path, '/'),
            'keyword' => (string) ($page->primary_h1 ?? $page->title ?? ''),
            'title' => (string) ($page->title ?? ''),
            'cluster' => (string) ($page->cluster_label ?? ''),
            'internal_inbound_count' => (int) ($page->internal_inlinks ?? 0),
        ];
    }
}
