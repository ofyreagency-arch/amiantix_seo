<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Ofyre\SeoEngine\Services\Console\SeoDoctorService;
use Symfony\Component\Process\Process;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('seo:sync-local-package {--source=} {--target=}', function (): int {
    $basePath = base_path();
    $sourceRoot = (string) ($this->option('source') ?: realpath($basePath.'/..'));
    $targetRoot = (string) ($this->option('target') ?: $basePath.'/vendor/ofyre/seo-engine');

    if ($sourceRoot === '' || ! File::exists($sourceRoot)) {
        $this->error('Package source path not found.');

        return self::FAILURE;
    }

    if (! File::isDirectory($targetRoot)) {
        $this->error('Package target path not found.');

        return self::FAILURE;
    }

    $entries = [
        'src',
        'config',
        'composer.json',
        'README.md',
    ];

    $copied = 0;
    $updated = 0;
    $skipped = 0;

    foreach ($entries as $entry) {
        $sourcePath = $sourceRoot.DIRECTORY_SEPARATOR.$entry;
        $targetPath = $targetRoot.DIRECTORY_SEPARATOR.$entry;

        if (! File::exists($sourcePath)) {
            continue;
        }

        if (File::isDirectory($sourcePath)) {
            foreach (File::allFiles($sourcePath) as $file) {
                $relative = ltrim(str_replace($sourcePath, '', $file->getPathname()), DIRECTORY_SEPARATOR);
                $destination = $targetPath.DIRECTORY_SEPARATOR.$relative;
                File::ensureDirectoryExists(dirname($destination));

                if (! File::exists($destination)) {
                    File::copy($file->getPathname(), $destination);
                    $copied++;

                    continue;
                }

                if (hash_file('sha1', $file->getPathname()) !== hash_file('sha1', $destination)) {
                    File::copy($file->getPathname(), $destination);
                    $updated++;

                    continue;
                }

                $skipped++;
            }

            continue;
        }

        File::ensureDirectoryExists(dirname($targetPath));

        if (! File::exists($targetPath)) {
            File::copy($sourcePath, $targetPath);
            $copied++;

            continue;
        }

        if (hash_file('sha1', $sourcePath) !== hash_file('sha1', $targetPath)) {
            File::copy($sourcePath, $targetPath);
            $updated++;

            continue;
        }

        $skipped++;
    }

    $this->info(sprintf(
        'Local package sync complete. copied=%d updated=%d skipped=%d',
        $copied,
        $updated,
        $skipped
    ));

    return self::SUCCESS;
})->purpose('Sync the local ofyre/seo-engine package into vendor when path repositories cannot refresh cleanly.');

