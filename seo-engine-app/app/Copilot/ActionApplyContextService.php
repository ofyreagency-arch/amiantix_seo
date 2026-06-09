<?php

declare(strict_types=1);

namespace App\Copilot;

use App\Models\SeoPage;
use App\Models\SeoSite;
use App\Models\SeoSitePage;

final class ActionApplyContextService
{
    public function canAutoApply(string $workflow, string $siteId, mixed $pageId, string $slug): bool
    {
        return match ($workflow) {
            'generate' => $siteId !== '',
            'linking', 'rewrite' => $this->pageResolvableForApply($siteId, $pageId, $slug),
            default => false,
        };
    }

    /**
     * @param  array{sections?:array<int,string>,topics?:array<int,string>,faq?:array<int,string>,content_summary?:string,title_change?:string|null}  $modificationPlan
     * @return array<string,mixed>
     */
    public function resolve(
        string $siteId,
        string $slug,
        mixed $pageId,
        string $workflow,
        bool $applyReady,
        string $subject,
        string $label,
        ?string $siteUrl = null,
        array $modificationPlan = [],
    ): array {
        $targetLabel = $label !== '' ? $label : ($subject !== '' ? $subject : 'Page ciblée');
        $targetPath = $this->targetPath($slug, $workflow);
        $targetUrl = $this->targetUrl($siteId, $slug, $siteUrl);
        $pageKind = $this->pageKind($siteId, $slug, $pageId, $workflow);
        $studioPage = $this->resolveStudioPage($siteId, $slug, $pageId);
        $hasPlan = $this->hasModificationPlan($modificationPlan);
        $whatWillChange = $this->whatWillChange($modificationPlan, $workflow);
        $liveImpact = $this->liveSiteImpact($pageKind, $workflow, $applyReady, $studioPage);

        return [
            'page_kind' => $pageKind,
            'page_kind_label' => $this->pageKindLabel($pageKind),
            'target_label' => $targetLabel,
            'target_path' => $targetPath,
            'target_url' => $targetUrl,
            'why_this_action' => $this->whyThisAction($pageKind, $targetLabel, $targetPath),
            'what_will_change' => $whatWillChange,
            'has_modification_plan' => $hasPlan,
            'live_site_impact' => $liveImpact['code'],
            'live_site_impact_label' => $liveImpact['label'],
            'live_site_impact_detail' => $liveImpact['detail'],
            'will_modify_live_site' => $liveImpact['will_modify'],
            'button_label' => $this->buttonLabel($pageKind, $workflow, $applyReady),
            'button_explanation' => $this->buttonExplanation($pageKind, $workflow, $applyReady, $targetLabel, $targetPath),
        ];
    }

    private function pageKind(string $siteId, string $slug, mixed $pageId, string $workflow): string
    {
        if ($workflow === 'generate') {
            return 'new_content';
        }

        if ($this->hasStudioPage($siteId, $slug, $pageId)) {
            return 'studio';
        }

        if ($this->hasObservedPage($siteId, $slug)) {
            return 'observed';
        }

        return 'unknown';
    }

    private function pageKindLabel(string $pageKind): string
    {
        return match ($pageKind) {
            'studio' => 'Article géré par PraeviSEO',
            'observed' => 'Page déjà sur votre site',
            'new_content' => 'Nouveau contenu à créer',
            default => 'Page à clarifier',
        };
    }

    private function targetPath(string $slug, string $workflow): ?string
    {
        if ($workflow === 'generate' && $slug === '') {
            return null;
        }

        if ($slug === '') {
            return '/';
        }

        return '/'.ltrim($slug, '/');
    }

