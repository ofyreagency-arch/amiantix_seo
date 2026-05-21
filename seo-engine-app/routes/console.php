<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

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
