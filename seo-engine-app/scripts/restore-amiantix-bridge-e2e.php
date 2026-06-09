<?php

declare(strict_types=1);

/**
 * One-shot ops script: restore Amiantix Symfony bridge + publish slug-test.
 * Run on SEO VPS: php scripts/restore-amiantix-bridge-e2e.php
 */

use App\Models\RemoteInstallation;
use App\Models\SeoPage;
use App\Models\SeoSite;
use App\RemoteInstallation\Connectors\SshRemoteConnector;
use App\RemoteInstallation\RemoteCommand;
use App\Services\Publication\SeoLivePublicationService;
use Illuminate\Support\Facades\Http;

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

const TEST_SLUG = 'slug-test';
const PRAEVISEO_API = 'https://seo.amiantix.com';

function step(string $label, callable $fn): mixed
{
    echo PHP_EOL.'== '.$label.' =='.PHP_EOL;
    try {
        $result = $fn();
        echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;

        return $result;
    } catch (Throwable $e) {
        echo 'ERROR: '.$e->getMessage().PHP_EOL;
        throw $e;
    }
}

/** @return array<string,mixed> */
function remoteConnectorFromLatestInstallation(): array
{
    $installation = RemoteInstallation::query()
        ->where('site_id', 'amiantix')
        ->orderByDesc('id')
        ->firstOrFail();

    $creds = $installation->encrypted_credentials ?? [];
    $path = trim((string) data_get($installation->connection_metadata, 'project_path', '/var/www/amiantix'));

    $connector = new SshRemoteConnector([
        'host' => (string) ($creds['host'] ?? ''),
        'port' => (int) ($creds['port'] ?? 22),
        'username' => (string) ($creds['username'] ?? ''),
        'secret' => (string) ($creds['secret'] ?? ''),
    ]);

    return compact('installation', 'connector', 'path', 'creds');
}

step('1_prepare_publication_target', function (): array {
    $site = SeoSite::query()->where('site_id', 'amiantix')->firstOrFail();
    $code = SeoSite::generatePublicationConnectCode();
    $settings = $site->settings_json ?? [];
    $publication = is_array($settings['publication'] ?? null) ? $settings['publication'] : [];

    $publication['mode'] = 'symfony_bridge';
    $publication['path_prefix'] = 'ressources';
    $publication['connect_code'] = $code;
    $publication['bridge_status'] = 'pending';
    $publication['webhook_url'] = 'https://amiantix.com/api/praeviseo/bridge/publish';
    unset($publication['shared_secret']);

    $settings['publication'] = $publication;

    $site->forceFill([
        'settings_json' => $settings,
        'webhook_url' => 'https://amiantix.com/api/praeviseo/bridge/publish',
    ])->save();

    return [
        'connect_code' => $code,
        'bridge_status' => $site->fresh()->publicationBridgeStatus(),
        'mode' => $site->fresh()->resolvedPublicationMode(),
    ];
});

$remote = remoteConnectorFromLatestInstallation();
$connector = $remote['connector'];
$path = $remote['path'];
$connector->connect();

step('2_remote_bridge_state_before', function () use ($connector, $path): array {
    $checks = [
        'package' => $connector->fileExists($path.'/vendor/praeviseo/symfony-bridge/composer.json'),
        'bundles' => $connector->fileExists($path.'/config/bundles.php'),
        'connect_cmd' => trim($connector->run(RemoteCommand::detectPraeviseoConnectCommand($path, 'symfony'), 45)->output),
        'praeviseo_url' => trim($connector->run(RemoteCommand::detectPraeviseoUrl($path, 'symfony'), 45)->output),
        'app_url' => trim($connector->run(RemoteCommand::detectAppUrl($path), 45)->output),
    ];

    return $checks;
});

step('3_enable_symfony_bridge', function () use ($connector, $path): array {
    $out = [];

    if (! $connector->fileExists($path.'/vendor/praeviseo/symfony-bridge/composer.json')) {
        $install = $connector->run(RemoteCommand::installSymfonyBridge($path), 300);
        $out['install'] = [
            'ok' => $install->successful,
            'stdout' => mb_substr($install->output, 0, 1200),
            'stderr' => mb_substr($install->errorOutput, 0, 600),
        ];
    } else {
        $out['install'] = 'already_present';
    }

    $dump = $connector->run(RemoteCommand::dumpSymfonyAutoload($path), 180);
    $out['dump_autoload'] = [
        'ok' => $dump->successful,
        'stdout' => mb_substr($dump->output, 0, 800),
        'stderr' => mb_substr($dump->errorOutput, 0, 400),
    ];

    $clear = $connector->run(RemoteCommand::clearSymfonyCache($path), 180);
    $out['cache_clear'] = ['ok' => $clear->successful];

    $out['connect_cmd_after'] = trim($connector->run(RemoteCommand::detectPraeviseoConnectCommand($path, 'symfony'), 45)->output);

    return $out;
});

