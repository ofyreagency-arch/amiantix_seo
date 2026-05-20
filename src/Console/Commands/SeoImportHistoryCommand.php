<?php

declare(strict_types=1);

namespace Ofyre\SeoEngine\Console\Commands;

use Illuminate\Console\Command;
use Ofyre\SeoEngine\Services\Console\SeoImportHistoryRunner;

final class SeoImportHistoryCommand extends Command
{
    protected $signature = 'seo:import-history {--windows=7,28,90,180,365 : Comma-separated time windows in days} {--limit=250 : Max Search Console rows per window}';

    protected $description = 'Import historical Search Console signals and refresh site-wide SEO intelligence.';

    public function handle(SeoImportHistoryRunner $runner): int
    {
        $summary = $runner->run((string) $this->option('windows'), (int) $this->option('limit'))['summary'];

        $this->info(sprintf(
            'Historical SEO import finished: %d window(s), %d page snapshots, %d query snapshots.',
            $summary['windows'],
            $summary['pages'],
            $summary['queries'],
        ));

        return self::SUCCESS;
    }
}
