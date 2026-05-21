<?php

declare(strict_types=1);

namespace App\ObservedSite;

use App\Models\SeoCrawlPage;
use App\Models\SeoPage;
use App\Models\SeoSite;
use App\Models\SeoSiteCrawl;
use App\Models\SeoSiteCrawlIssue;
use App\Models\SeoSiteLink;
use App\Models\SeoSitePage;
use App\Models\SeoSitePageSnapshot;
use App\Models\SeoSiteSchema;
use App\Models\SeoSiteSitemap;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class SiteCrawlerService
{
    /** @var array<string,bool> */
    private array $visited = [];

    /** @var array<string,bool> */
    private array $knownSitemaps = [];

    public function crawl(SeoSite $site, int $maxPages = 80): array
    {
        $this->visited = [];
        $this->knownSitemaps = [];

        $baseUrl = rtrim($this->normalizeUrl($site->url), '/');

        $crawl = SeoSiteCrawl::query()->create([
            'site_id' => $site->site_id,
            'base_url' => $baseUrl,
            'status' => 'running',
            'max_pages' => $maxPages,
            'started_at' => now(),
        ]);

        SeoCrawlPage::query()->where('site_id', $site->site_id)->delete();

        $discovery = $this->discoverSitemaps($site, $crawl, $baseUrl);
        $queue = collect($discovery['page_urls'])
            ->prepend($baseUrl.'/')
            ->unique()
            ->values()
            ->map(fn (string $url): array => ['url' => $url, 'depth' => $this->depthFromUrl($url)])
            ->all();

        $crawled = [];

        while (! empty($queue) && count($crawled) < $maxPages) {
            $item = array_shift($queue);
            $url = $this->normalizeUrl((string) ($item['url'] ?? ''));
            $depth = (int) ($item['depth'] ?? 0);

            if ($url === '' || isset($this->visited[$url]) || $depth > 4) {
                continue;
            }

            $this->visited[$url] = true;

            try {
                $response = Http::timeout(10)
                    ->withHeaders(['User-Agent' => 'SEOEngine/2.0 (+https://seo.amiantix.com)'])
                    ->get($url);
            } catch (\Throwable $exception) {
                $this->recordIssue($site->site_id, $crawl->id, null, 'fetch_failed', 'warning', $url, $exception->getMessage());
                continue;
            }

            $statusCode = $response->status();
            $contentType = strtolower((string) $response->header('content-type', ''));

            if ($statusCode >= 400) {
                $sitePage = $this->upsertObservedPage($site->site_id, $crawl->id, $url, [
                    'title' => null,
                    'meta_description' => null,
                    'canonical_url' => null,
                    'h1' => [],
                    'word_count' => 0,
                    'status_code' => $statusCode,
                    'is_indexable' => false,
                ]);

                $this->recordIssue($site->site_id, $crawl->id, $sitePage->id, 'http_error', 'warning', $url, 'HTTP '.$statusCode);
                continue;
            }

            if (! str_contains($contentType, 'text/html')) {
                $this->recordIssue($site->site_id, $crawl->id, null, 'non_html_resource', 'info', $url, 'Content-Type '.$contentType);
                continue;
            }

            $data = $this->parsePage($response->body(), $url, $baseUrl);
            $sitePage = $this->upsertObservedPage($site->site_id, $crawl->id, $url, [
                'title' => $data['title'],
                'meta_description' => $data['meta_description'],
                'canonical_url' => $data['canonical_url'],
                'h1' => $data['h1'],
                'word_count' => $data['word_count'],
                'status_code' => $statusCode,
                'is_indexable' => $data['is_indexable'],
            ]);

            $snapshot = SeoSitePageSnapshot::query()->create([
                'site_id' => $site->site_id,
                'site_crawl_id' => $crawl->id,
                'site_page_id' => $sitePage->id,
                'url' => $url,
                'title' => $data['title'],
                'meta_description' => $data['meta_description'],
                'canonical_url' => $data['canonical_url'],
                'h1_json' => $data['h1'],
                'h2_json' => $data['h2'],
                'h3_json' => $data['h3'],
                'content_text' => $data['content_text'],
                'content_html' => $data['content_html'],
                'robots_meta' => $data['robots_meta'],
                'status_code' => $statusCode,
                'is_indexable' => $data['is_indexable'],
                'word_count' => $data['word_count'],
                'internal_links_count' => $data['internal_links_count'],
                'outlinks_count' => $data['outlinks_count'],
                'schema_count' => count($data['schemas']),
                'content_hash' => sha1($data['content_text']),
                'observed_at' => now(),
            ]);

            $sitePage->forceFill(['last_snapshot_id' => $snapshot->id])->save();

            $this->persistSchemas($site->site_id, $crawl->id, $sitePage->id, $url, $data['schemas']);
            $this->persistLinks($site->site_id, $crawl->id, $sitePage->id, $url, $data['links'], $baseUrl);
            $this->persistLegacyResult($site, $url, $depth, $statusCode, $data);
            $this->detectPageIssues($site->site_id, $crawl->id, $sitePage->id, $url, $data, $statusCode);

            $crawled[] = $sitePage;

            if ($depth < 3) {
                foreach ($data['links'] as $link) {
                    if (! ($link['is_internal'] ?? false)) {
                        continue;
                    }

                    $targetUrl = $this->normalizeUrl((string) ($link['url'] ?? ''));
                    if ($targetUrl === '' || isset($this->visited[$targetUrl])) {
                        continue;
                    }

                    $queue[] = ['url' => $targetUrl, 'depth' => $depth + 1];
                }
            }
        }

        $this->finalizeCrawl($site->site_id, $crawl->id, $discovery['sitemap_urls'], count($crawled));

        return $crawled;
    }

    public function results(string $siteId): array
    {
        $pages = SeoCrawlPage::query()->where('site_id', $siteId)->orderBy('depth')->orderBy('url')->get();
        $covered = $pages->where('is_covered', true)->count();
        $uncovered = $pages->where('is_covered', false)->count();

        return [
            'pages' => $pages,
            'total' => $pages->count(),
            'covered' => $covered,
            'uncovered' => $uncovered,
            'rate' => $pages->count() > 0 ? round($covered / $pages->count() * 100) : 0,
            'latest_crawl' => SeoSiteCrawl::query()
                ->where('site_id', $siteId)
                ->latest('started_at')
                ->first(),
            'issues_count' => SeoSiteCrawlIssue::query()->where('site_id', $siteId)->count(),
        ];
    }

    /**
     * @return array{page_urls:array<int,string>,sitemap_urls:array<int,string>}
     */
    private function discoverSitemaps(SeoSite $site, SeoSiteCrawl $crawl, string $baseUrl): array
    {
        $sitemapUrls = [$baseUrl.'/sitemap.xml'];
        $robotsUrl = $baseUrl.'/robots.txt';

        try {
            $robotsResponse = Http::timeout(8)
                ->withHeaders(['User-Agent' => 'SEOEngine/2.0 (+https://seo.amiantix.com)'])
                ->get($robotsUrl);

            if ($robotsResponse->successful()) {
                foreach (preg_split('/\r\n|\r|\n/', $robotsResponse->body()) ?: [] as $line) {
                    if (preg_match('/^\s*Sitemap:\s*(.+)$/i', $line, $matches) === 1) {
                        $candidate = $this->normalizeUrl(trim($matches[1]));
                        if ($candidate !== '') {
                            $sitemapUrls[] = $candidate;
                        }
                    }
                }
            }
        } catch (\Throwable $exception) {
            $this->recordIssue($site->site_id, $crawl->id, null, 'robots_unreachable', 'info', $robotsUrl, $exception->getMessage());
        }

        $pageUrls = [];
        $stack = collect($sitemapUrls)->unique()->values()->all();

        while ($stack !== []) {
            $sitemapUrl = array_shift($stack);
            $normalizedSitemapUrl = $this->normalizeUrl((string) $sitemapUrl);

            if ($normalizedSitemapUrl === '' || isset($this->knownSitemaps[$normalizedSitemapUrl])) {
                continue;
            }

            $this->knownSitemaps[$normalizedSitemapUrl] = true;

            try {
                $response = Http::timeout(10)
                    ->withHeaders(['User-Agent' => 'SEOEngine/2.0 (+https://seo.amiantix.com)'])
                    ->get($normalizedSitemapUrl);
            } catch (\Throwable $exception) {
                $this->recordIssue($site->site_id, $crawl->id, null, 'sitemap_unreachable', 'warning', $normalizedSitemapUrl, $exception->getMessage());
                continue;
            }

            if (! $response->successful()) {
                $this->recordIssue($site->site_id, $crawl->id, null, 'sitemap_http_error', 'warning', $normalizedSitemapUrl, 'HTTP '.$response->status());
                continue;
            }

            $parsed = $this->parseSitemapXml($response->body());

            SeoSiteSitemap::query()->updateOrCreate(
                [
                    'site_id' => $site->site_id,
                    'url_hash' => sha1($normalizedSitemapUrl),
                ],
                [
                    'site_crawl_id' => $crawl->id,
                    'url' => $normalizedSitemapUrl,
                    'sitemap_type' => $parsed['type'],
                    'parent_url' => null,
                    'lastmod_at' => null,
                    'discovered_at' => now(),
                    'meta_json' => [
                        'url_count' => count($parsed['urls']),
                        'sitemap_count' => count($parsed['sitemaps']),
                    ],
                ]
            );

            foreach ($parsed['urls'] as $url) {
                $normalizedPageUrl = $this->normalizeUrl($url);
                if ($normalizedPageUrl !== '' && str_starts_with($normalizedPageUrl, $baseUrl)) {
                    $pageUrls[] = $normalizedPageUrl;
                }
            }

            foreach ($parsed['sitemaps'] as $childSitemap) {
                $normalizedChild = $this->normalizeUrl($childSitemap);
                if ($normalizedChild === '') {
                    continue;
                }

                SeoSiteSitemap::query()->updateOrCreate(
                    [
                        'site_id' => $site->site_id,
                        'url_hash' => sha1($normalizedChild),
                    ],
                    [
                        'site_crawl_id' => $crawl->id,
                        'url' => $normalizedChild,
                        'sitemap_type' => 'sitemap',
                        'parent_url' => $normalizedSitemapUrl,
                        'discovered_at' => now(),
                    ]
                );

                if (! isset($this->knownSitemaps[$normalizedChild])) {
                    $stack[] = $normalizedChild;
                }
            }
        }

        return [
            'page_urls' => array_values(array_unique($pageUrls)),
            'sitemap_urls' => array_values(array_keys($this->knownSitemaps)),
        ];
    }

    /**
     * @return array{
     *     title:string,
     *     meta_description:string,
     *     canonical_url:?string,
     *     robots_meta:string,
     *     h1:array<int,string>,
     *     h2:array<int,string>,
     *     h3:array<int,string>,
     *     content_text:string,
     *     content_html:string,
     *     word_count:int,
     *     is_indexable:bool,
     *     internal_links_count:int,
     *     outlinks_count:int,
     *     links:array<int,array{url:string,anchor:string,is_internal:bool,is_nofollow:bool}>,
     *     schemas:array<int,array<string,mixed>>
     * }
     */
    private function parsePage(string $html, string $url, string $baseUrl): array
    {
        libxml_use_internal_errors(true);

        $dom = new DOMDocument();
        $dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        $xpath = new DOMXPath($dom);

        $title = trim((string) ($xpath->query('//title')->item(0)?->textContent ?? ''));
        $metaDescription = trim((string) ($xpath->query('//meta[@name="description"]/@content')->item(0)?->nodeValue ?? ''));
        $canonicalUrl = $this->normalizeUrl((string) ($xpath->query('//link[@rel="canonical"]/@href')->item(0)?->nodeValue ?? ''));
        $robotsMeta = trim((string) ($xpath->query('//meta[@name="robots"]/@content')->item(0)?->nodeValue ?? ''));
        $bodyNode = $xpath->query('//body')->item(0);
        $contentText = trim((string) ($bodyNode?->textContent ?? ''));
        $contentHtml = $this->innerHtml($bodyNode);
        $wordCount = str_word_count(strip_tags($contentText));
        $isIndexable = ! str_contains(strtolower($robotsMeta), 'noindex');

        $headings = [
            'h1' => $this->extractTexts($xpath, '//h1'),
            'h2' => $this->extractTexts($xpath, '//h2'),
            'h3' => $this->extractTexts($xpath, '//h3'),
        ];

        $links = [];
        $internalLinksCount = 0;
        $outlinksCount = 0;

        foreach ($xpath->query('//a[@href]') as $node) {
            $href = trim((string) $node->attributes?->getNamedItem('href')?->nodeValue);
            $absoluteUrl = $this->toAbsolute($href, $url, $baseUrl);

            if ($absoluteUrl === null) {
                continue;
            }

            $isInternal = str_starts_with($absoluteUrl, $baseUrl);
            $rel = strtolower((string) $node->attributes?->getNamedItem('rel')?->nodeValue);

            $links[] = [
                'url' => $absoluteUrl,
                'anchor' => trim((string) $node->textContent),
                'is_internal' => $isInternal,
                'is_nofollow' => str_contains($rel, 'nofollow'),
            ];

            if ($isInternal) {
                $internalLinksCount++;
            } else {
                $outlinksCount++;
            }
        }

        $schemas = [];
        foreach ($xpath->query('//script[@type="application/ld+json"]') as $node) {
            $json = trim((string) $node->textContent);

            if ($json === '') {
                continue;
            }

            $decoded = json_decode($json, true);
            if (is_array($decoded)) {
                $schemas[] = $decoded;
            }
        }

        return [
            'title' => $title,
            'meta_description' => $metaDescription,
            'canonical_url' => $canonicalUrl !== '' ? $canonicalUrl : null,
            'robots_meta' => $robotsMeta,
            'h1' => $headings['h1'],
            'h2' => $headings['h2'],
            'h3' => $headings['h3'],
            'content_text' => $contentText,
            'content_html' => $contentHtml,
            'word_count' => $wordCount,
            'is_indexable' => $isIndexable,
            'internal_links_count' => $internalLinksCount,
            'outlinks_count' => $outlinksCount,
            'links' => array_values($this->uniqueLinks($links)),
            'schemas' => $schemas,
        ];
    }

    /**
     * @return array{type:string,urls:array<int,string>,sitemaps:array<int,string>}
     */
    private function parseSitemapXml(string $xml): array
    {
        $normalizedXml = trim($xml);
        $looksLikeIndex = preg_match('/<\s*sitemapindex\b/i', $normalizedXml) === 1;
        $fallbackLocs = $this->extractLocTags($normalizedXml);

        $document = @simplexml_load_string($xml);

        if ($document === false) {
            return $looksLikeIndex
                ? ['type' => 'index', 'urls' => [], 'sitemaps' => $fallbackLocs]
                : ['type' => 'urlset', 'urls' => $fallbackLocs, 'sitemaps' => []];
        }

        $namespaces = $document->getNamespaces(true);
        if ($namespaces !== []) {
            foreach ($namespaces as $prefix => $namespace) {
                $document->registerXPathNamespace($prefix !== '' ? $prefix : 'ns', $namespace);
            }
        }

        $name = strtolower($document->getName());

        if ($name === 'sitemapindex') {
            $sitemaps = [];
            foreach ($this->xpathValues($document, ['//ns:sitemap/ns:loc', '//sitemap/loc']) as $location) {
                if ($location !== '') {
                    $sitemaps[] = $location;
                }
            }

            if ($sitemaps === []) {
                $sitemaps = $fallbackLocs;
            }

            return ['type' => 'index', 'urls' => [], 'sitemaps' => $sitemaps];
        }

        $urls = [];
        foreach ($this->xpathValues($document, ['//ns:url/ns:loc', '//url/loc']) as $location) {
            if ($location !== '') {
                $urls[] = $location;
            }
        }

        if ($urls === []) {
            $urls = $fallbackLocs;
        }

        return ['type' => 'urlset', 'urls' => $urls, 'sitemaps' => []];
    }

    /**
     * @param  array{title:?string,meta_description:?string,canonical_url:?string,h1:array<int,string>,word_count:int,status_code:int,is_indexable:bool}  $data
     */
    private function upsertObservedPage(string $siteId, int $crawlId, string $url, array $data): SeoSitePage
    {
        $normalizedUrl = $this->normalizeUrl($url);
        $page = SeoSitePage::query()->firstOrNew([
            'site_id' => $siteId,
            'url_hash' => sha1($normalizedUrl),
        ]);

        $page->fill([
            'normalized_url' => $normalizedUrl,
            'path' => $this->pathFromUrl($normalizedUrl),
            'title' => $data['title'],
            'meta_description' => $data['meta_description'],
            'canonical_url' => $data['canonical_url'],
            'primary_h1' => $data['h1'][0] ?? null,
            'indexability_state' => $data['is_indexable'] ? 'indexable' : 'noindex',
            'last_status_code' => $data['status_code'],
            'last_crawl_id' => $crawlId,
            'latest_word_count' => $data['word_count'],
            'discovered_at' => $page->exists ? ($page->discovered_at ?? now()) : now(),
            'last_seen_at' => now(),
        ])->save();

        return $page;
    }

    /**
     * @param  array<int,array{url:string,anchor:string,is_internal:bool,is_nofollow:bool}>  $links
     */
    private function persistLinks(string $siteId, int $crawlId, int $sourcePageId, string $sourceUrl, array $links, string $baseUrl): void
    {
        foreach ($links as $link) {
            $targetUrl = $this->normalizeUrl((string) ($link['url'] ?? ''));
            if ($targetUrl === '') {
                continue;
            }

            $targetPage = null;
            if ((bool) ($link['is_internal'] ?? false)) {
                $targetPage = SeoSitePage::query()->firstOrCreate(
                    [
                        'site_id' => $siteId,
                        'url_hash' => sha1($targetUrl),
                    ],
                    [
                        'normalized_url' => $targetUrl,
                        'path' => $this->pathFromUrl($targetUrl),
                        'indexability_state' => 'unknown',
                        'discovered_at' => now(),
                        'last_seen_at' => now(),
                    ]
                );
            }

            SeoSiteLink::query()->create([
                'site_id' => $siteId,
                'site_crawl_id' => $crawlId,
                'source_page_id' => $sourcePageId,
                'target_page_id' => $targetPage?->id,
                'source_url' => $sourceUrl,
                'target_url' => $targetUrl,
                'anchor_text' => (string) ($link['anchor'] ?? ''),
                'relation_type' => (bool) ($link['is_internal'] ?? false) ? 'internal' : 'outbound',
                'is_internal' => (bool) ($link['is_internal'] ?? false),
                'is_nofollow' => (bool) ($link['is_nofollow'] ?? false),
                'discovered_at' => now(),
            ]);
        }
    }

    /**
     * @param  array<int,array<string,mixed>>  $schemas
     */
    private function persistSchemas(string $siteId, int $crawlId, int $pageId, string $pageUrl, array $schemas): void
    {
        foreach ($schemas as $schema) {
            $type = $schema['@type'] ?? ($schema[0]['@type'] ?? null);

            SeoSiteSchema::query()->create([
                'site_id' => $siteId,
                'site_crawl_id' => $crawlId,
                'site_page_id' => $pageId,
                'page_url' => $pageUrl,
                'schema_type' => is_string($type) ? $type : null,
                'schema_json' => $schema,
                'content_hash' => sha1(json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: ''),
                'observed_at' => now(),
            ]);
        }
    }

    /**
     * @param  array{
     *     title:string,
     *     meta_description:string,
     *     word_count:int
     * }  $data
     */
    private function persistLegacyResult(SeoSite $site, string $url, int $depth, int $statusCode, array $data): void
    {
        $path = parse_url($url, PHP_URL_PATH) ?? '/';
        $existing = SeoPage::query()
            ->where('site_id', $site->site_id)
            ->where('slug', ltrim($path, '/'))
            ->first();

        SeoCrawlPage::query()->create([
            'site_id' => $site->site_id,
            'url' => $url,
            'title' => $data['title'],
            'meta_description' => $data['meta_description'],
            'status_code' => $statusCode,
            'word_count' => $data['word_count'],
            'depth' => $depth,
            'is_covered' => $existing !== null,
            'coverage_page_id' => $existing?->id,
            'crawled_at' => now(),
        ]);
    }

    /**
     * @param  array{
     *     title:string,
     *     meta_description:string,
     *     canonical_url:?string,
     *     h1:array<int,string>,
     *     word_count:int,
     *     is_indexable:bool,
     *     internal_links_count:int,
     *     outlinks_count:int
     * }  $data
     */
    private function detectPageIssues(string $siteId, int $crawlId, int $pageId, string $url, array $data, int $statusCode): void
    {
        if ($statusCode >= 400) {
            $this->recordIssue($siteId, $crawlId, $pageId, 'http_error', 'warning', $url, 'HTTP '.$statusCode);
        }

        if ($data['title'] === '') {
            $this->recordIssue($siteId, $crawlId, $pageId, 'missing_title', 'warning', $url, 'Page title is missing.');
        }

        if ($data['meta_description'] === '') {
            $this->recordIssue($siteId, $crawlId, $pageId, 'missing_meta_description', 'info', $url, 'Meta description is missing.');
        }

        if ($data['h1'] === []) {
            $this->recordIssue($siteId, $crawlId, $pageId, 'missing_h1', 'warning', $url, 'Primary H1 is missing.');
        }

        if ($data['word_count'] < 150) {
            $this->recordIssue($siteId, $crawlId, $pageId, 'thin_content', 'info', $url, 'Observed content is very short.');
        }

        if (! $data['is_indexable']) {
            $this->recordIssue($siteId, $crawlId, $pageId, 'noindex_detected', 'warning', $url, 'Robots meta indicates noindex.');
        }

        if ($data['canonical_url'] !== null && $data['canonical_url'] !== '' && $this->normalizeUrl($data['canonical_url']) !== $this->normalizeUrl($url)) {
            $this->recordIssue($siteId, $crawlId, $pageId, 'canonical_mismatch', 'info', $url, 'Canonical points to '.$data['canonical_url']);
        }
    }

    private function finalizeCrawl(string $siteId, int $crawlId, array $sitemapUrls, int $crawledCount): void
    {
        $inlinks = SeoSiteLink::query()
            ->where('site_id', $siteId)
            ->where('site_crawl_id', $crawlId)
            ->where('is_internal', true)
            ->whereNotNull('target_page_id')
            ->selectRaw('target_page_id, COUNT(*) as aggregate_count')
            ->groupBy('target_page_id')
            ->pluck('aggregate_count', 'target_page_id');

        $outlinks = SeoSiteLink::query()
            ->where('site_id', $siteId)
            ->where('site_crawl_id', $crawlId)
            ->where('is_internal', true)
            ->selectRaw('source_page_id, COUNT(*) as aggregate_count')
            ->groupBy('source_page_id')
            ->pluck('aggregate_count', 'source_page_id');

        $pages = SeoSitePage::query()
            ->where('site_id', $siteId)
            ->where('last_crawl_id', $crawlId)
            ->get();

        $maxInlinks = max(1, (int) $inlinks->max());
        $maxWordCount = max(1, (int) $pages->max('latest_word_count'));

        foreach ($pages as $page) {
            $incoming = (int) ($inlinks[$page->id] ?? 0);
            $outgoing = (int) ($outlinks[$page->id] ?? 0);
            $authority = round(min(1, ($incoming / $maxInlinks) * 0.8 + ($page->latest_word_count / $maxWordCount) * 0.2), 4);
            $orphan = $page->path === '/' ? 0.0 : ($incoming === 0 ? 1.0 : round(max(0, 1 - ($incoming / $maxInlinks)), 4));
            $pillar = round(min(1, ($authority * 0.55) + (($page->latest_word_count / $maxWordCount) * 0.30) + ($page->path === '/' ? 0.15 : 0.0)), 4);

            $page->forceFill([
                'internal_inlinks' => $incoming,
                'internal_outlinks' => $outgoing,
                'authority_score' => $authority,
                'orphan_score' => $orphan,
                'pillar_likelihood' => $pillar,
            ])->save();
        }

        SeoSiteCrawl::query()->whereKey($crawlId)->update([
            'status' => 'completed',
            'discovered_url_count' => count($sitemapUrls),
            'crawled_url_count' => $crawledCount,
            'completed_at' => now(),
            'meta_json' => [
                'sitemaps' => $sitemapUrls,
                'issues_count' => SeoSiteCrawlIssue::query()->where('site_id', $siteId)->where('site_crawl_id', $crawlId)->count(),
            ],
        ]);
    }

    private function recordIssue(
        string $siteId,
        ?int $crawlId,
        ?int $pageId,
        string $type,
        string $severity,
        ?string $url,
        string $details,
        array $meta = [],
    ): void {
        SeoSiteCrawlIssue::query()->create([
            'site_id' => $siteId,
            'site_crawl_id' => $crawlId,
            'site_page_id' => $pageId,
            'issue_type' => $type,
            'severity' => $severity,
            'url' => $url,
            'details' => $details,
            'meta_json' => $meta,
            'detected_at' => now(),
        ]);
    }

    private function normalizeUrl(?string $url): string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return '';
        }

        $parts = parse_url($url);
        if ($parts === false || ! isset($parts['host'])) {
            return '';
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? 'https'));
        $host = strtolower((string) $parts['host']);
        $path = (string) ($parts['path'] ?? '/');
        $path = $path === '' ? '/' : $path;
        $path = $path !== '/' ? rtrim($path, '/') : '/';
        $query = isset($parts['query']) && $parts['query'] !== '' ? '?'.$parts['query'] : '';

        return $scheme.'://'.$host.$path.$query;
    }

    private function pathFromUrl(string $url): string
    {
        $path = (string) (parse_url($url, PHP_URL_PATH) ?? '/');

        return $path === '' ? '/' : $path;
    }

    private function depthFromUrl(string $url): int
    {
        $segments = array_values(array_filter(explode('/', trim($this->pathFromUrl($url), '/'))));

        return count($segments);
    }

    private function toAbsolute(string $href, string $currentUrl, string $baseUrl): ?string
    {
        $href = trim($href);

        if ($href === '' || str_starts_with($href, '#') || str_starts_with($href, 'mailto:') || str_starts_with($href, 'tel:') || str_starts_with($href, 'javascript:')) {
            return null;
        }

        if (str_starts_with($href, 'http://') || str_starts_with($href, 'https://')) {
            return $this->normalizeUrl($href);
        }

        $current = parse_url($currentUrl);
        if ($current === false || ! isset($current['host'])) {
            return null;
        }

        if (str_starts_with($href, '/')) {
            $scheme = strtolower((string) ($current['scheme'] ?? 'https'));
            return $this->normalizeUrl($scheme.'://'.$current['host'].$href);
        }

        $basePath = dirname((string) ($current['path'] ?? '/'));
        $basePath = $basePath === '\\' ? '/' : $basePath;
        $candidate = rtrim($baseUrl, '/').'/'.ltrim(trim($basePath, '/').'/'.$href, '/');

        return $this->normalizeUrl($candidate);
    }

    /**
     * @return array<int,string>
     */
    private function extractTexts(DOMXPath $xpath, string $expression): array
    {
        $texts = [];
        foreach ($xpath->query($expression) as $node) {
            $text = trim((string) $node->textContent);
            if ($text !== '') {
                $texts[] = $text;
            }
        }

        return array_values(array_unique($texts));
    }

    private function innerHtml(?\DOMNode $node): string
    {
        if ($node === null) {
            return '';
        }

        $html = '';
        foreach ($node->childNodes as $child) {
            $html .= $node->ownerDocument?->saveHTML($child) ?? '';
        }

        return trim($html);
    }

    /**
     * @param  array<int,array{url:string,anchor:string,is_internal:bool,is_nofollow:bool}>  $links
     * @return array<string,array{url:string,anchor:string,is_internal:bool,is_nofollow:bool}>
     */
    private function uniqueLinks(array $links): array
    {
        $unique = [];

        foreach ($links as $link) {
            $key = sha1(($link['url'] ?? '').'|'.($link['anchor'] ?? ''));
            $unique[$key] = $link;
        }

        return $unique;
    }

    /**
     * @param  array<int,string>  $expressions
     * @return array<int,string>
     */
    private function xpathValues(\SimpleXMLElement $document, array $expressions): array
    {
        foreach ($expressions as $expression) {
            $nodes = $document->xpath($expression);
            if ($nodes === false || $nodes === []) {
                continue;
            }

            return array_values(array_filter(array_map(
                static fn (mixed $node): string => trim((string) $node),
                $nodes
            )));
        }

        return [];
    }

    /**
     * @return array<int,string>
     */
    private function extractLocTags(string $xml): array
    {
        preg_match_all('/<\s*loc\s*>(.*?)<\s*\/\s*loc\s*>/is', $xml, $matches);

        return array_values(array_filter(array_map(
            static fn (string $value): string => trim(html_entity_decode(strip_tags($value))),
            $matches[1] ?? []
        )));
    }
}
