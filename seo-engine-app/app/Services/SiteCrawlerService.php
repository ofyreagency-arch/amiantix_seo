<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\SeoCrawlPage;
use App\Models\SeoPage;
use App\Models\SeoSite;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Http;

class SiteCrawlerService
{
    private array $visited = [];

    public function crawl(SeoSite $site, int $maxPages = 80): array
    {
        $this->visited = [];
        $baseUrl       = rtrim($site->url, '/');

        SeoCrawlPage::query()->where('site_id', $site->site_id)->delete();

        $queue = [['url' => $baseUrl.'/', 'depth' => 0]];
        $crawled = [];

        while (! empty($queue) && count($crawled) < $maxPages) {
            $item  = array_shift($queue);
            $url   = $item['url'];
            $depth = $item['depth'];

            if (isset($this->visited[$url]) || $depth > 4) {
                continue;
            }

            $this->visited[$url] = true;

            try {
                $response = Http::timeout(8)
                    ->withHeaders(['User-Agent' => 'SEOEngine/1.0 (+https://seo.amiantix.com)'])
                    ->get($url);

                if (! $response->successful()) {
                    continue;
                }

                $data = $this->parsePage($response->body(), $url);

                $path     = parse_url($url, PHP_URL_PATH) ?? '/';
                $existing = SeoPage::query()
                    ->where('site_id', $site->site_id)
                    ->where('slug', ltrim($path, '/'))
                    ->first();

                $crawled[] = SeoCrawlPage::query()->create([
                    'site_id'          => $site->site_id,
                    'url'              => $url,
                    'title'            => $data['title'],
                    'meta_description' => $data['meta'],
                    'status_code'      => $response->status(),
                    'word_count'       => $data['word_count'],
                    'depth'            => $depth,
                    'is_covered'       => $existing !== null,
                    'coverage_page_id' => $existing?->id,
                    'crawled_at'       => now(),
                ]);

                if ($depth < 3) {
                    foreach ($data['links'] as $link) {
                        $absolute = $this->toAbsolute($link, $baseUrl);
                        if ($absolute && str_starts_with($absolute, $baseUrl) && ! isset($this->visited[$absolute])) {
                            $queue[] = ['url' => $absolute, 'depth' => $depth + 1];
                        }
                    }
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return $crawled;
    }

    public function results(string $siteId): array
    {
        $pages      = SeoCrawlPage::query()->where('site_id', $siteId)->orderBy('depth')->orderBy('url')->get();
        $covered    = $pages->where('is_covered', true)->count();
        $uncovered  = $pages->where('is_covered', false)->count();

        return [
            'pages'     => $pages,
            'total'     => $pages->count(),
            'covered'   => $covered,
            'uncovered' => $uncovered,
            'rate'      => $pages->count() > 0 ? round($covered / $pages->count() * 100) : 0,
        ];
    }

    private function parsePage(string $html, string $url): array
    {
        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        $xpath = new DOMXPath($dom);

        $title = $xpath->query('//title')->item(0)?->textContent ?? '';
        $meta  = $xpath->query('//meta[@name="description"]/@content')->item(0)?->nodeValue ?? '';
        $body  = $xpath->query('//body')->item(0)?->textContent ?? '';
        $words = str_word_count(strip_tags($body));

        $links = [];
        foreach ($xpath->query('//a/@href') as $href) {
            $links[] = $href->nodeValue;
        }

        return [
            'title'      => trim($title),
            'meta'       => trim($meta),
            'word_count' => $words,
            'links'      => array_unique($links),
        ];
    }

    private function toAbsolute(string $href, string $base): ?string
    {
        $href = trim($href);
        if (str_starts_with($href, '#') || str_starts_with($href, 'mailto:') || str_starts_with($href, 'tel:')) {
            return null;
        }
        if (str_starts_with($href, 'http')) {
            return rtrim($href, '/');
        }
        if (str_starts_with($href, '/')) {
            $parsed = parse_url($base);
            return ($parsed['scheme'] ?? 'https').'://'.($parsed['host'] ?? '').$href;
        }
        return null;
    }
}