    private function targetUrl(string $siteId, string $slug, ?string $siteUrl): ?string
    {
        $path = $slug !== '' ? '/'.ltrim($slug, '/') : '/';
        $observed = SeoSitePage::query()
            ->where('site_id', $siteId)
            ->where('path', $path)
            ->orderByDesc('last_seen_at')
            ->first();

        if ($observed?->normalized_url) {
            return (string) $observed->normalized_url;
        }

        $baseUrl = trim((string) ($siteUrl ?? SeoSite::query()->where('site_id', $siteId)->value('url') ?? ''));

        if ($baseUrl === '') {
            return null;
        }

        return rtrim($baseUrl, '/').($slug !== '' ? '/'.ltrim($slug, '/') : '/');
    }

    /**
     * @param  array{sections?:array<int,string>,topics?:array<int,string>,faq?:array<int,string>,content_summary?:string,title_change?:string|null}  $modificationPlan
     */
    private function hasModificationPlan(array $modificationPlan): bool
    {
        return ($modificationPlan['sections'] ?? []) !== []
            || ($modificationPlan['topics'] ?? []) !== []
            || ($modificationPlan['faq'] ?? []) !== []
            || trim((string) ($modificationPlan['content_summary'] ?? '')) !== ''
            || trim((string) ($modificationPlan['title_change'] ?? '')) !== '';
    }

    /**
     * @param  array{sections?:array<int,string>,topics?:array<int,string>,faq?:array<int,string>,content_summary?:string,title_change?:string|null}  $modificationPlan
     */
    private function whatWillChange(array $modificationPlan, string $workflow): string
    {
        $summary = trim((string) ($modificationPlan['content_summary'] ?? ''));
        $parts = [];

        if ($summary !== '') {
            $parts[] = $summary;
        }

        $sections = array_values(array_filter((array) ($modificationPlan['sections'] ?? [])));
        if ($sections !== []) {
            $parts[] = 'Sections : '.implode(' ; ', array_slice($sections, 0, 2));
        }

        $faq = array_values(array_filter((array) ($modificationPlan['faq'] ?? [])));
        if ($faq !== []) {
            $parts[] = 'FAQ : '.implode(' ; ', array_slice($faq, 0, 2));
        }

        $topics = array_values(array_filter((array) ($modificationPlan['topics'] ?? [])));
        if ($topics !== []) {
            $parts[] = 'Sujets : '.implode(' ; ', array_slice($topics, 0, 2));
        }

        $titleChange = trim((string) ($modificationPlan['title_change'] ?? ''));
        if ($titleChange !== '') {
            $parts[] = 'Titre proposé : '.$titleChange;
        }

        if ($parts !== []) {
            return implode(' ', $parts);
        }

        return match ($workflow) {
            'generate' => 'PraeviSEO préparera un nouveau brouillon éditorial autour de ce sujet.',
            'linking' => 'PraeviSEO renforcera les liens internes vers et depuis cette page.',
            default => 'PraeviSEO proposera un enrichissement ciblé du contenu existant.',
        };
    }

    private function whyThisAction(string $pageKind, string $targetLabel, ?string $targetPath): string
    {
        $pathSuffix = $targetPath ? ' ('.$targetPath.')' : '';

        return match ($pageKind) {
            'observed' => sprintf(
                'PraeviSEO voit déjà « %s »%s dans Google et sur votre site. Cette action vise à renforcer cette page précise.',
                $targetLabel,
                $pathSuffix,
            ),
            'studio' => sprintf(
                '« %s » est déjà suivie dans le studio PraeviSEO%s. Cette action peut être préparée puis appliquée depuis le moteur.',
                $targetLabel,
                $pathSuffix,
            ),
            'new_content' => sprintf(
                'Google montre un intérêt pour « %s ». PraeviSEO propose de créer un contenu dédié avant toute publication.',
                $targetLabel,
            ),
            default => sprintf('PraeviSEO a identifié un levier utile autour de « %s ».', $targetLabel),
        };
    }

