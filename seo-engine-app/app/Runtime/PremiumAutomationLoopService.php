<?php

declare(strict_types=1);

namespace App\Runtime;

use App\ActionLayer\SeoSuggestionWorkflowService;
use App\Jobs\RunObservedSiteCrawlJob;
use App\Models\SeoPage;
use App\Models\SeoSite;
use App\Models\SeoSiteCrawl;
use App\Models\SeoSuggestion;
use App\Services\Media\SeoPageImageGenerator;
use App\Services\Publication\SeoLivePublicationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Ofyre\SeoEngine\Services\Rewrite\SeoRewriteService;
use Throwable;

class PremiumAutomationLoopService
{
    public function __construct(
        private readonly SeoRewriteService $rewrite,
        private readonly SeoSuggestionWorkflowService $workflow,
        private readonly SeoPageImageGenerator $images,
        private readonly SeoLivePublicationService $livePublication,
    ) {}

    /**
     * @return array{executed:bool,action:?string,reason:string}
     */
    public function runForSite(SeoSite $site): array
    {
        $site->loadMissing(['googleConnection', 'latestObservedCrawl']);
        $latestCrawl = $site->latestObservedCrawl;

        if ($site->publicationBridgeStatus() !== 'connected') {
            return ['executed' => false, 'action' => null, 'reason' => 'bridge_not_connected'];
        }

        if (! $this->gscConnected($site)) {
            return ['executed' => false, 'action' => null, 'reason' => 'gsc_not_connected'];
        }

        if (! $latestCrawl) {
            $this->scheduleObservedCrawlIfIdle($site, 'premium_loop_seed');

            return ['executed' => true, 'action' => 'crawl', 'reason' => 'seeded_first_crawl'];
        }

        if (in_array((string) $latestCrawl->status, ['pending', 'running'], true)) {
            return ['executed' => false, 'action' => null, 'reason' => 'crawl_in_progress'];
        }

        if ((string) $latestCrawl->status === 'failed') {
            return ['executed' => false, 'action' => null, 'reason' => 'crawl_failed'];
        }

        $stored = data_get($site->settings_json, 'automation.actions', []);
        $stored = is_array($stored) ? $stored : [];

        if ($this->shouldRunAction($stored['images'] ?? null, $latestCrawl)) {
            $images = $this->attemptImageGeneration($site);
            if ($images !== null) {
                return $images;
            }
        }

        if ($this->shouldRunAction($stored['publication'] ?? null, $latestCrawl)) {
            $publication = $this->attemptPublication($site);
            if ($publication !== null) {
                return $publication;
            }
        }

        if ($this->shouldRunAction($stored['linking'] ?? null, $latestCrawl)) {
            $linking = $this->attemptInternalLinking($site);
            if ($linking !== null) {
                return $linking;
            }
        }

        if ($this->shouldRunAction($stored['rewrite'] ?? null, $latestCrawl)) {
            $rewrite = $this->attemptRewrite($site);
            if ($rewrite !== null) {
                return $rewrite;
            }
        }

        return ['executed' => false, 'action' => null, 'reason' => 'no_actionable_step'];
    }

    private function gscConnected(SeoSite $site): bool
    {
        $status = (string) ($site->resolvedGoogleConnection()?->connection_status ?? '');

        return in_array($status, ['connected', 'configured', 'connected_empty'], true);
    }

    private function shouldRunAction(mixed $storedAction, SeoSiteCrawl $latestCrawl): bool
    {
        $payload = is_array($storedAction) ? $storedAction : [];
        $state = (string) ($payload['state'] ?? 'idle');

        if (in_array($state, ['pending', 'running'], true)) {
            return false;
        }

        $crawlCompletedAt = $latestCrawl->completed_at;
        if (! $crawlCompletedAt instanceof Carbon) {
            return $state === 'idle';
        }

        $updatedAt = isset($payload['updated_at']) && filled($payload['updated_at'])
            ? Carbon::parse((string) $payload['updated_at'])
            : null;

        if (! $updatedAt instanceof Carbon) {
            return true;
        }

        return $updatedAt->lt($crawlCompletedAt);
    }