Artisan::command('seo:smoke-check {--site_id=} {--include-tests : Run targeted free cockpit regression tests too}', function (SeoDoctorService $doctor): int {
    $this->info('SEO Smoke Check');
    $this->newLine();

    $doctorResult = $doctor->inspect(app());

    if ($doctorResult['ok']) {
        $this->info('Doctor: OK');
    } else {
        $this->error('Doctor: errors detected');
    }

    foreach ($doctorResult['warnings'] as $warning) {
        $this->warn('Warning: '.$warning);
    }

    $configuredCommands = collect(config('seo-engine.scheduler.commands', []))->count();
    $this->line(sprintf('Scheduler commands configured: %d', $configuredCommands));

    $siteId = (string) ($this->option('site_id') ?: '');

    $connectionQuery = DB::table('seo_site_google_connections');

    if ($siteId !== '') {
        $connectionQuery->where('site_id', $siteId);
    }

    $connection = $connectionQuery
        ->orderByDesc('last_sync_at')
        ->first([
            'site_id',
            'connection_status',
            'property_url',
            'last_sync_at',
            'last_validated_at',
            'last_error',
            'meta_json',
        ]);

    if ($connection === null) {
        $this->warn('No Search Console site connection found for smoke check.');
    } else {
        $meta = json_decode((string) ($connection->meta_json ?? '{}'), true);
        $lastSync = is_array($meta['last_sync'] ?? null) ? $meta['last_sync'] : [];
        $analytics = is_array($lastSync['analytics'] ?? null) ? $lastSync['analytics'] : [];
        $siteTotals = is_array($analytics['site_totals'] ?? null) ? $analytics['site_totals'] : [];

        $this->line(sprintf(
            'GSC site: %s | status=%s | property=%s',
            (string) $connection->site_id,
            (string) ($connection->connection_status ?? 'unknown'),
            (string) ($connection->property_url ?? 'n/a')
        ));
        $this->line(sprintf(
            'Last sync: %s | Last validated: %s | Last error: %s',
            (string) ($connection->last_sync_at ?? 'n/a'),
            (string) ($connection->last_validated_at ?? 'n/a'),
            (string) ($connection->last_error ?? 'none')
        ));
        $this->line(sprintf(
            'Last sync status: %s | Data as of: %s',
            (string) ($lastSync['status'] ?? 'unknown'),
            (string) ($siteTotals['end_date'] ?? 'n/a')
        ));

        $metricsQuery = DB::table('seo_search_console_metrics')
            ->where('site_id', $connection->site_id);

        $metricsTotal = (clone $metricsQuery)->count();
        $metrics28 = (clone $metricsQuery)->where('window_days', 28)->count();
        $latestMetricDate = (clone $metricsQuery)->max('metric_date');
        $pagesCount = DB::table('seo_pages')->where('site_id', $connection->site_id)->count();
        $observedPagesCount = DB::table('seo_site_pages')->where('site_id', $connection->site_id)->count();

        $this->line(sprintf(
            'Metrics: total=%d | window_28=%d | latest_metric_date=%s',
            $metricsTotal,
            $metrics28,
            (string) ($latestMetricDate ?? 'n/a')
        ));
        $this->line(sprintf(
            'SEO pages: generated=%d | observed=%d',
            $pagesCount,
            $observedPagesCount
        ));
    }

    if ((bool) $this->option('include-tests')) {
        $this->newLine();
        $this->info('Running targeted regression tests...');

        $tests = [
            'tests/Feature/ClientSitesDashboardMetricsTest.php',
            'tests/Feature/ClientWorkspaceOptimizationsTest.php',
            'tests/Feature/ClientWorkspacePublicationsTest.php',
        ];

        foreach ($tests as $test) {
            $this->line('> '.$test);
            $process = new Process([
                PHP_BINARY,
                'artisan',
                'test',
                $test,
            ], base_path());
            $process->setTimeout(300);
            $process->setEnv([
                ...$_ENV,
                ...$_SERVER,
                'APP_ENV' => 'testing',
                'DB_CONNECTION' => 'sqlite',
                'DB_DATABASE' => ':memory:',
                'CACHE_STORE' => 'array',
                'SESSION_DRIVER' => 'array',
                'QUEUE_CONNECTION' => 'sync',
                'MAIL_MAILER' => 'array',
            ]);
            $process->run(function (string $type, string $buffer): void {
                $this->output->write($buffer);
            });

            if (! $process->isSuccessful()) {
                $this->error('Smoke check failed on '.$test);

                return self::FAILURE;
            }
        }
    }

    $this->newLine();
    $this->info('SEO smoke check completed.');

    return $doctorResult['ok'] ? self::SUCCESS : self::FAILURE;
})->purpose('Run a quick end-to-end SEO health check for doctor, GSC sync, metrics and optional cockpit tests.');

Artisan::command('seo:bridge-validate {site_id} {--page-id= : Optional seo_pages.id to verify publish-live observation chain}', function (
    \App\Services\Publication\BridgePublicationValidator $validator,
): int {
    $siteId = (string) $this->argument('site_id');
    $site = \App\Models\SeoSite::query()->where('site_id', $siteId)->first();

    if (! $site) {
        $this->error('Site introuvable: '.$siteId);

        return self::FAILURE;
    }

    $report = $validator->inspectSite($site);

    $this->info('Bridge validation — '.$site->name.' ('.$site->site_id.')');
    $this->line('Mode: '.(string) $report['publication_mode']);
    $this->line('Statut bridge: '.(string) $report['bridge_status']);
    $this->line('Endpoint: '.(string) ($report['endpoint'] ?? '—'));
    $this->line('Secret: '.(($report['has_secret'] ?? false) ? 'oui' : 'non'));
    $this->line('Préfixe: '.(string) ($report['path_prefix'] ?? '—'));
    $this->line('Prêt publication: '.(($report['ready'] ?? false) ? 'oui' : 'non'));

    if ($report['endpoint_reachable'] !== null) {
        $this->line('Endpoint joignable: '.(($report['endpoint_reachable'] ?? false) ? 'oui' : 'non').' ('.(string) ($report['endpoint_reachable_detail'] ?? '').')');
    }

    $pageId = $this->option('page-id');

    if ($pageId !== null && $pageId !== '') {
        $page = \App\Models\SeoPage::query()
            ->where('site_id', $site->site_id)
            ->whereKey((int) $pageId)
            ->first();

        if (! $page) {
            $this->error('Page introuvable pour ce site.');

            return self::FAILURE;
        }

        $pageReport = $validator->inspectPublishedPage($site, $page);
        $this->newLine();
        $this->info('Chaîne publish-live → observe');
        $this->line('Live: '.(($pageReport['published_live'] ?? false) ? 'oui' : 'non'));
        $this->line('URL live: '.(string) ($pageReport['live_url'] ?? '—'));
        $this->line('Observed match: '.(($pageReport['observed_matched'] ?? false) ? 'oui' : 'non'));
        $this->line('HTTP observed: '.(string) ($pageReport['observed_http_status'] ?? '—'));
        $this->line('Règle: '.(string) ($pageReport['observed_match_rule'] ?? '—'));
        $this->line('Chaîne OK: '.(($pageReport['chain_ok'] ?? false) ? 'oui' : 'non'));

        if (! ($pageReport['chain_ok'] ?? false)) {
            return self::FAILURE;
        }
    }

    if (! ($report['ready'] ?? false)) {
        return self::FAILURE;
    }

    return self::SUCCESS;
})->purpose('Validate Laravel bridge configuration and optional publish-live observation chain for one site.');

