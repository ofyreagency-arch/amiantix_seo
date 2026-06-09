<?php

declare(strict_types=1);

namespace App\Services\Publication;

use App\Models\SeoPage;
use App\Models\SeoSite;
use App\ObservedSite\SiteCrawlerService;
use App\SeoPresets\Shared\FieldExpertWritingDirectives;
use Illuminate\Support\Facades\Http;

class PublishedContentValidationService
{
    public function __construct(
        private readonly SeoLivePublicationService $livePublication,
        private readonly SiteCrawlerService $crawler,
    ) {}

    /**
     * @return array<string,mixed>
     */
    public function validate(SeoSite $site, SeoPage $page, bool $publishIfNeeded = true): array
    {
        if ($publishIfNeeded && ! $page->isPublishedInEngine()) {
            $page->forceFill([
                'status' => 'published',
                'published_at' => $page->published_at ?? now(),
            ])->save();
            $page = $page->refresh();
        }

        if ($publishIfNeeded && ! $page->isPublishedLive()) {
            $page = $this->livePublication->publish($page->fresh(), $site->fresh());
        }

        $liveUrl = trim((string) ($page->live_url ?? ''));

        if ($liveUrl === '') {
            return [
                'ok' => false,
                'stage' => 'publication',
                'error' => 'Aucune URL live disponible pour valider le HTML publié.',
            ];
        }

        $publishedHtml = $this->fetchPublishedHtml($liveUrl);
        $extracted = $this->extractPublishedBody($publishedHtml);

        $report = [
            'ok' => true,
            'stage' => 'published_html',
            'live_url' => $liveUrl,
            'http_status' => $publishedHtml['status'],
            'word_count' => $extracted['word_count'],
            'h2_headings' => $extracted['h2_headings'],
            'draft_word_count' => $this->wordCount((string) ($page->content ?? '')),
            'validation_error' => null,
            'draft_validation_error' => null,
        ];

        try {
            FieldExpertWritingDirectives::assertFieldExpertPayload([
                'title' => (string) ($page->title ?? ''),
                'meta_description' => (string) ($page->meta_description ?? ''),
                'h1' => (string) ($page->h1 ?? ''),
                'content' => $extracted['content_html'],
                'faq' => $extracted['faq'],
            ]);
        } catch (\Throwable $exception) {
            $report['ok'] = false;
            $report['validation_error'] = $exception->getMessage();
        }

        try {
            FieldExpertWritingDirectives::assertFieldExpertPayload([
                'title' => (string) ($page->title ?? ''),
                'meta_description' => (string) ($page->meta_description ?? ''),
                'h1' => (string) ($page->h1 ?? ''),
                'content' => (string) ($page->content ?? ''),
                'faq' => $page->faq_json ?? [],
            ]);
        } catch (\Throwable $exception) {
            $report['draft_validation_error'] = $exception->getMessage();
        }

        return $report;
    }

    /**
     * @return array{status:int,html:string}
     */
    private function fetchPublishedHtml(string $liveUrl): array
    {
        $response = Http::timeout(20)
            ->withHeaders(['User-Agent' => 'Praeviseo-PublishedValidator/1.0'])
            ->get($liveUrl);

        return [
            'status' => $response->status(),
            'html' => (string) $response->body(),
        ];
    }

    /**
     * @param  array{status:int,html:string}  $publishedHtml
     * @return array{content_html:string,faq:array<int,array<string,string>>,h2_headings:array<int,string>,word_count:int}
     */
    private function extractPublishedBody(array $publishedHtml): array
    {
        $html = $publishedHtml['html'];

        if ($publishedHtml['status'] >= 400 || trim($html) === '') {
            throw new \RuntimeException('Impossible de récupérer le HTML publié (HTTP '.$publishedHtml['status'].').');
        }

        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        $xpath = new \DOMXPath($dom);

        $articleNodes = $xpath->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' prose-article ')]");
        $contentHtml = '';

        if ($articleNodes !== false && $articleNodes->length > 0) {
            foreach ($articleNodes as $node) {
                $contentHtml .= $this->innerHtml($node);
            }
        }

        if ($contentHtml === '') {
            $articleTag = $xpath->query('//article');
            if ($articleTag !== false && $articleTag->length > 0) {
                $contentHtml = $this->innerHtml($articleTag->item(0));
            }
        }

        $faq = [];
        foreach ($xpath->query('//details[contains(@class,"faq-item")]') ?: [] as $details) {
            $question = trim((string) ($xpath->query('.//summary//span', $details)?->item(0)?->textContent ?? ''));
            $answer = trim((string) ($xpath->query('.//p', $details)?->item(0)?->textContent ?? ''));

            if ($question !== '') {
                $faq[] = ['question' => $question, 'answer' => $answer];
            }
        }

        $plain = trim(strip_tags($contentHtml));
        preg_match_all('/<h2\b[^>]*>(.*?)<\/h2>/is', $contentHtml, $h2Matches);

        return [
            'content_html' => $contentHtml,
            'faq' => $faq,
            'h2_headings' => array_values(array_filter(array_map(
                static fn (string $heading): string => trim(strip_tags($heading)),
                $h2Matches[1] ?? [],
            ))),
            'word_count' => $this->wordCount($plain),
        ];
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

        return $html;
    }

    private function wordCount(string $plain): int
    {
        preg_match_all('/[\p{L}\p{N}\']+/u', $plain, $matches);

        return count($matches[0] ?? []);
    }
}
