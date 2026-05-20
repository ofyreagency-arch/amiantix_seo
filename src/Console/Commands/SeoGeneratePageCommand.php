<?php

declare(strict_types=1);

namespace Ofyre\SeoEngine\Console\Commands;

use Illuminate\Console\Command;
use Ofyre\SeoEngine\Services\Console\SeoGeneratePageRunner;

final class SeoGeneratePageCommand extends Command
{
    protected $signature = 'seo:generate-page {keyword} {--publish : Publish the page immediately}';

    protected $description = 'Generate or update one programmatic SEO page.';

    public function handle(SeoGeneratePageRunner $runner): int
    {
        $publish = (bool) $this->option('publish');
        $result = $runner->run((string) $this->argument('keyword'), $publish ? 'published' : 'draft', $publish);
        $page = $result['page'];

        if (is_string($result['warning'])) {
            $this->warn($result['warning']);
        }

        $this->info('SEO page ready: /'.$page->slug.' (score '.$page->seo_score.'/100, '.$page->status.')');

        return self::SUCCESS;
    }
}
