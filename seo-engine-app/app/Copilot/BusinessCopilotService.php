<?php

declare(strict_types=1);

namespace App\Copilot;

use App\Models\SeoPage;
use App\Models\SeoRecommendation;
use App\Models\SeoSitePage;
use App\Recommendations\ImpactEstimatorService;
use Illuminate\Support\Collection;

final class BusinessCopilotService
{
    public function __construct(
        private readonly ImpactEstimatorService $impactEstimator,
    ) {}

    /**
     * @param  Collection<int,array<string,mixed>>  $gscOpportunities
     * @param  Collection<int,SeoRecommendation>  $recommendations
     * @return array{
     *   headline:string,
     *   subheadline:string,
     *   daily_priority:array<int,array<string,mixed>>,
     *   top_action:array<string,mixed>|null
     * }
     */
    public function build(Collection $gscOpportunities, Collection $recommendations, int $limit = 5): array
    {
        $actions = collect()
            ->merge($gscOpportunities->map(fn (array $item): array => $this->fromGscOpportunity($item)))
            ->merge($recommendations->map(fn (SeoRecommendation $item): array => $this->fromRecommendation($item)))
            ->filter(fn (array $item): bool => ($item['monthly_gain_visitors'] ?? 0) > 0 || ($item['priority_score'] ?? 0) > 0)
            ->unique(fn (array $item): string => ($item['site_id'] ?? '').'|'.($item['subject'] ?? '').'|'.($item['action_verb'] ?? ''))
            ->sortByDesc(fn (array $item): int => (int) ($item['sort_score'] ?? 0))
            ->values()
            ->take($limit)
            ->values()
            ->map(function (array $item, int $index): array {
                $item['rank'] = $index + 1;
                $item['card_title'] = sprintf('#%d %s', $index + 1, (string) ($item['headline'] ?? 'Action recommandée'));

                return $item;
            })
            ->all();

        $top = $actions[0] ?? null;

        return [
            'headline' => $top !== null
                ? 'Votre action la plus rentable aujourd’hui'
                : 'PraeviSEO surveille votre visibilité',
            'subheadline' => $top !== null
                ? 'Traitez d’abord ce qui peut vous apporter le plus de visiteurs avec le moins d’effort.'
                : 'Dès qu’un levier concret apparaît dans Google, il sera classé ici par gain potentiel.',
            'daily_priority' => $actions,
            'top_action' => $top,
        ];
    }

    /**
     * @param  array<string,mixed>  $item
     * @return array<string,mixed>
     */
    private function fromGscOpportunity(array $item): array
    {
        $type = (string) ($item['type'] ?? '');
        $metrics = is_array($item['metrics'] ?? null) ? $item['metrics'] : [];
        $impressions = (int) ($metrics['impressions'] ?? 0);
        $position = (float) ($metrics['position'] ?? 0);
        $ctr = (float) ($metrics['ctr'] ?? 0);
        $query = isset($item['query']) ? trim((string) $item['query']) : '';
        $label = trim((string) ($item['label'] ?? 'Votre page'));
        $subject = $query !== '' ? $query : $label;
        $siteId = (string) ($item['site_id'] ?? '');
        $slug = (string) ($item['slug'] ?? '');
        $pageId = $item['page_id'] ?? null;

        $profile = $this->gscBusinessProfile($type, $subject, $label, $impressions, $position, $ctr);
        $gain = $this->estimateGain($profile['action_key'], $impressions, $position, $ctr);
        $effort = $this->effortProfile($profile['effort_key']);
        $workflow = $this->workflowForGscType($type);
        $signalReady = ($item['action_state'] ?? '') === 'ready' && ! ($item['pending_suggestion'] ?? false);

        return $this->composeAction([
            'source' => 'gsc_opportunity',
            'source_id' => 'gsc-'.$siteId.'-'.($slug !== '' ? $slug : $subject).'-'.$type,
            'site_id' => $siteId,
            'site_name' => (string) ($item['site_name'] ?? ''),
            'page_id' => $pageId,
            'slug' => $slug,
            'query' => $query !== '' ? $query : null,
            'subject' => $subject,
            'action_verb' => $profile['action_verb'],
            'headline' => $profile['headline'],
            'problem_plain' => $profile['problem_plain'],
            'why_plain' => $profile['why_plain'],
            'action_label' => $profile['action_label'],
            'action_detail' => $profile['action_detail'],
            'monthly_gain_visitors' => $gain['visitors'],
            'monthly_gain_min' => $gain['min'],
            'monthly_gain_max' => $gain['max'],
            'estimated_volume' => $this->estimateMonthlyVolume($impressions),
            'current_position' => $position > 0 ? round($position, 1) : null,
            'effort_level' => $effort['level'],
            'effort_label' => $effort['label'],
            'effort_minutes' => $effort['minutes'],
            'effort_display' => $effort['display'],
            'apply_mode' => (string) ($item['action'] ?? $profile['apply_mode']),
            'apply_workflow' => $workflow,
            'apply_ready' => $signalReady && $this->canAutoApply($workflow, $siteId, $pageId, $slug, $query !== '' ? $query : null),
            'apply_href' => $this->applyHref($siteId, $slug, $pageId, (string) ($item['action'] ?? '')),
            'priority_score' => (int) ($item['priority_score'] ?? 0),
            'sort_score' => $gain['visitors'] * 10 + (int) ($item['priority_score'] ?? 0),
        ]);
    }

