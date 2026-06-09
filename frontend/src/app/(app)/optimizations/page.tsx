export const dynamic = "force-dynamic";

import { BusinessCopilotPriority } from "@/components/cockpit/business-copilot";
import { CopilotFeedbackBanner } from "@/components/cockpit/copilot-feedback-banner";
import { Badge } from "@/components/ui/badge";
import { CockpitSectionNav } from "@/components/cockpit/section-nav";
import { Topbar } from "@/components/layout/topbar";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import {
  getClientDifficultyLabel,
  getClientImpactLabel,
  getClientOpportunityPriorityLabel,
  getClientOpportunityStateLabel,
  getClientOpportunityTypeLabel,
  getClientRecommendationBadge,
  getOptimizations,
  getSitePath,
  type PraeviseoGscOpportunity,
} from "@/lib/praeviseo-api";
import { formatDate } from "@/lib/utils";

type PageSearchParams = Promise<Record<string, string | string[] | undefined>>;

function getValue(value: string | string[] | undefined, fallback = ""): string {
  if (Array.isArray(value)) {
    return value[0] ?? fallback;
  }

  return value ?? fallback;
}

function opportunityModificationHint(item: PraeviseoGscOpportunity) {
  const preview = item.modification_preview;
  if (!preview?.sections?.length && !preview?.faq?.length) {
    return null;
  }

  return (
    <div className="mt-2 space-y-1 rounded-lg bg-surface-2 px-3 py-2 text-xs text-text-subtle">
      {preview.sections[0] ? <p>Section : {preview.sections[0]}</p> : null}
      {preview.faq[0] ? <p>FAQ : {preview.faq[0]}</p> : null}
    </div>
  );
}

function opportunityMetricLine(metrics: Record<string, number | string>): string {
  const impressions = Number(metrics.impressions ?? 0);
  const ctr = Number(metrics.ctr ?? 0);
  const position = Number(metrics.position ?? 0);
  const previousImpressions = Number(metrics.previous_impressions ?? 0);

  if (impressions <= 0) {
    return "Signal encore léger dans Google, position à confirmer.";
  }

  if (previousImpressions > 0) {
    return `${new Intl.NumberFormat("fr-FR").format(impressions)} impressions recentes, ${new Intl.NumberFormat("fr-FR").format(previousImpressions)} avant, position ${position.toFixed(1)}`;
  }

  return `${new Intl.NumberFormat("fr-FR").format(impressions)} affichage(s) dans Google, avec une présence moyenne autour de la ${Math.round(position)}e place`;
}

