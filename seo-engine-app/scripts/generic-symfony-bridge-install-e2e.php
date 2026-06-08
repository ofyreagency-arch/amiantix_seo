<?php

declare(strict_types=1);

/**
 * Generic Symfony bridge installation + publish validation (from zero on a remote project).
 *
 * Usage (on SEO VPS):
 *   php scripts/generic-symfony-bridge-install-e2e.php {siteId} {testSlug}
 */

use App\Models\RemoteInstallation;
use App\Models\SeoPage;
use App\Models\SeoSite;
use App\RemoteInstallation\Connectors\SshRemoteConnector;
use App\RemoteInstallation\RemoteCommand;
use App\RemoteInstallation\RemoteInstallationService;
use App\Services\Publication\SeoLivePublicationService;
use Illuminate\Support\Facades\Http;

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$siteId = $argv[1] ?? null;
$testSlug = trim((string) ($argv[2] ?? 'bridge-e2e-test'));

if (! is_string($siteId) || $siteId === '') {
    fwrite(STDERR, "Usage: php scripts/generic-symfony-bridge-install-e2e.php {siteId} [testSlug]\n");
    exit(1);
}

/** @var SeoSite $site */
$site = SeoSite::query()->where('site_id', $siteId)->firstOrFail();

$settings = $site->settings_json ?? [];
$publication = is_array($settings['publication'] ?? null) ? $settings['publication'] : [];
$publication['mode'] = 'symfony_bridge';
$publication['path_prefix'] = $publication['path_prefix'] ?? 'ressources';
$publication['connect_code'] = $publication['connect_code'] ?? SeoSite::generatePublicationConnectCode();
$publication['bridge_status'] = 'pending';
unset($publication['shared_secret']);
$settings['publication'] = $publication;
$site->forceFill(['settings_json' => $settings])->save();

/** @var RemoteInstallation|null $installation */
$installation = RemoteInstallation::query()
    ->where('site_id', $siteId)
    ->orderByDesc('id')
    ->first();

if (! $installation) {
    fwrite(STDERR, "No remote_installation found for site {$siteId}. Create one before running this script.\n");
    exit(1);
}

app(RemoteInstallationService::class)->run($installation->fresh());

$site = $site->fresh();
$report = [
    'site_id' => $siteId,
    'site_url' => $site->url,
    'bridge_status' => $site->publicationBridgeStatus(),
    'mode' => $site->resolvedPublicationMode(),
    'secret_present' => filled($site->publicationSharedSecret()),
    'installation_status' => $installation->fresh()->status,
];

if ($site->publicationBridgeStatus() !== 'connected' || ! $site->publicationSharedSecret()) {
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
    exit(2);
}

$page = SeoPage::query()->updateOrCreate(
    ['site_id' => $siteId, 'slug' => $testSlug],
    [
        'keyword' => 'bridge e2e test',
        'status' => 'published',
        'published_at' => now(),
        'title' => 'Bridge E2E test page',
        'h1' => 'Bridge E2E test page',
        'meta_description' => 'Generic Symfony bridge validation page.',
        'content' => '<p>Generic Symfony bridge E2E validation.</p>',
        'faq_json' => [],
        'schema_json' => [],
        'internal_links_json' => [],
        'published_live' => false,
        'live_url' => null,
    ],
);

$published = app(SeoLivePublicationService::class)->publish($page->fresh(), $site->fresh());
$liveUrl = (string) ($published->live_url ?? '');
$httpStatus = $liveUrl !== '' ? Http::timeout(20)->get($liveUrl)->status() : null;
$prefix = trim((string) ($site->publicationPathPrefix() ?: 'ressources'), '/');
$sitemapUrl = rtrim((string) $site->url, '/').'/'.$prefix.'-sitemap.xml';
$sitemapStatus = Http::timeout(20)->get($sitemapUrl)->status();
$sitemapBody = (string) Http::get($sitemapUrl)->body();

$projectPath = trim((string) data_get($installation->fresh()->connection_metadata, 'project_path', ''));
$publishedPagesCount = null;
$connectCommandPresent = null;

if ($projectPath !== '' && $installation->connection_type === 'ssh') {
    $credentials = $installation->encrypted_credentials ?? [];
    $connector = new SshRemoteConnector([
        'host' => (string) ($credentials['host'] ?? ''),
        'port' => (int) ($credentials['port'] ?? 22),
        'username' => (string) ($credentials['username'] ?? ''),
        'secret' => (string) ($credentials['secret'] ?? ''),
    ]);

    try {
        $connector->connect();
        $countResult = $connector->run(RemoteCommand::countSymfonyPublishedPages($projectPath), 60);
        $publishedPagesCount = trim($countResult->output);
        $connectResult = $connector->run(RemoteCommand::detectPraeviseoConnectCommand($projectPath, 'symfony'), 60);
        $connectCommandPresent = trim($connectResult->output) === 'present';
    } finally {
        $connector->disconnect();
    }
}

$report['page'] = [
    'id' => $published->id,
    'slug' => $published->slug,
    'published_live' => $published->published_live,
    'live_url' => $liveUrl,
    'last_push_status' => data_get($site->fresh()->settings_json, 'publication.last_push_status'),
];
$report['remote'] = [
    'project_path' => $projectPath,
    'praeviseo_connect_present' => $connectCommandPresent,
    'praeviseo_published_pages_count' => $publishedPagesCount,
];
$report['http'] = [
    'live_url_status' => $httpStatus,
    'sitemap_url' => $sitemapUrl,
    'sitemap_status' => $sitemapStatus,
    'sitemap_contains_slug' => str_contains($sitemapBody, $testSlug),
];

echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;

$publishedPagesOk = is_string($publishedPagesCount) && ctype_digit($publishedPagesCount) && (int) $publishedPagesCount >= 1;

exit(
    $httpStatus === 200
    && $sitemapStatus === 200
    && $publishedPagesOk
    && $connectCommandPresent === true
    ? 0
    : 3
);
