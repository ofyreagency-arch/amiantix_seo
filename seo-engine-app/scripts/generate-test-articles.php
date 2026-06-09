<?php

declare(strict_types=1);

/**
 * Génère N articles de test à partir d'une liste de sujets.
 *
 * Usage :
 *   php scripts/generate-test-articles.php --site=amiantix --topics="sujet 1|sujet 2|sujet 3"
 */

use App\Models\SeoPage;
use App\Models\SeoSite;
use App\Runtime\SeoEngineContext;
use App\SeoPresets\Shared\FieldExpertWritingDirectives;
use Illuminate\Support\Str;
use Ofyre\SeoEngine\Services\Console\SeoGeneratePageRunner;

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$options = getopt('', ['site:', 'topics:', 'output::']);
$siteId = trim((string) ($options['site'] ?? ''));
$topicsRaw = trim((string) ($options['topics'] ?? ''));
$outputPath = trim((string) ($options['output'] ?? ''));

if ($siteId === '' || $topicsRaw === '') {
    fwrite(STDERR, "Usage: php scripts/generate-test-articles.php --site=SITE_ID --topics=\"sujet 1|sujet 2\"\n");
    exit(1);
}

/** @var SeoSite $site */
$site = SeoSite::query()->where('site_id', $siteId)->firstOrFail();

if ($site->siteProfileStatus() !== 'ready') {
    fwrite(STDERR, "SiteProfile not ready\n");
    exit(2);
}

app(SeoEngineContext::class)->loadFromSite($site);
$runner = app(SeoGeneratePageRunner::class);

$topics = array_values(array_filter(array_map('trim', explode('|', $topicsRaw))));
$results = [];

foreach ($topics as $topic) {
    $slug = Str::slug(Str::lower($topic)) ?: 'article-test';
    SeoPage::query()
        ->where('site_id', $site->site_id)
        ->where('slug', 'like', $slug.'%')
        ->delete();

    $entry = [
        'keyword' => $topic,
        'status' => 'error',
    ];

    try {
        $generated = $runner->run($topic, 'draft', false);
        $page = $generated['page'];
        $content = (string) ($page->content ?? '');

        $injectionChecks = [
            'checklist' => str_contains($content, 'Checklist operationnelle') || str_contains($content, 'Checklist opérationnelle'),
            'errors_block' => str_contains($content, 'Erreurs frequentes et blocages') || str_contains($content, 'Erreurs fréquentes et blocages'),
            'routine' => str_contains($content, 'Routine documentaire'),
            'resources' => str_contains($content, 'Ressources et pages utiles'),
            'bridge_phrase' => str_contains($content, 'C est dans ce passage') || str_contains($content, 'C\'est dans ce passage'),
        ];

        try {
            FieldExpertWritingDirectives::assertFieldExpertPayload([
                'title' => (string) ($page->title ?? ''),
                'meta_description' => (string) ($page->meta_description ?? ''),
                'h1' => (string) ($page->h1 ?? ''),
                'content' => $content,
                'faq' => $page->faq_json ?? [],
            ]);
            $validationError = null;
        } catch (Throwable $validationException) {
            $validationError = $validationException->getMessage();
        }

        preg_match_all('/[\p{L}\p{N}\']+/u', trim(strip_tags($content)), $matches);

        $entry = [
            'keyword' => $topic,
            'status' => 'generated',
            'slug' => $page->slug ?? null,
            'title' => $page->title ?? null,
            'generation_source' => $page->generation_source ?? null,
            'word_count' => count($matches[0] ?? []),
            'h2_count' => preg_match_all('/<h2\b/i', $content),
            'injections' => $injectionChecks,
            'validation_error' => $validationError,
            'content_excerpt' => mb_substr(trim(strip_tags($content)), 0, 400),
        ];
    } catch (Throwable $exception) {
        $entry['error'] = $exception->getMessage();
    }

    $results[] = $entry;
    echo '== Generated: '.$topic." ==\n";
}

$report = [
    'generated_at' => now()->toIso8601String(),
    'site_id' => $site->site_id,
    'articles' => $results,
];

$json = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
echo $json."\n";

if ($outputPath !== '') {
    file_put_contents($outputPath, $json);
}

$failed = collect($results)->contains(fn (array $item): bool => ($item['status'] ?? '') !== 'generated' || ($item['validation_error'] ?? null) !== null);

exit($failed ? 3 : 0);