export default async function OptimizationsPage({ searchParams }: { searchParams?: PageSearchParams }) {
  const optimizations = await getOptimizations();
  const resolvedSearchParams = searchParams ? await searchParams : {};
  const copilotFeedback = getValue(resolvedSearchParams.copilot_feedback) || null;
  const copilotFeedbackTitle = getValue(resolvedSearchParams.copilot_title) || null;
  const copilotFeedbackDetail = getValue(resolvedSearchParams.copilot_detail) || null;
  const focus = getValue(resolvedSearchParams.focus);
  const focusSite = getValue(resolvedSearchParams.site);
  const focusQuery = getValue(resolvedSearchParams.query);
  const focusSlug = getValue(resolvedSearchParams.slug);
  const opportunities = optimizations.gsc_opportunities.items;
  const observedRecommendations = optimizations.recommendations.items;
  const recommendationOpportunityCount = optimizations.recommendations.summary.total;
  const gscOpportunityCount = optimizations.gsc_opportunities.summary.total;
  const queryWatchlist = opportunities.filter((item) => item.query).slice(0, 4);
  const pagesToRefresh = opportunities.filter((item) => item.type === "near_top_10" || item.type === "sustained_drop").slice(0, 4);
  const quickWins = opportunities.filter((item) => item.priority_level === "high" || item.action_state === "ready").slice(0, 4);
  const actionPlan = observedRecommendations.slice(0, 4);
  const leadOpportunity = quickWins[0] ?? opportunities[0] ?? null;
  const leadRecommendation = actionPlan[0] ?? null;
  const summarySignals = [
    optimizations.gsc_opportunities.summary.near_top_10 > 0
      ? `${optimizations.gsc_opportunities.summary.near_top_10} page(s) peuvent gagner des visiteurs avec un renfort ciblé`
      : null,
    optimizations.gsc_opportunities.summary.low_ctr > 0
      ? `${optimizations.gsc_opportunities.summary.low_ctr} page(s) attirent encore trop peu de clics`
      : null,
    optimizations.gsc_opportunities.summary.emerging_queries > 0
      ? `${optimizations.gsc_opportunities.summary.emerging_queries} requête(s) progressent rapidement`
      : null,
    optimizations.gsc_opportunities.summary.sustained_drop > 0
      ? `${optimizations.gsc_opportunities.summary.sustained_drop} page(s) perdent de la visibilité`
      : null,
  ].filter((item): item is string => item !== null);
  const liveMoments = [
    {
      label: "Signaux Google détectés",
      value: gscOpportunityCount,
      detail:
        gscOpportunityCount > 0
          ? "Google a déjà remonté des signaux exploitables"
          : "aucun levier fort n’est encore remonté",
      tone: gscOpportunityCount > 0 ? "warning" : "secondary",
    },
    {
      label: "Pages proches d’un gain",
      value: optimizations.gsc_opportunities.summary.near_top_10,
      detail:
        optimizations.gsc_opportunities.summary.near_top_10 > 0
          ? "un renfort ciblé peut faire la différence"
          : "pas de page chaude à renforcer tout de suite",
      tone: optimizations.gsc_opportunities.summary.near_top_10 > 0 ? "success" : "secondary",
    },
    {
      label: "Clics à améliorer",
      value: optimizations.gsc_opportunities.summary.low_ctr,
      detail:
        optimizations.gsc_opportunities.summary.low_ctr > 0
          ? "des pages sont vues mais sous-cliquées"
          : "aucune page nettement sous-cliquée pour le moment",
      tone: optimizations.gsc_opportunities.summary.low_ctr > 0 ? "warning" : "secondary",
    },
    {
      label: "Plan d’action prêt",
      value: optimizations.recommendations.summary.total,
      detail:
        optimizations.recommendations.summary.total > 0
          ? "PraeviSEO a déjà des actions concrètes à recommander"
          : "aucune action observée forte pour le moment",
      tone: optimizations.recommendations.summary.total > 0 ? "warning" : "secondary",
    },
  ] as const;
  const timelineFeed = [
    ...opportunities.slice(0, 4).map((item) => ({
      id: `opportunity-${item.site_id}-${item.slug}-${item.type}`,
      title: item.label,
      detail: item.reason,
      badge: item.action_state_label,
      badgeVariant: item.action_state === "ready" ? "success" : item.action_state === "pending" ? "warning" : "secondary",
      meta: `${item.site_name} · signal Google récent`,
      timestamp: 0,
    })),
    ...optimizations.items.slice(0, 4).map((item) => ({
      id: `optimization-${item.id}`,
      title: item.page.title,
      detail: item.summary,
      badge: item.status === "pending" ? "Action en cours" : "Déjà traitée",
      badgeVariant: item.status === "pending" ? "warning" : "secondary",
      meta: item.created_at ? formatDate(item.created_at) : "Récemment",
      timestamp: item.created_at ? new Date(item.created_at).getTime() : 0,
    })),
    ...observedRecommendations.slice(0, 4).map((item) => ({
      id: `recommendation-${item.id}`,
      title: item.title,
      detail: item.suggested_action ?? item.reasoning,
      badge: item.priority <= 30 ? "À faire en premier" : "Action recommandée",
      badgeVariant: item.priority <= 30 ? "warning" : "secondary",
      meta: item.generated_at ? formatDate(item.generated_at) : `${item.site_id} · recommandation observée`,
      timestamp: item.generated_at ? new Date(item.generated_at).getTime() : 0,
    })),
  ]
    .sort((a, b) => b.timestamp - a.timestamp)
    .slice(0, 6);
  const optimizationDecisionBlocks = [
    {
      title: "Gain estimé",
      items: [
        leadRecommendation
          ? `${getClientImpactLabel(leadRecommendation.estimated_impact)} sur l’action ${leadRecommendation.title}.`
          : "Le prochain gain estimé apparaîtra ici dès qu’une recommandation claire remontera.",
        leadOpportunity
          ? `Google montre déjà un levier concret sur ${leadOpportunity.label}.`
          : "Les prochains signaux Google utiles apparaîtront ici automatiquement.",
      ],
    },
    {
      title: "Effort estimé",
      items: [
        leadRecommendation
          ? `${getClientDifficultyLabel(leadRecommendation.difficulty)} pour la meilleure action actuellement suggérée.`
          : "PraeviSEO précisera l’effort requis dès qu’une action sortira du lot.",
        pagesToRefresh.length > 0
          ? `${pagesToRefresh.length} page(s) peuvent déjà être renforcées sans chantier trop lourd.`
          : "Aucune page chaude à renforcer rapidement pour le moment.",
      ],
    },
    {
      title: "Priorité",
      items: [
        leadOpportunity
          ? `${getClientOpportunityPriorityLabel(leadOpportunity.priority_label)} sur ${leadOpportunity.label}.`
          : "Aucune priorité très forte n’est encore remontée.",
        optimizations.gsc_opportunities.summary.high_priority > 0
          ? `${optimizations.gsc_opportunities.summary.high_priority} opportunité(s) sont déjà classées en priorité haute.`
          : "Les prochaines priorités fortes apparaîtront ici quand le signal deviendra assez net.",
      ],
    },
    {
      title: "Action recommandée",
      items: [
        leadRecommendation?.suggested_action ?? leadOpportunity?.action ?? "PraeviSEO affichera ici l’action la plus utile dès qu’elle sera claire.",
        actionPlan[0]?.reasoning ?? "PraeviSEO continue de comparer les prochains gains possibles.",
      ],
    },
  ] as const;
  const opportunityActions = (item: (typeof opportunities)[number]) => {
    const actions: Array<{ label: string; href: string; variant?: "primary" | "secondary" }> = [];

    if (item.type === "emerging_query" && item.query) {
      actions.push({
        label: "Créer l’article ciblé",
        href: `/publications?focus=query&site=${encodeURIComponent(item.site_id)}&query=${encodeURIComponent(item.query)}`,
        variant: "primary",
      });
    } else if (item.type === "near_top_10" || item.type === "sustained_drop") {
      actions.push({
        label: item.slug ? "Voir la page concernée" : "Voir les pages",
        href: item.slug
          ? `/publications?focus=content&site=${encodeURIComponent(item.site_id)}&slug=${encodeURIComponent(item.slug)}`
          : `/pages?focus=refresh&site=${encodeURIComponent(item.site_id)}&target=${encodeURIComponent(item.label)}`,
        variant: "primary",
      });
    } else if (item.type === "low_ctr") {
      actions.push({
        label: item.slug ? "Voir la page concernée" : "Voir la visibilité Google",
        href: item.slug
          ? `/publications?focus=content&site=${encodeURIComponent(item.site_id)}&slug=${encodeURIComponent(item.slug)}`
          : `/sites/${item.site_id}/search-console`,
        variant: "primary",
      });
    } else {
      actions.push({
        label: "Ouvrir l’automatisation",
        href: `/sites/${item.site_id}/automation`,
        variant: "primary",
      });
    }

    if (item.query) {
      actions.push({
        label: "Voir les requêtes",
        href: `/queries?focus=query&site=${encodeURIComponent(item.site_id)}&query=${encodeURIComponent(item.query)}`,
        variant: "secondary",
      });
    } else if (item.slug) {
      actions.push({
        label: "Voir les pages",
        href: `/pages?focus=content&site=${encodeURIComponent(item.site_id)}&target=${encodeURIComponent(item.slug)}`,
        variant: "secondary",
      });
    } else {
      actions.push({
        label: "Ouvrir le site",
        href: getSitePath(item.site_id),
        variant: "secondary",
      });
    }

    return actions;
  };

  const recommendationActions = (item: (typeof observedRecommendations)[number]) => {
    const suggestionText = `${item.title} ${item.suggested_action ?? ""} ${item.reasoning}`.toLowerCase();
    const actions: Array<{ label: string; href: string; variant?: "primary" | "secondary" }> = [];

    if (item.type === "create_page" || suggestionText.includes("nouvelle page") || suggestionText.includes("nouveau contenu")) {
      actions.push({
        label: "Créer le contenu ciblé",
        href: `/publications?focus=query&site=${encodeURIComponent(item.site_id)}&query=${encodeURIComponent(item.cluster ?? item.title)}`,
        variant: "primary",
      });
      actions.push({
        label: "Voir les requêtes",
        href: `/queries?focus=query&site=${encodeURIComponent(item.site_id)}&query=${encodeURIComponent(item.cluster ?? item.title)}`,
        variant: "secondary",
      });
    } else if (item.type === "refresh_page" || suggestionText.includes("réécri") || suggestionText.includes("rafraîch")) {
      actions.push({
        label: "Voir les pages à renforcer",
        href: `/pages?focus=refresh&site=${encodeURIComponent(item.site_id)}&target=${encodeURIComponent(item.title)}`,
        variant: "primary",
      });
      actions.push({
        label: "Ouvrir l’automatisation",
        href: `/sites/${item.site_id}/automation`,
        variant: "secondary",
      });
    } else if (item.type === "add_internal_links" || suggestionText.includes("maillage") || suggestionText.includes("lien")) {
      actions.push({
        label: "Voir les pages à relier",
        href: `/pages?focus=linking&site=${encodeURIComponent(item.site_id)}&target=${encodeURIComponent(item.title)}`,
        variant: "primary",
      });
      actions.push({
        label: "Ouvrir l’automatisation",
        href: `/sites/${item.site_id}/automation`,
        variant: "secondary",
      });
    } else {
      actions.push({
        label: "Ouvrir l’automatisation",
        href: `/sites/${item.site_id}/automation`,
        variant: "primary",
      });
      actions.push({
        label: "Voir les optimisations",
        href: "/optimizations",
        variant: "secondary",
      });
    }

    return actions;
  };
  const focusMessage =
    focus === "query"
      ? {
          title: "Opportunité ouverte depuis une requête",
          detail: `${focusQuery || "Cette requête"} a été envoyée ici pour décider si elle doit ouvrir un contenu, renforcer une page ou rester en veille. Si le sujet est assez net, ouvre maintenant le studio éditorial.`,
          href: `/publications?focus=query&site=${encodeURIComponent(focusSite)}&query=${encodeURIComponent(focusQuery)}`,
        }
      : focusSite && focusSlug
        ? {
            title: `Action ouverte pour « ${focusSlug} »`,
            detail:
              "PraeviSEO a préparé un plan de modification pour cette page. Ouvrez le studio pour voir le contenu et appliquer les changements proposés.",
            href: `/pages?focus=content&site=${encodeURIComponent(focusSite)}&target=${encodeURIComponent(focusSlug)}`,
          }
        : null;

  return (
    <div className="min-h-screen">
      <Topbar
        title="Actions métier"
        subtitle="Classées par gain potentiel : ce qui bloque, quoi faire, combien de temps, comment l’appliquer."
        actions={
          <div className="flex items-center gap-2">
            <Button href="/sites" size="sm">
              Lancer les actions
            </Button>
            <Button href="/dashboard" variant="secondary" size="sm">
              Retour au dashboard
            </Button>
          </div>
        }
      />
      <div className="p-6 space-y-6">
        <CockpitSectionNav
          items={[
            {
              label: "Priorité du jour",
              href: "#priorite-jour",
              count: optimizations.business_copilot.daily_priority.length,
              tone: "warning",
            },
            { label: "Vue d’ensemble", href: "#vue-ensemble", count: recommendationOpportunityCount, tone: "default" },
            { label: "Gains rapides", href: "#gains-rapides", count: quickWins.length, tone: "warning" },
            { label: "Pages à renforcer", href: "#pages-refresh", count: pagesToRefresh.length, tone: "secondary" },
            { label: "Requêtes Google", href: "#requetes", count: queryWatchlist.length, tone: "success" },
            { label: "Plan d’action", href: "#plan-action", count: actionPlan.length, tone: "warning" },
            { label: "Activité", href: "#activite", count: optimizations.items.length, tone: "default" },
          ]}
        />

        <CopilotFeedbackBanner
          feedback={copilotFeedback}
          title={copilotFeedbackTitle}
          detail={copilotFeedbackDetail}
        />

        <div id="priorite-jour" className="scroll-mt-24">
          <BusinessCopilotPriority copilot={optimizations.business_copilot} returnTo="/optimizations" />
        </div>

        <div className="rounded-2xl border border-brand/20 bg-brand-muted px-6 py-6">
          <h1 className="text-2xl font-bold tracking-tight text-text">Toutes les actions classées par impact business</h1>
          <p className="mt-2 max-w-3xl text-sm leading-7 text-text-muted">
            Au-delà de la priorité du jour, retrouvez ici le détail des signaux Google et des actions recommandées — toujours traduits en gains visiteurs et modifications concrètes.
          </p>
          <div className="mt-4 flex flex-wrap gap-3">
            <Button href="/sites">
              Lancer les actions
            </Button>
            <Button href="/activity" variant="secondary">
              Voir le contexte récent
            </Button>
          </div>
        </div>

        {focusMessage ? (
          <div className="rounded-2xl border border-brand/20 bg-brand-muted px-5 py-4">
            <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
              <div>
                <div className="text-sm font-semibold text-text">{focusMessage.title}</div>
                <p className="mt-2 text-sm leading-6 text-text-muted">
                  {focusMessage.detail}
                  {focusSite ? ` Site ciblé : ${focusSite}.` : ""}
                </p>
              </div>
              <Button href={focusMessage.href} size="sm">
                Ouvrir la bonne section
              </Button>
            </div>
          </div>
        ) : null}

        <div className="grid gap-4 xl:grid-cols-4">
          {optimizationDecisionBlocks.map((block) => (
            <Card key={block.title}>
              <CardHeader>
                <CardTitle className="text-base">{block.title}</CardTitle>
              </CardHeader>
              <CardContent className="space-y-3">
                {block.items.map((item) => (
                  <div key={item} className="rounded-2xl border border-border bg-surface-2 px-4 py-4 text-sm leading-6 text-text-muted">
                    {item}
                  </div>
                ))}
              </CardContent>
            </Card>
          ))}
        </div>

        <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
          {liveMoments.map((item) => (
            <Card key={item.label} className="border-border-subtle bg-surface/80">
              <CardHeader className="pb-3">
                <div className="flex items-center justify-between gap-3">
                  <CardDescription>{item.label}</CardDescription>
                  <Badge variant={item.tone}>{String(item.value)}</Badge>
                </div>
                <CardTitle className="text-sm leading-6 text-text-muted font-medium">{item.detail}</CardTitle>
              </CardHeader>
            </Card>
          ))}
        </div>

        <div id="vue-ensemble" className="grid gap-4 md:grid-cols-2 xl:grid-cols-4 scroll-mt-24">
          {[
            ["Actionnables maintenant", optimizations.gsc_opportunities.summary.ready],
            ["Priorité haute", optimizations.gsc_opportunities.summary.high_priority],
            ["Proches d’un gain rapide", optimizations.gsc_opportunities.summary.near_top_10],
            ["Clics à améliorer", optimizations.gsc_opportunities.summary.low_ctr],
          ].map(([label, value]) => (
            <Card key={label}>
              <CardHeader className="pb-2">
                <CardDescription>{label}</CardDescription>
                <CardTitle className="text-3xl">{value}</CardTitle>
              </CardHeader>
            </Card>
          ))}
        </div>

        <div className="rounded-2xl border border-border bg-surface px-5 py-4">
          <div className="text-sm font-semibold text-text">Ce que PraeviSEO voit dans Google en ce moment</div>
          <div className="mt-3 flex flex-wrap gap-2">
            {summarySignals.length === 0 ? (
              <div className="text-sm text-text-muted">
                Aucun signal fort supplémentaire pour le moment. Le cockpit continue de surveiller les prochains imports GSC.
              </div>
            ) : (
              summarySignals.map((item) => (
                <span key={item} className="rounded-full border border-border bg-surface-2 px-3 py-1 text-xs text-text">
                  {item}
                </span>
              ))
            )}
          </div>
        </div>

        <div className="grid gap-6 xl:grid-cols-2">
          <Card className="border-border-subtle bg-surface/90">
            <CardHeader>
              <CardTitle>Pourquoi agir maintenant</CardTitle>
              <CardDescription>
                Le signal Google le plus concret que PraeviSEO voit déjà derrière cette action.
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-3">
              {leadOpportunity ? (
                <>
                  <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                      <p className="text-sm font-semibold text-text">{leadOpportunity.label}</p>
                      <p className="text-xs text-text-subtle">
                        {leadOpportunity.site_name} / {leadOpportunity.slug || "/"}
                      </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                      <Badge variant={leadOpportunity.priority_level === "high" ? "warning" : "secondary"}>
                        {getClientOpportunityPriorityLabel(leadOpportunity.priority_label)}
                      </Badge>
                      <Badge variant={leadOpportunity.action_state === "ready" ? "success" : "secondary"}>
                        {getClientOpportunityStateLabel(leadOpportunity.action_state_label)}
                      </Badge>
                    </div>
                  </div>
                  <p className="text-sm text-text-muted">{leadOpportunity.reason}</p>
                  <div className="rounded-lg bg-surface-2 px-3 py-2 text-xs text-text-subtle">
                    {opportunityMetricLine(leadOpportunity.metrics)}
                  </div>
                  <p className="text-sm text-text">
                    Action a ouvrir : <span className="font-medium">{leadOpportunity.action}</span>
                  </p>
                  <div className="flex flex-wrap gap-2">
                    {opportunityActions(leadOpportunity).map((action) => (
                      <Button key={`${leadOpportunity.site_id}-${leadOpportunity.slug}-${action.label}`} href={action.href} size="sm" variant={action.variant ?? "secondary"}>
                        {action.label}
                      </Button>
                    ))}
                  </div>
                </>
              ) : (
                <div className="rounded-xl border border-border bg-surface-2 px-4 py-4 text-sm text-text-muted">
                  Aucun signal assez fort pour prioriser une action immediate. PraeviSEO continuera de rouvrir ce bloc au prochain import utile.
                </div>
              )}
            </CardContent>
          </Card>

          <Card className="border-border-subtle bg-surface/90">
            <CardHeader>
              <CardTitle>Gain estimé et effort</CardTitle>
              <CardDescription>
                La meilleure action déjà prête, avec son ratio gain attendu / difficulté.
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-3">
              {leadRecommendation ? (
                <>
                  <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                      <p className="text-sm font-semibold text-text">{leadRecommendation.title}</p>
                      <p className="text-xs text-text-subtle">
                        {leadRecommendation.site_id}
                        {leadRecommendation.cluster ? ` · sujet ${leadRecommendation.cluster}` : ""}
                      </p>
                    </div>
                    <Badge variant={leadRecommendation.priority <= 30 ? "warning" : "secondary"}>
                      Priorité {leadRecommendation.priority}
                    </Badge>
                  </div>
                  <div className="flex flex-wrap gap-2">
                    <Badge variant="secondary">{getClientImpactLabel(leadRecommendation.estimated_impact)}</Badge>
                    <Badge variant="secondary">{getClientDifficultyLabel(leadRecommendation.difficulty)}</Badge>
                  </div>
                  <p className="text-sm text-text-muted">{leadRecommendation.reasoning}</p>
                  <p className="text-sm text-text">
                    Action a ouvrir :{" "}
                    <span className="font-medium">{leadRecommendation.suggested_action ?? "a préciser bientôt"}</span>
                  </p>
                  <div className="flex flex-wrap gap-2">
                    {recommendationActions(leadRecommendation).map((action) => (
                      <Button key={`${leadRecommendation.id}-${action.label}`} href={action.href} size="sm" variant={action.variant ?? "secondary"}>
                        {action.label}
                      </Button>
                    ))}
                  </div>
                </>
              ) : (
                <div className="rounded-xl border border-border bg-surface-2 px-4 py-4 text-sm text-text-muted">
                  Aucune action assez forte pour l’instant. Ce bloc se remplira dès qu’une action au bon ratio impact / effort remonte.
                </div>
              )}
            </CardContent>
          </Card>
        </div>

        <div id="gains-rapides" className="grid gap-6 xl:grid-cols-2 scroll-mt-24">
          <Card>
            <CardHeader>
              <CardTitle>Gains rapides</CardTitle>
              <CardDescription>
                Les actions les plus rentables à traiter en premier pour gagner des visiteurs.
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-3">
              {quickWins.length === 0 ? (
                <div className="rounded-xl border border-border bg-surface-2 px-4 py-4 text-sm text-text-muted">
                  Aucun gain rapide net pour le moment. PraeviSEO rouvrira ce bloc dès qu’un signal plus chaud remonte.
                </div>
              ) : (
                quickWins.map((item) => (
                  <div key={`${item.site_id}-${item.slug}-${item.type}-quick`} className="rounded-xl border border-border px-4 py-3">
                    <div className="flex items-center justify-between gap-3">
                      <div>
                        <p className="text-sm font-semibold text-text">{item.label}</p>
                        <p className="text-xs text-text-subtle">{item.site_name}</p>
                      </div>
                      <Badge variant={item.priority_level === "high" ? "warning" : "success"}>{getClientOpportunityPriorityLabel(item.priority_label)}</Badge>
                    </div>
                    <p className="mt-2 text-sm text-text-muted">{item.reason}</p>
                    <p className="mt-2 text-sm text-text">
                      À faire : <span className="font-medium">{item.action}</span>
                    </p>
                    {opportunityModificationHint(item)}
                    <div className="mt-3 flex flex-wrap gap-2">
                      {opportunityActions(item).map((action) => (
                        <Button key={`${item.site_id}-${item.slug}-${action.label}`} href={action.href} size="sm" variant={action.variant ?? "secondary"}>
                          {action.label}
                        </Button>
                      ))}
                    </div>
                  </div>
                ))
              )}
            </CardContent>
          </Card>

          <Card id="pages-refresh" className="scroll-mt-24">
            <CardHeader>
              <CardTitle>Pages à renforcer</CardTitle>
              <CardDescription>
                Les pages qui peuvent gagner des visiteurs rapidement si on complète ce qui manque aujourd’hui.
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-3">
              {pagesToRefresh.length === 0 ? (
                <div className="rounded-xl border border-border bg-surface-2 px-4 py-4 text-sm text-text-muted">
                  Aucune page à renforcer en ce moment. PraeviSEO remontera les prochaines baisses ou pages proches d’un gain rapide.
                </div>
              ) : (
                pagesToRefresh.map((item) => (
                  <div key={`${item.site_id}-${item.slug}-${item.type}-refresh`} className="rounded-xl border border-border px-4 py-3">
                    <div className="flex items-center justify-between gap-3">
                      <div>
                        <p className="text-sm font-semibold text-text">{item.label}</p>
                        <p className="text-xs text-text-subtle">{opportunityMetricLine(item.metrics)}</p>
                      </div>
                      <Badge variant={item.type === "sustained_drop" ? "danger" : "secondary"}>
                        {getClientOpportunityTypeLabel(item.type)}
                      </Badge>
                    </div>
                    <p className="mt-2 text-sm text-text-muted">{item.reason}</p>
                    <p className="mt-2 text-sm text-text">
                      À faire : <span className="font-medium">{item.action}</span>
                    </p>
                    {opportunityModificationHint(item)}
                    <div className="mt-3 flex flex-wrap gap-2">
                      {opportunityActions(item).map((action) => (
                        <Button key={`${item.site_id}-${item.slug}-${action.label}`} href={action.href} size="sm" variant={action.variant ?? "secondary"}>
                          {action.label}
                        </Button>
                      ))}
                    </div>
                  </div>
                ))
              )}
            </CardContent>
          </Card>
        </div>

        <div id="requetes" className="grid gap-6 xl:grid-cols-2 scroll-mt-24">
          <Card>
            <CardHeader>
              <CardTitle>Requêtes Google</CardTitle>
              <CardDescription>
                Les requêtes en hausse ou à potentiel repérées par PraeviSEO dans Google.
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-3">
              {queryWatchlist.length === 0 ? (
                <div className="rounded-xl border border-border bg-surface-2 px-4 py-4 text-sm text-text-muted">
                  Aucune requête montante suffisamment claire pour le moment. Le cockpit surveille déjà les prochains signaux.
                </div>
              ) : (
                queryWatchlist.map((item) => (
                  <div key={`${item.site_id}-${item.query}-query`} className="rounded-xl border border-border px-4 py-3">
                    <div className="flex items-center justify-between gap-3">
                      <div>
                        <p className="text-sm font-semibold text-text">{item.query}</p>
                        <p className="text-xs text-text-subtle">{item.site_name}</p>
                      </div>
                      <Badge variant={item.priority_level === "high" ? "warning" : "success"}>{getClientOpportunityPriorityLabel(item.priority_label)}</Badge>
                    </div>
                    <p className="mt-2 text-sm text-text-muted">{item.reason}</p>
                    <p className="mt-2 text-sm text-text">
                      Page a renforcer : <span className="font-medium">{item.label}</span>
                    </p>
                    <div className="mt-3 flex flex-wrap gap-2">
                      {opportunityActions(item).map((action) => (
                        <Button key={`${item.site_id}-${item.query}-${action.label}`} href={action.href} size="sm" variant={action.variant ?? "secondary"}>
                          {action.label}
                        </Button>
                      ))}
                    </div>
                  </div>
                ))
              )}
            </CardContent>
          </Card>

          <Card id="activite" className="scroll-mt-24">
            <CardHeader>
              <CardTitle>Contexte récent</CardTitle>
              <CardDescription>
                Le minimum utile pour comprendre d’où vient l’action. Le journal complet reste dans Activité.
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
              {timelineFeed.length === 0 ? (
                <div className="rounded-xl border border-border bg-surface-2 px-4 py-4 text-sm text-text-muted">
                  Aucune recommandation récente pour le moment. PraeviSEO remplira ce feed au prochain cycle utile.
                </div>
              ) : (
                timelineFeed.map((item) => (
                  <div key={item.id} className="rounded-xl border border-border p-4 space-y-3">
                    <div className="flex flex-wrap items-center gap-2 justify-between">
                      <div>
                        <p className="text-sm font-semibold text-text">{item.title}</p>
                        <p className="text-xs text-text-subtle">{item.meta}</p>
                      </div>
                      <div className="flex items-center gap-2">
                        <Badge variant={item.badgeVariant as "warning" | "secondary" | "success"}>
                          {item.badge}
                        </Badge>
                      </div>
                    </div>
                    <p className="text-sm text-text-muted">{item.detail}</p>
                  </div>
                ))
              )}
              <div className="pt-2">
                <Button href="/activity" variant="secondary" className="w-full">
                  Comprendre ce qui s’est passé
                </Button>
              </div>
            </CardContent>
          </Card>
        </div>

        <Card id="plan-action" className="scroll-mt-24">
          <CardHeader>
            <CardTitle>Plan d’action recommandé par PraeviSEO</CardTitle>
            <CardDescription>
              Les actions déjà prêtes les plus utiles pour améliorer la visibilité, classées par impact, effort et priorité.
            </CardDescription>
          </CardHeader>
          <CardContent className="space-y-4">
            {actionPlan.length === 0 ? (
              <div className="rounded-xl border border-border bg-surface-2 px-4 py-4 text-sm text-text-muted">
                Aucun plan d’action fort pour le moment. PraeviSEO enrichira ce bloc dès que de nouvelles recommandations deviennent utiles.
              </div>
            ) : (
              actionPlan.map((item) => (
                <div key={item.id} className="rounded-xl border border-border p-4 space-y-3">
                  <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                      <p className="text-sm font-semibold text-text">{item.title}</p>
                      <p className="text-xs text-text-subtle">
                        {item.site_id}
                        {item.cluster ? ` · sujet ${item.cluster}` : ""}
                      </p>
                    </div>
                    <div className="flex items-center gap-2">
                      <Badge variant={item.priority <= 30 ? "warning" : "secondary"}>
                        {getClientRecommendationBadge(item.priority)}
                      </Badge>
                      <Badge variant="secondary">{getClientImpactLabel(item.estimated_impact)}</Badge>
                      <Badge variant="secondary">{getClientDifficultyLabel(item.difficulty)}</Badge>
                    </div>
                  </div>
                  <p className="text-sm text-text-muted">{item.reasoning}</p>
                  <p className="text-sm text-text">
                    Action suggérée : <span className="font-medium">{item.suggested_action ?? "à préciser bientôt"}</span>
                  </p>
                  <p className="text-xs text-text-subtle">
                    Pourquoi maintenant : {item.estimated_impact === "high" ? "PraeviSEO voit un gain significatif à court terme" : "PraeviSEO voit un levier utile à ouvrir dans le bon ordre"}.
                  </p>
                </div>
              ))
            )}
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Opportunités détectées dans Google</CardTitle>
            <CardDescription>
              Ce sont les pages où PraeviSEO voit un gain réaliste à court terme à partir de Google Search Console.
            </CardDescription>
          </CardHeader>
          <CardContent className="space-y-4">
            {opportunities.length === 0 ? (
              <div className="rounded-xl border border-border bg-surface-2 px-4 py-4 text-sm text-text-muted">
                Aucun levier Google fort pour le moment. PraeviSEO continue de surveiller les performances et
                rouvrira des actions dès qu’un signal utile remonte.
              </div>
            ) : (
              opportunities.map((item) => (
                <div key={`${item.site_id}-${item.type}-${item.page_id ?? item.slug}-${item.query ?? "none"}`} className="rounded-xl border border-border p-4 space-y-3">
                  <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                      <p className="text-sm font-semibold text-text">{item.label || "Page suivie"}</p>
                      <p className="text-xs text-text-subtle">
                        {item.site_name} / {item.slug || "/"}
                      </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                      <Badge variant={item.priority_level === "high" ? "warning" : item.priority_level === "medium" ? "brand-subtle" : "secondary"}>
                        {getClientOpportunityPriorityLabel(item.priority_label)}
                      </Badge>
                      <Badge variant={item.action_state === "ready" ? "success" : item.action_state === "pending" ? "warning" : "secondary"}>
                        {getClientOpportunityStateLabel(item.action_state_label)}
                      </Badge>
                      {item.pending_suggestion ? <Badge variant="secondary">Déjà prévue</Badge> : null}
                      <Badge variant="secondary">{getClientOpportunityTypeLabel(item.type)}</Badge>
                    </div>
                  </div>

                  <p className="text-sm text-text-muted">{item.reason}</p>
                  {opportunityModificationHint(item)}

                  {item.query ? (
                    <div className="rounded-lg bg-surface-2 px-3 py-2 text-xs text-text-subtle">
                      Requête observée : <span className="font-medium text-text">{item.query}</span>
                    </div>
                  ) : null}

                  <div className="rounded-lg bg-surface-2 px-3 py-2 text-xs text-text-subtle">
                    {opportunityMetricLine(item.metrics)}
                  </div>

                  <div className="flex flex-wrap items-center justify-between gap-3">
                    <p className="text-sm text-text">
                      Action suggérée : <span className="font-medium">{item.action}</span>
                    </p>
                    <div className="flex flex-wrap gap-2">
                      {opportunityActions(item).map((action) => (
                        <Button key={`${item.site_id}-${item.slug}-${item.query}-${action.label}`} href={action.href} variant={action.variant ?? "secondary"} size="sm">
                          {action.label}
                        </Button>
                      ))}
                    </div>
                  </div>
                </div>
              ))
            )}
          </CardContent>
        </Card>

      </div>
    </div>
  );
}