    private function fromRecommendation(SeoRecommendation $recommendation): array
    {
        $meta = is_array($recommendation->meta_json) ? $recommendation->meta_json : [];
        $impact = is_array($meta['impact_estimate'] ?? null) ? $meta['impact_estimate'] : [];
        $signals = [
            'impressions' => (int) data_get($meta, 'gsc_impressions', data_get($meta, 'impressions', 0)),
            'position' => (float) data_get($meta, 'gsc_position', data_get($meta, 'position', 0)),
            'ctr' => (float) data_get($meta, 'gsc_ctr', data_get($meta, 'ctr', 0)),
        ];

        if ($impact === []) {
            $impact = $this->impactEstimator->estimate(
                (string) $recommendation->type,
                is_array($meta['page_classification'] ?? null) ? $meta['page_classification'] : [],
                is_array($meta['business_intent'] ?? null) ? $meta['business_intent'] : [],
                $signals,
            );
        }

        $subject = $this->recommendationSubject((string) $recommendation->title, $meta);
        $profile = $this->recommendationBusinessProfile((string) $recommendation->type, $subject, (string) $recommendation->suggested_action);
        $visitors = (int) round(((int) ($impact['monthly_gain_min'] ?? 0) + (int) ($impact['monthly_gain_max'] ?? 0)) / 2);
        $effort = $this->effortProfile((string) $recommendation->difficulty);

        return $this->composeAction([
            'source' => 'recommendation',
            'source_id' => 'reco-'.$recommendation->id,
            'site_id' => (string) $recommendation->site_id,
            'site_name' => '',
            'page_id' => $recommendation->site_page_id,
            'slug' => (string) data_get($meta, 'path', ''),
            'query' => null,
            'subject' => $subject,
            'action_verb' => $profile['action_verb'],
            'headline' => $profile['headline'],
            'problem_plain' => $profile['problem_plain'],
            'why_plain' => $profile['why_plain'],
            'action_label' => $profile['action_label'],
            'action_detail' => $profile['action_detail'],
            'monthly_gain_visitors' => max($visitors, (int) ($impact['monthly_gain_min'] ?? 0)),
            'monthly_gain_min' => (int) ($impact['monthly_gain_min'] ?? 0),
            'monthly_gain_max' => (int) ($impact['monthly_gain_max'] ?? 0),
            'estimated_volume' => $this->estimateMonthlyVolume($signals['impressions']),
            'current_position' => $signals['position'] > 0 ? round($signals['position'], 1) : null,
            'effort_level' => $effort['level'],
            'effort_label' => $effort['label'],
            'effort_minutes' => $effort['minutes'],
            'effort_display' => $this->formatEffortDisplay($effort, max($visitors, (int) ($impact['monthly_gain_min'] ?? 0))),
            'apply_mode' => $profile['apply_mode'],
            'apply_workflow' => $this->workflowForRecommendationType((string) $recommendation->type),
            'apply_ready' => $this->canAutoApply(
                $this->workflowForRecommendationType((string) $recommendation->type),
                (string) $recommendation->site_id,
                $recommendation->site_page_id,
                (string) data_get($meta, 'path', ''),
                $subject,
            ),
            'apply_href' => $this->applyHref((string) $recommendation->site_id, '', $recommendation->site_page_id, $profile['apply_mode']),
            'priority_score' => max(0, 100 - (int) $recommendation->priority),
            'sort_score' => max($visitors, (int) ($impact['monthly_gain_min'] ?? 0)) * 10 + max(0, 100 - (int) $recommendation->priority),
        ]);
    }

