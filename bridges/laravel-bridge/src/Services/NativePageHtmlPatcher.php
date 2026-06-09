<?php

declare(strict_types=1);

namespace Praeviseo\LaravelBridge\Services;

/**
 * @see \Praeviseo\SymfonyBridge\Service\NativePageHtmlPatcher
 */
final class NativePageHtmlPatcher
{
    public function normalizePath(string $path): string
    {
        $trimmed = trim($path, '/');

        return $trimmed === '' ? '/' : '/'.$trimmed;
    }

    /**
     * @param  array{title?:string,meta_description?:?string,content_html?:string,faq_json?:array<int,mixed>}  $patch
     */
    public function apply(string $html, array $patch): string
    {
        $title = trim((string) ($patch['title'] ?? ''));
        $metaDescription = trim((string) ($patch['meta_description'] ?? ''));
        $contentHtml = trim((string) ($patch['content_html'] ?? ''));
        $faq = is_array($patch['faq_json'] ?? null) ? $patch['faq_json'] : [];
        $liveBaseline = $html;

        if ($title !== '') {
            $html = $this->replaceTitle($html, $title);
        }

        if ($metaDescription !== '') {
            $html = $this->replaceOrInjectMetaDescription($html, $metaDescription);
        }

        $enrichment = $this->buildEnrichmentHtml($liveBaseline, $contentHtml, $faq);

        if ($enrichment !== '') {
            $html = $this->injectBeforeClosingBody($html, $enrichment);
        }

        $schema = $this->buildFaqSchemaScript($faq, $liveBaseline);

        if ($schema !== '') {
            $html = $this->injectBeforeClosingHead($html, $schema);
        }

        return $html;
    }

    private function replaceTitle(string $html, string $title): string
    {
        $escaped = htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        if (preg_match('/<title[^>]*>.*?<\/title>/is', $html) === 1) {
            return (string) preg_replace('/<title[^>]*>.*?<\/title>/is', '<title>'.$escaped.'</title>', $html, 1);
        }

        return $this->injectBeforeClosingHead($html, '<title>'.$escaped.'</title>');
    }

    private function replaceOrInjectMetaDescription(string $html, string $description): string
    {
        $escaped = htmlspecialchars($description, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $tag = '<meta name="description" content="'.$escaped.'">';

        if (preg_match('/<meta[^>]+name=["\']description["\'][^>]*>/i', $html) === 1) {
            return (string) preg_replace(
                '/<meta[^>]+name=["\']description["\'][^>]*>/i',
                $tag,
                $html,
                1,
            );
        }

        return $this->injectBeforeClosingHead($html, $tag);
    }

    /**
     * @param  array<int,mixed>  $faq
     */
    private function buildEnrichmentHtml(string $liveHtml, string $patchContentHtml, array $faq): string
    {
        $liveHeadingKeys = $this->headingKeys($liveHtml);
        $parts = [];

        foreach ($this->extractH2Sections($patchContentHtml) as $heading => $sectionHtml) {
            if (! isset($liveHeadingKeys[$this->headingKey($heading)])) {
                $parts[] = $sectionHtml;
            }
        }

        $faqHtml = $this->buildFaqHtml($faq, $liveHtml);

        if ($faqHtml !== '') {
            $parts[] = $faqHtml;
        }

        if ($parts === []) {
            return '';
        }

        return '<section class="praeviseo-native-enrichment" data-praeviseo-native="1" aria-label="Enrichissements SEO">'
            .implode('', $parts)
            .'</section>';
    }

    /**
     * @return array<string,string>
     */
    private function extractH2Sections(string $html): array
    {
        if ($html === '') {
            return [];
        }

        if (preg_match_all('/<h2[^>]*>.*?<\/h2>/is', $html, $matches, PREG_OFFSET_CAPTURE) < 1 || $matches[0] === []) {
            return [];
        }

        $sections = [];
        $count = count($matches[0]);

        for ($index = 0; $index < $count; $index++) {
            $start = $matches[0][$index][1];
            $headingHtml = $matches[0][$index][0];
            $end = $index + 1 < $count ? $matches[0][$index + 1][1] : strlen($html);
            $headingText = trim(strip_tags($headingHtml));
            $sections[$headingText] = substr($html, $start, $end - $start);
        }

        return $sections;
    }

    /**
     * @return array<string,true>
     */
    private function headingKeys(string $html): array
    {
        $keys = [];

        if (preg_match_all('/<h2[^>]*>(.*?)<\/h2>/is', $html, $matches) < 1) {
            return $keys;
        }

        foreach ($matches[1] as $heading) {
            $key = $this->headingKey((string) $heading);

            if ($key !== '') {
                $keys[$key] = true;
            }
        }

        return $keys;
    }

    private function headingKey(string $heading): string
    {
        return mb_strtolower(trim(strip_tags($heading)));
    }

    /**
     * @param  array<int,mixed>  $faq
     */
    private function buildFaqHtml(array $faq, string $liveHtml): string
    {
        $items = [];

        foreach ($faq as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $question = trim((string) ($entry['question'] ?? ''));
            $answer = trim((string) ($entry['answer'] ?? ''));

            if ($question === '' || $answer === '') {
                continue;
            }

            if (stripos($liveHtml, $question) !== false) {
                continue;
            }

            $items[] = '<div class="praeviseo-native-faq-item"><h3>'
                .htmlspecialchars($question, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                .'</h3><p>'
                .htmlspecialchars($answer, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                .'</p></div>';
        }

        if ($items === []) {
            return '';
        }

        return '<div class="praeviseo-native-faq"><h2>Questions fréquentes</h2>'.implode('', $items).'</div>';
    }

    /**
     * @param  array<int,mixed>  $faq
     */
    private function buildFaqSchemaScript(array $faq, string $liveHtml): string
    {
        $entities = [];

        foreach ($faq as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $question = trim((string) ($entry['question'] ?? ''));
            $answer = trim((string) ($entry['answer'] ?? ''));

            if ($question === '' || $answer === '' || stripos($liveHtml, $question) !== false) {
                continue;
            }

            $entities[] = [
                '@type' => 'Question',
                'name' => $question,
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $answer,
                ],
            ];
        }

        if ($entities === []) {
            return '';
        }

        $payload = json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => $entities,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if (! is_string($payload)) {
            return '';
        }

        return '<script type="application/ld+json">'.$payload.'</script>';
    }

    private function injectBeforeClosingBody(string $html, string $injection): string
    {
        if (stripos($html, '</body>') !== false) {
            return str_ireplace('</body>', $injection.'</body>', $html);
        }

        return $html.$injection;
    }

    private function injectBeforeClosingHead(string $html, string $injection): string
    {
        if (stripos($html, '</head>') !== false) {
            return str_ireplace('</head>', $injection.'</head>', $html);
        }

        return $injection.$html;
    }
}
