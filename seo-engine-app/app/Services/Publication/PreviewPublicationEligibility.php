<?php

declare(strict_types=1);

namespace App\Services\Publication;

use App\Models\SeoSite;

final class PreviewPublicationEligibility
{
    public function __construct(
        private readonly SeoLivePublicationService $livePublication,
        private readonly ObservedNativePublicationGuard $nativeGuard,
    ) {}

    /**
     * @param  array<string,mixed>  $preview
     * @return array<string,mixed>
     */
    public function forPreview(SeoSite $site, array $preview): array
    {
        $applyContext = is_array($preview['apply_context'] ?? null) ? $preview['apply_context'] : [];
        $pageKind = (string) ($applyContext['page_kind'] ?? 'unknown');
        $modificationPlan = is_array($preview['modification_plan'] ?? null) ? $preview['modification_plan'] : [];
        $targetStatus = $this->livePublication->targetStatusForSite($site);
        $hasPlan = $this->hasModificationPlan($modificationPlan);

        if ($pageKind !== 'observed') {
            return [
                'can_confirm_publish' => false,
                'confirm_publish_blocked_reason' => 'La publication native depuis la prévisualisation concerne les pages déjà sur votre site, pas les articles studio.',
                'confirm_publish_detail' => null,
                'confirm_publish_button_label' => 'Confirmer et publier',
                'requires_manual_validation' => false,
            ];
        }

        if ($site->resolvedPublicationMode() === 'disabled') {
            return [
                'can_confirm_publish' => false,
                'confirm_publish_blocked_reason' => 'La publication externe est désactivée pour ce site.',
                'confirm_publish_detail' => null,
                'confirm_publish_button_label' => 'Confirmer et publier',
                'requires_manual_validation' => false,
            ];
        }

        if (! (bool) ($targetStatus['engine_actionable'] ?? false)) {
            return [
                'can_confirm_publish' => false,
                'confirm_publish_blocked_reason' => 'Le connecteur de publication n est pas encore prêt (endpoint ou secret manquant).',
                'confirm_publish_detail' => null,
                'confirm_publish_button_label' => 'Confirmer et publier',
                'requires_manual_validation' => false,
            ];
        }

        if ($site->publicationBridgeStatus() !== 'connected') {
            return [
                'can_confirm_publish' => false,
                'confirm_publish_blocked_reason' => 'Le bridge client n est pas encore connecté. Finalisez la connexion avant de publier sur l URL native.',
                'confirm_publish_detail' => null,
                'confirm_publish_button_label' => 'Confirmer et publier',
                'requires_manual_validation' => false,
            ];
        }

        if (! $hasPlan) {
            return [
                'can_confirm_publish' => false,
                'confirm_publish_blocked_reason' => 'Aucun enrichissement concret n a encore été proposé pour cette page.',
                'confirm_publish_detail' => null,
                'confirm_publish_button_label' => 'Confirmer et publier',
                'requires_manual_validation' => false,
            ];
        }

        $slug = (string) ($preview['slug'] ?? '');
        $targetPath = (string) ($applyContext['target_path'] ?? '/'.ltrim($slug, '/'));

        if ($this->nativeGuard->isHomepage($slug, $targetPath)) {
            return [
                'can_confirm_publish' => false,
                'confirm_publish_blocked_reason' => $this->nativeGuard->homepageBlockedReason(),
                'confirm_publish_detail' => 'La prévisualisation reste disponible pour valider le plan, mais la publication automatique est volontairement désactivée sur la page d accueil.',
                'confirm_publish_button_label' => 'Confirmer et publier',
                'requires_manual_validation' => true,
            ];
        }

        $targetUrl = (string) ($applyContext['target_url'] ?? '');

        return [
            'can_confirm_publish' => true,
            'confirm_publish_blocked_reason' => null,
            'confirm_publish_detail' => sprintf(
                'PraeviSEO va pousser les enrichissements validés vers %s sans créer de nouvelle URL /ressources/.',
                $targetUrl !== '' ? $targetUrl : $targetPath,
            ),
            'confirm_publish_button_label' => 'Confirmer et publier',
            'requires_manual_validation' => false,
        ];
    }

    /**
     * @param  array{sections?:array<int,string>,topics?:array<int,string>,faq?:array<int,string>,content_summary?:string,title_change?:string|null}  $plan
     */
    private function hasModificationPlan(array $plan): bool
    {
        return ($plan['sections'] ?? []) !== []
            || ($plan['faq'] ?? []) !== []
            || ($plan['topics'] ?? []) !== []
            || trim((string) ($plan['content_summary'] ?? '')) !== ''
            || trim((string) ($plan['title_change'] ?? '')) !== '';
    }
}
