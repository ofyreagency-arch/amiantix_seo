<?php

declare(strict_types=1);

/**
 * Validation réelle SiteProfile + génération métier.
 *
 * Usage (sur le VPS SEO) :
 *   php scripts/production-site-profile-validation.php --site=amiantix --keyword="diagnostic amiante avant travaux"
 *   php scripts/production-site-profile-validation.php --site=symfony-bridge-lab --keyword="publication ressources symfony" --onboard
 */

use App\Models\SeoPage;
use App\Models\SeoSite;
use App\Runtime\SeoEngineContext;
use App\Services\Preset\PresetBlueprintProvider;
use App\Services\Preset\PresetInternalLinkProvider;
use App\Services\Preset\PresetPromptProfile;
use App\Understanding\SiteOnboardingService;
use Illuminate\Support\Str;
use Ofyre\SeoEngine\Services\Console\SeoGeneratePageRunner;

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$options = getopt('', ['site:', 'keyword:', 'onboard', 'skip-generate', 'output::']);
$siteId = trim((string) ($options['site'] ?? ''));
$keyword = trim((string) ($options['keyword'] ?? ''));
$runOnboard = array_key_exists('onboard', $options);
$skipGenerate = array_key_exists('skip-generate', $options);
$outputPath = trim((string) ($options['output'] ?? ''));

if ($siteId === '' || $keyword === '') {
    fwrite(STDERR, "Usage: php scripts/production-site-profile-validation.php --site=SITE_ID --keyword=\"mot cle\" [--onboard] [--skip-generate] [--output=path.json]\n");
    exit(1);
}

/** @var SeoSite $site */
$site = SeoSite::query()->where('site_id', $siteId)->firstOrFail();

$report = [
    'validated_at' => now()->toIso8601String(),
    'site_id' => $site->site_id,
    'site_name' => $site->name,
    'site_url' => $site->url,
    'niche' => $site->niche,
    'preset' => $site->resolvedPreset(),
    'locale' => $site->locale,
    'keyword' => $keyword,
];

if ($runOnboard) {
    echo "== Onboarding synchronisé (crawl + analyse + profil) ==\n";
    $profile = app(SiteOnboardingService::class)->runSynchronously($site->fresh());
    $report['onboarding'] = ['mode' => 'sync', 'profile_status' => $profile['status'] ?? null];
    $site = $site->fresh();
}

$profile = $site->siteProfile();
$report['site_profile'] = $profile;
$report['site_profile_status'] = $site->siteProfileStatus();

if ($site->siteProfileStatus() !== 'ready') {
    $report['error'] = 'site_profile.status != ready — génération bloquée';
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n";
    exit(2);
}

app(SeoEngineContext::class)->loadFromSite($site);

$internalLinks = app(PresetInternalLinkProvider::class);
$blueprints = app(PresetBlueprintProvider::class);
$prompts = app(PresetPromptProfile::class);

$cluster = $internalLinks->clusterForKeyword($keyword);
$blueprint = $blueprints->resolve($keyword, $cluster);
$editorialSections = $blueprints->expectedEditorialSections($blueprint);
$expectedSignals = $blueprints->expectedSignals($blueprint);
$corePrompt = $prompts->generationCorePrompt($keyword, $cluster, $blueprint, $editorialSections, $expectedSignals);

$report['generation_context'] = [
    'cluster' => $cluster,
    'blueprint' => $blueprint,
    'editorial_sections' => $editorialSections,
    'expected_signals' => $expectedSignals,
];
$report['core_prompt'] = $corePrompt;
$report['core_prompt_length'] = mb_strlen($corePrompt);

if (! $skipGenerate) {
    echo "== Génération réelle (OpenAI) ==\n";
    $slug = Str::slug(Str::lower($keyword)) ?: 'article-validation';
    SeoPage::query()->where('site_id', $site->site_id)->where('slug', 'like', $slug.'%')->delete();

    try {
        $result = app(SeoGeneratePageRunner::class)->run($keyword, 'draft', false);
        $page = $result['page'];
    } catch (Throwable $generationException) {
        $report['generation_error'] = $generationException->getMessage();
        $report['generation_error_class'] = $generationException::class;
        echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n";
        if ($outputPath !== '') {
            file_put_contents($outputPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }
        exit(4);
    }

    $content = (string) ($page->content ?? '');
    $report['generated_page'] = [
        'id' => $page->id ?? null,
        'slug' => $page->slug ?? null,
        'title' => $page->title ?? null,
        'h1' => $page->h1 ?? null,
        'meta_description' => $page->meta_description ?? null,
        'generation_source' => $page->generation_source ?? null,
        'generation_error' => $page->generation_error ?? null,
        'word_count' => str_word_count(strip_tags($content)),
        'content' => $content,
        'faq' => $page->faq_json ?? [],
    ];
    $report['generation_trace'] = $page->generation_trace_json ?? null;

    $forbidden = [
        'Field example',
        'SaaS knowledge base',
        'innovative solution',
        'Operational context',
        'Write a business article',
        'professional SaaS',
    ];
    $englishMarkers = [' the ', ' and ', ' your ', ' with ', ' this article ', ' knowledge base '];

    $violations = [];
    foreach ($forbidden as $phrase) {
        if (stripos($content, $phrase) !== false) {
            $violations[] = 'forbidden_phrase:'.$phrase;
        }
    }
    foreach ($englishMarkers as $marker) {
        if (stripos(' '.strtolower($content).' ', strtolower($marker)) !== false) {
            $violations[] = 'english_marker:'.trim($marker);
        }
    }

    $report['quality_checks'] = [
        'violations' => $violations,
        'passed' => $violations === [],
    ];
}

$json = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
echo $json."\n";

if ($outputPath !== '') {
    file_put_contents($outputPath, $json);
    echo "\nRapport écrit dans: {$outputPath}\n";
}

exit(($report['quality_checks']['passed'] ?? true) ? 0 : 3);
