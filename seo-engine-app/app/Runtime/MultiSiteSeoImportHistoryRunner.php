<?php

declare(strict_types=1);

namespace App\Runtime;

use App\Models\SeoSite;
use App\Models\SeoSiteGoogleConnection;
use Illuminate\Support\Str;
use Ofyre\SeoEngine\Contracts\HistoricalSeoImporter;
use Ofyre\SeoEngine\Services\Console\SeoImportHistoryRunner;

class MultiSiteSeoImportHistoryRunner extends SeoImportHistoryRunner
{
    public function __construct(
        HistoricalSeoImporter $history,
        private readonly SeoEngineContext $context,
    ) {
        parent::__construct($history);
        $this->history = $history;
    }

    private readonly HistoricalSeoImporter $history;

    /**
     * @return array{windows:array<int,int>,summary:array{windows:int,pages:int,queries:int}}
     */
    public function run(string $windowsOption, int $limit): array
    {
        $windows = collect(explode(',', $windowsOption))
            ->map(fn (string $window): int => max(7, (int) trim($window)))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $sites = SeoSite::query()
            ->active()
            ->with('googleConnection')
            ->get()
            ->filter(fn (SeoSite $site): bool => $site->hasSearchConsoleConfigured())
            ->values();

        if ($sites->isEmpty()) {
            return [
                'windows' => $windows,
                'summary' => $this->history->import($windows, $limit),
            ];
        }

        $totals = [
            'windows' => count($windows),
            'pages' => 0,
            'queries' => 0,
        ];

        foreach ($sites as $site) {
            $this->context->loadFromSite($site);

            try {
                $summary = $this->history->import($windows, $limit);

                $totals['pages'] += (int) ($summary['pages'] ?? 0);
                $totals['queries'] += (int) ($summary['queries'] ?? 0);

                $this->updateConnectionStatus($site, [
                    'connection_status' => 'connected',
                    'last_sync_at' => now(),
                    'last_validated_at' => now(),
                    'last_error' => null,
                ]);
            } catch (\Throwable $e) {
                $this->updateConnectionStatus($site, [
                    'connection_status' => 'error',
                    'last_error' => Str::limit($e->getMessage(), 500),
                ]);
            }
        }

        return [
            'windows' => $windows,
            'summary' => $totals,
        ];
    }

    /**
     * @param  array<string,mixed>  $attributes
     */
    private function updateConnectionStatus(SeoSite $site, array $attributes): void
    {
        $connection = $site->resolvedGoogleConnection();

        if ($connection instanceof SeoSiteGoogleConnection) {
            $connection->forceFill($attributes)->save();

            return;
        }

        SeoSiteGoogleConnection::query()->updateOrCreate(
            ['site_id' => $site->site_id],
            array_merge([
                'connection_mode' => $site->resolvedGscConnectionMode() ?: 'service_account',
                'property_url' => $site->resolvedGscSiteUrl(),
                'credentials_path' => $site->resolvedGscCredentialsPath(),
            ], $attributes)
        );
    }
}
