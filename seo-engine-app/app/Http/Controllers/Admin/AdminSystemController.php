<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Ofyre\SeoEngine\Services\Console\SeoDoctorService;

class AdminSystemController extends Controller
{
    public function show(Application $app, SeoDoctorService $doctor): View
    {
        $infrastructure = [
            'php_version'    => PHP_VERSION,
            'laravel_version' => $app->version(),
            'environment'    => $app->environment(),
            'debug_mode'     => (bool) config('app.debug'),
            'timezone'       => (string) config('app.timezone', 'UTC'),
        ];

        [$dbOk, $dbError] = $this->checkDatabase();
        [$cacheOk, $cacheError] = $this->checkCache();

        $queueDriver = (string) config('queue.default', 'sync');

        $storagePath = storage_path();
        $diskFree    = (int) (disk_free_space($storagePath) ?: 0);
        $diskTotal   = (int) (disk_total_space($storagePath) ?: 0);
        $diskUsedPct = $diskTotal > 0 ? (int) round((1 - $diskFree / $diskTotal) * 100) : 0;

        $memoryUsageMb = (int) round(memory_get_usage(true) / 1048576);
        $memoryLimitMb = (int) ini_get('memory_limit');

        $doctorResult = $doctor->inspect($app);

        $openaiKey        = (bool) config('services.openai.api_key');
        $openaiModel      = (string) config('services.openai.model', 'gpt-4o-mini');
        $gscEnabled       = (bool) config('seo-engine.search_console.enabled', false);
        $embeddingsEnabled = (bool) config('seo-engine.embeddings.enabled', false);

        $schedulerEnabled  = (bool) config('seo-engine.scheduler.enabled', true);
        $schedulerCommands = (array) config('seo-engine.scheduler.commands', []);

        $logErrors   = $this->readRecentLogErrors(50);
        $logWarnings = $this->readRecentLogLines('WARNING', 20);

        $openaiPing = $openaiKey ? $this->pingOpenAi() : null;

        return view('admin.system', compact(
            'infrastructure',
            'dbOk', 'dbError',
            'cacheOk', 'cacheError',
            'queueDriver',
            'diskFree', 'diskTotal', 'diskUsedPct',
            'memoryUsageMb', 'memoryLimitMb',
            'doctorResult',
            'openaiKey', 'openaiModel', 'openaiPing',
            'gscEnabled', 'embeddingsEnabled',
            'schedulerEnabled', 'schedulerCommands',
            'logErrors', 'logWarnings',
        ));
    }

    /** @return array{0:bool,1:string|null} */
    private function checkDatabase(): array
    {
        try {
            DB::connection()->getPdo();

            return [true, null];
        } catch (\Throwable $e) {
            return [false, Str::limit($e->getMessage(), 200)];
        }
    }

    /** @return array{0:bool,1:string|null} */
    private function checkCache(): array
    {
        try {
            Cache::put('_seo_system_ping', true, 10);
            $ok = Cache::get('_seo_system_ping') === true;
            Cache::forget('_seo_system_ping');

            return [$ok, $ok ? null : 'Cache put/get mismatch.'];
        } catch (\Throwable $e) {
            return [false, Str::limit($e->getMessage(), 200)];
        }
    }

    /** @return array{ok:bool,ms:int|null,error:string|null}|null */
    private function pingOpenAi(): ?array
    {
        $key = config('services.openai.api_key');
        if (! $key) {
            return null;
        }

        $start = microtime(true);

        try {
            $response = Http::withToken((string) $key)
                ->timeout(8)
                ->get('https://api.openai.com/v1/models');

            $ms = (int) round((microtime(true) - $start) * 1000);

            if ($response->status() === 401) {
                return ['ok' => false, 'ms' => $ms, 'error' => 'Invalid API key (401).'];
            }

            return ['ok' => $response->successful(), 'ms' => $ms, 'error' => $response->successful() ? null : 'HTTP '.$response->status()];
        } catch (\Throwable $e) {
            $ms = (int) round((microtime(true) - $start) * 1000);

            return ['ok' => false, 'ms' => $ms, 'error' => Str::limit($e->getMessage(), 120)];
        }
    }

    /** @return array<int,string> */
    private function readRecentLogErrors(int $limit): array
    {
        return $this->readRecentLogLines('ERROR', $limit);
    }

    /** @return array<int,string> */
    private function readRecentLogLines(string $level, int $limit): array
    {
        $logPath = storage_path('logs/laravel.log');

        if (! file_exists($logPath) || ! is_readable($logPath)) {
            return [];
        }

        $lines = @file($logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if (! is_array($lines) || $lines === []) {
            return [];
        }

        return collect(array_slice($lines, -1000))
            ->filter(fn (string $line): bool => str_contains($line, '.'.$level))
            ->values()
            ->take($limit)
            ->map(fn (string $line): string => Str::limit(trim($line), 280))
            ->all();
    }
}
