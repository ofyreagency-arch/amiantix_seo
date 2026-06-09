<?php

declare(strict_types=1);

use App\Models\SeoSite;
use App\Models\SeoSitePage;
use App\Runtime\PremiumArticleGenerationService;

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$site = SeoSite::query()->where('site_id', 'amiantix')->first();
$service = app(PremiumArticleGenerationService::class);

$loginPages = SeoSitePage::query()
    ->where('site_id', 'amiantix')
    ->where(function ($query): void {
        $query
            ->where('path', 'like', '%login%')
            ->orWhere('path', 'like', '%mot-de-passe%')
            ->orWhere('title', 'like', '%Mot de passe%');
    })
    ->get(['path', 'title', 'indexability_state'])
    ->all();

echo json_encode([
    'profile_kw' => $site ? $service->resolveProfileKeyword($site) : null,
    'candidate_kw' => $site ? $service->resolveCandidateKeyword($site) : null,
    'profile_status' => $site?->siteProfileStatus(),
    'login_pages' => $loginPages,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT).PHP_EOL;
