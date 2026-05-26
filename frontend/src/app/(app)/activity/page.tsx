import { CockpitSectionNav } from "@/components/cockpit/section-nav";
import { CockpitMetricGrid } from "@/components/cockpit/metric-grid";
import { CockpitSignalItem, CockpitSignalListCard } from "@/components/cockpit/signal-list";
import { Topbar } from "@/components/layout/topbar";
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

        <div id="vue-ensemble" className="scroll-mt-24">
          <CockpitMetricGrid
            items={[
              { label: "Événements récents", value: timelineFeed.length },
              { label: "Mouvements de pages", value: movementFeed.length, tone: "success" },
              { label: "Alertes actives", value: alertFeed.length, tone: alertFeed.length > 0 ? "warning" : "secondary" },
              { label: "Opportunités ouvertes", value: optimizations.gsc_opportunities.summary.total, tone: "warning" },
            ]}
          />
        </div>

        <div id="mouvements" className="grid gap-6 xl:grid-cols-2 scroll-mt-24">
          <CockpitSignalListCard
            title="Mouvements récents"
            description="Les pages qui montent ou qui ralentissent le plus en ce moment."
            empty={movementFeed.length === 0}
            emptyMessage="Aucun mouvement fort pour le moment. Les prochains imports Google animeront ce bloc."
          >
            {movementFeed.map((item) => (
              <CockpitSignalItem
                key={`${item.site_name}-${item.slug}-${item.trend}`}
                title={item.label}
                subtitle={item.site_name}
                badge={item.trend === "down" ? "En baisse" : "En hausse"}
                badgeTone={item.trend === "down" ? "danger" : "success"}
                description={`${item.delta_impressions > 0 ? "+" : ""}${item.delta_impressions} impressions, CTR ${item.ctr.toFixed(1)} %, position ${item.position.toFixed(1)}.`}
              />
            ))}
          </CockpitSignalListCard>

          <CockpitSignalListCard
            id="alertes"
            className="scroll-mt-24"
            title="Alertes simples"
            description="CTR faible, recul durable ou baisse de visibilité : les signaux à traiter vite."
            empty={alertFeed.length === 0}
            emptyMessage="Aucune alerte forte pour le moment. Le cockpit reste en veille active."
          >
            {alertFeed.map((item) => (
              <CockpitSignalItem
                key={`${item.site_id}-${item.slug}-${item.type}-alert`}
                title={item.label}
                subtitle={item.site_name}
                badge={item.priority_label}
                badgeTone={item.type === "sustained_drop" ? "danger" : "warning"}
                description={item.reason}
              />
            ))}
          </CockpitSignalListCard>
        </div>

        <CockpitSignalListCard
          id="timeline"
          className="scroll-mt-24"
          title="Timeline SEO"
          description="Progression, historique et activité détectée récemment dans PraeviSEO."
          empty={timelineFeed.length === 0}
          emptyMessage="La timeline se remplira automatiquement avec les prochaines variations SEO."
        >
          {timelineFeed.map((item) => (
            <CockpitSignalItem
              key={item.id}
              title={item.title}
              subtitle={item.meta}
              badge={item.badge}
              badgeTone={item.badgeVariant as "success" | "warning" | "secondary" | "danger"}
              description={item.detail}
            />
          ))}
        </CockpitSignalListCard>
      </div>
    </div>
  );
}
