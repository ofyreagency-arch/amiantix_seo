<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SeoPage;
use App\Models\SeoSuggestion;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ClientWorkspaceController extends Controller
{
    public function optimizations(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $siteIds = $user->seoSites()->pluck('seo_sites.site_id');

        $suggestions = SeoSuggestion::query()
            ->with(['page'])
            ->whereHas('page', fn ($query) => $query->whereIn('site_id', $siteIds))
            ->latest()
            ->limit(24)
            ->get();

        return response()->json([
            'stats' => [
                'pending' => $suggestions->where('status', 'pending')->count(),
                'applied' => $suggestions->where('status', 'applied')->count(),
                'rejected' => $suggestions->where('status', 'rejected')->count(),
                'total' => $suggestions->count(),
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

        $pages = SeoPage::query()
            ->whereIn('site_id', $siteIds)
            ->where(function ($query): void {
                $query
                    ->whereNotNull('published_at')
                    ->orWhere('published_live', true)
                    ->orWhereNotNull('live_url');
            })
            ->orderByDesc('published_live_at')
            ->orderByDesc('published_at')
            ->limit(24)
            ->get();

        return response()->json([
            'stats' => [
                'engine_published' => $pages->filter(fn (SeoPage $page): bool => $page->isPublishedInEngine())->count(),
                'live_published' => $pages->filter(fn (SeoPage $page): bool => $page->isPublishedLive())->count(),
                'with_live_url' => $pages->filter(fn (SeoPage $page): bool => ! empty($page->live_url))->count(),
            ],
            'items' => $pages->map(fn (SeoPage $page): array => [
                'id' => $page->id,
                'site_id' => $page->site_id,
                'title' => (string) ($page->title ?: $page->h1 ?: $page->slug ?: 'Page sans titre'),
                'slug' => (string) ($page->slug ?: ''),
                'status' => (string) $page->status,
                'published_at' => $page->published_at,
                'published_live' => (bool) $page->published_live,
                'published_live_at' => $page->published_live_at,
                'live_url' => $page->live_url,
                'seo_score' => $page->seo_score,
                'indexability_score' => $page->indexability_score,
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
}
