<?php

declare(strict_types=1);

namespace Ofyre\SeoEngine\Console\Commands;

use Illuminate\Console\Command;
use Ofyre\SeoEngine\Services\Console\SeoRecalculateScoresRunner;

final class SeoRecalculateScoresCommand extends Command
{
    protected $signature = 'seo:recalculate-scores {slug? : Recalculate one page only} {--audits : Store a fresh seo_audits row for each page}';

    protected $description = 'Recalculate SEO, quality, indexability and image scores for existing SEO pages.';

    public function handle(SeoRecalculateScoresRunner $runner): int
    {
        $slug = $this->argument('slug');
        $count = $runner->run(
            is_string($slug) && $slug !== '' ? $slug : null,
            (bool) $this->option('audits'),
            function (object $page): void {
                $this->line('Recalculated '.$page->slug);
            }
        );

        if ($count === 0) {
            $this->warn('No SEO page found.');

            return self::FAILURE;
        }

        $this->info('Recalculated '.$count.' SEO page(s).');

        return self::SUCCESS;
    }
}
