<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SeoPage;
use App\Models\SeoSemanticLink;
use App\Models\SeoSite;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class AdminSemanticController extends Controller
{
    public function show(string $siteId): View
    {
        $site       = SeoSite::query()->where('site_id', $siteId)->firstOrFail();
        $pageCount  = SeoPage::query()->where('site_id', $siteId)->count();
        $linkCount  = SeoSemanticLink::query()->where('site_id', $siteId)->count();

        return view('admin.sites.semantic', compact('site', 'pageCount', 'linkCount'));
    }

    public function data(string $siteId): JsonResponse
    {
        $pages = SeoPage::query()
            ->where('site_id', $siteId)
            ->select(['id', 'keyword', 'slug', 'cluster', 'seo_score', 'status'])
            ->get()
            ->keyBy('id');

        $links = SeoSemanticLink::query()
            ->where('site_id', $siteId)
            ->whereIn('relation_type', ['internal_link', 'semantic_neighbor', 'cannibalization'])
            ->get(['source_id', 'target_id', 'relation_type', 'similarity_score']);

        $nodes = $pages->map(fn (SeoPage $p) => [
            'id'      => $p->id,
            'label'   => $p->keyword,
            'cluster' => $p->cluster ?? 'general',
            'score'   => (float) ($p->seo_score ?? 0),
            'status'  => $p->status,
            'slug'    => $p->slug,
        ])->values();

        $edges = $links
            ->filter(fn ($l) => isset($pages[$l->source_id]) && isset($pages[$l->target_id]))
            ->map(fn ($l) => [
                'source'   => $l->source_id,
                'target'   => $l->target_id,
                'type'     => $l->relation_type,
                'strength' => round($l->similarity_score, 2),
            ])->values();

        return response()->json(compact('nodes', 'edges'));
    }
}