    /**
     * @return array{executed:bool,action:string,reason:string}|null
     */
    private function attemptRewrite(SeoSite $site): ?array
    {
        $page = $this->resolveRewriteCandidatePage($site->site_id);

        if (! $page) {
            return null;
        }

        try {
            $this->markActionState($site, 'rewrite', 'running', 'PraeviSEO relance automatiquement une amélioration de contenu.');
            $existingSuggestion = $page->suggestions()
                ->where('status', 'pending')
                ->latest('created_at')
                ->first();

            $suggestion = $existingSuggestion ?: $this->rewrite->createSuggestion($page->fresh(['suggestions']), 'enrich');

            $this->appendExecutionHistory(
                $site,
                'Boucle premium : réécriture préparée',
                sprintf('PraeviSEO a préparé une nouvelle amélioration automatique pour "%s".', (string) $page->title),
                'default',
                'auto_rewrite_prepared',
            );
            $this->markActionState($site, 'rewrite', 'completed', sprintf('Une amélioration automatique est prête pour "%s".', (string) $page->title));

            return [
                'executed' => true,
                'action' => 'rewrite',
                'reason' => $existingSuggestion ? 'pending_rewrite_reused' : 'rewrite_created',
            ];
        } catch (Throwable $e) {
            $this->markActionState(
                $site,
                'rewrite',
                'failed',
                'La boucle premium n a pas pu relancer la réécriture.',
                $this->premiumActionErrorMessage($e, 'PraeviSEO n a pas pu préparer automatiquement cette amélioration de contenu.')
            );
            $this->appendExecutionHistory($site, 'Boucle premium : réécriture interrompue', $e->getMessage(), 'danger', 'auto_rewrite_failed');

            return ['executed' => false, 'action' => 'rewrite', 'reason' => 'rewrite_failed'];
        }
    }

    /**
     * @return array{executed:bool,action:string,reason:string}|null
     */
    private function attemptInternalLinking(SeoSite $site): ?array
    {
        ['page' => $page, 'suggestion' => $existingSuggestion] = $this->resolveInternalLinkingCandidate($site->site_id);

        if (! $page) {
            return null;
        }

        try {
            $this->markActionState($site, 'linking', 'running', 'PraeviSEO relance automatiquement un renfort de maillage.');
            $suggestion = $existingSuggestion ?: $this->rewrite->createSuggestion($page->fresh(['suggestions', 'observedPage']), 'add-internal-links-only');
            $internalLinks = $this->extractSuggestionInternalLinks($suggestion);

            if ($internalLinks === []) {
                $this->markActionState(
                    $site,
                    'linking',
                    'failed',
                    'La boucle premium n a pas trouvé de maillage assez utile à appliquer.',
                    'PraeviSEO n a pas encore trouvé assez de liens internes sûrs et utiles pour appliquer ce renfort automatiquement.'
                );

                return ['executed' => false, 'action' => 'linking', 'reason' => 'no_safe_internal_links'];
            }

            $this->workflow->apply($suggestion->fresh());

            $this->appendExecutionHistory(
                $site,
                'Boucle premium : maillage renforcé',
                sprintf('PraeviSEO a ajouté %d lien(s) interne(s) utiles sur "%s".', count($internalLinks), (string) $page->title),
                'default',
                'auto_linking_applied',
            );
            $this->markActionState($site, 'linking', 'completed', sprintf('%d lien(s) interne(s) ont été ajoutés automatiquement sur "%s".', count($internalLinks), (string) $page->title));
            $this->scheduleObservedCrawlIfIdle($site, 'after_linking');

            return ['executed' => true, 'action' => 'linking', 'reason' => 'linking_applied'];
        } catch (Throwable $e) {
            $this->markActionState(
                $site,
                'linking',
                'failed',
                'La boucle premium n a pas pu renforcer le maillage.',
                $this->premiumActionErrorMessage($e, 'PraeviSEO n a pas pu appliquer automatiquement les liens internes prévus.')
            );
            $this->appendExecutionHistory($site, 'Boucle premium : maillage interrompu', $e->getMessage(), 'danger', 'auto_linking_failed');

            return ['executed' => false, 'action' => 'linking', 'reason' => 'linking_failed'];
        }
    }

    /**
     * @return array{executed:bool,action:string,reason:string}|null
     */
    private function attemptPublication(SeoSite $site): ?array
    {
        $page = $this->resolvePublicationCandidatePage($site->site_id);

        if (! $page) {
            return null;
        }

        try {
            $this->markActionState($site, 'publication', 'running', 'PraeviSEO relance automatiquement une publication live.');
            $page = $this->livePublication->publish($page, $site);

            $this->appendExecutionHistory(
                $site,
                'Boucle premium : publication envoyée',
                sprintf('PraeviSEO a renvoyé "%s" vers le site live dans la boucle automatique.', (string) $page->title),
                'default',
                'auto_publication_sent',
            );
            $this->markActionState($site, 'publication', 'completed', sprintf('La page "%s" a été republiée automatiquement.', (string) $page->title));
            $this->scheduleObservedCrawlIfIdle($site, 'after_publication');

            return ['executed' => true, 'action' => 'publication', 'reason' => 'publication_sent'];
        } catch (Throwable $e) {
            $this->markActionState(
                $site,
                'publication',
                'failed',
                'La boucle premium n a pas pu republier la meilleure page.',
                $this->premiumActionErrorMessage($e, 'PraeviSEO n a pas pu pousser automatiquement la page vers le site live.')
            );
            $this->appendExecutionHistory($site, 'Boucle premium : publication interrompue', $e->getMessage(), 'danger', 'auto_publication_failed');

            return ['executed' => false, 'action' => 'publication', 'reason' => 'publication_failed'];
        }
    }

