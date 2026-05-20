<?php

declare(strict_types=1);

namespace Ofyre\SeoEngine\Console\Commands;

use Illuminate\Console\Command;
use Ofyre\SeoEngine\Services\Console\SeoDetectCannibalizationRunner;

final class SeoDetectCannibalizationCommand extends Command
{
    protected $signature = 'seo:detect-cannibalization {slug? : Analyze one page only} {--limit=100 : Maximum number of pages to analyze}';

    protected $description = 'Detect semantic cannibalization risks between published SEO pages.';

    public function handle(SeoDetectCannibalizationRunner $runner): int
    {
        if (! config('seo-engine.embeddings.enabled', false)) {
            $this->warn('SEO embeddings are disabled. Set SEO_EMBEDDINGS_ENABLED=true to run this command.');

            return self::SUCCESS;
        }

        $summary = $runner->run(
            $this->argument('slug') ? (string) $this->argument('slug') : null,
            (int) $this->option('limit'),
        );

        $this->info(sprintf(
            'Detected cannibalization risks for %d page(s) with %d risk(s).',
            $summary['pages'],
            $summary['risks'],
        ));

        return self::SUCCESS;
    }
}