    /**
     * @return array{code:string,label:string,detail:string,will_modify:bool}
     */
    private function liveSiteImpact(string $pageKind, string $workflow, bool $applyReady, ?SeoPage $studioPage): array
    {
        if ($pageKind === 'observed') {
            return [
                'code' => 'advisory_only',
                'label' => 'Conseil pour l’instant',
                'detail' => 'PraeviSEO ne modifie pas encore automatiquement cette page sur votre site. Vous voyez ici le plan à appliquer, puis une future version pourra le publier directement.',
                'will_modify' => false,
            ];
        }

        if ($workflow === 'generate') {
            return [
                'code' => 'draft_only',
                'label' => 'Brouillon d’abord',
                'detail' => 'Le clic crée ou enrichit un brouillon dans PraeviSEO. Rien n’est envoyé sur votre site tant que vous n’avez pas validé la publication.',
                'will_modify' => false,
            ];
        }

        if ($pageKind === 'studio' && $applyReady) {
            $publishedLive = $studioPage?->isPublishedLive() ?? false;

            return [
                'code' => $publishedLive ? 'live_auto' : 'studio_then_publish',
                'label' => $publishedLive ? 'Peut modifier le site' : 'Studio puis publication',
                'detail' => $publishedLive
                    ? 'Si vous appliquez maintenant, PraeviSEO peut réécrire cette page puis la republier sur l’URL live déjà suivie.'
                    : 'PraeviSEO appliquera d’abord la modification dans le studio. La page live ne changera qu’après publication explicite.',
                'will_modify' => $publishedLive,
            ];
        }

        return [
            'code' => 'review_first',
            'label' => 'À vérifier avant action',
            'detail' => 'Ouvrez d’abord la page concernée pour voir le plan, la cible exacte et le mode d’application avant toute modification.',
            'will_modify' => false,
        ];
    }

    private function buttonLabel(string $pageKind, string $workflow, bool $applyReady): string
    {
        if ($applyReady) {
            return match ($workflow) {
                'generate' => 'Créer le brouillon',
                'linking' => 'Appliquer le maillage',
                default => 'Appliquer automatiquement',
            };
        }

        if ($pageKind === 'observed') {
            return 'Voir la prévisualisation';
        }

        return 'Voir la prévisualisation';
    }

    private function buttonExplanation(
        string $pageKind,
        string $workflow,
        bool $applyReady,
        string $targetLabel,
        ?string $targetPath,
    ): string {
        $pathSuffix = $targetPath ? ' ('.$targetPath.')' : '';

        if ($applyReady && $workflow === 'generate') {
            return 'Crée un brouillon PraeviSEO. Aucune page live n’est modifiée tant que vous ne publiez pas.';
        }

        if ($applyReady && $pageKind === 'studio') {
            return sprintf('Lance la modification sur « %s » depuis le moteur PraeviSEO.', $targetLabel);
        }

        if ($pageKind === 'observed') {
            return sprintf(
                'Affiche ce que PraeviSEO voit aujourd’hui sur « %s »%s et ce qu’il propose d’ajouter. Aucune modification n’est envoyée sur votre site tant que vous ne confirmez pas.',
                $targetLabel,
                $pathSuffix,
            );
        }

        return sprintf(
            'Compare l’état actuel et la version proposée pour « %s »%s avant toute application.',
            $targetLabel,
            $pathSuffix,
        );
    }

    private function resolveStudioPage(string $siteId, string $slug, mixed $pageId): ?SeoPage
    {
        if ($pageId) {
            return SeoPage::query()->where('site_id', $siteId)->whereKey($pageId)->first();
        }

        if ($slug === '') {
            return null;
        }

        return SeoPage::query()
            ->where('site_id', $siteId)
            ->where('slug', $slug)
            ->first();
    }

    private function hasStudioPage(string $siteId, string $slug, mixed $pageId): bool
    {
        return $this->resolveStudioPage($siteId, $slug, $pageId) !== null;
    }

    private function hasObservedPage(string $siteId, string $slug): bool
    {
        if ($siteId === '' || $slug === '') {
            return false;
        }

        return SeoSitePage::query()
            ->where('site_id', $siteId)
            ->where('path', '/'.ltrim($slug, '/'))
            ->exists();
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

        if ($this->hasStudioPage($siteId, $slug, $pageId)) {
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
}
