<?php

declare(strict_types=1);

/**
 * Verify native page patch is visible on a live client URL.
 *
 * Usage:
 *   php scripts/native-live-render-e2e.php https://example.com/faq
 */

use Illuminate\Support\Facades\Http;

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$url = trim((string) ($argv[1] ?? ''));

if ($url === '') {
    fwrite(STDERR, "Usage: php scripts/native-live-render-e2e.php {liveUrl}\n");
    exit(1);
}

$response = Http::timeout(20)
    ->withHeaders(['User-Agent' => 'Praeviseo-NativeRenderE2E/1.0'])
    ->get($url);

$body = (string) $response->body();
$headers = $response->headers();

$report = [
    'url' => $url,
    'http_status' => $response->status(),
    'patch_header' => $headers['X-Praeviseo-Native-Patch'][0] ?? null,
    'has_native_marker' => str_contains($body, 'data-praeviseo-native="1"'),
    'has_native_enrichment' => str_contains($body, 'praeviseo-native-enrichment'),
    'ok' => $response->successful()
        && (
            str_contains($body, 'data-praeviseo-native="1"')
            || ($headers['X-Praeviseo-Native-Patch'][0] ?? null) === 'applied'
        ),
];

echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;

exit($report['ok'] ? 0 : 1);
