<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SeoPage;
use App\Models\SeoSearchConsoleMetric;
use App\Models\SeoSite;
use App\Models\SeoSuggestion;
use App\Runtime\GscOpportunityService;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class ClientWorkspaceController extends Controller
{
    public function optimizations(Request $request, GscOpportunityService $gscOpportunities): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $sites = $user->seoSites()
            ->with('googleConnection')
            ->select([
                'seo_sites.id',
                'seo_sites.site_id',
                'seo_sites.name',
                'seo_sites.url',
                'seo_sites.gsc_site_url',
                'seo_sites.gsc_credentials_path',
            ])
            ->get();
        $siteIds = $sites->pluck('site_id');

        $suggestions = SeoSuggestion::query()
            ->with(['page'])
            ->whereHas('page', fn ($query) => $query->whereIn('site_id', $siteIds))
            ->latest()
            ->limit(24)
            ->get();

        $opportunityPayloads = $sites
            ->map(function (SeoSite $site) use ($gscOpportunities): array {
                $payload = $gscOpportunities->summarize($site->site_id, $site->hasSearchConsoleConfigured());

                return [
                    'site' => $site,
                    'summary' => is_array($payload['summary'] ?? null) ? $payload['summary'] : [],
                    'items' => collect($payload['items'] ?? []),
                ];
            });

        $opportunityItems = $opportunityPayloads
            ->flatMap(function (array $payload): array {
                /** @var SeoSite $site */
                $site = $payload['site'];

                return $payload['items']
                    ->map(function (array $item) use ($site): array {
                        return [
                            'site_id' => $site->site_id,
                            'site_name' => $site->name,
                            'site_url' => $site->url,
                            'type' => (string) ($item['type'] ?? ''),
                            'label' => (string) ($item['label'] ?? ''),
                            'slug' => (string) ($item['slug'] ?? ''),
                            'page_id' => $item['page_id'] ?? null,
                            'query' => isset($item['query']) ? (string) $item['query'] : null,
                            'reason' => (string) ($item['reason'] ?? ''),
                            'action' => (string) ($item['action'] ?? ''),
                            'priority_level' => (string) ($item['priority_level'] ?? 'watch'),
                            'priority_label' => (string) ($item['priority_label'] ?? 'A surveiller'),
                            'priority_score' => (int) ($item['priority_score'] ?? 0),
                            'action_state' => (string) ($item['action_state'] ?? 'ready'),
                            'action_state_label' => (string) ($item['action_state_label'] ?? 'Actionnable maintenant'),
                            'pending_suggestion' => (bool) ($item['pending_suggestion'] ?? false),
                            'metrics' => is_array($item['metrics'] ?? null) ? $item['metrics'] : [],
                        ];
                    })
                    ->all();
            })
            ->sortByDesc('priority_score')
            ->values();

        $opportunitySummary = [
            'low_ctr' => (int) $opportunityPayloads->sum(fn (array $payload): int => (int) ($payload['summary']['low_ctr'] ?? 0)),
            'near_top_10' => (int) $opportunityPayloads->sum(fn (array $payload): int => (int) ($payload['summary']['near_top_10'] ?? 0)),
            'emerging_queries' => (int) $opportunityPayloads->sum(fn (array $payload): int => (int) ($payload['summary']['emerging_queries'] ?? 0)),
            'sustained_drop' => (int) $opportunityPayloads->sum(fn (array $payload): int => (int) ($payload['summary']['sustained_drop'] ?? 0)),
            'total' => $opportunityItems->count(),
            'ready' => $opportunityItems->where('action_state', 'ready')->count(),
            'high_priority' => $opportunityItems->where('priority_level', 'high')->count(),
        ];

        return response()->json([
            'stats' => [
                'pending' => $suggestions->where('status', 'pending')->count(),
                'applied' => $suggestions->where('status', 'applied')->count(),
                'rejected' => $suggestions->where('status', 'rejected')->count(),
                'total' => $suggestions->count(),
            ],
            'gsc_opportunities' => [
                'summary' => $opportunitySummary,
                'items' => $opportunityItems->take(12)->all(),
            ],
            'items' => $suggestions->map(function (SeoSuggestion $suggestion): array {
                $page = $suggestion->page;
                $suggestionsJson = is_array($suggestion->suggestions_json) ? $suggestion->suggestions_json : [];
                $signalsJson = is_array($suggestion->signals_json) ? $suggestion->signals_json : [];

                return [
                    'id' => $suggestion->id,
                    'status' => (string) $suggestion->status,
                    'source' => (string) $suggestion->source,
                    'created_at' => $suggestion->created_at,
                    'page' => [
                        'id' => $page?->id,
                        'title' => (string) ($page?->title ?: $page?->h1 ?: $page?->slug ?: 'Page sans titre'),
                        'slug' => (string) ($page?->slug ?: ''),
                        'site_id' => (string) ($page?->site_id ?: ''),
                    ],
                    'summary' => (string) ($suggestionsJson['summary'] ?? $signalsJson['summary'] ?? $signalsJson['reason'] ?? 'Suggestion générée par le moteur.'),
                    'impact_expected' => (string) ($suggestionsJson['impact_expected'] ?? $signalsJson['impact_expected'] ?? 'Améliorer la page avant la prochaine publication.'),
                ];
            })->values(),
        ]);
    }

    public function publications(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $siteIds = $user->seoSites()->pluck('seo_sites.site_id');
        $hasPublishedLiveColumn = Schema::hasColumn('seo_pages', 'published_live');
        $hasPublishedLiveAtColumn = Schema::hasColumn('seo_pages', 'published_live_at');
        $hasLiveUrlColumn = Schema::hasColumn('seo_pages', 'live_url');

        $query = SeoPage::query()->whereIn('site_id', $siteIds);

        $query->where(function ($query) use ($hasPublishedLiveColumn, $hasLiveUrlColumn): void {
            $query->whereNotNull('published_at');

            if ($hasPublishedLiveColumn) {
                $query->orWhere('published_live', true);
            }

            if ($hasLiveUrlColumn) {
                $query->orWhereNotNull('live_url');
            }
        });

        if ($hasPublishedLiveAtColumn) {
            $query->orderByDesc('published_live_at');
        }

        $pages = $query
            ->orderByDesc('published_at')
            ->limit(24)
            ->get();
        $siteUrls = SeoSite::query()
            ->whereIn('site_id', $siteIds)
            ->pluck('url', 'site_id');
        $pageIds = $pages->pluck('id')->filter()->values();
        $latestSuggestions = SeoSuggestion::query()
            ->whereIn('seo_page_id', $pageIds)
            ->latest()
            ->get()
            ->groupBy('seo_page_id')
            ->map(fn ($rows) => $rows->first());
        $pageMetrics = SeoSearchConsoleMetric::query()
            ->whereIn('site_id', $siteIds)
            ->where('window_days', 28)
            ->whereNull('query')
            ->whereNotNull('url')
            ->orderByDesc('metric_date')
            ->orderByDesc('id')
            ->get()
            ->groupBy('site_id');

        return response()->json([
            'stats' => [
                'engine_published' => $pages->filter(fn (SeoPage $page): bool => $page->isPublishedInEngine())->count(),
                'live_published' => $hasPublishedLiveColumn
                    ? $pages->filter(fn (SeoPage $page): bool => $page->isPublishedLive())->count()
                    : 0,
                'with_live_url' => $hasLiveUrlColumn
                    ? $pages->filter(fn (SeoPage $page): bool => ! empty($page->live_url))->count()
                    : 0,
            ],
            'items' => $pages->map(fn (SeoPage $page): array => [
                'id' => $page->id,
                'site_id' => $page->site_id,
                'title' => (string) ($page->title ?: $page->h1 ?: $page->slug ?: 'Page sans titre'),
                'slug' => (string) ($page->slug ?: ''),
                'status' => (string) $page->status,
                'published_at' => $page->published_at,
                'published_live' => $hasPublishedLiveColumn ? (bool) $page->published_live : false,
                'published_live_at' => $hasPublishedLiveAtColumn ? $page->published_live_at : null,
                'live_url' => $hasLiveUrlColumn ? $page->live_url : null,
                'seo_score' => $page->seo_score,
                'indexability_score' => $page->indexability_score,
                'topical_score' => $page->topical_score,
                'quality_score' => $page->quality_score,
                'cluster' => $page->cluster,
                'gsc_metrics' => $this->publicationMetricsForPage(
                    $page,
                    (string) ($siteUrls[$page->site_id] ?? ''),
                    $pageMetrics
                ),
                'latest_suggestion' => $this->serializePublicationSuggestion($latestSuggestions->get($page->id)),
            ])->values(),
        ]);
    }

    public function settings(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $sites = $user->seoSites()
            ->with('googleConnection')
            ->orderBy('created_at')
            ->get();

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => (string) $user->name,
                'email' => (string) $user->email,
            ],
            'sites' => $sites->map(fn ($site): array => [
                'site_id' => $site->site_id,
                'name' => $site->name,
                'url' => $site->url,
                'publication_mode' => $site->resolvedPublicationMode(),
                'publication_mode_label' => $site->resolvedPublicationModeLabel(),
                'publication_path_prefix' => $site->publicationPathPrefix(),
                'publication_bridge_status' => $site->publicationBridgeStatus(),
                'gsc_connection_status' => $site->resolvedGscConnectionStatus(),
                'gsc_property_url' => $site->resolvedGscSiteUrl(),
                'gsc_account_email' => $site->resolvedGoogleConnection()?->google_account_email,
            ])->values(),
        ]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
        ]);

        $user->update($data);

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => (string) $user->name,
                'email' => (string) $user->email,
            ],
        ]);
    }

    /**
     * @param  \Illuminate\Support\Collection<string, \Illuminate\Support\Collection<int, \App\Models\SeoSearchConsoleMetric>>  $pageMetrics
     * @return array<string, int|float|null>
     */
    private function publicationMetricsForPage(
        SeoPage $page,
        string $siteUrl,
        \Illuminate\Support\Collection $pageMetrics,
    ): array {
        $siteRows = $pageMetrics->get($page->site_id, collect());

        if ($siteRows->isEmpty()) {
            return [
                'impressions' => 0,
                'clicks' => 0,
                'ctr' => 0.0,
                'position' => null,
            ];
        }

        $targetUrls = collect([
            $page->live_url,
            rtrim($siteUrl, '/').$page->canonicalPath(),
            rtrim($siteUrl, '/').$page->canonicalPath().'/',
        ])
            ->filter(fn ($value) => is_string($value) && trim($value) !== '')
            ->map(fn (string $value): string => rtrim($value, '/'))
            ->unique()
            ->values();

        $metric = $siteRows->first(function ($row) use ($targetUrls) {
            $rowUrl = rtrim((string) $row->url, '/');

            return $targetUrls->contains($rowUrl)
                && $this->metricHasAnalytics($row);
        })
            ?? $siteRows->first(function ($row) use ($targetUrls) {
                $rowUrl = rtrim((string) $row->url, '/');

                return $targetUrls->contains($rowUrl);
            });

        return [
            'impressions' => (int) round((float) ($metric?->impressions ?? 0)),
            'clicks' => (int) round((float) ($metric?->clicks ?? 0)),
            'ctr' => round(((float) ($metric?->ctr ?? 0.0)) * 100, 2),
            'position' => $metric ? round((float) $metric->position, 1) : null,
        ];
    }

    private function metricHasAnalytics(SeoSearchConsoleMetric $metric): bool
    {
        $payload = is_array($metric->payload_json) ? $metric->payload_json : [];

        if (is_array($payload['analytics'] ?? null) && $payload['analytics'] !== []) {
            return true;
        }

        return (float) $metric->impressions > 0.0
            || (float) $metric->clicks > 0.0
            || (float) $metric->position > 0.0;
    }

    /**
     * @return array<string,mixed>|null
     */
    private function serializePublicationSuggestion(?SeoSuggestion $suggestion): ?array
    {
        if (! $suggestion) {
            return null;
        }

        $suggestionsJson = is_array($suggestion->suggestions_json) ? $suggestion->suggestions_json : [];
        $signalsJson = is_array($suggestion->signals_json) ? $suggestion->signals_json : [];

        return [
            'id' => $suggestion->id,
            'status' => (string) $suggestion->status,
            'source' => (string) $suggestion->source,
            'summary' => (string) ($suggestionsJson['summary'] ?? $signalsJson['summary'] ?? $signalsJson['reason'] ?? 'Suggestion détectée par PraeviSEO.'),
            'impact_expected' => (string) ($suggestionsJson['impact_expected'] ?? $signalsJson['impact_expected'] ?? ''),
            'created_at' => $suggestion->created_at,
        ];
    }
}
