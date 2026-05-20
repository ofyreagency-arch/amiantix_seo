<?php

declare(strict_types=1);

namespace Ofyre\SeoEngine\Console\Commands;

use Illuminate\Console\Command;
use Ofyre\SeoEngine\Services\Console\SeoSignalSuggestionQueueRunner;

final class SeoQueueSignalSuggestionsCommand extends Command
{
    protected $signature = 'seo:queue-signal-suggestions
        {slug? : Queue signal suggestions for one page only}
        {--limit=100 : Maximum number of pages to scan}';

    protected $description = 'Turn semantic and Search Console signals into pending SEO change suggestions.';

    public function handle(SeoSignalSuggestionQueueRunner $runner): int
    {
        $summary = $runner->run(
            $this->argument('slug') ? (string) $this->argument('slug') : null,
            (int) $this->option('limit'),
        );

        $this->info(sprintf(
            'Queued %d signal suggestion(s), cleared %d stale suggestion(s) across %d page(s).',
            $summary['queued'],
            $summary['cleared'],
            $summary['pages'],
        ));

        return self::SUCCESS;
    }
}
