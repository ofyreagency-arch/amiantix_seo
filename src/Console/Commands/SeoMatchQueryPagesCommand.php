<?php

declare(strict_types=1);

namespace Ofyre\SeoEngine\Console\Commands;

use Illuminate\Console\Command;
use Ofyre\SeoEngine\Services\Console\SeoMatchQueryPagesRunner;

final class SeoMatchQueryPagesCommand extends Command
{
    protected $signature = 'seo:match-query-pages {slug? : Analyze one page only} {--window=28 : Search Console window in days} {--limit=250 : Maximum number of query rows to analyze} {--force : Regenerate query embeddings even if the content hash did not change}';

    protected $description = 'Match Search Console queries to SEO pages using semantic embeddings.';

    public function handle(SeoMatchQueryPagesRunner $runner): int
    {
        if (! config('seo-engine.embeddings.enabled', false)) {
            $this->warn('SEO embeddings are disabled. Set SEO_EMBEDDINGS_ENABLED=true to run this command.');

            return self::SUCCESS;
        }

        $summary = $runner->run(
            $this->argument('slug') ? (string) $this->argument('slug') : null,
            (int) $this->option('window'),
            (int) $this->option('limit'),
            (bool) $this->option('force'),
        );

        $this->info(sprintf(
            'Matched %d query opportunity(ies) across %d query metric(s); embedded %d, skipped %d.',
            $summary['opportunities'],
            $summary['queries'],
            $summary['embedded'],
            $summary['skipped'],
        ));

        return self::SUCCESS;
    }
}
