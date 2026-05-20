<?php

declare(strict_types=1);

namespace Ofyre\SeoEngine\Console\Commands;

use Illuminate\Console\Command;
use Ofyre\SeoEngine\Contracts\SeoPageRepository;
use Ofyre\SeoEngine\Services\Console\SeoPageStatusPresenter;
use Ofyre\SeoEngine\Services\Review\SeoPageStatusService;

final class SeoPageStatusCommand extends Command
{
    protected $signature = 'seo:page-status {slug}';

    protected $description = 'Explain the current SEO review status and blocking reasons for one SEO page.';

    public function handle(
        SeoPageRepository $pages,
        SeoPageStatusService $statusService,
        SeoPageStatusPresenter $presenter
    ): int {
        $page = $pages->findBySlug((string) $this->argument('slug'));

        if (! $page) {
            $this->error('SEO page not found.');

            return self::FAILURE;
        }

        $rendered = $presenter->present($statusService->summarize($page));

        foreach (array_slice($rendered['summary'], 0, 2) as $line) {
            $this->line($line);
        }

        $this->newLine();
        foreach (array_slice($rendered['summary'], 2) as $line) {
            $this->line($line);
        }
        $this->newLine();

        if ($rendered['blocking'] === ['Blocking reasons : none']) {
            $this->info($rendered['blocking'][0]);
        } else {
            $this->warn($rendered['blocking'][0]);
            foreach (array_slice($rendered['blocking'], 1) as $line) {
                $this->line($line);
            }
        }

        if ($rendered['warnings'] !== []) {
            $this->newLine();
            $this->line('Warnings :');
            foreach ($rendered['warnings'] as $line) {
                $this->line($line);
            }
        }

        if ($rendered['recommendations'] !== []) {
            $this->newLine();
            $this->line('Recommendations :');
            foreach ($rendered['recommendations'] as $line) {
                $this->line($line);
            }
        }

        return self::SUCCESS;
    }
}
