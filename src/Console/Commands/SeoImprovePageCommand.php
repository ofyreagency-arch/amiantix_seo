<?php

declare(strict_types=1);

namespace Ofyre\SeoEngine\Console\Commands;

use Illuminate\Console\Command;
use Ofyre\SeoEngine\Services\Console\SeoImprovePageRunner;

final class SeoImprovePageCommand extends Command
{
    protected $signature = 'seo:improve-page {slug}';

    protected $description = 'Rewrite and enrich one SEO page.';

    public function handle(SeoImprovePageRunner $runner): int
    {
        $result = $runner->run((string) $this->argument('slug'));

        if (! $result) {
            $this->error('SEO page not found.');

            return self::FAILURE;
        }

        $page = $result['page'];
        $status = $result['status'];

        $this->info('Improved '.$page->slug);
        $this->line('SEO score : '.$page->seo_score);
        $this->line('Indexability : '.$page->indexability_score);
        $this->line('Topical score : '.$page->topical_score);
        $this->line('Quality score : '.$page->quality_score);
        $this->line('FAQ count : '.$status['scores']['faq_count']);

        if ($status['blocking_reasons'] !== []) {
            $this->warn('Blocking reasons :');
            foreach ($status['blocking_reasons'] as $reason) {
                $this->line('* '.$reason);
            }
        }

        return self::SUCCESS;
    }
}
