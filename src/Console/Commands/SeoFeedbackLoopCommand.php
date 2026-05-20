<?php

declare(strict_types=1);

namespace Ofyre\SeoEngine\Console\Commands;

use Illuminate\Console\Command;
use Ofyre\SeoEngine\Services\Console\SeoFeedbackLoopRunner;

final class SeoFeedbackLoopCommand extends Command
{
    protected $signature = 'seo:feedback-loop';

    protected $description = 'Create safe Search Console suggestions without changing live pages.';

    public function handle(SeoFeedbackLoopRunner $runner): int
    {
        $created = $runner->run();

        $this->info('SEO feedback loop finished: '.$created.' suggestions created.');

        return self::SUCCESS;
    }
}
