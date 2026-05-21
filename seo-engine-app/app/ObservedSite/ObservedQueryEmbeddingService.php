<?php

declare(strict_types=1);

namespace App\ObservedSite;

use App\Models\SeoSearchConsoleMetric;
use App\Services\SemanticGraph\Support\ObservedSemanticSupport;
use Illuminate\Support\Str;
use Ofyre\SeoEngine\Contracts\EmbeddingProvider;
use Ofyre\SeoEngine\Contracts\VectorStore;

class ObservedQueryEmbeddingService
{
    public function __construct(
        private readonly EmbeddingProvider $provider,
        private readonly VectorStore $vectors,
        private readonly ObservedSemanticSupport $support,
    ) {}

    /**
     * @return array{embedded:int,skipped:int,entities:int}
     */
    public function embedSite(string $siteId, int $window = 28, int $limit = 250, bool $force = false): array
    {
        $rows = SeoSearchConsoleMetric::query()
            ->where('site_id', $siteId)
            ->whereNotNull('query')
            ->where('metric_date', '>=', now()->subDays($window)->toDateString())
            ->selectRaw('query, MAX(url) as url, SUM(clicks) as clicks, SUM(impressions) as impressions, AVG(ctr) as ctr, AVG(position) as position')
            ->groupBy('query')
            ->orderByDesc('impressions')
            ->limit($limit)
            ->get();

        $embedded = 0;
        $skipped = 0;
        $entities = 0;
        $version = (string) config('seo-engine.embeddings.observed_query_version', 'observed_query_v1');

        foreach ($rows as $row) {
            $query = trim((string) ($row->query ?? ''));
            if ($query === '') {
                continue;
            }

            $entities++;
            $entityKey = $this->queryEntityKey($query);
            $sourceText = $this->sourceText((string) $query, (string) ($row->url ?? ''));
            $sourceHash = sha1($version."\n".$sourceText);
            $existing = $this->vectors->find('observed_query', $entityKey);

            if (! $force && $existing && (string) ($existing->source_hash ?? '') === $sourceHash && (string) ($existing->embedding_version ?? '') === $version) {
                $skipped++;
                continue;
            }

            $embedding = $this->provider->embed($sourceText);

            $this->vectors->upsert(
                'observed_query',
                $entityKey,
                null,
                $sourceText,
                $sourceHash,
                $this->provider->model(),
                $version,
                $embedding,
                [
                    'site_id' => $siteId,
                    'query' => $query,
                    'url' => $this->support->normalizeUrl((string) ($row->url ?? '')),
                    'clicks' => (float) ($row->clicks ?? 0),
                    'impressions' => (float) ($row->impressions ?? 0),
                    'ctr' => (float) ($row->ctr ?? 0),
                    'position' => (float) ($row->position ?? 0),
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

    public function queryEntityKey(string $query): string
    {
        return 'observed_query:'.sha1(Str::lower(Str::ascii(trim($query))));
    }

    private function sourceText(string $query, string $url): string
    {
        return trim(implode("\n", array_filter([
            'Query: '.trim($query),
            $url !== '' ? 'Observed URL: '.$this->support->normalizeUrl($url) : null,
        ])));
    }
}