    /**
     * @param  array<string,mixed>  $meta
     */
    private function recommendationSubject(string $title, array $meta): string
    {
        $context = trim((string) ($meta['context_label'] ?? ''));

        if ($context !== '') {
            return $context;
        }

        return trim(preg_replace('/^(Reconnect orphan page|Strengthen weak page|Resolve overlap|Refresh the|Expand cluster):\s*/i', '', $title) ?? $title);
    }

    /**
     * @return array<string,string>
     */
    private function gscBusinessProfile(
        string $type,
        string $subject,
        string $label,
        int $impressions,
        float $position,
        float $ctr,
    ): array {
        return match ($type) {
            'near_top_10' => [
                'action_key' => 'refresh_page',
                'action_verb' => 'actualiser',
                'headline' => 'Actualiser cet article',
                'problem_plain' => sprintf('« %s » est proche de la première page Google mais n’y arrive pas encore.', $subject),
                'why_plain' => $position > 0
                    ? sprintf('Vous êtes autour de la %.0fe position : un renfort ciblé peut vous faire gagner des visiteurs rapidement.', $position)
                    : 'Google commence déjà à vous montrer sur cette recherche : un contenu plus complet peut faire la différence.',
                'action_label' => 'Enrichir le contenu et clarifier le titre de la page',
                'action_detail' => 'Ajoutez une section utile, renforcez le titre et répondez plus précisément à ce que cherchent vos prospects.',
                'apply_mode' => 'rafraichir la page',
                'effort_key' => 'medium',
            ],
            'low_ctr' => [
                'action_key' => 'improve_ctr',
                'action_verb' => 'corriger',
                'headline' => 'Corriger cette page',
                'problem_plain' => sprintf('Des internautes voient « %s » dans Google, mais trop peu cliquent.', $subject),
                'why_plain' => $ctr > 0
                    ? sprintf('Votre page apparaît déjà (taux de clic autour de %.1f %%), mais le titre ou l’accroche ne convainquent pas assez.', $ctr)
                    : 'Votre page est visible dans Google, mais elle n’incite pas assez au clic.',
                'action_label' => 'Réécrire le titre et la description pour donner envie de cliquer',
                'action_detail' => 'Formulez un titre plus concret, orienté bénéfice client, sans jargon technique.',
                'apply_mode' => 'relancer le CTR',
                'effort_key' => 'low',
            ],
            'emerging_query' => [
                'action_key' => 'create_page',
                'action_verb' => 'créer',
                'headline' => 'Créer ce contenu',
                'problem_plain' => sprintf('Google associe déjà votre site à « %s », mais sans page vraiment adaptée.', $subject),
                'why_plain' => $impressions > 0
                    ? 'Une nouvelle demande apparaît : mieux la couvrir maintenant vous place devant vos concurrents.'
                    : 'Une nouvelle recherche progresse : agir tôt évite de laisser ce trafic à un concurrent.',
                'action_label' => 'Publier ou renforcer une page dédiée à cette recherche',
                'action_detail' => 'Créez un contenu qui répond directement à la question de vos prospects.',
                'apply_mode' => 'créer une page',
                'effort_key' => 'high',
            ],
            'sustained_drop' => [
                'action_key' => 'refresh_page',
                'action_verb' => 'corriger',
                'headline' => 'Corriger cette page',
                'problem_plain' => sprintf('« %s » perd des visiteurs depuis plusieurs semaines.', $label),
                'why_plain' => 'Chaque semaine sans action, vous laissez une partie de votre visibilité à vos concurrents.',
                'action_label' => 'Actualiser la page et vérifier qu’elle répond encore au besoin client',
                'action_detail' => 'Mettez à jour le contenu, les preuves et les réponses aux questions fréquentes.',
                'apply_mode' => 'rafraichir la page',
                'effort_key' => 'medium',
            ],
            default => [
                'action_key' => 'refresh_page',
                'action_verb' => 'améliorer',
                'headline' => 'Améliorer cette page',
                'problem_plain' => sprintf('Une opportunité est détectée sur « %s ».', $subject),
                'why_plain' => 'Un levier concret peut encore être activé sur ce sujet.',
                'action_label' => 'Renforcer la page concernée',
                'action_detail' => 'Apportez plus de clarté et de profondeur sur ce que vos clients cherchent.',
                'apply_mode' => 'enrichir',
                'effort_key' => 'medium',
            ],
        };
    }

