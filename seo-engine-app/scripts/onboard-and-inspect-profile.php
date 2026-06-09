<?php

declare(strict_types=1);

/**
 * Relance l'onboarding et expose le SiteProfile filtré (editorial_topics, services, pages).
 *
 * Usage :
 *   php scripts/onboard-and-inspect-profile.php --site=amiantix --onboard
 */

use App\Models\SeoSite;
use App\Runtime\PremiumArticleGenerationService;
use App\Understanding\EditorialTopicClassifier;
use App\Understanding\SiteOnboardingService;

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$options = getopt('', ['site:', 'onboard', 'output::']);
$siteId = trim((string) ($options['site'] ?? ''));
$runOnboard = array_key_exists('onboard', $options);
$outputPath = trim((string) ($options['output'] ?? ''));

if ($siteId === '') {
    fwrite(STDERR, "Usage: php scripts/onboard-and-inspect-profile.php --site=SITE_ID [--onboard] [--output=path.json]\n");
    exit(1);
}

/** @var SeoSite $site */
$site = SeoSite::query()->where('site_id', $siteId)->firstOrFail();

if ($runOnboard) {
    echo "== Onboarding synchronisé ==\n";
    app(SiteOnboardingService::class)->runSynchronously($site->fresh());
    $site = $site->fresh();
}

$profile = $site->siteProfile();
$classifier = app(EditorialTopicClassifier::class);
$articles = app(PremiumArticleGenerationService::class);

$forbiddenNeedles = [
    'parlons de vos dossiers',
    'politique de confidential',
    'mentions l',
    'conditions g',
    'données personnelles',
    'donnees personnelles',
    'exercer vos droits',
    'restez informé',
    'restez informe',
    'newsletter',
    'questions fréquentes sur amiantix',
    'questions frequentes sur amiantix',
    'contact & démo',
    'contact & demo',
    'nous répondons sous',
    'nous repondons sous',
];

$editorialTopics = is_array($profile['editorial_topics'] ?? null) ? $profile['editorial_topics'] : [];
$topicViolations = [];

foreach ($editorialTopics as $topic) {
    $normalized = mb_strtolower((string) $topic);
    foreach ($forbiddenNeedles as $needle) {
        if (str_contains($normalized, $needle)) {
            $topicViolations[] = ['topic' => $topic, 'needle' => $needle];
        }
    }
}

$report = [
    'inspected_at' => now()->toIso8601String(),
    'site_id' => $site->site_id,
    'site_profile_status' => $site->siteProfileStatus(),
    'onboarding_ran' => $runOnboard,
    'business' => $profile['business'] ?? null,
    'services' => collect($profile['services'] ?? [])->map(fn (array $service): array => [
        'name' => $service['name'] ?? null,
        'intent' => $service['intent'] ?? null,
        'path' => $service['path'] ?? null,
        'headings' => $service['headings'] ?? [],
    ])->values()->all(),
    'main_pages' => collect($profile['main_pages'] ?? [])->map(fn (array $page): array => [
        'path' => $page['path'] ?? null,
        'title' => $page['title'] ?? null,
        'role' => $page['role'] ?? null,
    ])->values()->all(),
    'vocabulary_core_terms' => array_slice((array) ($profile['vocabulary']['core_terms'] ?? []), 0, 15),
    'editorial_topics' => $editorialTopics,
    'editorial_topics_top15' => array_slice($editorialTopics, 0, 15),
    'editorial_topics_count' => count($editorialTopics),
    'topic_violations' => $topicViolations,
    'topics_clean' => $topicViolations === [],
    'next_profile_keyword' => $articles->resolveProfileKeyword($site),
    'next_candidate_keyword' => $articles->resolveCandidateKeyword($site),
];

$json = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
echo $json."\n";

if ($outputPath !== '') {
    file_put_contents($outputPath, $json);
}

exit($report['topics_clean'] && $report['site_profile_status'] === 'ready' ? 0 : 2);
