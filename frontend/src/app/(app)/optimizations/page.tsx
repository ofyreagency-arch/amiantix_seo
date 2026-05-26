import { Badge } from "@/components/ui/badge";
import { CockpitSectionNav } from "@/components/cockpit/section-nav";
import { Topbar } from "@/components/layout/topbar";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { getOptimizations } from "@/lib/praeviseo-api";
import { formatDate } from "@/lib/utils";

function opportunityTypeLabel(type: string): string {
  return (
    {
      low_ctr: "CTR faible",
      near_top_10: "Proche du top 10",
      emerging_query: "Requête émergente",
      sustained_drop: "Baisse durable",
    }[type] ?? "Opportunité SEO"
  );
}

function opportunityMetricLine(metrics: Record<string, number | string>): string {
  const impressions = Number(metrics.impressions ?? 0);
  const ctr = Number(metrics.ctr ?? 0);
  const position = Number(metrics.position ?? 0);
  const previousImpressions = Number(metrics.previous_impressions ?? 0);

  if (previousImpressions > 0) {
    return `${new Intl.NumberFormat("fr-FR").format(impressions)} impressions recentes, ${new Intl.NumberFormat("fr-FR").format(previousImpressions)} avant, position ${position.toFixed(1)}`;
  }

  return `${new Intl.NumberFormat("fr-FR").format(impressions)} impressions, CTR ${ctr.toFixed(1)} %, position ${position.toFixed(1)}`;
}

