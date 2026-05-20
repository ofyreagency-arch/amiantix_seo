<?php

declare(strict_types=1);

namespace Ofyre\SeoEngine\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

final class SeoInstallCommand extends Command
{
    protected $signature = 'seo:install {--force : Overwrite the published SEO config if it already exists} {--dry-run : Show the installation steps without writing files or running doctor}';

    protected $description = 'Publish the SEO engine config and print the next setup steps for a new Laravel host app.';

    public function handle(Filesystem $files): int
    {
        $configPath = config_path('seo-engine.php');
        $configExists = $files->exists($configPath);
        $force = (bool) $this->option('force');
        $dryRun = (bool) $this->option('dry-run');

        $this->info('SEO Engine Install');
        $this->newLine();

        if ($dryRun) {
            $this->line(sprintf('[DRY RUN] %s %s', $configExists ? 'Config already exists at' : 'Config would be published to', $configPath));
        } elseif ($configExists && ! $force) {
            $this->warn('Config already exists: '.$configPath);
            $this->line('Use `php artisan seo:install --force` if you want to overwrite it.');
        } else {
            $exitCode = $this->call('vendor:publish', array_filter([
                '--tag' => 'seo-engine-config',
                '--force' => $force,
            ], static fn (mixed $value): bool => $value !== false));

            if ($exitCode !== self::SUCCESS) {
                $this->error('Config publication failed.');

                return self::FAILURE;
            }

            $this->info('SEO engine config published.');
        }

        $this->newLine();
        $this->line('Next steps');
        foreach ($this->nextSteps() as $step) {
            $this->line('- '.$step);
        }

        if ($dryRun) {
            $this->newLine();
            $this->line('[DRY RUN] `seo:doctor` was not executed.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->line('Running `seo:doctor`...');
        $doctorExitCode = $this->call('seo:doctor');

        return $doctorExitCode === self::SUCCESS ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @return array<int,string>
     */
    private function nextSteps(): array
    {
        return [
            'Fill `seo-engine.site.*` in `config/seo-engine.php` for the target site and runtime host.',
            'Configure the classes in `seo-engine.contracts.*` for your preset, repository, persisters, feedback loop, Search Console importer and vector store.',
            'Add your OpenAI key, Search Console credentials, Redis queue and API token in `.env` if the app will run as a private SEO microservice.',
            'Register a host `SeoRuntimeServiceProvider` so the Laravel runtime binds the package contracts explicitly.',
            'Expose only private API endpoints from the host app and protect them with a bearer token middleware.',
            'Ensure Laravel scheduler and queue workers are running so the SEO commands can execute automatically.',
            'Run `php artisan seo:doctor` after wiring your adapters.',
        ];
    }
}
