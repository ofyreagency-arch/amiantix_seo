<?php

declare(strict_types=1);

/**
 * Generic native_update E2E validation (non-Amiantix lab site).
 *
 * Usage:
 *   php scripts/generic-native-update-e2e.php
 */

use App\Models\SeoPage;
use App\Models\SeoSite;
use App\Models\SeoSiteCrawl;
use App\Models\SeoSitePage;
use App\Models\SeoSitePageSnapshot;
use App\Services\Publication\ConfirmPreviewPublicationService;
use App\Services\Publication\SeoLivePublicationService;
use Illuminate\Support\Facades\Http;

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$siteId = 'praeviseo-native-lab';
$siteUrl = 'https://lab-client-generic.test';
$nativeSlug = 'nos-services';
$nativePath = '/'.$nativeSlug;
$liveUrl = rtrim($siteUrl, '/').$nativePath;
$bridgePrefix = 'articles';
$webhook = rtrim($siteUrl, '/').'/api/praeviseo/bridge/publish';

Http::fake([
    $webhook => Http::response([
        'status' => 'ok',
        'scope' => 'native_update',
        'target_path' => $nativePath,
        'live_url' => $liveUrl,
        'sitemap_url' => rtrim($siteUrl, '/').'/sitemap.xml',
    ], 200),
    $liveUrl => Http::response('<html><body><h1>Lab native page</h1></body></html>', 200),
]);

/** @var SeoSite $site */
$site = SeoSite::query()->updateOrCreate(
    ['site_id' => $siteId],
    [
        'name' => 'PraeviSEO Native Lab',
        'url' => $siteUrl,
        'niche' => 'generic-services',
        'locale' => 'fr',
        'preset' => 'generic',
        'api_token_hash' => hash('sha256', 'native-lab-token'),
        'is_active' => true,
        'webhook_url' => $webhook,
        'settings_json' => [
            'publication' => [
                'mode' => 'laravel_bridge',
                'webhook_url' => $webhook,
                'shared_secret' => 'native-lab-secret',
                'path_prefix' => $bridgePrefix,
                'bridge_status' => 'connected',
            ],
        ],
    ],
);

$crawl = SeoSiteCrawl::query()->updateOrCreate(
    [
        'site_id' => $site->site_id,
        'base_url' => $siteUrl,
    ],
    [
        'status' => 'completed',
        'max_pages' => 20,
        'meta_json' => ['source' => 'generic-native-update-e2e'],
    ],
);

$observed = SeoSitePage::query()->updateOrCreate(
    [
        'site_id' => $site->site_id,
        'path' => $nativePath,
    ],
    [
        'normalized_url' => $liveUrl,
        'url_hash' => hash('sha256', $liveUrl),
        'title' => 'Nos services',
        'latest_word_count' => 350,
    ],
);

$snapshot = SeoSitePageSnapshot::query()->updateOrCreate(
    [
        'site_id' => $site->site_id,
        'site_page_id' => $observed->id,
        'url' => $liveUrl,
    ],
    [
        'site_crawl_id' => $crawl->id,
        'title' => 'Nos services',
        'h2_json' => ['Interventions'],
        'content_text' => 'Page native générique pour validation PraeviSEO.',
        'word_count' => 350,
        'observed_at' => now(),
    ],
);

$observed->forceFill(['last_snapshot_id' => $snapshot->id])->save();

$homepage = SeoSitePage::query()->updateOrCreate(
    [
        'site_id' => $site->site_id,
        'path' => '/',
    ],
    [
        'normalized_url' => rtrim($siteUrl, '/'),
        'url_hash' => hash('sha256', rtrim($siteUrl, '/')),
        'title' => 'Accueil',
        'latest_word_count' => 400,
    ],
);

$homepageSnapshot = SeoSitePageSnapshot::query()->updateOrCreate(
    [
        'site_id' => $site->site_id,
        'site_page_id' => $homepage->id,
        'url' => rtrim($siteUrl, '/'),
    ],
    [
        'site_crawl_id' => $crawl->id,
        'title' => 'Accueil',
        'h2_json' => ['Engagements'],
        'content_text' => 'Page d accueil du lab client générique.',
        'word_count' => 400,
        'observed_at' => now(),
    ],
);

$homepage->forceFill(['last_snapshot_id' => $homepageSnapshot->id])->save();

SeoPage::query()
    ->where('site_id', $site->site_id)
    ->where('slug', $nativeSlug)
    ->delete();

$result = app(ConfirmPreviewPublicationService::class)->confirm($site->fresh(), $nativeSlug);
$page = SeoPage::query()->where('site_id', $site->site_id)->where('slug', $nativeSlug)->first();
$scope = $page ? app(SeoLivePublicationService::class)->publicationScopeFor($page, $site) : null;

$homepagePreview = app(\App\Copilot\ActionPreviewService::class)->build($site->site_id, 'accueil');

$homepageOk = is_array($homepagePreview)
    && ($homepagePreview['can_confirm_publish'] ?? true) === false
    && ($homepagePreview['requires_manual_validation'] ?? false) === true;

$report = [
    'ok' => ($result['publication_scope'] ?? null) === 'native_update'
        && ($result['published_live'] ?? false) === true
        && $scope === 'native_update'
        && $homepageOk,
    'site_id' => $siteId,
    'preset' => $site->resolvedPreset(),
    'niche' => $site->niche,
    'native_slug' => $nativeSlug,
    'native_path' => $nativePath,
    'bridge_prefix' => $bridgePrefix,
    'publication' => $result,
    'scope_after_publish' => $scope,
    'homepage_guard' => [
        'preview_available' => is_array($homepagePreview),
        'can_confirm_publish' => $homepagePreview['can_confirm_publish'] ?? null,
        'requires_manual_validation' => $homepagePreview['requires_manual_validation'] ?? null,
        'ok' => $homepageOk,
    ],
];

echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;

exit($report['ok'] ? 0 : 1);
