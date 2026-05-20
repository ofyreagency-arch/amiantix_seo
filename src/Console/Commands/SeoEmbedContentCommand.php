<?php

declare(strict_types=1);

namespace Ofyre\SeoEngine\Console\Commands;

use Illuminate\Console\Command;
use Ofyre\SeoEngine\Services\Console\SeoEmbedContentRunner;

final class SeoEmbedContentCommand extends Command
{
    protected $signature = 'seo:embed-content {slug? : Embed one page only} {--limit=100 : Maximum number of pages to embed} {--force : Regenerate embeddings even if the content hash did not change}';

    protected $description = 'Generate and persist semantic embeddings for SEO content.';

    public function handle(SeoEmbedContentRunner $runner): int
    {
        if (! config('seo-engine.embeddings.enabled', false)) {
            $this->warn('SEO embeddings are disabled. Set SEO_EMBEDDINGS_ENABLED=true to run this command.');

            return self::SUCCESS;
        }

        $summary = $runner->run(
            $this->argument('slug') ? (string) $this->argument('slug') : null,
            (int) $this->option('limit'),
            (bool) $this->option('force'),
        );

        $this->info(sprintf(
            'Embedded %d page(s), skipped %d, scanned %d.',
            $summary['embedded'],
            $summary['skipped'],
            $summary['entities'],
        ));

        return self::SUCCESS;
    }
}
