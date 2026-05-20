<?php

declare(strict_types=1);

namespace App\SeoBridge\Repositories;

use App\Models\SeoAudit;
use App\Models\SeoPage;
use App\Models\SeoSemanticLink;
use App\Models\SeoSuggestion;
use App\Services\SeoEngineContext;
use Ofyre\SeoEngine\Contracts\SeoCockpitRepository;

class DatabaseSeoCockpitRepository implements SeoCockpitRepository
{
    public function __construct(private readonly SeoEngineContext $context) {}

    public function dashboardPages(): mixed
    {
        return SeoPage::query()
            ->where('site_id', $this->context->siteId())
            ->latest('updated_at')
            ->paginate(25);
    }

    public function dashboardStats(): array
    {
        $siteId = $this->context->siteId();

        return [
            'pending'     => SeoPage::query()->where('site_id', $siteId)->where('status', 'review')->count(),
            'published'   => SeoPage::query()->where('site_id', $siteId)->where('status', 'published')->count(),
            'rejected'    => SeoSuggestion::query()->whereHas('page', fn ($q) => $q->where('site_id', $siteId))->where('status', 'rejected')->count(),
            'suggestions' => SeoSuggestion::query()->whereHas('page', fn ($q) => $q->where('site_id', $siteId))->where('status', 'pending')->count(),
        ];
    }

    public function dashboardInsights(): array
    {
        $siteId = $this->context->siteId();

        return [
            'average_seo_score'          => round((float) SeoPage::query()->where('site_id', $siteId)->avg('seo_score'), 1),
            'average_indexability_score' => round((float) SeoPage::query()->where('site_id', $siteId)->avg('indexability_score'), 1),
            'pending_suggestions'        => SeoSuggestion::query()->whereHas('page', fn ($q) => $q->where('site_id', $siteId))->where('status', 'pending')->count(),
        ];
    }

    public function previewUrl(object $page): string
    {
        return rtrim($this->context->url(), '/').$page->canonicalPath();
    }

    public function loadEditPage(object $page): object
    {
        return SeoPage::query()->with(['audits', 'suggestions', 'searchConsoleMetrics'])->findOrFail((int) $page->id);
    }

    public function timelineForPage(object $page): array
    {
        return SeoAudit::query()
            ->where('seo_page_id', (int) $page->id)
            ->latest('created_at')
            ->limit(20)
            ->get()
            ->map(static fn (SeoAudit $audit): array => [
                'type' => 'audit',
                'score' => $audit->score,
                'issues' => $audit->issues_json ?? [],
                'recommendations' => $audit->recommendations_json ?? [],
                'created_at' => $audit->created_at?->toIso8601String(),
            ])
            ->all();
    }

    public function inventoryForPage(object $page): mixed
    {
        return SeoSuggestion::query()
            ->where('seo_page_id', (int) $page->id)
            ->latest('created_at')
            ->paginate(20);
    }

    public function semanticContextForPage(object $page): array
    {
        $sourceKey = (string) ($page->slug ?? '');

        return [
            'internal_links' => $this->linkPayload('internal_link', $sourceKey),
            'cannibalization_risks' => $this->linkPayload('cannibalization', $sourceKey),
            'query_matches' => $this->linkPayload('query_match', $sourceKey),
        ];
    }

    private function linkPayload(string $relationType, string $sourceKey): array
    {
        return SeoSemanticLink::query()
            ->where('site_id', $this->context->siteId())
            ->where('relation_type', $relationType)
            ->where('source_key', $sourceKey)
            ->orderByDesc('similarity_score')
            ->limit(10)
            ->get()
            ->map(static fn (SeoSemanticLink $link): array => [
                'label' => $link->label,
                'url' => $link->url,
                'reason' => $link->reason,
                'similarity_score' => (float) $link->similarity_score,
                'meta' => $link->meta_json ?? [],
            ])
            ->all();
    }
}
