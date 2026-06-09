<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SeoPage;
use App\Models\SeoRecommendation;
use App\Models\SeoSearchConsoleMetric;
use App\Models\SeoSemanticLink;
use App\Models\SeoSite;
use App\Models\SeoSitePageSnapshot;
use App\Models\SeoSuggestion;
use App\Copilot\ActionApplyContextService;
use App\Copilot\BusinessCopilotModificationPlanner;
use App\Copilot\BusinessCopilotService;
use App\Runtime\GscOpportunityService;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ClientWorkspaceController extends Controller
{
    public function optimizations(
        Request $request,
        GscOpportunityService $gscOpportunities,
        BusinessCopilotService $businessCopilot,
        BusinessCopilotModificationPlanner $modificationPlanner,
    ): JsonResponse
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
        $recommendations = SeoRecommendation::query()
            ->whereIn('site_id', $siteIds)
            ->where('status', 'pending')
            ->orderBy('priority')
            ->orderByDesc('generated_at')
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
            ->values()
            ->map(fn (array $item): array => $this->enrichOpportunityWithBusinessCopy($item, $modificationPlanner, app(ActionApplyContextService::class)));

        $opportunitySummary = [
            'low_ctr' => (int) $opportunityPayloads->sum(fn (array $payload): int => (int) ($payload['summary']['low_ctr'] ?? 0)),
            'near_top_10' => (int) $opportunityPayloads->sum(fn (array $payload): int => (int) ($payload['summary']['near_top_10'] ?? 0)),
            'emerging_queries' => (int) $opportunityPayloads->sum(fn (array $payload): int => (int) ($payload['summary']['emerging_queries'] ?? 0)),
            'sustained_drop' => (int) $opportunityPayloads->sum(fn (array $payload): int => (int) ($payload['summary']['sustained_drop'] ?? 0)),
            'total' => $opportunityItems->count(),
            'ready' => $opportunityItems->where('action_state', 'ready')->count(),
            'high_priority' => $opportunityItems->where('priority_level', 'high')->count(),
        ];

        $businessCopilotPayload = $businessCopilot->build($opportunityItems, $recommendations);
        $siteNames = $sites->pluck('name', 'site_id');
        $businessCopilotPayload['daily_priority'] = collect($businessCopilotPayload['daily_priority'])
            ->map(function (array $action) use ($siteNames): array {
                if (($action['site_name'] ?? '') === '') {
                    $action['site_name'] = (string) ($siteNames[(string) ($action['site_id'] ?? '')] ?? '');
                }

                return $action;
            })
            ->all();
        if (is_array($businessCopilotPayload['top_action'] ?? null)) {
            $topSiteId = (string) ($businessCopilotPayload['top_action']['site_id'] ?? '');
            if (($businessCopilotPayload['top_action']['site_name'] ?? '') === '') {
                $businessCopilotPayload['top_action']['site_name'] = (string) ($siteNames[$topSiteId] ?? '');
            }
        }

        return response()->json([
            'business_copilot' => $businessCopilotPayload,
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
            'recommendations' => [
                'summary' => [
                    'total' => $recommendations->count(),
                    'high_priority' => $recommendations->filter(fn (SeoRecommendation $recommendation): bool => (int) $recommendation->priority <= 30)->count(),
                    'refresh' => $recommendations->where('type', 'refresh_page')->count(),
                    'internal_links' => $recommendations->where('type', 'add_internal_links')->count(),
                    'clusters' => $recommendations->filter(
                        fn (SeoRecommendation $recommendation): bool => in_array((string) $recommendation->type, ['create_page', 'expand_cluster'], true)
                    )->count(),
                ],
                'items' => $recommendations->map(fn (SeoRecommendation $recommendation): array => [
                    'id' => $recommendation->id,
                    'site_id' => (string) $recommendation->site_id,
                    'type' => (string) $recommendation->type,
                    'priority' => (int) $recommendation->priority,
                    'estimated_impact' => (string) $recommendation->estimated_impact,
                    'difficulty' => (string) $recommendation->difficulty,
                    'cluster' => $recommendation->cluster ? (string) $recommendation->cluster : null,
                    'title' => (string) $recommendation->title,
                    'reasoning' => (string) $recommendation->reasoning,
                    'suggested_action' => $recommendation->suggested_action ? (string) $recommendation->suggested_action : null,
                    'status' => (string) $recommendation->status,
                    'generated_at' => $recommendation->generated_at,
                ])->values()->all(),
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
            $query->whereIn('status', ['draft', 'review', 'published'])
                ->orWhereNotNull('published_at');

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
            ->with('observedPage')
            ->orderByRaw("case status when 'published' then 0 when 'review' then 1 when 'draft' then 2 else 3 end")
            ->orderByDesc('published_at')
            ->orderByDesc('updated_at')
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
        $observedPageIds = $pages->pluck('observed_site_page_id')->filter()->values();
        $observedSnapshots = SeoSitePageSnapshot::query()
            ->whereIn('site_page_id', $observedPageIds)
            ->orderByDesc('observed_at')
            ->orderByDesc('id')
            ->get()
            ->groupBy('site_page_id')
            ->map(fn ($rows) => $rows->take(2)->values());
        $semanticLinks = SeoSemanticLink::query()
            ->whereIn('site_id', $siteIds)
            ->where(function ($query) use ($observedPageIds): void {
                $query->whereIn('source_id', $observedPageIds)
                    ->orWhereIn('target_id', $observedPageIds);
            })
            ->whereIn('relation_type', ['observed_internal_link', 'observed_cannibalization', 'observed_query_match', 'observed_overlap'])
            ->get();

        return response()->json([
            'stats' => [
                'draft' => $pages->filter(fn (SeoPage $page): bool => (string) $page->status === 'draft')->count(),
                'review' => $pages->filter(fn (SeoPage $page): bool => (string) $page->status === 'review')->count(),
                'published' => $pages->filter(fn (SeoPage $page): bool => (string) $page->status === 'published')->count(),
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
                'live_verified' => $this->publicationLiveVerified($page),
                'published_live_at' => $hasPublishedLiveAtColumn ? $page->published_live_at : null,
                'live_url' => $hasLiveUrlColumn ? $page->live_url : null,
                'live_status' => $this->publicationLiveStatus($page),
                'preview_url' => $this->publicationPreviewUrl($page),
                'meta_description' => $page->meta_description ? (string) $page->meta_description : null,
                'excerpt' => $this->publicationExcerpt($page),
                'content_body' => $this->publicationBody($page),
                'content_word_count' => $this->publicationWordCount($page),
                'image_url' => $this->publicationImageUrl($page),
                'image_alt' => $page->image_alt ? (string) $page->image_alt : null,
                'image_status' => $page->image_status ? (string) $page->image_status : null,
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
                'observed_content' => $this->serializeObservedPublicationContext(
                    $page,
                    $observedSnapshots,
                    $semanticLinks
                ),
                'latest_suggestion' => $this->serializePublicationSuggestion($latestSuggestions->get($page->id)),
            ])->values(),
        ]);
    }

    public function destroyPublication(Request $request, int $pageId): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $siteIds = $user->seoSites()->pluck('seo_sites.site_id');

        /** @var SeoPage $page */
        $page = SeoPage::query()
            ->whereIn('site_id', $siteIds)
            ->findOrFail($pageId);

        $deleted = [
            'audits' => $page->audits()->count(),
            'suggestions' => $page->suggestions()->count(),
            'overrides' => $page->overrides()->count(),
            'search_console_metrics' => $page->searchConsoleMetrics()->count(),
        ];

        $page->audits()->delete();
        $page->suggestions()->delete();
        $page->overrides()->delete();
        $page->searchConsoleMetrics()->delete();
        $page->delete();

        return response()->json([
            'status' => 'ok',
            'deleted' => [
                'page_id' => $pageId,
                'site_id' => $page->site_id,
                'slug' => $page->slug,
                'title' => $page->title,
                'related' => $deleted,
            ],
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

    private function publicationPreviewUrl(SeoPage $page): string
    {
        return '/publications?focus=content&site='.urlencode((string) $page->site_id)
            .'&slug='.urlencode((string) $page->slug)
            .'&action=preview#apercu-blog';
    }

    private function publicationExcerpt(SeoPage $page): string
    {
        $fallback = $page->meta_description ?: $page->content ?: $page->title ?: $page->h1 ?: $page->keyword;

        return (string) Str::of((string) $fallback)
            ->replaceMatches('/[#>*_`-]+/u', ' ')
            ->replaceMatches('/\s+/u', ' ')
            ->trim()
            ->limit(220);
    }

    private function publicationBody(SeoPage $page): string
    {
        return trim((string) ($page->content ?? ''));
    }

    private function publicationWordCount(SeoPage $page): int
    {
        return str_word_count(
            (string) Str::of((string) ($page->content ?? ''))
                ->replaceMatches('/<[^>]+>/u', ' ')
                ->replaceMatches('/\s+/u', ' ')
                ->trim()
        );
    }

    private function publicationImageUrl(SeoPage $page): ?string
    {
        $imagePath = trim((string) ($page->image_path ?? ''));

        if ($imagePath === '') {
            return null;
        }

        if (Str::startsWith($imagePath, ['http://', 'https://', '/'])) {
            return $imagePath;
        }

        return asset('storage/'.$imagePath);
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

    /**
     * @param  \Illuminate\Support\Collection<int, \Illuminate\Support\Collection<int, SeoSitePageSnapshot>>  $snapshots
     * @param  \Illuminate\Support\Collection<int, SeoSemanticLink>  $semanticLinks
     * @return array<string,mixed>|null
     */
    private function serializeObservedPublicationContext(
        SeoPage $page,
        \Illuminate\Support\Collection $snapshots,
        \Illuminate\Support\Collection $semanticLinks,
    ): ?array {
        $observedPage = $page->observedPage;

        if (! $observedPage) {
            return null;
        }

        /** @var \Illuminate\Support\Collection<int, SeoSitePageSnapshot> $snapshotSeries */
        $snapshotSeries = $snapshots->get($observedPage->id, collect());
        $snapshot = $snapshotSeries->first();
        $previousSnapshot = $snapshotSeries->skip(1)->first();
        $currentWordCount = $snapshot ? (int) $snapshot->word_count : (int) $observedPage->latest_word_count;
        $previousWordCount = $previousSnapshot?->word_count !== null ? (int) $previousSnapshot->word_count : null;
        $wordDelta = $previousWordCount !== null ? $currentWordCount - $previousWordCount : 0;
        $pageLinks = $semanticLinks->filter(function (SeoSemanticLink $link) use ($observedPage): bool {
            return (int) $link->source_id === (int) $observedPage->id
                || (int) $link->target_id === (int) $observedPage->id;
        });
        $internalLinkSuggestions = $pageLinks
            ->where('relation_type', 'observed_internal_link')
            ->sortByDesc('similarity_score')
            ->values();
        $cannibalizationLinks = $pageLinks
            ->where('relation_type', 'observed_cannibalization')
            ->sortByDesc('similarity_score')
            ->values();
        $queryMatches = $pageLinks
            ->where('relation_type', 'observed_query_match')
            ->sortByDesc('similarity_score')
            ->values();
        $overlaps = $pageLinks
            ->where('relation_type', 'observed_overlap')
            ->sortByDesc('similarity_score')
            ->values();

        return [
            'authority_score' => (int) round((float) $observedPage->authority_score * 100),
            'orphan_score' => (int) round((float) $observedPage->orphan_score * 100),
            'overlap_score' => (int) round((float) $observedPage->overlap_score * 100),
            'pillar_likelihood' => (int) round((float) $observedPage->pillar_likelihood * 100),
            'cluster_label' => $observedPage->cluster_label ? (string) $observedPage->cluster_label : null,
            'indexability_state' => (string) $observedPage->indexability_state,
            'observed_http_status' => $observedPage->last_status_code !== null ? (int) $observedPage->last_status_code : null,
            'internal_inlinks' => (int) $observedPage->internal_inlinks,
            'internal_outlinks' => (int) $observedPage->internal_outlinks,
            'snapshot_word_count' => $currentWordCount,
            'snapshot_observed_at' => $snapshot?->observed_at,
            'snapshot_title' => $snapshot?->title ?: $observedPage->title,
            'snapshot_previous_word_count' => $previousWordCount,
            'snapshot_previous_observed_at' => $previousSnapshot?->observed_at,
            'snapshot_word_delta' => $wordDelta,
            'internal_link_suggestions_count' => $internalLinkSuggestions->count(),
            'cannibalization_count' => $cannibalizationLinks->count(),
            'query_match_count' => $queryMatches->count(),
            'overlap_count' => $overlaps->count(),
            'top_internal_link_target' => $internalLinkSuggestions->first()?->label,
            'top_cannibalization_target' => $cannibalizationLinks->first()?->label,
            'top_query_match' => $queryMatches->first()?->label,
        ];
    }

    private function publicationLiveVerified(SeoPage $page): bool
    {
        if (! $page->isPublishedLive() || blank($page->live_url)) {
            return false;
        }

        $observedPage = $page->observedPage;

        if (! $observedPage) {
            return false;
        }

        $statusCode = (int) ($observedPage->last_status_code ?? 0);

        return $statusCode > 0 && $statusCode < 400;
    }

    /**
     * @return array<string, mixed>
     */
    private function publicationLiveStatus(SeoPage $page): array
    {
        $liveVerified = $this->publicationLiveVerified($page);
        $observedPage = $page->observedPage;
        $statusCode = $observedPage?->last_status_code !== null ? (int) $observedPage->last_status_code : null;
        $liveUrl = blank($page->live_url) ? null : (string) $page->live_url;

        if ($liveVerified) {
            return [
                'state' => 'visible',
                'label' => 'Visible sur le site',
                'detail' => 'PraeviSEO a trouvé l’URL live et la dernière lecture observée répond bien.',
                'source' => 'seo_pages.published_live + seo_site_pages.last_status_code',
                'http_status' => $statusCode,
                'link' => $liveUrl,
            ];
        }

        if ($page->isPublishedLive() && $liveUrl !== null) {
            return [
                'state' => 'to_verify',
                'label' => 'Publication à vérifier',
                'detail' => 'Une URL live est enregistrée, mais la dernière lecture observée ne confirme pas encore une réponse saine.',
                'source' => 'seo_pages.live_url + seo_site_pages.last_status_code',
                'http_status' => $statusCode,
                'link' => $liveUrl,
            ];
        }

        return [
            'state' => 'draft_only',
            'label' => 'Encore dans le moteur',
            'detail' => 'Le contenu existe dans seo_pages, mais aucune URL live vérifiée n’est encore confirmée.',
            'source' => 'seo_pages.status',
            'http_status' => $statusCode,
            'link' => $liveUrl,
        ];
    }

    /**
     * @param  array<string,mixed>  $item
     * @return array<string,mixed>
     */
    private function enrichOpportunityWithBusinessCopy(
        array $item,
        BusinessCopilotModificationPlanner $modificationPlanner,
        ActionApplyContextService $applyContext,
    ): array {
        $query = isset($item['query']) ? trim((string) $item['query']) : '';
        $label = trim((string) ($item['label'] ?? ''));
        $subject = $query !== '' ? $query : $label;
        $metrics = is_array($item['metrics'] ?? null) ? $item['metrics'] : [];
        $position = (float) ($metrics['position'] ?? 0);
        $siteId = (string) ($item['site_id'] ?? '');
        $slug = (string) ($item['slug'] ?? '');
        $pageId = $item['page_id'] ?? null;
        $workflow = match ((string) ($item['type'] ?? '')) {
            'emerging_query' => 'generate',
            default => 'rewrite',
        };
        $signalReady = ($item['action_state'] ?? '') === 'ready' && ! ($item['pending_suggestion'] ?? false);

        $plan = $modificationPlanner->planForGsc(
            $siteId,
            (string) ($item['type'] ?? ''),
            $subject,
            $label,
            is_numeric($pageId) ? (int) $pageId : null,
            $slug,
            $query !== '' ? $query : null,
            null,
        );

        $modificationPlan = [
            'content_summary' => (string) ($plan['content_summary'] ?? ''),
            'sections' => array_values(array_slice((array) ($plan['sections'] ?? []), 0, 2)),
            'faq' => array_values(array_slice((array) ($plan['faq'] ?? []), 0, 2)),
            'topics' => array_values(array_slice((array) ($plan['topics'] ?? []), 0, 2)),
            'title_change' => $plan['title_change'] ?? null,
        ];

        $item['reason'] = $this->businessOpportunityReason((string) ($item['type'] ?? ''), $label, $position, $plan);
        $item['action'] = (string) ($plan['action_label'] ?: ($item['action'] ?? ''));
        $item['modification_preview'] = [
            'content_summary' => $modificationPlan['content_summary'],
            'sections' => $modificationPlan['sections'],
            'faq' => $modificationPlan['faq'],
        ];
        $item['apply_context'] = $applyContext->resolve(
            $siteId,
            $slug,
            $pageId,
            $workflow,
            $signalReady && $applyContext->canAutoApply($workflow, $siteId, $pageId, $slug),
            $subject,
            $label,
            trim((string) ($item['site_url'] ?? '')) !== '' ? (string) $item['site_url'] : null,
            $modificationPlan,
        );

        return $item;
    }

    /**
     * @param  array<string,mixed>  $plan
     */
    private function businessOpportunityReason(string $type, string $label, float $position, array $plan): string
    {
        $summary = trim((string) ($plan['content_summary'] ?? ''));
        if ($summary !== '' && ! str_contains($summary, 'PraeviSEO précisera')) {
            return $summary;
        }

        return match ($type) {
            'near_top_10' => $position > 0
                ? sprintf('« %s » apparaît autour de la %.0fe position : PraeviSEO peut compléter ce qui manque pour gagner des visiteurs.', $label, $position)
                : sprintf('« %s » peut gagner des visiteurs avec un renfort ciblé.', $label),
            'low_ctr' => sprintf('« %s » est visible dans Google, mais attire encore trop peu de clics.', $label),
            'emerging_query' => sprintf('Google associe déjà votre site à une recherche autour de « %s ».', $label),
            'sustained_drop' => sprintf('« %s » perd de la visibilité depuis plusieurs semaines.', $label),
            default => sprintf('PraeviSEO voit un levier utile sur « %s ».', $label),
        };
    }
}
