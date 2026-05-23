<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class SeoSyncLocalPackageCommand extends Command
{
    protected $signature = 'seo:sync-local-package';

    protected $description = 'Resynchronise le package ofyre/seo-engine installé dans vendor avec la source locale.';

    public function handle(): int
    {
        $composerBinary = is_file(base_path('composer.phar'))
            ? [PHP_BINARY, 'composer.phar']
            : ['composer'];

        $command = array_merge($composerBinary, ['update', 'ofyre/seo-engine', '--no-interaction']);
        $process = new Process($command, base_path());
        $process->setTimeout(600);

        $this->components->info('Synchronisation du package local en cours...');
        $process->run(function (string $type, string $buffer): void {
            $this->output->write($buffer);
        });

        if (! $process->isSuccessful()) {
            $this->components->error('La synchronisation du package local a échoué.');

            return self::FAILURE;
        }

        $this->components->info('Package local synchronisé. Vérifiez maintenant le statut avec `php artisan seo:package-status`.');

        return self::SUCCESS;
    }
}