export default async function OptimizationsPage() {
  const optimizations = await getOptimizations();
  const opportunities = optimizations.gsc_opportunities.items;
  const queryWatchlist = opportunities.filter((item) => item.query).slice(0, 4);
  const pagesToRefresh = opportunities.filter((item) => item.type === "near_top_10" || item.type === "sustained_drop").slice(0, 4);
  const quickWins = opportunities.filter((item) => item.priority_level === "high" || item.action_state === "ready").slice(0, 4);
  const summarySignals = [
    optimizations.gsc_opportunities.summary.near_top_10 > 0
      ? `${optimizations.gsc_opportunities.summary.near_top_10} page(s) approchent du top 10`
      : null,
    optimizations.gsc_opportunities.summary.low_ctr > 0
      ? `${optimizations.gsc_opportunities.summary.low_ctr} page(s) ont un CTR à relancer`
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
      label: "Nouvelles opportunités",
      value: optimizations.gsc_opportunities.summary.total,
      detail:
        optimizations.gsc_opportunities.summary.total > 0
          ? "PraeviSEO a déjà des leviers concrets à ouvrir"
          : "aucun levier fort n’est encore remonté",
      tone: optimizations.gsc_opportunities.summary.total > 0 ? "warning" : "secondary",
    },
    {
      label: "Pages proches du top 10",
      value: optimizations.gsc_opportunities.summary.near_top_10,
      detail:
        optimizations.gsc_opportunities.summary.near_top_10 > 0
          ? "gains rapides potentiels si on rafraîchit"
          : "pas de page chaude à pousser tout de suite",
      tone: optimizations.gsc_opportunities.summary.near_top_10 > 0 ? "success" : "secondary",
    },
    {
      label: "CTR à relancer",
      value: optimizations.gsc_opportunities.summary.low_ctr,
      detail:
        optimizations.gsc_opportunities.summary.low_ctr > 0
          ? "des pages sont vues mais sous-cliquées"
          : "aucun CTR faible net pour le moment",
      tone: optimizations.gsc_opportunities.summary.low_ctr > 0 ? "warning" : "secondary",
    },
    {
      label: "Baisses détectées",
      value: optimizations.gsc_opportunities.summary.sustained_drop,
      detail:
        optimizations.gsc_opportunities.summary.sustained_drop > 0
          ? "certaines pages perdent de la visibilité"
          : "pas de recul durable fort en ce moment",
      tone: optimizations.gsc_opportunities.summary.sustained_drop > 0 ? "danger" : "secondary",
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
      badge: item.status === "pending" ? "Reco ouverte" : "Reco suivie",
      badgeVariant: item.status === "pending" ? "warning" : "secondary",
      meta: item.created_at ? formatDate(item.created_at) : "Récemment",
      timestamp: item.created_at ? new Date(item.created_at).getTime() : 0,
    })),
  ]
    .sort((a, b) => b.timestamp - a.timestamp)
    .slice(0, 6);

  return (
    <div className="min-h-screen">
      <Topbar
        title="Optimisations"
        subtitle="Les opportunités SEO détectées par PraeviSEO à partir de Google Search Console."
        actions={
          <Button href="/dashboard" variant="secondary" size="sm">
            Retour au dashboard
          </Button>
        }
      />
      <div className="p-6 space-y-6">
        <CockpitSectionNav
          items={[
            { label: "Vue d’ensemble", href: "#vue-ensemble", count: optimizations.gsc_opportunities.summary.total, tone: "default" },
            { label: "Gains rapides", href: "#gains-rapides", count: quickWins.length, tone: "warning" },
            { label: "Pages à refresh", href: "#pages-refresh", count: pagesToRefresh.length, tone: "secondary" },
            { label: "Requêtes Google", href: "#requetes", count: queryWatchlist.length, tone: "success" },
            { label: "Activité SEO", href: "#activite", count: optimizations.items.length, tone: "default" },
          ]}
        />

        <div className="rounded-2xl border border-brand/20 bg-brand-muted px-6 py-6">
          <h1 className="text-2xl font-bold tracking-tight text-text">Vos meilleures opportunités SEO, sans installer quoi que ce soit</h1>
          <p className="mt-2 max-w-3xl text-sm leading-7 text-text-muted">
            PraeviSEO lit déjà Google Search Console pour repérer les pages proches du top 10, les CTR faibles,
            les baisses de visibilité et les requêtes qui méritent une réponse plus forte.
          </p>
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
            ["Proches du top 10", optimizations.gsc_opportunities.summary.near_top_10],
            ["CTR à relancer", optimizations.gsc_opportunities.summary.low_ctr],
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

        <div id="gains-rapides" className="grid gap-6 xl:grid-cols-2 scroll-mt-24">
          <Card>
            <CardHeader>
              <CardTitle>Gains rapides</CardTitle>
              <CardDescription>
                Les opportunités les plus actionnables maintenant pour faire bouger le SEO vite.
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
                      <Badge variant={item.priority_level === "high" ? "warning" : "success"}>{item.priority_label}</Badge>
                    </div>
                    <p className="mt-2 text-sm text-text-muted">{item.reason}</p>
                  </div>
                ))
              )}
            </CardContent>
          </Card>

          <Card id="pages-refresh" className="scroll-mt-24">
            <CardHeader>
              <CardTitle>Pages à refresh</CardTitle>
              <CardDescription>
                Les pages qui approchent d’un gain réel si on les rafraîchit ou les renforce maintenant.
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-3">
              {pagesToRefresh.length === 0 ? (
                <div className="rounded-xl border border-border bg-surface-2 px-4 py-4 text-sm text-text-muted">
                  Aucune page à refresh en ce moment. Le cockpit remontera les prochaines baisses ou zones proches du top 10.
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
                        {opportunityTypeLabel(item.type)}
                      </Badge>
                    </div>
                    <p className="mt-2 text-sm text-text-muted">{item.action}</p>
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
                      <Badge variant={item.priority_level === "high" ? "warning" : "success"}>{item.priority_label}</Badge>
                    </div>
                    <p className="mt-2 text-sm text-text-muted">{item.reason}</p>
                  </div>
                ))
              )}
            </CardContent>
          </Card>

          <Card id="activite" className="scroll-mt-24">
            <CardHeader>
              <CardTitle>Activité SEO</CardTitle>
              <CardDescription>
                Le feed chronologique des opportunités et recommandations déjà visibles dans PraeviSEO.
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
            </CardContent>
          </Card>
        </div>

        <Card>
          <CardHeader>
            <CardTitle>Opportunités détectées dans Google</CardTitle>
            <CardDescription>
              Ce sont les pages où PraeviSEO voit un gain réaliste à court terme à partir des signaux GSC.
            </CardDescription>
          </CardHeader>
          <CardContent className="space-y-4">
            {opportunities.length === 0 ? (
              <div className="rounded-xl border border-border bg-surface-2 px-4 py-4 text-sm text-text-muted">
                Aucune opportunité GSC forte pour le moment. PraeviSEO continue de surveiller les performances et
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
                        {item.priority_label}
                      </Badge>
                      <Badge variant={item.action_state === "ready" ? "success" : item.action_state === "pending" ? "warning" : "secondary"}>
                        {item.action_state_label}
                      </Badge>
                      <Badge variant="secondary">{opportunityTypeLabel(item.type)}</Badge>
                    </div>
                  </div>

                  <p className="text-sm text-text-muted">{item.reason}</p>

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
                    <Button href={`/sites/${item.site_id}`} variant="secondary" size="sm">
                      Ouvrir le site
                    </Button>
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