    /**
     * @return array<string,string>
     */
    private function recommendationBusinessProfile(string $type, string $subject, ?string $suggestedAction): array
    {
        $actionDetail = $this->translateSuggestedAction($suggestedAction);

        return match ($type) {
            'refresh_page' => [
                'action_verb' => 'actualiser',
                'headline' => 'Actualiser cet article',
                'problem_plain' => sprintf('« %s » manque encore de profondeur pour convaincre vos prospects.', $subject),
                'why_plain' => 'La page existe, mais elle n’explique pas assez clairement votre valeur ni les réponses attendues.',
                'action_label' => 'Enrichir le contenu et clarifier les sections clés',
                'action_detail' => $actionDetail,
                'apply_mode' => 'rafraichir la page',
            ],
            'create_page', 'expand_cluster' => [
                'action_verb' => 'créer',
                'headline' => 'Créer ce contenu',
                'problem_plain' => sprintf('Vos prospects cherchent « %s » et votre site ne répond pas encore assez bien.', $subject),
                'why_plain' => 'Une page dédiée vous permet de capter une demande que vos concurrents peuvent déjà couvrir.',
                'action_label' => 'Publier une page dédiée à ce sujet',
                'action_detail' => $actionDetail,
                'apply_mode' => 'créer une page',
            ],
            'add_internal_links' => [
                'action_verb' => 'relier',
                'headline' => 'Mieux mettre en avant cette page',
                'problem_plain' => sprintf('« %s » est trop isolée : Google et vos visiteurs la trouvent difficilement.', $subject),
                'why_plain' => 'Sans liens depuis vos autres pages, une bonne page reste invisible et ne génère pas de contacts.',
                'action_label' => 'Ajouter des liens depuis vos pages les plus visibles',
                'action_detail' => $actionDetail,
                'apply_mode' => 'améliorer le maillage',
            ],
            'differentiate_intent' => [
                'action_verb' => 'clarifier',
                'headline' => 'Clarifier vos pages',
                'problem_plain' => sprintf('Deux pages parlent du même sujet autour de « %s » et se concurrencent.', $subject),
                'why_plain' => 'Google ne sait plus laquelle montrer : vous diluez votre visibilité et vos chances de conversion.',
                'action_label' => 'Différencier clairement chaque page ou fusionner les contenus',
                'action_detail' => $actionDetail,
                'apply_mode' => 'clarifier les pages',
            ],
            default => [
                'action_verb' => 'améliorer',
                'headline' => 'Améliorer cette page',
                'problem_plain' => sprintf('Une action utile est possible sur « %s ».', $subject),
                'why_plain' => 'Un ajustement ciblé peut améliorer votre visibilité sans refaire tout le site.',
                'action_label' => 'Appliquer l’amélioration recommandée',
                'action_detail' => $actionDetail,
                'apply_mode' => 'enrichir',
            ],
        };
    }

    private function translateSuggestedAction(?string $suggestedAction): string
    {
        $action = trim((string) $suggestedAction);

        return match (true) {
            str_contains(strtolower($action), 'internal links') => 'Ajoutez des liens depuis vos pages les plus consultées vers cette page.',
            str_contains(strtolower($action), 'coverage depth') => 'Ajoutez des sections concrètes, des exemples et des réponses aux questions fréquentes.',
            str_contains(strtolower($action), 'differentiate intent') => 'Repositionnez chaque page sur un angle client distinct.',
            $action !== '' => $action,
            default => 'Renforcez le contenu pour répondre plus clairement à l’intention de vos prospects.',
        };
    }

    /**
     * @return array{visitors:int,min:int,max:int}
     */
    private function estimateGain(string $actionKey, int $impressions, float $position, float $ctr): array
    {
        $impact = $this->impactEstimator->estimate($actionKey, [], [], [
            'impressions' => $impressions,
            'position' => $position,
            'ctr' => $ctr,
        ]);

        $mid = (int) round(((int) ($impact['monthly_gain_min'] ?? 0) + (int) ($impact['monthly_gain_max'] ?? 0)) / 2);

        if ($mid <= 0 && $impressions > 0) {
            $mid = max(15, (int) round($impressions * 0.08));
        }

        return [
            'visitors' => max($mid, (int) ($impact['monthly_gain_min'] ?? 0)),
            'min' => (int) ($impact['monthly_gain_min'] ?? max(10, (int) floor($mid * 0.7))),
            'max' => (int) ($impact['monthly_gain_max'] ?? max($mid, (int) ceil($mid * 1.4))),
        ];
    }

