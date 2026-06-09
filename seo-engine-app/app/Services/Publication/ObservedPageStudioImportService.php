<?php

declare(strict_types=1);

namespace App\Services\Publication;

use App\Models\SeoPage;
use App\Models\SeoSite;
use App\Models\SeoSitePage;
use App\Models\SeoSitePageSnapshot;

final class ObservedPageStudioImportService
{
    public function importOrResolve(
        SeoSite $site,
        SeoSitePage $observedPage,
        ?SeoSitePageSnapshot $snapshot = null,
    ): SeoPage {
        $linked = SeoPage::query()
            ->where('site_id', $site->site_id)
            ->where('observed_site_page_id', $observedPage->id)
            ->first();

        if ($linked) {
            return $linked->fresh(['observedPage']);
        }

        $slug = $this->slugFromPath((string) $observedPage->path);
        $existing = SeoPage::query()
            ->where('site_id', $site->site_id)
            ->where('slug', $slug)
            ->first();

        if ($existing) {
            $existing->forceFill([
                'observed_site_page_id' => $observedPage->id,
                'observed_page_match_rule' => 'preview_import',
                'observed_page_linked_at' => now(),
            ])->save();

            return $existing->fresh(['observedPage']);
        }

        $title = trim((string) ($snapshot?->title ?: $observedPage->title ?: $slug));
        $content = $this->contentFromSnapshot($snapshot);

        return SeoPage::query()->create([
            'site_id' => $site->site_id,
            'observed_site_page_id' => $observedPage->id,
            'observed_page_match_rule' => 'preview_import',
            'observed_page_linked_at' => now(),
            'keyword' => $title,
            'slug' => $slug,
            'status' => 'published',
            'published_at' => now(),
            'title' => $title,
            'h1' => $title,
            'meta_description' => trim((string) ($snapshot?->meta_description ?? '')) ?: null,
            'content' => $content,
            'canonical_url' => (string) ($observedPage->normalized_url ?: ''),
            'generation_source' => 'hybrid',
        ])->fresh(['observedPage']);
    }

    private function slugFromPath(string $path): string
    {
        $slug = trim($path, '/');

        return $slug !== '' ? $slug : 'accueil';
    }

    private function contentFromSnapshot(?SeoSitePageSnapshot $snapshot): string
    {
        $html = trim((string) ($snapshot?->content_html ?? ''));

        if ($html !== '') {
            return $html;
        }

        $text = trim((string) ($snapshot?->content_text ?? ''));

        if ($text === '') {
            return '<p></p>';
        }

        $paragraphs = preg_split('/\R{2,}/u', $text) ?: [];

        return collect($paragraphs)
            ->map(fn (string $paragraph): string => '<p>'.e(trim($paragraph)).'</p>')
            ->implode('');
    }
}
