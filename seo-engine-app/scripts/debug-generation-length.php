<?php

declare(strict_types=1);

use App\Models\SeoSite;
use App\Runtime\SeoEngineContext;
use App\SeoPresets\Shared\FieldExpertWritingDirectives;
use Ofyre\SeoEngine\Services\Generation\SeoGenerationService;

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$options = getopt('', ['site:', 'keyword:']);
$siteId = trim((string) ($options['site'] ?? 'amiantix'));
$keyword = trim((string) ($options['keyword'] ?? 'diagnostic amiante avant travaux'));

$site = SeoSite::query()->where('site_id', $siteId)->firstOrFail();
app(SeoEngineContext::class)->loadFromSite($site);

$result = app(SeoGenerationService::class)->generatePayload($keyword);
$content = (string) ($result['payload']['content'] ?? '');
$plain = trim(strip_tags($content));

preg_match_all('/[\p{L}\p{N}\']+/u', $plain, $matches);

echo json_encode([
    'generation_source' => $result['generation_source'] ?? null,
    'generation_error' => $result['generation_error'] ?? null,
    'html_length' => mb_strlen($content),
    'plain_length' => mb_strlen($plain),
    'unicode_word_count' => count($matches[0] ?? []),
    'h2_count' => preg_match_all('/<h2\b/i', $content),
    'validation_error' => (function () use ($result): ?string {
        try {
            FieldExpertWritingDirectives::assertFieldExpertPayload(is_array($result['payload'] ?? null) ? $result['payload'] : []);

            return null;
        } catch (Throwable $exception) {
            return $exception->getMessage();
        }
    })(),
    'content_excerpt' => mb_substr($plain, 0, 600),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n";