    private function estimateMonthlyVolume(int $impressions): ?int
    {
        if ($impressions <= 0) {
            return null;
        }

        return max($impressions, (int) round($impressions * 1.15));
    }

    /**
     * @return array{level:string,label:string,minutes:int,display:string}
     */
    private function effortProfile(string $difficulty): array
    {
        return match ($difficulty) {
            'low', 'easy' => [
                'level' => 'easy',
                'label' => 'Facile',
                'minutes' => 5,
                'display' => '',
            ],
            'high', 'important' => [
                'level' => 'important',
                'label' => 'Important',
                'minutes' => 120,
                'display' => '',
            ],
            default => [
                'level' => 'medium',
                'label' => 'Moyen',
                'minutes' => 30,
                'display' => '',
            ],
        };
    }

    private function formatEffortDisplay(array $effort, int $monthlyGain): string
    {
        $emoji = match ($effort['level']) {
            'easy' => '🟢',
            'important' => '🔴',
            default => '🟠',
        };

        return sprintf(
            '%s %s — %d min — +%d visiteurs/mois',
            $emoji,
            $effort['label'],
            (int) $effort['minutes'],
            max(0, $monthlyGain),
        );
    }

    /**
     * @param  array<string,mixed>  $payload
     * @return array<string,mixed>
     */
    private function composeAction(array $payload): array
    {
        $payload['effort_display'] = $payload['effort_display'] !== ''
            ? $payload['effort_display']
            : $this->formatEffortDisplay(
                [
                    'level' => (string) ($payload['effort_level'] ?? 'medium'),
                    'label' => (string) ($payload['effort_label'] ?? 'Moyen'),
                    'minutes' => (int) ($payload['effort_minutes'] ?? 30),
                ],
                (int) ($payload['monthly_gain_visitors'] ?? 0),
            );

        $payload['gain_display'] = sprintf(
            '+%d visiteurs/mois',
            (int) ($payload['monthly_gain_visitors'] ?? 0),
        );

        $payload['card_title'] = sprintf(
            '#%d %s',
            0,
            (string) ($payload['headline'] ?? 'Action recommandée'),
        );

        return $payload;
    }

    private function workflowForGscType(string $type): string
    {
        return match ($type) {
            'emerging_query' => 'generate',
            default => 'rewrite',
        };
    }

    private function workflowForRecommendationType(string $type): string
    {
        return match ($type) {
            'create_page', 'expand_cluster' => 'generate',
            'add_internal_links' => 'linking',
            default => 'rewrite',
        };
    }

    private function canAutoApply(string $workflow, string $siteId, mixed $pageId, string $slug, ?string $keyword = null): bool
    {
        return match ($workflow) {
            'generate' => $siteId !== '',
            'linking', 'rewrite' => $this->pageResolvableForApply($siteId, $pageId, $slug),
            default => false,
        };
    }

    private function pageResolvableForApply(string $siteId, mixed $pageId, string $slug): bool
    {
        if ($siteId === '') {
            return false;
        }

        if ($pageId) {
            return true;
        }

        if ($slug === '') {
            return true;
        }

        if (SeoPage::query()->where('site_id', $siteId)->where('slug', $slug)->exists()) {
            return true;
        }

        $path = '/'.ltrim($slug, '/');
        $observedId = SeoSitePage::query()
            ->where('site_id', $siteId)
            ->where('path', $path)
            ->value('id');

        if (! $observedId) {
            return false;
        }

        return SeoPage::query()
            ->where('site_id', $siteId)
            ->where('observed_site_page_id', (int) $observedId)
            ->exists();
    }

    private function applyHref(string $siteId, string $slug, mixed $pageId, string $mode): string
    {
        if ($siteId === '') {
            return '/optimizations';
        }

        $query = http_build_query(array_filter([
            'site' => $siteId,
            'slug' => $slug !== '' ? $slug : null,
            'focus' => $mode !== '' ? $mode : null,
        ]));

        if ($pageId) {
            return '/sites/'.$siteId.'/automation?'.$query;
        }

        return '/optimizations?'.$query;
    }
}
