<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SeoPage;
use App\Models\SeoSearchConsoleMetric;
use App\Models\SeoSemanticLink;
use App\Models\SeoSite;
use App\Models\SeoSiteCrawl;
use App\Models\SeoSiteGoogleConnection;
use App\Models\SeoSitePage;
use App\Models\SeoSiteSitemap;
use App\Models\SeoSuggestion;
use App\Models\SeoVector;
use App\Services\Publication\SeoLivePublicationService;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
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
        $diskFreeHuman = $this->formatBytes($diskFree);

        $memoryUsageMb = (int) round(memory_get_usage(true) / 1048576);
        $memoryLimitMb = $this->toMegabytes((string) ini_get('memory_limit'));

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
        $runtimeModules = $this->buildRuntimeModules(
            $openaiKey,
            $openaiModel,
            $openaiPing,
            $queueDriver,
            $gscEnabled,
            $embeddingsEnabled,
            $schedulerEnabled,
            $schedulerCommands,
        );
        $runtimeSummary = [
            'ok' => collect($runtimeModules)->where('status', 'ok')->count(),
            'warning' => collect($runtimeModules)->where('status', 'warning')->count(),
            'critical' => collect($runtimeModules)->where('status', 'critical')->count(),
            'total' => count($runtimeModules),
        ];
        $gscRuntimeActive = $gscEnabled
            || SeoSiteGoogleConnection::query()->exists()
            || SeoSearchConsoleMetric::query()->exists();

        return view('admin.system', compact(
            'infrastructure',
            'dbOk', 'dbError',
            'cacheOk', 'cacheError',
            'queueDriver',
            'diskFree', 'diskFreeHuman', 'diskTotal', 'diskUsedPct',
            'memoryUsageMb', 'memoryLimitMb',
            'doctorResult',
            'openaiKey', 'openaiModel', 'openaiPing',
            'gscEnabled', 'gscRuntimeActive', 'embeddingsEnabled',
            'schedulerEnabled', 'schedulerCommands',
            'logErrors', 'logWarnings',
            'runtimeModules', 'runtimeSummary',
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

    /**
     * @param  array<int, array<string,mixed>|string>  $schedulerCommands
     * @param  array{ok:bool,ms:int|null,error:string|null}|null  $openaiPing
     * @return array<int,array<string,mixed>>
     */
    private function buildRuntimeModules(
        bool $openaiKey,
        string $openaiModel,
        ?array $openaiPing,
        string $queueDriver,
        bool $gscEnabled,
        bool $embeddingsEnabled,
        bool $schedulerEnabled,
        array $schedulerCommands,
    ): array {
        $siteCount = SeoSite::query()->count();
        $activeSiteCount = SeoSite::query()->where('is_active', true)->count();
        $connectedGscCount = SeoSiteGoogleConnection::query()
            ->whereIn('connection_status', ['configured', 'connected', 'connected_empty'])
            ->count();
        $emptyGscSyncCount = SeoSiteGoogleConnection::query()
            ->where('connection_status', 'connected_empty')
            ->count();
        $latestGscError = SeoSiteGoogleConnection::query()
            ->whereNotNull('last_error')
            ->latest('updated_at')
            ->first();
        $latestGscSyncAt = SeoSiteGoogleConnection::query()->max('last_sync_at');
        $gscMetricCount = SeoSearchConsoleMetric::query()->count();
        $latestGscMetricAt = SeoSearchConsoleMetric::query()->max('metric_date');

        $latestCrawl = SeoSiteCrawl::query()
            ->orderByDesc('completed_at')
            ->orderByDesc('created_at')
            ->first();
        $observedPageCount = SeoSitePage::query()->count();
        $indexableObservedCount = SeoSitePage::query()->where('indexability_state', 'indexable')->count();
        $latestObservedAt = SeoSitePage::query()->max('last_seen_at');

        $sitemapCount = SeoSiteSitemap::query()->count();
        $latestSitemapAt = SeoSiteSitemap::query()->max('discovered_at');

        $vectorCount = SeoVector::query()->count();
        $semanticLinkCount = SeoSemanticLink::query()->count();

        $pageCount = SeoPage::query()->count();
        $hasLivePublicationService = class_exists(SeoLivePublicationService::class);
        $hasLivePublicationColumns = Schema::hasColumns('seo_pages', ['published_live', 'published_live_at', 'live_url']);
        $publishedLiveSupported = $hasLivePublicationService && $hasLivePublicationColumns;
        $publishedLiveCount = $publishedLiveSupported
            ? SeoPage::query()->where('published_live', true)->count()
            : 0;

        $pendingSuggestionCount = SeoSuggestion::query()->where('status', 'pending')->count();
        $recentAutoSuggestionCount = SeoSuggestion::query()
            ->where('created_at', '>=', now()->subDays(7))
            ->where(function ($query): void {
                $query->where('source', 'like', 'rewrite_engine:%')
                    ->orWhere('source', 'like', 'feedback_loop:%')
                    ->orWhere('source', 'like', 'signal_queue:%');
            })
            ->count();

        $failedJobsCount = Schema::hasTable('failed_jobs')
            ? (int) DB::table('failed_jobs')->count()
            : 0;
        $latestFailedJobAt = Schema::hasTable('failed_jobs')
            ? DB::table('failed_jobs')->max('failed_at')
            : null;

        $hasSitemapRoute = Route::has('public.sitemap');
        $hasPublicPageRoute = Route::has('public.page');

        return [
            $this->module(
                'openai',
                'OpenAI',
                ! $openaiKey ? 'critical' : (($openaiPing['ok'] ?? false) ? 'ok' : 'critical'),
                ! $openaiKey
                    ? 'Clé API absente.'
                    : (($openaiPing['ok'] ?? false)
                        ? 'API joignable et prête pour génération/réécriture.'
                        : 'API non joignable ou en erreur.'),
                [
                    'Modèle' => $openaiModel,
                    'Latence' => $openaiPing['ms'] ?? '—',
                    'Dernière erreur' => $openaiPing['error'] ?? '—',
                ],
                $openaiPing['ms'] ?? null,
                $openaiPing['error'] ?? null,
            ),
            $this->module(
                'search_console',
                'Search Console',
                $connectedGscCount === 0
                    ? 'warning'
                    : ($latestGscError ? 'critical' : (($gscMetricCount > 0 && $emptyGscSyncCount === 0) ? 'ok' : 'warning')),
                $connectedGscCount === 0
                    ? 'Aucun site connecté à Google Search Console.'
                    : ($emptyGscSyncCount > 0
                        ? 'Connexion GSC active, mais au moins un site synchronise à vide.'
                        : ($gscMetricCount > 0
                        ? 'Des données Search Console alimentent bien le moteur.'
                        : 'Connexion présente, mais aucune donnée GSC reçue pour le moment.')),
                [
                    'Sites connectés' => $connectedGscCount.' / '.$activeSiteCount,
                    'Syncs vides' => $emptyGscSyncCount,
                    'Dernière synchro' => $latestGscSyncAt ?: '—',
                    'Métriques reçues' => $gscMetricCount,
                    'Dernière donnée' => $latestGscMetricAt ?: '—',
                    'Dernière erreur' => $latestGscError?->last_error ?: '—',
                ],
                $latestGscSyncAt ?: $latestGscMetricAt,
                $latestGscError?->last_error,
            ),
            $this->module(
                'monitoring',
                'Monitoring / Crawl',
                $latestCrawl === null
                    ? 'warning'
                    : (($latestCrawl->status ?? null) !== 'completed'
                        ? 'critical'
                        : ($this->isStaleDate($latestCrawl->completed_at, 2) || $observedPageCount === 0 ? 'warning' : 'ok')),
                $latestCrawl === null
                    ? 'Aucun crawl récent détecté.'
                    : (($latestCrawl->status ?? null) !== 'completed'
                        ? 'Le dernier crawl ne s est pas terminé correctement.'
                        : 'Le monitoring observed alimente bien la couche runtime.'),
                [
                    'Pages observées' => $observedPageCount,
                    'Dernier crawl' => $latestCrawl?->completed_at?->toDateTimeString() ?: $latestCrawl?->started_at?->toDateTimeString() ?: '—',
                    'État crawl' => $latestCrawl?->status ?: '—',
                    'Dernière page vue' => $latestObservedAt ?: '—',
                ],
                $latestCrawl?->completed_at?->toDateTimeString() ?: $latestCrawl?->started_at?->toDateTimeString(),
                ($latestCrawl !== null && ($latestCrawl->status ?? null) !== 'completed') ? 'Crawl incomplet ou bloqué.' : null,
            ),
            $this->module(
                'sitemap',
                'Sitemap',
                ! $hasSitemapRoute
                    ? 'critical'
                    : ($sitemapCount > 0 ? 'ok' : ($siteCount > 0 ? 'warning' : 'warning')),
                ! $hasSitemapRoute
                    ? 'Route sitemap absente.'
                    : ($sitemapCount > 0
                        ? 'Des URLs sitemap sont bien détectées côté runtime.'
                        : 'Aucun sitemap détecté pour le moment.'),
                [
                    'Route sitemap' => $hasSitemapRoute ? 'oui' : 'non',
                    'Sitemaps détectés' => $sitemapCount,
                    'Dernière découverte' => $latestSitemapAt ?: '—',
                    'Pages publiées' => $pageCount,
                ],
                $latestSitemapAt,
                ! $hasSitemapRoute ? 'Fonction sitemap absente.' : null,
            ),
            $this->module(
                'indexation',
                'Indexation',
                $pageCount === 0
                    ? 'warning'
                    : (($indexableObservedCount > 0 || $gscMetricCount > 0) ? 'ok' : 'warning'),
                $pageCount === 0
                    ? 'Aucune page moteur à suivre pour l indexation.'
                    : (($indexableObservedCount > 0 || $gscMetricCount > 0)
                        ? 'Le runtime voit des signaux d indexabilité et/ou des données Google.'
                        : 'Pas encore de signal concret d indexation.'),
                [
                    'Pages moteur' => $pageCount,
                    'Pages observed indexables' => $indexableObservedCount,
                    'Données Google' => $gscMetricCount,
                    'Route publique' => $hasPublicPageRoute ? 'oui' : 'non',
                ],
                $latestObservedAt ?: $latestGscMetricAt,
                null,
            ),
            $this->module(
                'queue',
                'Queue',
                $queueDriver === 'sync'
                    ? 'warning'
                    : ($failedJobsCount > 0 ? 'critical' : 'ok'),
                $queueDriver === 'sync'
                    ? 'Les jobs tournent en ligne, pas en arrière-plan.'
                    : ($failedJobsCount > 0
                        ? 'Des jobs ont échoué récemment.'
                        : 'La queue est configurée pour traiter le moteur en arrière-plan.'),
                [
                    'Driver' => $queueDriver,
                    'Jobs en échec' => $failedJobsCount,
                    'Dernier échec' => $latestFailedJobAt ?: '—',
                ],
                $latestFailedJobAt,
                $failedJobsCount > 0 ? 'Des jobs sont présents dans failed_jobs.' : null,
            ),
            $this->module(
                'scheduler',
                'Cron / Scheduler',
                ! $schedulerEnabled
                    ? 'critical'
                    : (empty($schedulerCommands) ? 'warning' : 'ok'),
                ! $schedulerEnabled
                    ? 'Le scheduler SEO est désactivé.'
                    : (empty($schedulerCommands)
                        ? 'Aucune commande SEO n est planifiée.'
                        : 'Configuration présente. Le cron système doit simplement être confirmé côté VPS.'),
                [
                    'Activé' => $schedulerEnabled ? 'oui' : 'non',
                    'Commandes planifiées' => count($schedulerCommands),
                    'Vérification cron' => empty($schedulerCommands) ? 'non configurée' : 'à confirmer en SSH',
                ],
                null,
                ! $schedulerEnabled ? 'seo-engine.scheduler.enabled=false' : null,
            ),
            $this->module(
                'embeddings',
                'Embeddings',
                ! $embeddingsEnabled
                    ? 'warning'
                    : (($vectorCount > 0 || $semanticLinkCount > 0) ? 'ok' : 'warning'),
                ! $embeddingsEnabled
                    ? 'Les embeddings sont désactivés dans la configuration.'
                    : (($vectorCount > 0 || $semanticLinkCount > 0)
                        ? 'Les vecteurs et/ou liens sémantiques alimentent le moteur.'
                        : 'Embeddings activés, mais encore aucune donnée exploitable en base.'),
                [
                    'Activé' => $embeddingsEnabled ? 'oui' : 'non',
                    'Vecteurs' => $vectorCount,
                    'Liens sémantiques' => $semanticLinkCount,
                ],
                null,
                null,
            ),
            $this->module(
                'autopilot',
                'Autopilot',
                $pendingSuggestionCount > 0 || $recentAutoSuggestionCount > 0 ? 'ok' : 'warning',
                $pendingSuggestionCount > 0 || $recentAutoSuggestionCount > 0
                    ? 'Le moteur a de vraies suggestions actives ou récentes.'
                    : 'Fonction présente, mais aucune activité autopilot récente détectée.',
                [
                    'Suggestions pending' => $pendingSuggestionCount,
                    'Activité récente' => $recentAutoSuggestionCount,
                ],
                null,
                null,
            ),
            $this->module(
                'publication',
                'Publication live',
                ! $publishedLiveSupported
                    ? 'critical'
                    : ($publishedLiveCount > 0 ? 'ok' : 'warning'),
                ! $publishedLiveSupported
                    ? 'La publication live n est pas entièrement disponible dans ce runtime.'
                    : ($publishedLiveCount > 0
                        ? 'Des pages sont déjà publiées en live.'
                        : 'La publication existe, mais aucune page live n est encore publiée.'),
                [
                    'Service' => $hasLivePublicationService ? 'oui' : 'non',
                    'Colonnes live' => $hasLivePublicationColumns ? 'oui' : 'non',
                    'Pages live' => $publishedLiveCount,
                ],
                null,
                ! $publishedLiveSupported ? 'Service ou colonnes de publication absents.' : null,
            ),
            $this->module(
                'rollback',
                'Rollback',
                'warning',
                'Aucun rollback explicite n est branché pour le moment, mais cela ne bloque pas le moteur SEO.',
                [
                    'Disponibilité' => 'non branchée',
                    'Historique suggestion' => SeoSuggestion::query()->count(),
                ],
                null,
                null,
            ),
        ];
    }

    /**
     * @param  array<string,mixed>  $details
     * @return array<string,mixed>
     */
    private function module(
        string $key,
        string $label,
        string $status,
        string $summary,
        array $details,
        int|string|null $lastRun = null,
        ?string $lastError = null,
    ): array {
        return [
            'key' => $key,
            'label' => $label,
            'status' => $status,
            'summary' => $summary,
            'details' => $details,
            'last_run' => $lastRun,
            'last_error' => $lastError,
        ];
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = min((int) floor(log($bytes, 1024)), count($units) - 1);

        return round($bytes / (1024 ** $power), 1).' '.$units[$power];
    }

    private function toMegabytes(string $iniValue): int
    {
        $value = trim($iniValue);

        if ($value === '' || $value === '-1') {
            return -1;
        }

        $number = (int) $value;
        $suffix = strtolower(substr($value, -1));

        return match ($suffix) {
            'g' => $number * 1024,
            'k' => (int) round($number / 1024),
            'm' => $number,
            default => $number,
        };
    }

    private function isStaleDate(mixed $date, int $days): bool
    {
        if (! $date instanceof \Carbon\CarbonInterface) {
            return true;
        }

        return $date->lt(now()->subDays($days));
    }
}
