<?php

declare(strict_types=1);

namespace Ofyre\SeoEngine\Console\Commands;

use Illuminate\Console\Command;
use Ofyre\SeoEngine\Services\Console\SeoDoctorService;

final class SeoDoctorCommand extends Command
{
    protected $signature = 'seo:doctor';

    protected $description = 'Inspect the SEO engine installation, config and contract wiring.';

    public function handle(SeoDoctorService $doctor): int
    {
        $report = $doctor->inspect($this->laravel);

        $this->info('SEO Engine Doctor');
        $this->newLine();

        foreach ($report['checks'] as $check) {
            $prefix = match ($check['status']) {
                'ok' => '[OK]',
                'warning' => '[WARN]',
                default => '[ERR]',
            };

            $this->line(sprintf('%s %s - %s', $prefix, $check['label'], $check['details']));
        }

        if ($report['warnings'] !== []) {
            $this->newLine();
            $this->warn('Warnings');
            foreach ($report['warnings'] as $warning) {
                $this->line('- '.$warning);
            }
        }

        $this->newLine();
        if ($report['ok']) {
            $this->info('SEO engine doctor passed.');

            return self::SUCCESS;
        }

        $this->error('SEO engine doctor found blocking issues.');

        return self::FAILURE;
    }
}
