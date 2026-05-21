<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SeoSemanticLink;
use App\Models\SeoSite;
use App\Models\SeoSiteLink;
use App\Models\SeoSitePage;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class AdminSemanticController extends Controller
{
    public function show(string $siteId): View
    {
        $site = SeoSite::query()->where('site_id', $siteId)->firstOrFail();
        $pageCount = SeoSitePage::query()->where('site_id', $siteId)->count();
        $linkCount = SeoSiteLink::query()->where('site_id', $siteId)->where('is_internal', true)->count()
            + SeoSemanticLink::query()->where('site_id', $siteId)->where('relation_type', 'observed_overlap')->count();

        return view('admin.sites.semantic', compact('site', 'pageCount', 'linkCount'));
    }

    public function data(string $siteId): JsonResponse
    {
        $pages = SeoSitePage::query()
            ->where('site_id', $siteId)
            ->select(['id', 'normalized_url', 'path', 'title', 'cluster_label', 'authority_score', 'orphan_score', 'pillar_likelihood'])
            ->get()
            ->keyBy('id');

        $internalEdges = SeoSiteLink::query()
            ->where('site_id', $siteId)
            ->where('is_internal', true)
            ->whereNotNull('source_page_id')
            ->whereNotNull('target_page_id')
            ->get(['source_page_id', 'target_page_id']);

        $overlapEdges = SeoSemanticLink::query()
            ->where('site_id', $siteId)
            ->where('relation_type', 'observed_overlap')
            ->get(['source_id', 'target_id', 'similarity_score']);

        $nodes = $pages->map(fn (SeoSitePage $page): array => [
            'id' => $page->id,
            'label' => $page->title ?: $page->path,
            'cluster' => $page->cluster_label ?? 'general',
            'score' => round((float) $page->authority_score * 100, 2),
            'orphan_score' => (float) $page->orphan_score,
            'pillar_likelihood' => (float) $page->pillar_likelihood,
            'url' => $page->normalized_url,
        ])->values();

        $edges = collect()
            ->merge($internalEdges->map(fn ($edge): array => [
                'source' => $edge->source_page_id,
                'target' => $edge->target_page_id,
                'type' => 'internal',
                'strength' => 0.5,
            ]))
            ->merge($overlapEdges->map(fn ($edge): array => [
                'source' => $edge->source_id,
                'target' => $edge->target_id,
                'type' => 'observed_overlap',
                'strength' => round((float) $edge->similarity_score, 2),
            ]))
            ->filter(fn (array $edge): bool => isset($pages[$edge['source']]) && isset($pages[$edge['target']]))
            ->values();

        return response()->json(compact('nodes', 'edges'));
    }
}
