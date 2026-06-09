<?php

declare(strict_types=1);

namespace App\Copilot;

use App\Models\SeoPage;
use App\Models\SeoSite;
use App\Models\SeoSitePage;
use App\Models\SeoSuggestion;
use App\ObservedSite\ObservedPageHealthService;
use App\ObservedSite\ObservedRewriteBridgeService;
use App\Services\Publication\ObservedNativePublicationGuard;

final class BusinessCopilotModificationPlanner
{
    public function __construct(
        private readonly ObservedRewriteBridgeService $observedRewrite,
        private readonly ObservedPageHealthService $pageHealth,
        private readonly PageModificationEvidenceService $pageEvidence,
        private readonly ObservedNativePublicationGuard $nativeGuard,
    ) {}

    /**
     * @return array{
     *   sections:array<int,string>,
     *   topics:array<int,string>,
     *   faq:array<int,string>,
     *   content_summary:string,
     *   title_change:?string,
     *   action_label:string,
     *   action_detail:string
     * }
     */
    public function planForGsc(
        string $siteId,
        string $type,
        string $subject,
        string $label,
        ?int $pageId,
        string $slug,
        ?string $query,
        ?int $pendingSuggestionId = null,
    ): array {
        $page = $this->resolveSeoPage($siteId, $pageId, $slug);
        $observed = $page
            ? $this->observedRewrite->contextForPage($page)
            : $this->observedContextForSlug($siteId, $slug);
        $suggestion = $this->resolveSuggestion($page, $pendingSuggestionId);
        $evidence = $this->pageEvidence->gather($siteId, $page?->id, $slug, $query, $subject);

        $sections = $this->sectionsFromSources($observed, $suggestion, $type, $subject, $query, $evidence);
        $topics = $this->topicsFromSources($siteId, $type, $subject, $query, $observed, $evidence);
        $faq = $this->faqFromSources($observed, $suggestion, $type, $subject, $query, $evidence);
        $titleChange = $this->titleChangeFromSources($type, $suggestion, $observed, $subject, $slug, $evidence);

        $actionLabel = $this->actionLabel($type, $sections, $topics, $faq, $titleChange);
        $actionDetail = $this->actionDetail($type, $sections, $topics, $faq, $titleChange, $subject, $query);
        $contentSummary = $this->contentSummary($sections, $topics, $faq, $titleChange);

        return [
            'sections' => $sections,
            'topics' => $topics,
            'faq' => $faq,
            'content_summary' => $contentSummary,
            'title_change' => $titleChange,
            'action_label' => $actionLabel,
            'action_detail' => $actionDetail,
        ];
    }

    /**
     * @param  array<string,mixed>  $meta
     * @return array{
     *   sections:array<int,string>,
     *   topics:array<int,string>,
     *   faq:array<int,string>,
     *   content_summary:string,
     *   title_change:?string,
     *   action_label:string,
     *   action_detail:string
     * }
     */
    public function planForRecommendation(
        string $siteId,
        string $type,
        string $subject,
        ?string $suggestedAction,
        array $meta,
        ?int $pageId,
    ): array {
        $path = (string) data_get($meta, 'path', '');
        $page = $this->resolveSeoPage($siteId, $pageId, $path);
        $observed = $page ? $this->observedRewrite->contextForPage($page) : ['sections' => [], 'faq' => [], 'flags' => []];
        $suggestion = $this->resolveSuggestion($page, null);
        $evidence = $this->pageEvidence->gather($siteId, $page?->id, $path, null, $subject);

        $sections = $this->sectionsFromSources($observed, $suggestion, $type, $subject, null, $evidence);
        if ($sections === [] && $suggestedAction !== null && trim($suggestedAction) !== '') {
            $sections[] = $this->translateEngineLine($suggestedAction);
        }

        $topics = $this->topicsFromSources($siteId, $type, $subject, null, $observed, $evidence);
        $cluster = trim((string) ($meta['cluster'] ?? ''));
        if ($cluster !== '' && ! in_array($cluster, $topics, true)) {
            $topics[] = $cluster;
        }

        $faq = $this->faqFromSources($observed, $suggestion, $type, $subject, null, $evidence);
        $titleChange = $type === 'refresh_page' ? null : null;

        return [
            'sections' => array_slice($sections, 0, 4),
            'topics' => array_slice(array_values(array_unique($topics)), 0, 3),
            'faq' => array_slice($faq, 0, 3),
            'content_summary' => $this->contentSummary($sections, $topics, $faq, $titleChange),
            'title_change' => $titleChange,
            'action_label' => $this->actionLabel($type, $sections, $topics, $faq, $titleChange),
            'action_detail' => $this->actionDetail($type, $sections, $topics, $faq, $titleChange, $subject, null),
        ];
    }

