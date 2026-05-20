<?php

declare(strict_types=1);

namespace Ofyre\SeoEngine\Services\Embeddings;

use Illuminate\Support\Str;
use Ofyre\SeoEngine\Contracts\EmbeddableContentRepository;
use Ofyre\SeoEngine\Contracts\EmbeddingProvider;
use Ofyre\SeoEngine\Contracts\VectorStore;

class ContentEmbeddingService
{
    public function __construct(
        private readonly EmbeddableContentRepository $content,
        private readonly EmbeddingProvider $provider,
        private readonly VectorStore $vectors,
    ) {}

    /**
     * @return array{embedded:int,skipped:int,entities:int}
     */
    public function embedPages(?string $slug = null, int $limit = 100, bool $force = false): array
    {
        $embedded = 0;
        $skipped = 0;
        $entities = 0;
        $version = (string) config('seo-engine.embeddings.page_version', 'page_v1');

        foreach ($this->content->pagesForEmbedding($slug, $limit) as $page) {
            $entities++;
            $entityKey = (string) ($page->slug ?? '');

            if ($entityKey === '') {
                $skipped++;
                continue;
            }

            $sourceText = $this->pageSourceText($page);
            $sourceHash = sha1($version."\n".$sourceText);
            $existing = $this->vectors->find('page', $entityKey);

            if (! $force && $existing && (string) ($existing->source_hash ?? '') === $sourceHash && (string) ($existing->embedding_version ?? '') === $version) {
                $skipped++;
                continue;
            }

            $embedding = $this->provider->embed($sourceText);

            $this->vectors->upsert(
                'page',
                $entityKey,
                isset($page->id) ? (int) $page->id : null,
                $sourceText,
                $sourceHash,
                $this->provider->model(),
                $version,
                $embedding,
                [
                    'keyword' => (string) ($page->keyword ?? ''),
                    'cluster' => (string) ($page->cluster ?? ''),
                    'status' => (string) ($page->status ?? ''),
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

    /**
     * @return array{embedded:int,skipped:int,entities:int}
     */
    public function embedQueries(?string $slug = null, int $window = 28, int $limit = 250, bool $force = false): array
    {
        $embedded = 0;
        $skipped = 0;
        $entities = 0;
        $version = (string) config('seo-engine.embeddings.query_version', 'query_v1');

        foreach ($this->content->queriesForMatching($slug, $window, $limit) as $metric) {
            $entities++;

            $query = trim((string) ($metric->query ?? ''));
            if ($query === '') {
                $skipped++;
                continue;
            }

            $entityKey = $this->queryEntityKey($query);
            $sourceText = $this->querySourceText($metric);
            $sourceHash = sha1($version."\n".$sourceText);
            $existing = $this->vectors->find('query', $entityKey);

            if (! $force && $existing && (string) ($existing->source_hash ?? '') === $sourceHash && (string) ($existing->embedding_version ?? '') === $version) {
                $skipped++;
                continue;
            }

            $embedding = $this->provider->embed($sourceText);

            $this->vectors->upsert(
                'query',
                $entityKey,
                null,
                $sourceText,
                $sourceHash,
                $this->provider->model(),
                $version,
                $embedding,
                [
                    'query' => $query,
                    'cluster' => (string) ($metric->cluster ?? ''),
                    'url' => (string) ($metric->url ?? ''),
                    'seo_page_id' => isset($metric->seo_page_id) ? (int) $metric->seo_page_id : null,
                    'window_days' => $window,
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

    public function pageSourceText(object $page): string
    {
        $content = trim(strip_tags((string) ($page->content ?? '')));
        $faq = collect($page->faq_json ?? [])
            ->map(static fn (array $item): string => trim(($item['question'] ?? '').' '.($item['answer'] ?? '')))
            ->filter()
            ->implode("\n");
        $headings = collect($this->extractHeadings((string) ($page->content ?? '')))->implode(' | ');

        return trim(implode("\n", array_filter([
            'Title: '.(string) ($page->title ?? ''),
            'H1: '.(string) ($page->h1 ?? ''),
            'Meta: '.(string) ($page->meta_description ?? ''),
            'Keyword: '.(string) ($page->keyword ?? ''),
            'Cluster: '.(string) ($page->cluster ?? ''),
            $headings !== '' ? 'Headings: '.$headings : null,
            $content !== '' ? 'Content: '.$content : null,
            $faq !== '' ? 'FAQ: '.$faq : null,
        ])));
    }

    public function queryEntityKey(string $query): string
    {
        return 'query:'.sha1(Str::lower(Str::ascii(trim($query))));
    }

    public function querySourceText(object $metric): string
    {
        return trim(implode("\n", array_filter([
            'Query: '.trim((string) ($metric->query ?? '')),
            filled($metric->cluster ?? null) ? 'Cluster: '.(string) $metric->cluster : null,
            filled($metric->url ?? null) ? 'Observed URL: '.(string) $metric->url : null,
        ])));
    }

    /**
     * @return array<int,string>
     */
    private function extractHeadings(string $content): array
    {
        preg_match_all('/<h[23][^>]*>(.*?)<\/h[23]>/i', $content, $matches);

        return collect($matches[1] ?? [])
            ->map(static fn (string $heading): string => Str::of(strip_tags($heading))->squish()->value())
            ->filter()
            ->values()
            ->all();
    }
}
