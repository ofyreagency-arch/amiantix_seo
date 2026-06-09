<?php

declare(strict_types=1);

namespace App\Services\Publication;

use App\Copilot\ActionPreviewService;
use App\Models\SeoSite;
use App\Models\SeoSitePage;
use App\Models\SeoSitePageSnapshot;
use RuntimeException;

final class ConfirmPreviewPublicationService
{
    public function __construct(
        private readonly ActionPreviewService $preview,
        private readonly PreviewPublicationEligibility $eligibility,
        private readonly ObservedNativePublicationGuard $nativeGuard,
        private readonly ObservedPageStudioImportService $import,
        private readonly ObservedPagePlanApplyService $planApply,
        private readonly SeoLivePublicationService $livePublication,
    ) {}

    /**
     * @return array<string,mixed>
     */
    public function confirm(SeoSite $site, string $slug, ?string $query = null): array
    {
        $slug = ltrim(trim($slug), '/');

        if ($slug === '') {
            throw new RuntimeException('Le slug de la page est requis pour confirmer la publication.');
        }

        $preview = $this->preview->build($site->site_id, $slug, $query);

        if (! $preview) {
            throw new RuntimeException('PraeviSEO n a pas encore assez de données pour publier cette page.');
        }

        $eligibility = $this->eligibility->forPreview($site, $preview);

        if (! ($eligibility['can_confirm_publish'] ?? false)) {
            throw new RuntimeException((string) ($eligibility['confirm_publish_blocked_reason'] ?? 'Publication native indisponible pour cette page.'));
        }

        if ($this->nativeGuard->isHomepage($slug, (string) ($preview['apply_context']['target_path'] ?? null))) {
            throw new RuntimeException($this->nativeGuard->homepageBlockedReason());
        }

        $observedPage = $this->resolveObservedPage($site->site_id, $slug);
        $snapshot = $this->latestSnapshot($observedPage);
        $page = $this->import->importOrResolve($site, $observedPage, $snapshot);
        $page = $this->planApply->apply($page, $preview['modification_plan'] ?? []);
        $published = $this->livePublication->publish($page->fresh(['observedPage']), $site);

        return [
            'page_id' => (int) $published->id,
            'slug' => (string) $published->slug,
            'title' => (string) $published->title,
            'published_live' => $published->isPublishedLive(),
            'live_url' => (string) ($published->live_url ?? ''),
            'publication_scope' => $this->livePublication->publicationScopeFor($published, $site),
            'confirm_publish_detail' => $eligibility['confirm_publish_detail'] ?? null,
        ];
    }

    private function resolveObservedPage(string $siteId, string $slug): SeoSitePage
    {
        $observed = $this->nativeGuard->resolveObservedPageBySlug($siteId, $slug);

        if (! $observed) {
            throw new RuntimeException('La page observée est introuvable sur ce site.');
        }

        return $observed;
    }

    private function latestSnapshot(SeoSitePage $observedPage): ?SeoSitePageSnapshot
    {
        if ($observedPage->last_snapshot_id) {
            return SeoSitePageSnapshot::query()->find($observedPage->last_snapshot_id);
        }

        return SeoSitePageSnapshot::query()
            ->where('site_page_id', $observedPage->id)
            ->orderByDesc('observed_at')
            ->first();
    }

}