    /**
     * @return array{executed:bool,action:string,reason:string}|null
     */
    private function attemptImageGeneration(SeoSite $site): ?array
    {
        $page = $this->resolveImageCandidatePage($site->site_id);

        if (! $page) {
            return null;
        }

        try {
            $this->markActionState($site, 'images', 'running', 'PraeviSEO relance automatiquement une image SEO.');
            $page = $this->images->generate($page);
            $page = $this->images->approve($page);

            $this->appendExecutionHistory(
                $site,
                'Boucle premium : image générée',
                sprintf('PraeviSEO a généré puis approuvé une image pour "%s".', (string) $page->title),
                'default',
                'auto_image_generated',
            );
            $this->markActionState($site, 'images', 'completed', sprintf('Une image SEO est prête pour "%s".', (string) $page->title));

            return ['executed' => true, 'action' => 'images', 'reason' => 'image_generated'];
        } catch (Throwable $e) {
            $this->markActionState(
                $site,
                'images',
                'failed',
                'La boucle premium n a pas pu générer l image prévue.',
                $this->premiumActionErrorMessage($e, 'PraeviSEO n a pas pu produire automatiquement l image SEO prévue.')
            );
            $this->appendExecutionHistory($site, 'Boucle premium : image interrompue', $e->getMessage(), 'danger', 'auto_image_failed');

            return ['executed' => false, 'action' => 'images', 'reason' => 'image_failed'];
        }
    }

    private function resolveRewriteCandidatePage(string $siteId): ?SeoPage
    {
        return SeoPage::query()
            ->where('site_id', $siteId)
            ->withCount([
                'suggestions as pending_suggestions_count' => fn ($query) => $query->where('status', 'pending'),
            ])
            ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', ['published'])
            ->orderByRaw('CASE WHEN observed_site_page_id IS NULL THEN 1 ELSE 0 END')
            ->orderByDesc('seo_score')
            ->orderByDesc('updated_at')
            ->first();
    }

    private function resolvePublicationCandidatePage(string $siteId): ?SeoPage
    {
        $query = SeoPage::query()
            ->where('site_id', $siteId)
            ->where(function ($builder): void {
                $builder
                    ->where('status', 'published')
                    ->orWhereNotNull('published_at');
            });

        if (Schema::hasColumn('seo_pages', 'published_live')) {
            $query->where(function ($builder): void {
                $builder
                    ->whereNull('published_live')
                    ->orWhere('published_live', false);
            });
        }

        return $query
            ->orderByDesc('published_at')
            ->orderByDesc('updated_at')
            ->first();
    }

    private function resolveImageCandidatePage(string $siteId): ?SeoPage
    {
        return SeoPage::query()
            ->where('site_id', $siteId)
            ->where(function (Builder $query): void {
                $query
                    ->whereNull('image_path')
                    ->orWhere('image_path', '')
                    ->orWhereNull('image_status')
                    ->orWhere('image_status', '!=', 'approved');
            })
            ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', ['published'])
            ->orderByRaw('CASE WHEN published_live = 1 THEN 0 ELSE 1 END')
            ->orderByDesc('seo_score')
            ->orderByDesc('updated_at')
            ->first();
    }

    /**
     * @return array{page:?SeoPage,suggestion:?SeoSuggestion}
     */
    private function resolveInternalLinkingCandidate(string $siteId): array
    {
        /** @var SeoSuggestion|null $pendingSuggestion */
        $pendingSuggestion = SeoSuggestion::query()
            ->with('page')
            ->where('status', 'pending')
            ->whereHas('page', fn (Builder $query) => $query->where('site_id', $siteId))
            ->latest('created_at')
            ->get()
            ->first(fn (SeoSuggestion $suggestion): bool => $this->extractSuggestionInternalLinks($suggestion) !== []);

        if ($pendingSuggestion && $pendingSuggestion->page) {
            return [
                'page' => $pendingSuggestion->page,
                'suggestion' => $pendingSuggestion,
            ];
        }

        $page = SeoPage::query()
            ->select('seo_pages.*')
            ->leftJoin('seo_site_pages', 'seo_site_pages.id', '=', 'seo_pages.observed_site_page_id')
            ->where('seo_pages.site_id', $siteId)
            ->whereNotNull('seo_pages.observed_site_page_id')
            ->with('observedPage')
            ->orderByRaw('CASE WHEN seo_pages.status = ? THEN 0 ELSE 1 END', ['published'])
            ->orderByRaw('COALESCE(seo_site_pages.internal_inlinks, 9999) ASC')
            ->orderByRaw('COALESCE(seo_site_pages.orphan_score, 0) DESC')
            ->orderByDesc('seo_pages.seo_score')
            ->orderByDesc('seo_pages.updated_at')
            ->first();

        return [
            'page' => $page,
            'suggestion' => null,
        ];
    }

