<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\SeoBridge\Drivers\OpenAiSeoGenerationDriver;
use Composer\InstalledVersions;
use Illuminate\Console\Command;
use Ofyre\SeoEngine\Services\Console\SeoGeneratePageRunner;
use Ofyre\SeoEngine\Services\Generation\SeoGenerationService;
use ReflectionClass;

class SeoPackageStatusCommand extends Command
{
    protected $signature = 'seo:package-status';

    protected $description = 'Affiche la vraie source package/vendor chargée par Laravel pour le moteur SEO.';

    public function handle(): int
    {
        $composer = $this->readComposerRepositoryMode();
        $packageVersion = class_exists(InstalledVersions::class)
            ? InstalledVersions::getPrettyVersion('ofyre/seo-engine')
            : null;

        $this->components->twoColumnDetail('Package version', $packageVersion ?: 'unknown');
        $this->components->twoColumnDetail('Path repository', (string) ($composer['url'] ?? 'n/a'));
        $this->components->twoColumnDetail('Symlink mode', ($composer['symlink'] ?? false) ? 'enabled' : 'disabled');

        foreach ([
            'SeoGenerationService' => SeoGenerationService::class,
            'SeoGeneratePageRunner' => SeoGeneratePageRunner::class,
            'OpenAiSeoGenerationDriver' => OpenAiSeoGenerationDriver::class,
        ] as $label => $class) {
            $path = (new ReflectionClass($class))->getFileName() ?: 'unknown';
            $mode = str_contains(str_replace('\\', '/', $path), '/vendor/ofyre/seo-engine/')
                ? 'vendor copy'
                : 'local source';

            $this->components->twoColumnDetail($label, $mode);
            $this->line('  '.$path);
        }

        return self::SUCCESS;
    }

    /**
     * @return array{url?:string,symlink?:bool}
     */
    private function readComposerRepositoryMode(): array
    {
        $composerPath = base_path('composer.json');

        if (! is_file($composerPath)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($composerPath), true);

        if (! is_array($decoded)) {
            return [];
        }

        foreach (($decoded['repositories'] ?? []) as $repository) {
            if (($repository['type'] ?? null) === 'path' && ($repository['url'] ?? null) === '..') {
                return [
                    'url' => $repository['url'],
                    'symlink' => (bool) ($repository['options']['symlink'] ?? false),
                ];
            }
        }

        return [];
    }
}