Artisan::command('seo:recommendation-audit {site_id} {--top=20 : Number of accepted recommendations to display}', function (
    \App\Recommendations\RecommendationEngineService $engine,
): int {
    $siteId = (string) $this->argument('site_id');
    $top = max(1, (int) $this->option('top'));

    $site = \App\Models\SeoSite::query()->where('site_id', $siteId)->first();

    if (! $site) {
        $this->error('Site introuvable: '.$siteId);

        return self::FAILURE;
    }

    $audit = $engine->audit($site);
    $accepted = collect($audit['accepted'] ?? [])->take($top)->values();
    $rejected = collect($audit['rejected'] ?? [])->values();

    $this->info('Audit recommandations — '.$site->name.' ('.$site->site_id.')');
    $this->line(sprintf(
        'Retenues: %d | Rejetées: %d | Pages analysées: %d',
        (int) ($audit['summary']['accepted'] ?? 0),
        (int) ($audit['summary']['rejected'] ?? 0),
        (int) ($audit['summary']['pages_analyzed'] ?? 0),
    ));

    $this->newLine();
    $this->info('SECTION 1 — Top recommandations retenues');

    if ($accepted->isEmpty()) {
        $this->warn('Aucune recommandation acceptée.');
    } else {
        $this->table(
            ['URL', 'Action', 'Recommendation Score', 'Page Type', 'Business Intent', 'SEO Eligibility', 'Impact', 'Why Generated'],
            $accepted->map(function (array $item): array {
                $meta = is_array($item['meta_json'] ?? null) ? $item['meta_json'] : [];

                return [
                    (string) ($meta['url'] ?? $meta['source_url'] ?? $meta['target_url'] ?? $meta['context_label'] ?? '—'),
                    (string) ($item['type'] ?? '—'),
                    (string) data_get($meta, 'scoring.recommendation_score', '—'),
                    (string) data_get($meta, 'page_classification.page_type', '—'),
                    (string) data_get($meta, 'business_intent.intent_type', '—'),
                    (string) data_get($meta, 'page_classification.seo_eligibility_score', '—'),
                    (string) data_get($meta, 'impact_estimate.estimated_impact', '—'),
                    (string) ($item['reasoning'] ?? '—'),
                ];
            })->all()
        );
    }

    $this->newLine();
    $this->info('SECTION 2 — Recommandations rejetées');

    if ($rejected->isEmpty()) {
        $this->warn('Aucune recommandation supprimée.');
    } else {
        $this->table(
            ['URL', 'Action', 'Page Type', 'Rejected By', 'Reason Rejected'],
            $rejected->map(fn (array $item): array => [
                (string) ($item['url'] ?? '—'),
                (string) ($item['action'] ?? '—'),
                (string) ($item['page_type'] ?? '—'),
                (string) ($item['rejected_by'] ?? $item['layer'] ?? '—'),
                (string) ($item['reason'] ?? '—'),
            ])->all()
        );
    }

    $pageTypes = collect($audit['summary']['page_types'] ?? [])->sortKeys();
    $rejectedBy = collect($audit['summary']['rejected_by'] ?? [])->sortKeys();

    $this->newLine();
    $this->info('SECTION 3 — Statistiques moteur');
    $this->line('Pages analysées : '.(int) ($audit['summary']['pages_analyzed'] ?? 0));
    $this->line('Recommandations générées : '.(int) ($audit['summary']['accepted'] ?? 0));
    $this->line('Recommandations rejetées : '.(int) ($audit['summary']['rejected'] ?? 0));

    $this->newLine();
    $this->info('Répartition des pages');

    if ($pageTypes->isEmpty()) {
        $this->warn('Aucune page classifiée.');
    } else {
        $this->table(
            ['Page Type', 'Count'],
            $pageTypes->map(fn (int $count, string $type): array => [$type, (string) $count])->values()->all()
        );
    }

    $this->newLine();
    $this->info('Répartition des rejets');

    if ($rejectedBy->isEmpty()) {
        $this->warn('Aucun rejet enregistré.');
    } else {
        $this->table(
            ['Rejected By', 'Count'],
            $rejectedBy->map(fn (int $count, string $type): array => [$type, (string) $count])->values()->all()
        );
    }

    return self::SUCCESS;
})->purpose('Audit retained and rejected SEO recommendations for one site with engine statistics and rejection layers.');
