<?php

declare(strict_types=1);

namespace App\ObservedSite;

use App\Models\SeoSitePage;
use App\Models\SeoSitePageSnapshot;
use Ofyre\SeoEngine\Contracts\EmbeddingProvider;
use Ofyre\SeoEngine\Contracts\VectorStore;

class ObservedPageEmbeddingService
{
    public function __construct(
        private readonly EmbeddingProvider $provider,
        private readonly VectorStore $vectors,
    ) {}

    /**
     * @return array{embedded:int,skipped:int,entities:int}
     */
    public function embedSite(string $siteId, int $limit = 250, bool $force = false): array
    {
        $pages = SeoSitePage::query()
            ->where('site_id', $siteId)
            ->whereNotNull('last_snapshot_id')
            ->orderByDesc('last_seen_at')
            ->limit($limit)
            ->get();

        $snapshots = SeoSitePageSnapshot::query()
            ->where('site_id', $siteId)
            ->whereIn('id', $pages->pluck('last_snapshot_id')->filter()->all())
            ->get()
            ->keyBy('id');

        $embedded = 0;
        $skipped = 0;
        $entities = 0;
        $version = (string) config('seo-engine.embeddings.observed_page_version', 'observed_page_v1');

        foreach ($pages as $page) {
            $snapshot = $snapshots->get($page->last_snapshot_id);
            if (! $snapshot) {
                continue;
            }

            $entities++;
            $entityKey = (string) $page->normalized_url;
            $sourceText = $this->sourceText($page, $snapshot);
            $sourceHash = sha1($version."\n".$sourceText);
            $existing = $this->vectors->find('observed_page', $entityKey);

            if (! $force && $existing && (string) ($existing->source_hash ?? '') === $sourceHash && (string) ($existing->embedding_version ?? '') === $version) {
                $skipped++;
                continue;
            }

            $embedding = $this->provider->embed($sourceText);

            $this->vectors->upsert(
                'observed_page',
                $entityKey,
                $page->id,
                $sourceText,
                $sourceHash,
                $this->provider->model(),
                $version,
                $embedding,
                [
                    'site_id' => $siteId,
                    'path' => (string) ($page->path ?? ''),
                    'cluster' => (string) ($page->cluster_label ?? ''),
                    'indexability_state' => (string) ($page->indexability_state ?? 'unknown'),
                ],
            );

            $embedded++;
        }

        return [
            'embedded' => $embedded,
            'skipped' => $skipped,
            'entities' => $entities,
        ];
    }

    private function sourceText(SeoSitePage $page, SeoSitePageSnapshot $snapshot): string
    {
        return trim(implode("\n", array_filter([
            'URL: '.(string) $page->normalized_url,
            'Path: '.(string) ($page->path ?? ''),
            'Title: '.(string) ($snapshot->title ?? $page->title ?? ''),
            'Meta: '.(string) ($snapshot->meta_description ?? $page->meta_description ?? ''),
            'H1: '.implode(' | ', $snapshot->h1_json ?? []),
            'H2: '.implode(' | ', $snapshot->h2_json ?? []),
            'H3: '.implode(' | ', $snapshot->h3_json ?? []),
            'Content: '.trim((string) ($snapshot->content_text ?? '')),
        ])));
    }
}
