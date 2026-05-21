<?php

declare(strict_types=1);

namespace App\SeoBridge\Repositories;

use App\Models\SeoPage;
use App\Models\SeoSearchConsoleMetric;
use App\Runtime\SeoEngineContext;
use Illuminate\Support\Facades\DB;
use Ofyre\SeoEngine\Contracts\EmbeddableContentRepository as EmbeddableContentRepositoryContract;

class EmbeddableContentRepository implements EmbeddableContentRepositoryContract
{
    public function __construct(private readonly SeoEngineContext $context) {}

    public function pagesForEmbedding(?string $slug = null, int $limit = 100): iterable
    {
        return SeoPage::query()
            ->where('site_id', $this->context->siteId())
            ->when($slug, fn ($query) => $query->where('slug', ltrim($slug, '/')))
            ->whereNotNull('content')
            ->limit($limit)
            ->get();
    }

    public function publishedPagesForSemanticLinks(?string $slug = null, int $limit = 250): iterable
    {
        return SeoPage::query()
            ->where('site_id', $this->context->siteId())
            ->published()
            ->when($slug, fn ($query) => $query->where('slug', ltrim($slug, '/')))
            ->limit($limit)
            ->get();
    }

    public function queriesForMatching(?string $slug = null, int $window = 28, int $limit = 250): iterable
    {
        $siteId = $this->context->siteId();

        $rows = SeoSearchConsoleMetric::query()
            ->from('seo_search_console_metrics as metrics')
            ->leftJoin('seo_pages as pages', 'pages.id', '=', 'metrics.seo_page_id')
            ->selectRaw('metrics.query, metrics.url, metrics.seo_page_id, MAX(pages.cluster) as cluster, SUM(metrics.clicks) as clicks, SUM(metrics.impressions) as impressions, AVG(metrics.ctr) as ctr, AVG(metrics.position) as position')
            ->whereNotNull('metrics.query')
            ->where('metrics.site_id', $siteId)
            ->where('metrics.metric_date', '>=', now()->subDays($window)->toDateString())
            ->when($slug, fn ($query) => $query->where('pages.slug', ltrim($slug, '/')))
            ->groupBy('metrics.query', 'metrics.url', 'metrics.seo_page_id')
            ->orderByDesc(DB::raw('SUM(metrics.impressions)'))
            ->limit($limit)
            ->get();

        $pageIds = $rows->pluck('seo_page_id')->filter()->unique()->all();
        $pages = SeoPage::query()->where('site_id', $siteId)->whereIn('id', $pageIds)->get()->keyBy('id');

        return $rows->map(function (object $row) use ($pages): object {
            $row->page = isset($row->seo_page_id) ? $pages->get((int) $row->seo_page_id) : null;

            return $row;
        });
    }
}
