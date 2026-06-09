<?php

declare(strict_types=1);

/**
 * Régénère un article, le publie et valide le HTML publié complet.
 *
 * Usage :
 *   php scripts/regenerate-and-validate-article.php --site=amiantix --keyword="diagnostic amiante avant travaux"
 */

use App\Models\SeoPage;
use App\Models\SeoSite;
use App\Runtime\SeoEngineContext;
use App\Services\Publication\PublishedContentValidationService;
use Illuminate\Support\Str;
use Ofyre\SeoEngine\Services\Console\SeoGeneratePageRunner;

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$options = getopt('', ['site:', 'keyword:', 'output::']);
$siteId = trim((string) ($options['site'] ?? ''));
$keyword = trim((string) ($options['keyword'] ?? ''));
$outputPath = trim((string) ($options['output'] ?? ''));

if ($siteId === '' || $keyword === '') {
    fwrite(STDERR, "Usage: php scripts/regenerate-and-validate-article.php --site=SITE --keyword=\"sujet\"\n");
    exit(1);
}

/** @var SeoSite $site */
$site = SeoSite::query()->where('site_id', $siteId)->firstOrFail();

if ($site->siteProfileStatus() !== 'ready') {
    fwrite(STDERR, "SiteProfile not ready\n");
    exit(2);
}

app(SeoEngineContext::class)->loadFromSite($site);

$slug = Str::slug(Str::lower($keyword)) ?: 'article-test';
SeoPage::query()->where('site_id', $site->site_id)->where('slug', 'like', $slug.'%')->delete();

echo "== Generating: {$keyword} ==\n";
$generated = app(SeoGeneratePageRunner::class)->run($keyword, 'draft', false);
$page = $generated['page'];

$page->forceFill([
    'status' => 'published',
    'published_at' => now(),
])->save();

echo "== Publishing live ==\n";
$validation = app(PublishedContentValidationService::class)->validate($site->fresh(), $page->fresh(), publishIfNeeded: true);
$page = $page->fresh();

$report = [
    'generated_at' => now()->toIso8601String(),
    'site_id' => $site->site_id,
    'keyword' => $keyword,
    'slug' => $page->slug,
    'title' => $page->title,
    'generation_source' => $page->generation_source,
    'live_url' => $page->live_url,
    'draft_word_count' => str_word_count(strip_tags((string) $page->content)),
    'published_validation' => $validation,
    'content_excerpt' => mb_substr(trim(strip_tags((string) $page->content)), 0, 500),
];

$json = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
echo $json."\n";

if ($outputPath !== '') {
    file_put_contents($outputPath, $json);
}

exit(($validation['ok'] ?? false) ? 0 : 3);