    /**
     * @return array<int,array{label:string,url:string,reason:mixed}>
     */
    private function extractSuggestionInternalLinks(SeoSuggestion $suggestion): array
    {
        $payload = is_array($suggestion->suggestions_json) ? $suggestion->suggestions_json : [];

        return collect($payload['internal_links'] ?? [])
            ->filter(fn (mixed $item): bool => is_array($item) && filled($item['url'] ?? null))
            ->map(fn (array $item): array => [
                'label' => (string) ($item['label'] ?? $item['text'] ?? $item['url']),
                'url' => (string) ($item['url'] ?? ''),
                'reason' => $item['reason'] ?? null,
            ])
            ->values()
            ->all();
    }

    private function scheduleObservedCrawlIfIdle(SeoSite $site, string $trigger): SeoSiteCrawl
    {
        $latestCrawl = $site->relationLoaded('latestObservedCrawl')
            ? $site->getRelation('latestObservedCrawl')
            : $site->latestObservedCrawl()->first();

        if ($latestCrawl && in_array((string) $latestCrawl->status, ['pending', 'running'], true)) {
            return $latestCrawl;
        }

        $crawl = SeoSiteCrawl::query()->create([
            'site_id' => $site->site_id,
            'base_url' => rtrim((string) $site->url, '/'),
            'status' => 'pending',
            'max_pages' => 80,
            'meta_json' => [
                'trigger' => $trigger,
            ],
        ]);

        RunObservedSiteCrawlJob::dispatch($crawl->id);
        $this->appendExecutionHistory(
            $site,
            $trigger === 'premium_loop_seed' ? 'Boucle premium : relecture relancée' : $this->executionHistoryLabelForTrigger($trigger),
            $trigger === 'premium_loop_seed'
                ? 'PraeviSEO relance une relecture automatique pour rouvrir la prochaine action utile.'
                : $this->executionHistoryDetailForTrigger($trigger),
            'secondary',
            $trigger,
        );

        return $crawl;
    }

    private function appendExecutionHistory(
        SeoSite $site,
        string $label,
        string $detail,
        string $tone = 'secondary',
        string $kind = 'event',
    ): void {
        $settings = $site->settings_json ?? [];
        $automation = is_array($settings['automation'] ?? null) ? $settings['automation'] : [];
        $history = is_array($automation['history'] ?? null) ? $automation['history'] : [];

        $history[] = [
            'at' => now()->toIso8601String(),
            'label' => $label,
            'detail' => $detail,
            'tone' => in_array($tone, ['default', 'secondary', 'danger'], true) ? $tone : 'secondary',
            'kind' => $kind,
        ];

        $automation['history'] = collect($history)
            ->sortByDesc('at')
            ->take(40)
            ->values()
            ->all();

        $settings['automation'] = $automation;
        $site->forceFill(['settings_json' => $settings])->save();
        $site->refresh();
    }

    private function markActionState(SeoSite $site, string $action, string $state, string $detail, ?string $error = null): void
    {
        $settings = $site->settings_json ?? [];
        $automation = is_array($settings['automation'] ?? null) ? $settings['automation'] : [];
        $actions = is_array($automation['actions'] ?? null) ? $automation['actions'] : [];

        $actions[$action] = [
            'state' => $state,
            'detail' => $detail,
            'updated_at' => now()->toIso8601String(),
            'error' => $state === 'failed' ? $error : null,
        ];

        $automation['actions'] = $actions;
        $settings['automation'] = $automation;
        $site->forceFill(['settings_json' => $settings])->save();
        $site->refresh();
    }

    private function premiumActionErrorMessage(Throwable $e, string $fallback): string
    {
        $message = trim($e->getMessage());

        return $message !== '' ? $message : $fallback;
    }

    private function executionHistoryLabelForTrigger(string $trigger): string
    {
        return match ($trigger) {
            'after_publication' => 'Relecture relancée après publication',
            'after_linking' => 'Relecture relancée après maillage',
            default => 'Crawl premium demandé',
        };
    }

    private function executionHistoryDetailForTrigger(string $trigger): string
    {
        return match ($trigger) {
            'after_publication' => 'PraeviSEO relit le site après une publication pour vérifier le résultat visible.',
            'after_linking' => 'PraeviSEO relit le site après un renfort de liens internes pour contrôler la nouvelle structure.',
            default => 'PraeviSEO a lancé une nouvelle lecture complète du site pour préparer ou contrôler les prochaines actions.',
        };
    }
}
