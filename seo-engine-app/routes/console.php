<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Ofyre\SeoEngine\Services\Console\SeoDoctorService;

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

        $this->line(sprintf(
            'Metrics: total=%d | window_28=%d | latest_metric_date=%s',
            $metricsTotal,
            $metrics28,
            (string) ($latestMetricDate ?? 'n/a')
        ));
        $this->line(sprintf('SEO pages: %d', $pagesCount));
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
            $exitCode = Artisan::call('test', ['tests' => [$test]]);
            $this->output->write(Artisan::output());

            if ($exitCode !== 0) {
                $this->error('Smoke check failed on '.$test);

                return self::FAILURE;
            }
        }
    }

    $this->newLine();
    $this->info('SEO smoke check completed.');

    return $doctorResult['ok'] ? self::SUCCESS : self::FAILURE;
})->purpose('Run a quick end-to-end SEO health check for doctor, GSC sync, metrics and optional cockpit tests.');