    /**
     * @return array{visitors:int,min:int,max:int,basis:string,display:string}
     */
    public function estimateGain(
        string $actionKey,
        int $impressions,
        float $position,
        float $ctrPercent,
        string $subject,
    ): array {
        $ctrRate = $ctrPercent > 1 ? min(1.0, $ctrPercent / 100) : min(1.0, max(0.0, $ctrPercent));
        $currentClicks = (int) round($impressions * $ctrRate);

        $targetPosition = match ($actionKey) {
            'improve_ctr' => max(1.0, $position),
            'create_page' => 8.0,
            default => max(4.0, $position > 0 ? $position - 3.5 : 6.0),
        };

        $targetCtr = $this->expectedCtrForPosition($targetPosition);
        $projectedClicks = (int) round($impressions * $targetCtr);
        $extra = max(0, $projectedClicks - $currentClicks);

        if ($extra <= 0 && $impressions > 0) {
            $liftFactor = match ($actionKey) {
                'create_page' => 0.12,
                'improve_ctr' => 0.06,
                default => 0.05,
            };
            $positionBoost = $position > 0 ? max(0.5, (16 - min($position, 15)) / 10) : 1.0;
            $extra = max(2, (int) round($impressions * $liftFactor * $positionBoost));
        }

        $min = max(1, (int) floor($extra * 0.7));
        $max = max($min + 1, (int) ceil($extra * 1.4));
        $visitors = (int) round(($min + $max) / 2);

        $basis = $impressions > 0
            ? sprintf(
                '%d affichages/mois, position %.0f%s',
                $impressions,
                $position > 0 ? $position : 0,
                $ctrPercent > 0 ? sprintf(', %.1f %% de clics', $ctrPercent) : '',
            )
            : 'Signal Google encore léger';

        $display = $min === $max
            ? sprintf('+%d visiteurs/mois', $visitors)
            : sprintf('+%d–%d visiteurs/mois', $min, $max);

        return [
            'visitors' => $visitors,
            'min' => $min,
            'max' => $max,
            'basis' => $basis,
            'display' => $display,
        ];
    }

    private function expectedCtrForPosition(float $position): float
    {
        return match (true) {
            $position <= 1.5 => 0.22,
            $position <= 3.5 => 0.10,
            $position <= 5.5 => 0.065,
            $position <= 8.5 => 0.038,
            $position <= 10.5 => 0.024,
            $position <= 15.0 => 0.014,
            default => 0.008,
        };
    }

    private function resolveSeoPage(string $siteId, ?int $pageId, string $slug): ?SeoPage
    {
        if ($pageId) {
            return SeoPage::query()->where('site_id', $siteId)->whereKey($pageId)->first();
        }

        if ($slug === '') {
            return null;
        }

        return SeoPage::query()->where('site_id', $siteId)->where('slug', $slug)->first();
    }

