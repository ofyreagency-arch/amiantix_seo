<?php

declare(strict_types=1);

namespace App\Runtime;

use App\Models\SeoPage;
use App\Services\Publication\SeoLivePublicationService;
use Illuminate\Support\Collection;

class PageWorkflowLifecycleService
{
    public function __construct(
        private readonly SeoLivePublicationService $livePublication,
    ) {}

    /**
     * @param  array<string,mixed>  $publicationSummary
     * @param  array<string,mixed>  $pageIndexationBacklog
     * @param  array<string,mixed>|null  $pageGscOpportunity
     * @return array<string,mixed>
     */
    public function summarize(
        SeoPage $page,
        array $publicationSummary,
        array $pageIndexationBacklog,
        ?array $pageGscOpportunity,
        int $pendingSuggestionCount,
    ): array {
        $backlogItems = collect($pageIndexationBacklog['items'] ?? [])
            ->filter(fn (mixed $item): bool => is_array($item))
            ->values();

        $readyBacklogAction = $backlogItems->first(fn (array $item): bool => in_array((string) ($item['action_kind'] ?? ''), ['engine_rewrite', 'quick_fix'], true)
            && (string) ($item['action_state'] ?? '') === 'ready');
        $manualBacklogAction = $backlogItems->first(fn (array $item): bool => (string) ($item['action_kind'] ?? '') === 'manual_review');

        $liveSupported = $this->livePublication->supportsLivePublication();
        $enginePublished = $page->isPublishedInEngine();
        $livePublished = $page->isPublishedLive();
        $hasBlockingRules = ($publicationSummary['failed_rules'] ?? []) !== [];

        $canPublishEngine = ! $enginePublished && ! $hasBlockingRules && $pendingSuggestionCount === 0;
        $canPublishLive = $liveSupported && $enginePublished && ! $livePublished;

        $gscActionState = is_array($pageGscOpportunity) ? (string) ($pageGscOpportunity['action_state'] ?? 'ready') : null;
        $gscActionReady = is_array($pageGscOpportunity) && $gscActionState === 'ready';

        $nextAction = match (true) {
            $pendingSuggestionCount > 0 => [
                'kind' => 'validate_suggestion',
                'label' => 'Valider la suggestion en attente',
                'detail' => 'Une suggestion éditoriale existe déjà. Il faut la relire, décider si elle améliore vraiment la page, puis l appliquer ou l ignorer.',
                'impact_expected' => 'Aucune publication supplémentaire tant que cette passe n est pas validée.',
                'manual_required' => false,
                'engine_actionable' => true,
            ],
            is_array($readyBacklogAction) => [
                'kind' => (string) ($readyBacklogAction['action_kind'] ?? 'engine_rewrite'),
                'label' => (string) ($readyBacklogAction['action_label'] ?? 'Lancer la correction moteur'),
                'detail' => (string) ($readyBacklogAction['reason'] ?? 'Le moteur a détecté un point d indexation actionnable sur cette page.'),
                'impact_expected' => (string) ($readyBacklogAction['impact_expected'] ?? 'Renforcer localement le signal que le moteur peut vraiment améliorer.'),
                'manual_required' => false,
                'engine_actionable' => true,
            ],
            is_array($manualBacklogAction) => [
                'kind' => 'manual_review',
                'label' => (string) ($manualBacklogAction['action_label'] ?? 'Revue technique humaine'),
                'detail' => (string) ($manualBacklogAction['reason'] ?? 'Le problème observé n est pas corrigeable directement par le moteur.'),
                'impact_expected' => (string) ($manualBacklogAction['impact_expected'] ?? 'Aucun gain tant que le problème technique réel n est pas corrigé côté site client.'),
                'manual_required' => true,
                'engine_actionable' => false,
            ],
            $canPublishEngine => [
                'kind' => 'publish_engine',
                'label' => 'Publier côté moteur',
                'detail' => 'Les blocages moteur sont levés. La page peut maintenant passer du workflow éditorial à la publication moteur.',
                'impact_expected' => 'La page devient publiable dans la phase live et entre dans la vraie boucle de monitoring.',
                'manual_required' => false,
                'engine_actionable' => true,
            ],
            $canPublishLive => [
                'kind' => 'publish_live',
                'label' => 'Publier sur le site client',
                'detail' => 'La page est validée côté moteur. Il reste à créer sa vraie URL publique côté site client.',
                'impact_expected' => 'La page pourra être crawlée, remonter dans le sitemap public et recevoir de vrais signaux Google.',
                'manual_required' => false,
                'engine_actionable' => true,
            ],
            $livePublished && $gscActionReady => [
                'kind' => 'gsc_opportunity',
                'label' => 'Réouvrir une amélioration post-publication',
                'detail' => (string) ($pageGscOpportunity['reason'] ?? 'Google remonte un signal utile à corriger sur cette page.'),
                'impact_expected' => 'Traiter une dérive réelle de trafic, de CTR ou de couverture après publication live.',
                'manual_required' => false,
                'engine_actionable' => true,
            ],
            $livePublished && $gscActionState === 'pending' => [
                'kind' => 'gsc_pending',
                'label' => 'Suggestion GSC déjà en attente',
                'detail' => 'Une relance post-publication est déjà ouverte depuis Google Search Console. Il vaut mieux la laisser vivre avant de rouvrir une autre passe.',
                'impact_expected' => 'Éviter les doublons et laisser le moteur mesurer proprement l impact réel.',
                'manual_required' => false,
                'engine_actionable' => false,
            ],
            $livePublished && $gscActionState === 'cooldown' => [
                'kind' => 'gsc_cooldown',
                'label' => 'Attendre la fin du cooldown',
                'detail' => 'Le moteur a déjà tenté récemment une action de ce type sur cette page. On attend de voir si le signal bouge réellement.',
                'impact_expected' => 'Éviter de forcer plusieurs corrections sur le même signal sans recul.',
                'manual_required' => false,
                'engine_actionable' => false,
            ],
            $livePublished => [
                'kind' => 'monitor_only',
                'label' => 'Continuer le monitoring',
                'detail' => 'La page est déjà en live et aucun signal assez fort ne justifie une réouverture immédiate.',
                'impact_expected' => 'Observer si Google ou le crawl dérivent avant de rouvrir une amélioration.',
                'manual_required' => false,
                'engine_actionable' => false,
            ],
            default => [
                'kind' => 'wait',
                'label' => 'Continuer le workflow éditorial',
                'detail' => 'La page n est pas encore dans la phase de publication réelle. On la stabilise d abord côté moteur.',
                'impact_expected' => 'Préparer une publication propre sans sauter d étape.',
                'manual_required' => false,
                'engine_actionable' => false,
            ],
        };

        $monitoring = match (true) {
            ! $livePublished => [
                'state' => 'pre_live',
                'label' => 'Monitoring post-publication non actif',
                'detail' => 'Le suivi réel commencera quand la page sera effectivement poussée sur le site client.',
            ],
            is_array($manualBacklogAction) => [
                'state' => 'technical_issue',
                'label' => 'Problème technique réel détecté',
                'detail' => (string) ($manualBacklogAction['reason'] ?? 'Le crawl ou Google remontent un problème qui nécessite une revue humaine.'),
            ],
            is_array($readyBacklogAction) => [
                'state' => 'indexation_issue',
                'label' => 'Signal d indexation actionnable',
                'detail' => (string) ($readyBacklogAction['reason'] ?? 'Le moteur détecte un blocage indexation qu il peut tenter d améliorer.'),
            ],
            $gscActionReady => [
                'state' => 'signal_drift',
                'label' => 'Signal Google en dérive exploitable',
                'detail' => (string) ($pageGscOpportunity['reason'] ?? 'Google remonte une opportunité exploitable sur cette page.'),
            ],
            $gscActionState === 'pending' => [
                'state' => 'improvement_pending',
                'label' => 'Amélioration post-publication déjà en cours',
                'detail' => 'Une suggestion liée aux signaux Google est déjà ouverte pour cette page.',
            ],
            $gscActionState === 'cooldown' => [
                'state' => 'cooldown',
                'label' => 'Cool down post-publication actif',
                'detail' => 'Le moteur attend un peu avant de rouvrir une nouvelle action sur ce signal.',
            ],
            default => [
                'state' => 'stable',
                'label' => 'Page live stable pour le moment',
                'detail' => 'Aucune dérive forte observée : la page reste en surveillance simple.',
            ],
        };

        return [
            'current_stage' => $livePublished
                ? 'live'
                : ($enginePublished ? 'engine_published' : ($pendingSuggestionCount > 0 ? 'validation' : (string) ($page->status ?: 'draft'))),
            'current_stage_label' => $livePublished
                ? 'Publié sur le site client'
                : ($enginePublished ? 'Publié côté moteur' : ($pendingSuggestionCount > 0 ? 'Validation suggestion' : 'Préparation éditoriale')),
            'publication' => [
                'engine_published' => $enginePublished,
                'live_published' => $livePublished,
                'live_supported' => $liveSupported,
                'can_publish_engine' => $canPublishEngine,
                'can_publish_live' => $canPublishLive,
            ],
            'next_action' => $nextAction,
            'monitoring' => $monitoring,
            'reopen_needed' => $livePublished && in_array($monitoring['state'], ['technical_issue', 'indexation_issue', 'signal_drift'], true),
            'backlog_actionable_item' => $readyBacklogAction,
            'backlog_manual_item' => $manualBacklogAction,
            'gsc_opportunity' => $pageGscOpportunity,
        ];
    }
}
