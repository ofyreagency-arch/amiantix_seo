<?php

declare(strict_types=1);

namespace App\Runtime;

use App\Models\SeoPage;
use App\Models\SeoSearchConsoleMetric;
use App\Models\SeoSuggestion;
use App\ObservedSite\SeoPageObservedLinkService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class IndexationBacklogService
{
    public const COOLDOWN_DAYS = 7;

    public function __construct(
        private readonly SeoPageObservedLinkService $observedLinks,
    ) {}

    /**
     * @return array{
     *   summary:array{google_not_indexed:int,without_google_signal:int,observed_404:int,observed_redirect:int,observed_noindex:int,total:int},
     *   items:array<int,array<string,mixed>>
     * }
     */
    public function summarize(string $siteId): array
    {
        $pageColumns = [
            'id',
            'site_id',
            'observed_site_page_id',
            'keyword',
            'slug',
            'title',
            'status',
            'canonical_url',
            'is_indexed',
            'forced_noindex',
        ];

        if (Schema::hasColumns('seo_pages', ['published_live', 'published_live_at'])) {
            $pageColumns[] = 'published_live';
            $pageColumns[] = 'published_live_at';
        }

        $pages = SeoPage::query()
            ->where('site_id', $siteId)
            ->get($pageColumns);

        if ($pages->isEmpty()) {
            return [
                'summary' => [
                    'google_not_indexed' => 0,
                    'without_google_signal' => 0,
                    'observed_404' => 0,
                    'observed_redirect' => 0,
                    'observed_noindex' => 0,
                    'total' => 0,
                ],
                'items' => [],
            ];
        }

        $latestMetrics = SeoSearchConsoleMetric::query()
            ->whereIn('seo_page_id', $pages->pluck('id'))
            ->whereNull('query')
            ->orderByDesc('metric_date')
            ->orderByDesc('id')
            ->get()
            ->groupBy('seo_page_id')
            ->map(fn ($group) => $group->first());

        $recentSuggestions = SeoSuggestion::query()
            ->whereIn('seo_page_id', $pages->pluck('id'))
            ->where('created_at', '>=', now()->subDays(self::COOLDOWN_DAYS))
            ->get(['id', 'seo_page_id', 'source', 'status', 'signals_json', 'created_at'])
            ->groupBy('seo_page_id');

        $items = collect();

        foreach ($pages as $page) {
            $metric = $latestMetrics->get($page->id);
            $observed = $this->observedLinks->resolveMatch($page)['page'] ?? null;
            /** @var Collection<int,SeoSuggestion> $suggestionRows */
            $suggestionRows = $recentSuggestions->get($page->id, collect());
            $label = trim((string) ($page->title ?: $page->keyword ?: $page->slug));
            $hasObservedTechnicalBlock = $observed && (
                (int) ($observed->last_status_code ?? 0) >= 300
                || in_array((string) $observed->indexability_state, ['noindex', 'non_indexable', 'blocked'], true)
            );

            if ($metric && $metric->is_indexed === false) {
                $action = $page->forced_noindex
                    ? $this->decorateQuickFixAction('clear_noindex', 'Retirer le noindex moteur')
                    : $this->decorateRewriteAction($suggestionRows, 'improve-indexability', 'google_not_indexed', 'Créer une correction moteur');

                $items->push($this->makeItem(
                    type: 'google_not_indexed',
                    label: $label,
                    page: $page,
                    source: 'Google inspection',
                    reason: $this->coverageReason($metric->coverage_json),
                    action: 'Verifier canonical, sitemap, maillage et qualite avant nouvelle demande d indexation.',
                    priorityScore: 520,
                    actionPlan: $action,
                ));
            }

            if ($page->isPublishedInEngine() && ! $metric && ! $hasObservedTechnicalBlock) {
                $items->push($this->makeItem(
                    type: 'without_google_signal',
                    label: $label,
                    page: $page,
                    source: 'Moteur + GSC',
                    reason: 'Page publiee cote moteur sans signal Google recent exploitable.',
                    action: 'Verifier la publication publique, le sitemap et les liens internes avant d attendre des impressions.',
                    priorityScore: $this->isPublishedLive($page) ? 320 : 240,
                    actionPlan: $this->decorateRewriteAction($suggestionRows, 'add-internal-links-only', 'without_google_signal', 'Renforcer le maillage'),
                ));
            }

            if ($observed && (int) ($observed->last_status_code ?? 0) >= 400) {
                $items->push($this->makeItem(
                    type: 'observed_404',
                    label: $label,
                    page: $page,
                    source: 'Crawl observed',
                    reason: 'La page resolue par le runtime repond en erreur HTTP '.$observed->last_status_code.'.',
                    action: 'Corriger la destination publiee ou supprimer la page casse cote site client.',
                    priorityScore: 500,
                    observedPath: $observed->path,
                    actionPlan: $this->decorateManualAction('Revue technique requise'),
                ));
            }

            if ($observed && (int) ($observed->last_status_code ?? 0) >= 300 && (int) ($observed->last_status_code ?? 0) < 400) {
                $items->push($this->makeItem(
                    type: 'observed_redirect',
                    label: $label,
                    page: $page,
                    source: 'Crawl observed',
                    reason: 'La page publiee aboutit actuellement sur une redirection HTTP '.$observed->last_status_code.'.',
                    action: 'Verifier l URL finale, la canonical et eviter de pousser une ancienne route en production.',
                    priorityScore: 360,
                    observedPath: $observed->path,
                    actionPlan: $this->decorateManualAction('Verifier la destination live'),
                ));
            }

            if ($observed && in_array((string) $observed->indexability_state, ['noindex', 'non_indexable', 'blocked'], true)) {
                $action = $page->forced_noindex
                    ? $this->decorateQuickFixAction('clear_noindex', 'Retirer le noindex moteur')
                    : $this->decorateManualAction('Verifier le noindex cote site');

                $items->push($this->makeItem(
                    type: 'observed_noindex',
                    label: $label,
                    page: $page,
                    source: 'Crawl observed',
                    reason: 'Le runtime observe actuellement un etat d indexabilite '.$observed->indexability_state.'.',
                    action: 'Verifier robots, meta noindex, canonicals et etat reel de la page cote site client.',
                    priorityScore: 340,
                    observedPath: $observed->path,
                    actionPlan: $action,
                ));
            }
        }

        $deduped = $items
            ->sortByDesc('priority_score')
            ->values();

        return [
            'summary' => [
                'google_not_indexed' => $deduped->where('type', 'google_not_indexed')->count(),
                'without_google_signal' => $deduped->where('type', 'without_google_signal')->count(),
                'observed_404' => $deduped->where('type', 'observed_404')->count(),
                'observed_redirect' => $deduped->where('type', 'observed_redirect')->count(),
                'observed_noindex' => $deduped->where('type', 'observed_noindex')->count(),
                'total' => $deduped->count(),
            ],
            'items' => $deduped->take(8)->all(),
        ];
    }

    /**
     * @return array{
     *   google_state:array<string,mixed>,
     *   observed_state:array<string,mixed>,
     *   publication_state:array<string,mixed>,
     *   items:array<int,array<string,mixed>>,
     *   actionable_count:int,
     *   manual_count:int,
     *   total:int
     * }
     */
    public function summarizeForPage(SeoPage $page): array
    {
        $latestMetric = SeoSearchConsoleMetric::query()
            ->where('seo_page_id', $page->id)
            ->whereNull('query')
            ->orderByDesc('metric_date')
            ->orderByDesc('id')
            ->first();

        $observed = $this->observedLinks->resolveMatch($page)['page'] ?? null;
        $items = collect($this->summarize((string) $page->site_id)['items'] ?? [])
            ->where('page_id', $page->id)
            ->values();

        return [
            'google_state' => [
                'label' => match (true) {
                    $latestMetric?->is_indexed === false => 'Google la voit mais ne l indexe pas encore',
                    $latestMetric?->is_indexed === true => 'Google l indexe',
                    $page->isPublishedInEngine() => 'Aucun signal Google recent exploitable',
                    default => 'Pas encore de signal Google',
                },
                'detail' => match (true) {
                    $latestMetric?->is_indexed === false => $this->coverageReason($latestMetric->coverage_json),
                    $latestMetric?->is_indexed === true => 'La derniere remontee Google stockee confirme une page indexee.',
                    $page->isPublishedInEngine() => 'La page existe cote moteur mais ne remonte pas encore de signal Google utile.',
                    default => 'La page n a pas encore de donnee Google exploitable dans le runtime.',
                },
                'indexed' => $latestMetric?->is_indexed,
                'metric_date' => $latestMetric?->metric_date,
            ],
            'observed_state' => [
                'label' => match (true) {
                    $observed === null => 'Aucun signal observed',
                    (int) ($observed->last_status_code ?? 0) >= 400 => 'Erreur HTTP observee',
                    (int) ($observed->last_status_code ?? 0) >= 300 => 'Redirection observee',
                    in_array((string) $observed->indexability_state, ['noindex', 'non_indexable', 'blocked'], true) => 'Indexation bloquee cote site',
                    default => 'Page observee sans alerte technique forte',
                },
                'detail' => match (true) {
                    $observed === null => 'Le runtime n a pas encore recrawle de page correspondante pour cette URL.',
                    (int) ($observed->last_status_code ?? 0) >= 400 => 'Le crawl observed lit actuellement un HTTP '.((int) $observed->last_status_code).'.',
                    (int) ($observed->last_status_code ?? 0) >= 300 => 'Le crawl observed aboutit actuellement sur une redirection HTTP '.((int) $observed->last_status_code).'.',
                    in_array((string) $observed->indexability_state, ['noindex', 'non_indexable', 'blocked'], true) => 'Le crawl observed remonte un etat '.$observed->indexability_state.'.',
                    default => 'HTTP '.((int) ($observed->last_status_code ?? 200)).' · indexability '.((string) ($observed->indexability_state ?? 'indexable')).'.',
                },
                'status_code' => $observed->last_status_code ?? null,
                'indexability_state' => $observed->indexability_state ?? null,
                'path' => $observed->path ?? null,
            ],
            'publication_state' => [
                'label' => match (true) {
                    $this->isPublishedLive($page) => 'Publiee en live',
                    $page->isPublishedInEngine() => 'Publiee cote moteur',
                    default => 'Encore en workflow editorial',
                },
                'detail' => match (true) {
                    $this->isPublishedLive($page) => 'La page est deja poussee sur le site public et doit etre suivie cote crawl/indexation.',
                    $page->isPublishedInEngine() => 'La page est validee cote moteur mais pas encore poussee en live.',
                    default => 'La page n est pas encore dans la phase de publication live.',
                },
                'engine_published' => $page->isPublishedInEngine(),
                'live_published' => $this->isPublishedLive($page),
            ],
            'items' => $items->all(),
            'actionable_count' => $items->whereIn('action_kind', ['engine_rewrite', 'quick_fix'])->count(),
            'manual_count' => $items->where('action_kind', 'manual_review')->count(),
            'total' => $items->count(),
        ];
    }

    /**
     * @param  array<int,string>|null  $coverage
     * @return array<string,mixed>
     */
    private function makeItem(
        string $type,
        string $label,
        SeoPage $page,
        string $source,
        string $reason,
        string $action,
        int $priorityScore,
        ?string $observedPath = null,
        array $actionPlan = [],
    ): array {
        return [
            'type' => $type,
            'label' => $label,
            'page_id' => $page->id,
            'slug' => $page->slug,
            'status' => $page->status,
            'source' => $source,
            'reason' => $reason,
            'action' => $action,
            'observed_path' => $observedPath,
            'priority_score' => $priorityScore,
            ...$actionPlan,
        ];
    }

    /**
     * @param  array<int,string>|null  $coverage
     */
    private function coverageReason(?array $coverage): string
    {
        if (! is_array($coverage) || $coverage === []) {
            return 'Google confirme que la page n est pas indexee, sans detail de couverture supplementaire stocke.';
        }

        $coverageState = collect($coverage)
            ->first(fn (string $item): bool => str_starts_with($item, 'coverage_state:'));

        if (is_string($coverageState)) {
            return 'Google remonte : '.trim(substr($coverageState, strlen('coverage_state:'))).'.';
        }

        return 'Google confirme un probleme d indexation sur cette page.';
    }

    private function isPublishedLive(SeoPage $page): bool
    {
        if (! Schema::hasColumns('seo_pages', ['published_live', 'published_live_at'])) {
            return false;
        }

        return $page->isPublishedLive();
    }

    /**
     * @param  Collection<int,SeoSuggestion>  $suggestions
     * @return array<string,mixed>
     */
    private function decorateRewriteAction(Collection $suggestions, string $mode, string $type, string $label): array
    {
        $existingPending = $this->findPendingSuggestion($suggestions, $mode, $type);
        $cooldownSuggestion = $this->findRecentTriggeredSuggestion($suggestions, $mode, $type);
        $actionState = $existingPending !== null
            ? 'pending'
            : ($cooldownSuggestion !== null ? 'cooldown' : 'ready');

        return [
            'action_kind' => 'engine_rewrite',
            'action_label' => $label,
            'mode' => $mode,
            'impact_expected' => match ($type) {
                'google_not_indexed' => 'Ajouter des signaux éditoriaux et de maillage pour aider la reprise Google sans réécriture globale.',
                'without_google_signal' => 'Mieux relier la page au reste du site pour qu elle soit plus vite découverte et comprise.',
                default => 'Renforcer localement les signaux que le moteur peut vraiment améliorer.',
            },
            'pending_suggestion' => $existingPending !== null,
            'pending_suggestion_id' => $existingPending?->id,
            'cooldown_active' => $cooldownSuggestion !== null,
            'cooldown_suggestion_id' => $cooldownSuggestion?->id,
            'action_state' => $actionState,
            'action_state_label' => match ($actionState) {
                'pending' => 'Suggestion deja en attente',
                'cooldown' => 'Cooldown actif',
                default => 'Action moteur possible',
            },
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function decorateQuickFixAction(string $quickFixAction, string $label): array
    {
        return [
            'action_kind' => 'quick_fix',
            'action_label' => $label,
            'quick_fix_action' => $quickFixAction,
            'impact_expected' => 'Retirer immédiatement un blocage moteur explicite avant nouvelle analyse ou republication.',
            'action_state' => 'ready',
            'action_state_label' => 'Correctif direct possible',
            'pending_suggestion' => false,
            'cooldown_active' => false,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function decorateManualAction(string $label): array
    {
        return [
            'action_kind' => 'manual_review',
            'action_label' => $label,
            'impact_expected' => 'Aucun impact direct tant que le probleme technique n est pas corrige cote site client.',
            'action_state' => 'manual',
            'action_state_label' => 'Revue manuelle requise',
            'pending_suggestion' => false,
            'cooldown_active' => false,
        ];
    }

    /**
     * @param  Collection<int,SeoSuggestion>  $suggestions
     */
    private function findPendingSuggestion(Collection $suggestions, string $mode, string $type): ?SeoSuggestion
    {
        return $suggestions->first(function (SeoSuggestion $suggestion) use ($mode, $type): bool {
            if ($suggestion->status !== 'pending') {
                return false;
            }

            return $this->matchesTrigger($suggestion, $mode, $type);
        });
    }

    /**
     * @param  Collection<int,SeoSuggestion>  $suggestions
     */
    private function findRecentTriggeredSuggestion(Collection $suggestions, string $mode, string $type): ?SeoSuggestion
    {
        return $suggestions->first(fn (SeoSuggestion $suggestion): bool => $this->matchesTrigger($suggestion, $mode, $type));
    }

    private function matchesTrigger(SeoSuggestion $suggestion, string $mode, string $type): bool
    {
        if ($suggestion->source !== 'rewrite_engine:'.$mode) {
            return false;
        }

        $trigger = is_array($suggestion->signals_json['indexation_backlog_trigger'] ?? null)
            ? $suggestion->signals_json['indexation_backlog_trigger']
            : [];

        return ($trigger['type'] ?? null) === $type;
    }
}