    /**
     * @return array<string,mixed>
     */
    private function observedContextForSlug(string $siteId, string $slug): array
    {
        if ($slug === '') {
            return ['sections' => [], 'faq' => [], 'flags' => [], 'rationale' => []];
        }

        $observedPage = $this->nativeGuard->resolveObservedPageBySlug($siteId, $slug);

        if (! $observedPage) {
            return ['sections' => [], 'faq' => [], 'flags' => [], 'rationale' => []];
        }

        $health = $this->pageHealth->forPage($observedPage);

        return [
            'matched' => true,
            'sections' => $this->sectionsFromFlags($health['flags']),
            'faq' => $this->faqFromFlags($health['flags'], $slug),
            'flags' => $health['flags'],
            'rationale' => [],
        ];
    }

    private function resolveSuggestion(?SeoPage $page, ?int $pendingSuggestionId): ?SeoSuggestion
    {
        if ($pendingSuggestionId) {
            return SeoSuggestion::query()->find($pendingSuggestionId);
        }

        if (! $page) {
            return null;
        }

        return $page->suggestions()
            ->where('status', 'pending')
            ->latest('created_at')
            ->first();
    }

    /**
     * @param  array<string,mixed>  $observed
     * @return array<int,string>
     */
    /**
     * @param  array<string,mixed>  $evidence
     */
    private function sectionsFromSources(
        array $observed,
        ?SeoSuggestion $suggestion,
        string $type,
        string $subject,
        ?string $query,
        array $evidence = [],
    ): array {
        $sections = collect();

        foreach ((array) ($evidence['section_gaps'] ?? []) as $line) {
            $sections->push((string) $line);
        }

        if ($type === 'near_top_10' && $query !== null && $query !== '') {
            $sections->push(sprintf(
                'Répondre explicitement à « %s » avec procédure, délais et responsabilités terrain.',
                $query,
            ));
        } elseif ($type === 'near_top_10') {
            $topQuery = (string) data_get($evidence, 'gsc_queries.0.query', '');
            if ($topQuery !== '') {
                $sections->push(sprintf(
                    'Renforcer la page sur la recherche « %s » (%d affichages/mois).',
                    $topQuery,
                    (int) data_get($evidence, 'gsc_queries.0.impressions', 0),
                ));
            }
        }

        if ($type === 'low_ctr') {
            $pageTitle = trim((string) ($evidence['page_title'] ?? $subject));
            $sections->push(sprintf(
                'Accroche plus claire en tête de page : bénéfice client et promesse concrète pour « %s ».',
                $pageTitle !== '' ? $pageTitle : $subject,
            ));
        }

        if ($type === 'emerging_query' && $query !== null) {
            $sections->prepend(sprintf('Introduction qui répond directement à « %s ».', $query));
            foreach (array_slice((array) ($evidence['missing_topics'] ?? []), 0, 2) as $topic) {
                $sections->push('Section manquante : '.(string) $topic);
            }
        }

        if (($evidence['word_count'] ?? null) !== null && (int) $evidence['word_count'] < 450) {
            $sections->push(sprintf(
                'Approfondir le contenu actuel (%d mots observés) avec des éléments métier plus concrets.',
                (int) $evidence['word_count'],
            ));
        }

        $payload = is_array($suggestion?->suggestions_json) ? $suggestion->suggestions_json : [];
        foreach (array_slice((array) ($payload['sections'] ?? []), 0, 4) as $line) {
            $translated = $this->translateEngineLine((string) $line);
            if ($translated !== '') {
                $sections->push($translated);
            }
        }

        foreach ($this->sectionsFromFlags(
            (array) ($observed['flags'] ?? []),
            count((array) ($evidence['section_gaps'] ?? [])) > 0,
        ) as $line) {
            $sections->push($line);
        }

        foreach ($observed['sections'] ?? [] as $line) {
            $translated = $this->translateEngineLine((string) $line);
            if ($translated !== '') {
                $sections->push($translated);
            }
        }

        return $sections->unique()->values()->take(4)->all();
    }

