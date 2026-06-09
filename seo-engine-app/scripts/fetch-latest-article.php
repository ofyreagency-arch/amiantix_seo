<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$page = App\Models\SeoPage::query()
    ->whereNotNull('generation_source')
    ->orderByDesc('updated_at')
    ->first();

if (! $page) {
    echo json_encode(['error' => 'no_generated_page'], JSON_PRETTY_PRINT).PHP_EOL;
    exit(1);
}

echo json_encode([
    'site_id' => $page->site_id,
    'slug' => $page->slug,
    'keyword' => $page->keyword,
    'title' => $page->title,
    'generation_source' => $page->generation_source,
    'updated_at' => optional($page->updated_at)?->toIso8601String(),
    'word_count' => str_word_count(strip_tags((string) $page->content)),
    'content' => $page->content,
    'faq_count' => count($page->faq_json ?? []),
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT).PHP_EOL;
