import { CockpitSectionNav } from "@/components/cockpit/section-nav";
import { Topbar } from "@/components/layout/topbar";
import { Badge } from "@/components/ui/badge";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { getDashboard, getOptimizations, getPublications } from "@/lib/praeviseo-api";
import { formatDate } from "@/lib/utils";

export default async function ActivityCockpitPage() {
  const dashboard = await getDashboard();
  const optimizations = await getOptimizations();
  const publications = await getPublications();

  const timelineFeed = [
    ...dashboard.sites
      .filter((site) => site.gsc_last_sync_at)
      .map((site) => ({
        id: `sync-${site.site_id}`,
        title: `${site.name} relu par Google`,
        detail:
          site.summary.gsc_delta_impressions > 0
            ? `+${new Intl.NumberFormat("fr-FR").format(site.summary.gsc_delta_impressions)} impressions sur la dernière période.`
            : site.summary.gsc_delta_impressions < 0
              ? `${new Intl.NumberFormat("fr-FR").format(site.summary.gsc_delta_impressions)} impressions sur la dernière période.`
              : "Le volume d’impressions reste stable sur la dernière période.",
        badge: "Import GSC",
        badgeVariant:
          site.summary.gsc_delta_impressions < 0 ? "danger" : site.summary.gsc_delta_impressions > 0 ? "success" : "secondary",
        meta: formatDate(site.gsc_last_sync_at as string),
        timestamp: new Date(site.gsc_last_sync_at as string).getTime(),
      })),
    ...optimizations.gsc_opportunities.items.slice(0, 6).map((item) => ({
      id: `opportunity-${item.site_id}-${item.slug}-${item.type}`,
      title: item.label,
      detail: item.reason,
      badge: item.priority_label,
      badgeVariant: item.priority_level === "high" ? "warning" : "secondary",
      meta: `${item.site_name} · opportunité détectée`,
      timestamp: 0,
    })),
    ...optimizations.items.slice(0, 6).map((item) => ({
      id: `optimization-${item.id}`,
      title: item.page.title,
      detail: item.summary,
      badge: item.status === "pending" ? "Reco ouverte" : "Reco suivie",
      badgeVariant: item.status === "pending" ? "warning" : "secondary",
      meta: item.created_at ? formatDate(item.created_at) : "Récemment",
      timestamp: item.created_at ? new Date(item.created_at).getTime() : 0,
    })),
    ...publications.items.slice(0, 6).map((item) => ({
      id: `publication-${item.id}`,
      title: item.title,
      detail: item.published_live
        ? "Le contenu est déjà visible sur le site."
        : "Le contenu reste prêt côté PraeviSEO.",
      badge: item.published_live ? "Visible" : "Préparé",
      badgeVariant: item.published_live ? "success" : "secondary",
      meta: item.published_at ? formatDate(item.published_at) : "Récemment",
      timestamp: item.published_at ? new Date(item.published_at).getTime() : 0,
    })),
  ]
    .sort((a, b) => b.timestamp - a.timestamp)
    .slice(0, 12);

  const alertFeed = optimizations.gsc_opportunities.items
    .filter((item) => item.type === "low_ctr" || item.type === "sustained_drop")
    .slice(0, 6);
  const movementFeed = dashboard.sites.flatMap((site) => [
    ...site.summary.top_rising_pages.map((item) => ({ ...item, site_name: site.name, trend: "up" as const })),
    ...site.summary.top_falling_pages.map((item) => ({ ...item, site_name: site.name, trend: "down" as const })),
  ]).slice(0, 8);

  return (
    <div className="min-h-screen">
      <Topbar
        title="Activité SEO"
        subtitle="Le feed vivant du cockpit : alertes, variations, mouvements et nouvelles opportunités détectées."
      />

      <div className="p-6 space-y-6">
        <CockpitSectionNav
          items={[
            { label: "Vue d’ensemble", href: "#vue-ensemble", count: timelineFeed.length, tone: "default" },
            { label: "Mouvements", href: "#mouvements", count: movementFeed.length, tone: "success" },
            { label: "Alertes", href: "#alertes", count: alertFeed.length, tone: "warning" },
            { label: "Timeline", href: "#timeline", count: timelineFeed.length, tone: "secondary" },
          ]}
        />

        <div className="rounded-2xl border border-brand/20 bg-brand-muted px-6 py-6">
          <h1 className="text-2xl font-bold tracking-tight text-text">Le feed vivant de votre SEO</h1>
          <p className="mt-2 max-w-3xl text-sm leading-7 text-text-muted">
            PraeviSEO donne ici une sensation de mouvement continu : variations Google, opportunités détectées,
            recommandations ouvertes et activité récente du cockpit.
          </p>
        </div>

        <div id="vue-ensemble" className="grid gap-4 md:grid-cols-2 xl:grid-cols-4 scroll-mt-24">
          {[
            ["Événements récents", timelineFeed.length],
            ["Mouvements de pages", movementFeed.length],
            ["Alertes actives", alertFeed.length],
            ["Opportunités ouvertes", optimizations.gsc_opportunities.summary.total],
          ].map(([label, value]) => (
            <Card key={label}>
              <CardHeader className="pb-2">
                <CardDescription>{label}</CardDescription>
                <CardTitle className="text-3xl">{value}</CardTitle>
              </CardHeader>
            </Card>
          ))}
        </div>

        <div id="mouvements" className="grid gap-6 xl:grid-cols-2 scroll-mt-24">
          <Card>
            <CardHeader>
              <CardTitle>Mouvements récents</CardTitle>
              <CardDescription>Les pages qui montent ou qui ralentissent le plus en ce moment.</CardDescription>
            </CardHeader>
            <CardContent className="space-y-3">
              {movementFeed.length === 0 ? (
                <div className="rounded-xl border border-border bg-surface-2 px-4 py-4 text-sm text-text-muted">
                  Aucun mouvement fort pour le moment. Les prochains imports Google animeront ce bloc.
                </div>
              ) : (
                movementFeed.map((item) => (
                  <div key={`${item.site_name}-${item.slug}-${item.trend}`} className="rounded-xl border border-border px-4 py-3">
                    <div className="flex items-center justify-between gap-3">
                      <div>
                        <p className="text-sm font-semibold text-text">{item.label}</p>
                        <p className="text-xs text-text-subtle">{item.site_name}</p>
                      </div>
                      <Badge variant={item.trend === "down" ? "danger" : "success"}>
                        {item.trend === "down" ? "En baisse" : "En hausse"}
                      </Badge>
                    </div>
                    <p className="mt-2 text-sm text-text-muted">
                      {item.delta_impressions > 0 ? "+" : ""}
                      {item.delta_impressions} impressions, CTR {item.ctr.toFixed(1)} %, position {item.position.toFixed(1)}.
                    </p>
                  </div>
                ))
              )}
            </CardContent>
          </Card>

          <Card id="alertes" className="scroll-mt-24">
            <CardHeader>
              <CardTitle>Alertes simples</CardTitle>
              <CardDescription>CTR faible, recul durable ou baisse de visibilité : les signaux à traiter vite.</CardDescription>
            </CardHeader>
            <CardContent className="space-y-3">
              {alertFeed.length === 0 ? (
                <div className="rounded-xl border border-border bg-surface-2 px-4 py-4 text-sm text-text-muted">
                  Aucune alerte forte pour le moment. Le cockpit reste en veille active.
                </div>
              ) : (
                alertFeed.map((item) => (
                  <div key={`${item.site_id}-${item.slug}-${item.type}-alert`} className="rounded-xl border border-border px-4 py-3">
                    <div className="flex items-center justify-between gap-3">
                      <div>
                        <p className="text-sm font-semibold text-text">{item.label}</p>
                        <p className="text-xs text-text-subtle">{item.site_name}</p>
                      </div>
                      <Badge variant={item.type === "sustained_drop" ? "danger" : "warning"}>{item.priority_label}</Badge>
                    </div>
                    <p className="mt-2 text-sm text-text-muted">{item.reason}</p>
                  </div>
                ))
              )}
            </CardContent>
          </Card>
        </div>

        <Card id="timeline" className="scroll-mt-24">
          <CardHeader>
            <CardTitle>Timeline SEO</CardTitle>
            <CardDescription>Progression, historique et activité détectée récemment dans PraeviSEO.</CardDescription>
          </CardHeader>
          <CardContent className="space-y-3">
            {timelineFeed.length === 0 ? (
              <div className="rounded-xl border border-border bg-surface-2 px-4 py-4 text-sm text-text-muted">
                La timeline se remplira automatiquement avec les prochaines variations SEO.
              </div>
            ) : (
              timelineFeed.map((item) => (
                <div key={item.id} className="rounded-xl border border-border px-4 py-3">
                  <div className="flex items-center justify-between gap-3">
                    <div>
                      <p className="text-sm font-semibold text-text">{item.title}</p>
                      <p className="text-xs text-text-subtle">{item.meta}</p>
                    </div>
                    <Badge variant={item.badgeVariant as "success" | "warning" | "secondary" | "danger"}>
                      {item.badge}
                    </Badge>
                  </div>
                  <p className="mt-2 text-sm text-text-muted">{item.detail}</p>
                </div>
              ))
            )}
          </CardContent>
        </Card>
      </div>
    </div>
  );
}