    /**
     * @param  array<string,mixed>  $observed
     * @return array<int,string>
     */
    /**
     * @param  array<string,mixed>  $evidence
     */
    private function topicsFromSources(
        string $siteId,
        string $type,
        string $subject,
        ?string $query,
        array $observed,
        array $evidence = [],
    ): array {
        $topics = collect([$subject]);

        if ($query !== null && $query !== '') {
            $topics->prepend($query);
        }

        foreach ((array) ($evidence['gsc_queries'] ?? []) as $row) {
            if (is_array($row) && filled($row['query'] ?? null)) {
                $topics->push((string) $row['query']);
            }
        }

        foreach (array_slice((array) ($evidence['missing_topics'] ?? []), 0, 2) as $topic) {
            $topics->push((string) $topic);
        }

        $cluster = trim((string) ($observed['cluster_label'] ?? ''));
        if ($cluster !== '') {
            $topics->push($cluster);
        }

        if ($type === 'emerging_query' || $type === 'create_page') {
            $site = SeoSite::query()->where('site_id', $siteId)->first();
            $profile = is_array($site?->settings_json['site_profile'] ?? null) ? $site->settings_json['site_profile'] : [];
            foreach (array_slice((array) ($profile['editorial_topics'] ?? []), 0, 2) as $topic) {
                if (is_string($topic) && trim($topic) !== '') {
                    $topics->push($topic);
                }
            }
        }

        return $topics->filter(fn (string $topic): bool => trim($topic) !== '')->unique()->values()->take(3)->all();
    }

    /**
     * @param  array<string,mixed>  $observed
     * @return array<int,string>
     */
    /**
     * @param  array<string,mixed>  $evidence
     */
    private function faqFromSources(
        array $observed,
        ?SeoSuggestion $suggestion,
        string $type,
        string $subject,
        ?string $query,
        array $evidence = [],
    ): array {
        $faq = collect();

        foreach ((array) ($evidence['faq_candidates'] ?? []) as $question) {
            $faq->push((string) $question);
        }

        $payload = is_array($suggestion?->suggestions_json) ? $suggestion->suggestions_json : [];
        foreach (array_slice((array) ($payload['faq'] ?? []), 0, 3) as $item) {
            if (is_array($item) && filled($item['question'] ?? null)) {
                $faq->push($this->translateEngineLine((string) $item['question']));
            }
        }

        foreach ($observed['faq'] ?? [] as $item) {
            $question = is_array($item) ? trim((string) ($item['question'] ?? '')) : '';
            if ($question !== '') {
                $faq->push($this->translateEngineLine($question));
            }
        }

        foreach ($this->faqFromFlags((array) ($observed['flags'] ?? []), $subject) as $question) {
            $faq->push($question);
        }

        return $faq
            ->map(fn (string $question): string => trim($question))
            ->reject(fn (string $question): bool => $this->isGenericFaqFallback($question))
            ->unique()
            ->values()
            ->take(3)
            ->all();
    }

    private function isGenericFaqFallback(string $question): bool
    {
        $normalized = mb_strtolower($question);

        return str_contains($normalized, 'combien de temps pour traiter')
            || str_contains($normalized, 'qui doit intervenir pour');
    }

    private function isSecondaryTechnicalSection(string $line): bool
    {
        $normalized = mb_strtolower($line);

        return str_contains($normalized, 'liens internes depuis vos pages')
            || str_contains($normalized, 'bloc de preuves')
            || str_contains($normalized, 'meta description')
            || str_contains($normalized, 'titre de page plus clair');
    }

    /**
     * @param  array<string,mixed>  $observed
     */
    /**
     * @param  array<string,mixed>  $evidence
     */
    private function titleChangeFromSources(
        string $type,
        ?SeoSuggestion $suggestion,
        array $observed,
        string $subject,
        string $slug,
        array $evidence,
    ): ?string {
        $payload = is_array($suggestion?->suggestions_json) ? $suggestion->suggestions_json : [];
        $suggested = trim((string) ($payload['title'] ?? ''));
        if ($suggested !== '') {
            return $suggested;
        }

        $needsTitle = $type === 'low_ctr'
            || in_array('missing_title', (array) ($observed['flags'] ?? []), true)
            || in_array('missing_meta_description', (array) ($observed['flags'] ?? []), true);

        if (! $needsTitle) {
            return null;
        }

        return $this->concreteTitleProposal($slug, $subject, $evidence);
    }

