export const dynamic = "force-dynamic";

import { ActionApplyContextPanel } from "@/components/cockpit/action-apply-context-panel";
import { BusinessCopilotApplyButton } from "@/components/cockpit/business-copilot-apply-button";
import { ConfirmPreviewPublishButton } from "@/components/cockpit/confirm-preview-publish-button";
import { ModificationPreviewDiff } from "@/components/cockpit/modification-preview-diff";
import { Topbar } from "@/components/layout/topbar";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { emptyActionApplyContext, getActionPreview } from "@/lib/praeviseo-api";
import { ArrowLeft, ExternalLink } from "lucide-react";

type PreviewSearchParams = Promise<Record<string, string | string[] | undefined>>;

function getValue(value: string | string[] | undefined, fallback = ""): string {
  if (Array.isArray(value)) {
    return value[0] ?? fallback;
  }

  return value ?? fallback;
}

export default async function ActionPreviewPage({ searchParams }: { searchParams?: PreviewSearchParams }) {
  const resolvedSearchParams = searchParams ? await searchParams : {};
  const siteId = getValue(resolvedSearchParams.site);
  const slug = getValue(resolvedSearchParams.slug);
  const query = getValue(resolvedSearchParams.query);
  const preview = siteId && slug ? await getActionPreview(siteId, slug, query || undefined) : null;
  const returnTo = `/optimizations?site=${encodeURIComponent(siteId)}&slug=${encodeURIComponent(slug)}`;
  const applyContext = preview?.apply_context ?? emptyActionApplyContext;

  return (
    <div className="min-h-screen">
      <Topbar
        title="Prévisualisation avant action"
        subtitle="Comparez l’état actuel de la page et les enrichissements proposés avant toute modification."
        actions={
          <Button href="/optimizations" variant="secondary" size="sm">
            <ArrowLeft className="h-4 w-4" />
            Retour aux actions
          </Button>
        }
      />

      <div className="space-y-6 p-6">
        {!preview ? (
          <Card>
            <CardHeader>
              <CardTitle>Prévisualisation indisponible</CardTitle>
              <CardDescription>
                PraeviSEO n’a pas encore assez de données pour comparer cette page. Vérifiez le site, le slug, ou relancez un crawl.
              </CardDescription>
            </CardHeader>
            <CardContent>
              <Button href="/optimizations">Retour aux actions</Button>
            </CardContent>
          </Card>
        ) : (
          <>
            <ActionApplyContextPanel context={applyContext} />

            <Card className="border-border-subtle bg-surface/90">
              <CardHeader>
                <CardTitle>Avant / Après pour « {preview.apply_context.target_label} »</CardTitle>
                <CardDescription>
                  {preview.site_name} · {preview.apply_context.target_path ?? `/${preview.slug}`}
                  {preview.current.observed_at ? ` · dernière lecture ${new Date(preview.current.observed_at).toLocaleDateString("fr-FR")}` : ""}
                </CardDescription>
              </CardHeader>
              <CardContent>
                <ModificationPreviewDiff preview={preview} />
              </CardContent>
            </Card>

            <Card className="border-border-subtle bg-surface/90">
              <CardHeader>
                <CardTitle>Étape suivante</CardTitle>
                <CardDescription>
                  {preview.apply_ready
                    ? "Vous pouvez appliquer depuis le studio si cette page est déjà gérée par PraeviSEO."
                    : preview.requires_manual_validation
                      ? "La page d’accueil reste en validation obligatoire : comparez le plan ici, puis finalisez la publication avec votre référent."
                      : preview.can_confirm_publish
                        ? "Validez le plan puis publiez directement sur l’URL native de votre site."
                        : "Cette prévisualisation sert à valider le plan avant toute publication native sur votre site."}
                </CardDescription>
              </CardHeader>
              <CardContent className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div className="flex flex-wrap gap-2">
                  <Button href="/optimizations" variant="secondary">
                    Retour aux actions
                  </Button>
                  <Button
                    href={`/pages?focus=content&site=${encodeURIComponent(preview.site_id)}&target=${encodeURIComponent(preview.slug)}`}
                    variant="secondary"
                  >
                    Voir la fiche page
                  </Button>
                  {preview.current.live_url ? (
                    <Button href={preview.current.live_url} variant="secondary" external>
                      Voir la page live
                      <ExternalLink className="h-4 w-4" />
                    </Button>
                  ) : null}
                </div>

                {preview.can_confirm_publish ? (
                  <ConfirmPreviewPublishButton preview={preview} returnTo={returnTo} />
                ) : preview.apply_ready ? (
                  <BusinessCopilotApplyButton
                    action={{
                      rank: 1,
                      source: "gsc_opportunity",
                      source_id: `preview-${preview.site_id}-${preview.slug}`,
                      site_id: preview.site_id,
                      site_name: preview.site_name,
                      page_id: null,
                      slug: preview.slug,
                      query: preview.query,
                      subject: preview.apply_context.target_label,
                      action_verb: "appliquer",
                      headline: preview.apply_context.target_label,
                      card_title: preview.apply_context.target_label,
                      problem_plain: "",
                      why_plain: "",
                      action_label: "",
                      action_detail: "",
                      gain_basis: "",
                      monthly_gain_visitors: 0,
                      monthly_gain_min: 0,
                      monthly_gain_max: 0,
                      gain_display: "",
                      estimated_volume: null,
                      current_position: null,
                      effort_level: "medium",
                      effort_label: "Moyen",
                      effort_minutes: 30,
                      effort_display: "",
                      apply_mode: "",
                      apply_workflow: preview.apply_workflow,
                      apply_ready: true,
                      apply_href: preview.apply_href ?? "/publications",
                      apply_context: preview.apply_context,
                    }}
                    featured
                    returnTo={returnTo}
                  />
                ) : (
                  <ConfirmPreviewPublishButton preview={preview} returnTo={returnTo} />
                )}
              </CardContent>
            </Card>
          </>
        )}
      </div>
    </div>
  );
}
