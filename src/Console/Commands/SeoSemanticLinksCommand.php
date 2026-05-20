<?php

declare(strict_types=1);

namespace Ofyre\SeoEngine\Console\Commands;

use Illuminate\Console\Command;
use Ofyre\SeoEngine\Services\Console\SeoSemanticLinksRunner;

final class SeoSemanticLinksCommand extends Command
{
    protected $signature = 'seo:semantic-links {slug? : Refresh link suggestions for one page only} {--limit=100 : Maximum number of pages to analyze}';

    protected $description = 'Compute semantic internal-link suggestions from stored page embeddings.';

    public function handle(SeoSemanticLinksRunner $runner): int
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
            'Refreshed semantic link suggestions for %d page(s) with %d suggestion(s).',
            $summary['pages'],
            $summary['suggestions'],
        ));

        return self::SUCCESS;
    }
}