    /**
     * @param  array<string,mixed>  $evidence
     */
    private function concreteTitleProposal(string $slug, string $subject, array $evidence): ?string
    {
        $topQuery = (string) data_get($evidence, 'gsc_queries.0.query', '');
        $pageTitle = trim((string) ($evidence['page_title'] ?? ''));
        $niche = (string) ($evidence['niche_key'] ?? 'generic');
        $slugHaystack = mb_strtolower($slug);

        $pageSpecific = match (true) {
            $niche === 'amiante' && str_contains($slugHaystack, 'faq') => 'FAQ amiante : délais de repérage, DTA, copropriété et responsabilités MOA',
            $niche === 'amiante' && (str_contains($slugHaystack, 'reference') || str_contains($slugHaystack, 'reglement')) => 'Références réglementaires amiante : repérage, SS3/SS4 et obligations chantier',
            $niche === 'amiante' && str_contains($slugHaystack, 'copropri') => 'Amiante en copropriété : repérage, coordination syndic et phasage chantier',
            default => null,
        };

        if ($pageSpecific !== null) {
            return $pageSpecific;
        }

        if ($topQuery !== '' && (int) data_get($evidence, 'gsc_queries.0.impressions', 0) >= 3) {
            $base = $pageTitle !== '' ? $pageTitle : $subject;

            return sprintf('%s — réponses pratiques sur « %s »', $base, $topQuery);
        }

        return null;
    }

    /**
     * @param  array<int,string>  $sections
     * @param  array<int,string>  $topics
     * @param  array<int,string>  $faq
     */
    private function actionLabel(
        string $type,
        array $sections,
        array $topics,
        array $faq,
        ?string $titleChange,
    ): string {
        if ($titleChange !== null && $type === 'low_ctr') {
            return 'Réécrire le titre et renforcer l’accroche de la page';
        }

        if ($sections !== []) {
            $primary = collect($sections)->first(
                fn (string $line): bool => ! $this->isSecondaryTechnicalSection($line),
            ) ?? $sections[0];

            return 'Ajouter : '.$primary;
        }

        return match ($type) {
            'emerging_query', 'create_page' => 'Créer une page structurée avec FAQ',
            'low_ctr' => 'Réécrire le titre pour donner envie de cliquer',
            default => 'Renforcer le contenu sur les sujets manquants',
        };
    }

    /**
     * @param  array<int,string>  $sections
     * @param  array<int,string>  $topics
     * @param  array<int,string>  $faq
     */
    private function actionDetail(
        string $type,
        array $sections,
        array $topics,
        array $faq,
        ?string $titleChange,
        string $subject,
        ?string $query,
    ): string {
        $parts = [];

        if ($titleChange !== null) {
            $parts[] = 'Nouveau titre : '.$titleChange;
        }

        if ($sections !== []) {
            $parts[] = 'Sections : '.implode(' ; ', array_slice($sections, 0, 2));
        }

        if ($topics !== []) {
            $parts[] = 'Sujets : '.implode(', ', $topics);
        }

        if ($faq !== []) {
            $parts[] = 'FAQ : '.implode(' / ', array_slice($faq, 0, 2));
        }

        if ($parts === []) {
            $focus = $query !== null && $query !== '' ? $query : $subject;

            return sprintf('PraeviSEO prépare un renfort ciblé sur « %s ».', $focus);
        }

        return implode('. ', $parts).'.';
    }

