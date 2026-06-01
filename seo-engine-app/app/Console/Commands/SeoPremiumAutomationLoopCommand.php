<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\SeoSite;
use App\Runtime\PremiumAutomationLoopService;
use Illuminate\Console\Command;

class SeoPremiumAutomationLoopCommand extends Command
{
    protected $signature = 'seo:premium-automation-loop {--site_id=} {--limit=25}';

    protected $description = 'Relance la prochaine action premium utile sur les sites déjà connectés.';

    public function handle(PremiumAutomationLoopService $loop): int
    {
        $siteId = trim((string) $this->option('site_id'));
        $limit = max(1, (int) $this->option('limit'));

        $query = SeoSite::query()
            ->with(['googleConnection', 'latestObservedCrawl'])
            ->where('is_active', true);

        if ($siteId !== '') {
            $query->where('site_id', $siteId);
        }

        $sites = $query
            ->orderBy('id')
            ->limit($limit)
            ->get();

        if ($sites->isEmpty()) {
            $this->warn('Aucun site premium actif à traiter.');

            return self::SUCCESS;
        }

        $executed = 0;

        foreach ($sites as $site) {
            $result = $loop->runForSite($site);

            if ($result['executed']) {
                $executed++;
                $this->components->twoColumnDetail($site->site_id, sprintf('action=%s | %s', (string) ($result['action'] ?? 'none'), $result['reason']));
            } else {
                $this->line(sprintf('%s: %s', $site->site_id, $result['reason']));
            }
        }

        $this->newLine();
        $this->info(sprintf('Boucle premium traitée. actions_lancées=%d / sites=%d', $executed, $sites->count()));

        return self::SUCCESS;
    }
}