step('3b_ensure_app_url', function () use ($connector, $path): array {
    $patch = $connector->run(RemoteCommand::ensureSymfonyAppUrl($path, 'https://amiantix.com'), 60);
    $clear = $connector->run(RemoteCommand::clearSymfonyCache($path), 180);

    return [
        'patch_ok' => $patch->successful,
        'app_url' => trim($connector->run(RemoteCommand::detectAppUrl($path), 45)->output),
        'cache_clear_ok' => $clear->successful,
    ];
});

$site = SeoSite::query()->where('site_id', 'amiantix')->firstOrFail();
$connectCode = (string) $site->publicationConnectCode();

step('4_connect_symfony_bridge', function () use ($connector, $path, $connectCode, $site): array {
    $connect = $connector->run(RemoteCommand::connectSymfony(
        $path,
        $connectCode,
        rtrim((string) config('app.url'), '/'),
        'ressources',
    ), 180);
    $result = [
        'ok' => $connect->successful,
        'stdout' => $connect->output,
        'stderr' => $connect->errorOutput,
        'exit' => $connect->exitCode,
    ];

    $site->refresh();

    $result['bridge_status'] = $site->publicationBridgeStatus();
    $result['secret_present'] = filled($site->publicationSharedSecret());
    $result['mode'] = $site->resolvedPublicationMode();

    if (! $connect->successful || $site->publicationBridgeStatus() !== 'connected' || ! $site->publicationSharedSecret()) {
        throw new RuntimeException('La connexion bridge n a pas abouti côté moteur.');
    }

    return $result;
});

step('5_create_test_page', function () use ($site): array {
    $page = SeoPage::query()->updateOrCreate(
        [
            'site_id' => $site->site_id,
            'slug' => TEST_SLUG,
        ],
        [
            'keyword' => 'test bridge praeviseo',
            'status' => 'published',
            'published_at' => now(),
            'title' => 'Test publication bridge PraeviSEO',
            'h1' => 'Test publication bridge PraeviSEO',
            'meta_description' => 'Page de validation E2E du bridge Symfony Amiantix.',
            'content' => '<p>Cette page valide la chaîne seo_pages → bridge Symfony → /ressources/slug-test.</p>',
            'faq_json' => [],
            'schema_json' => [],
            'internal_links_json' => [],
            'published_live' => false,
            'live_url' => null,
        ],
    );

    return ['page_id' => $page->id, 'slug' => $page->slug, 'status' => $page->status];
});

step('6_publish_via_bridge', function () use ($site): array {
    $page = SeoPage::query()
        ->where('site_id', $site->site_id)
        ->where('slug', TEST_SLUG)
        ->firstOrFail();

    $published = app(SeoLivePublicationService::class)->publish($page->fresh(), $site->fresh());

    return [
        'published_live' => $published->published_live,
        'live_url' => $published->live_url,
        'last_push_status' => data_get($site->fresh()->settings_json, 'publication.last_push_status'),
        'last_error' => data_get($site->fresh()->settings_json, 'publication.last_error'),
    ];
});

step('7_verify_public_url', function (): array {
    $url = 'https://amiantix.com/ressources/'.TEST_SLUG;
    $response = Http::timeout(20)->get($url);

    return [
        'url' => $url,
        'http_status' => $response->status(),
        'body_preview' => mb_substr(strip_tags((string) $response->body()), 0, 200),
    ];
});

step('8_verify_sitemap', function (): array {
    $url = 'https://amiantix.com/ressources-sitemap.xml';
    $response = Http::timeout(20)->get($url);

    return [
        'url' => $url,
        'http_status' => $response->status(),
        'contains_slug' => str_contains((string) $response->body(), TEST_SLUG),
        'body_preview' => mb_substr((string) $response->body(), 0, 400),
    ];
});

$connector->disconnect();

echo PHP_EOL.'DONE'.PHP_EOL;