    /**
     * @param  array<int,string>  $sections
     * @param  array<int,string>  $topics
     * @param  array<int,string>  $faq
     */
    private function contentSummary(
        array $sections,
        array $topics,
        array $faq,
        ?string $titleChange,
    ): string {
        $chunks = [];

        if ($titleChange !== null) {
            $chunks[] = 'un titre plus convaincant';
        }

        if ($sections !== []) {
            $chunks[] = count($sections).' section(s) utile(s)';
        }

        if ($faq !== []) {
            $chunks[] = count($faq).' question(s) FAQ';
        }

        if ($topics !== []) {
            $chunks[] = 'couverture de : '.implode(', ', array_slice($topics, 0, 2));
        }

        if ($chunks === []) {
            return 'PraeviSEO précisera le patch après analyse de la page live.';
        }

        return 'PraeviSEO va ajouter '.implode(', ', $chunks).'.';
    }

    /**
     * @param  array<int,string>  $flags
     * @return array<int,string>
     */
    /**
     * @param  array<int,string>  $flags
     * @return array<int,string>
     */
    private function sectionsFromFlags(array $flags, bool $limitTechnical = false): array
    {
        return collect($flags)
            ->map(fn (string $flag): ?string => match ($flag) {
                'missing_title' => $limitTechnical ? null : 'Titre de page plus clair, aligné sur la recherche principale.',
                'missing_meta_description' => $limitTechnical ? null : 'Meta description orientée bénéfice client pour améliorer le taux de clic.',
                'missing_cluster_signal' => 'Sous-titres (H2/H3) qui clarifient l’angle métier de la page.',
                'low_authority' => $limitTechnical ? null : 'Section preuves : exemples terrain, chiffres, procédures et points de vigilance.',
                'orphan_high' => $limitTechnical ? null : 'Liens internes depuis vos pages les plus visibles vers cette page.',
                'overlap_high' => 'Repositionner l’angle pour ne plus cannibaliser une autre page du site.',
                'non_indexable' => 'Corriger les blocages d’indexation visibles dans le contenu et les métadonnées.',
                'unhealthy_status' => 'Rafraîchir le contenu pour rétablir une page saine côté technique.',
                default => null,
            })
            ->filter(fn (?string $line): bool => is_string($line) && $line !== '')
            ->values()
            ->all();
    }

    /**
     * @param  array<int,string>  $flags
     * @return array<int,string>
     */
    private function faqFromFlags(array $flags, string $subject): array
    {
        $faq = [];

        if (in_array('overlap_high', $flags, true)) {
            $faq[] = 'En quoi cette page est différente des autres pages proches sur votre site ?';
        }

        if (in_array('low_authority', $flags, true)) {
            $faq[] = sprintf('Quels éléments concrets renforcer pour crédibiliser « %s » ?', $subject);
        }

        if (in_array('orphan_high', $flags, true)) {
            $faq[] = 'Depuis quelles pages du site faut-il renvoyer vers ce contenu ?';
        }

        return $faq;
    }

    private function translateEngineLine(string $line): string
    {
        $line = trim($line);

        return match (true) {
            str_contains(strtolower($line), 'rewrite the title') => 'Réécrire le titre pour mieux refléter la recherche principale.',
            str_contains(strtolower($line), 'meta description') => 'Ajouter une meta description plus convaincante.',
            str_contains(strtolower($line), 'headings that reinforce') => 'Renforcer les sous-titres pour clarifier le sujet traité.',
            str_contains(strtolower($line), 'proof points') => 'Ajouter preuves, exemples terrain et détails concrets.',
            str_contains(strtolower($line), 'internal linking') => 'Créer des liens internes depuis vos pages les plus consultées.',
            str_contains(strtolower($line), 'differentiate the angle') => 'Différencier l’angle pour éviter le chevauchement avec d’autres pages.',
            str_contains(strtolower($line), 'indexability blocker') => 'Lever le blocage d’indexation détecté sur la page.',
            str_contains(strtolower($line), 'coverage depth') => 'Approfondir les sections trop superficielles.',
            str_contains(strtolower($line), 'internal links') => 'Ajouter des liens internes depuis vos pages visibles.',
            $line !== '' => $line,
            default => '',
        };
    }
}
