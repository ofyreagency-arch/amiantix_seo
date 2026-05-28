import { CockpitSectionNav } from "@/components/cockpit/section-nav";
import { CockpitMetricGrid } from "@/components/cockpit/metric-grid";
import { CockpitSignalItem, CockpitSignalListCard } from "@/components/cockpit/signal-list";
import { Topbar } from "@/components/layout/topbar";
import { getDashboard } from "@/lib/praeviseo-api";
import { formatDate } from "@/lib/utils";

export default async function IndexationCockpitPage() {
  const dashboard = await getDashboard();

  const connectedSites = dashboard.sites.filter((site) => site.readiness.gsc_connected);
  const syncedSites = connectedSites.filter((site) => site.summary.gsc_indexation_synced);
  const indexationScopeLabel = syncedSites[0]?.summary.gsc_indexation_scope_label ?? "URLs inspectées via Google";
  const indexationScopeHint =
    syncedSites[0]?.summary.gsc_indexation_scope_hint ??
    "PraeviSEO compte ici les URLs qu’il suit et inspecte dans Google Search Console. Le rapport Pages complet de Google peut afficher davantage d’URLs.";
  const freshestSyncAt = connectedSites
    .map((site) => site.gsc_last_sync_at)
    .filter((value): value is string => Boolean(value))
    .sort()
    .at(-1);
  const freshestDataAsOf = connectedSites
    .map((site) => site.gsc_data_as_of)
    .filter((value): value is string => Boolean(value))
    .sort()
    .at(-1);
  const pendingSites = connectedSites.filter((site) => !site.summary.gsc_indexation_synced);
  const totalIndexedPages = syncedSites.reduce((sum, site) => sum + site.summary.gsc_indexed_pages, 0);
  const totalNonIndexedPages = syncedSites.reduce((sum, site) => sum + site.summary.gsc_non_indexed_pages, 0);
  const totalObservedCrawlIssues = connectedSites.reduce((sum, site) => sum + site.summary.observed_crawl_issues, 0);
  const observedHealthSites = connectedSites.filter(
    (site) =>
      site.summary.observed_crawl_issues > 0 ||
      site.summary.observed_orphan_pages > 0 ||
      site.summary.observed_weak_pages > 0
  );
  const indexationAlerts = syncedSites
    .flatMap((site) => site.summary.indexation_alerts.map((item) => ({ ...item, site_name: site.name })))
    .slice(0, 8);
  const indexationNarrative =
    totalNonIndexedPages > 0
      ? `PraeviSEO voit déjà ${totalIndexedPages} page${totalIndexedPages > 1 ? "s" : ""} bien lue${totalIndexedPages > 1 ? "s" : ""} par Google, mais ${totalNonIndexedPages} autre${totalNonIndexedPages > 1 ? "s" : ""} demande${totalNonIndexedPages > 1 ? "nt" : ""} encore une vérification ou un renfort.`
      : `PraeviSEO voit déjà ${totalIndexedPages} page${totalIndexedPages > 1 ? "s" : ""} bien lue${totalIndexedPages > 1 ? "s" : ""} par Google, sans blocage fort pour le moment.`;
  const mostExposedSite = [...syncedSites].sort(
    (a, b) => b.summary.gsc_non_indexed_pages - a.summary.gsc_non_indexed_pages
  )[0];
  const indexationHighlights = [
    totalIndexedPages > 0
      ? `PraeviSEO a déjà une base utile : ${totalIndexedPages} page${totalIndexedPages > 1 ? "s" : ""} déjà bien lue${totalIndexedPages > 1 ? "s" : ""} par Google.`
      : "PraeviSEO attend encore les premières pages clairement lues par Google.",
    totalNonIndexedPages > 0
      ? `${totalNonIndexedPages} page${totalNonIndexedPages > 1 ? "s" : ""} demande${totalNonIndexedPages > 1 ? "nt" : ""} encore une vérification côté Google.`
      : "Aucune page fragile forte n’apparaît dans la dernière lecture.",
    mostExposedSite
      ? `${mostExposedSite.name} concentre ${mostExposedSite.summary.gsc_non_indexed_pages} page${mostExposedSite.summary.gsc_non_indexed_pages > 1 ? "s" : ""} encore fragile${mostExposedSite.summary.gsc_non_indexed_pages > 1 ? "s" : ""}.`
      : "Le cockpit ne détecte pas encore de site particulièrement exposé.",
    totalObservedCrawlIssues > 0
      ? `${totalObservedCrawlIssues} issue(s) crawl observée(s) complètent aussi la lecture d’indexation de PraeviSEO.`
      : "Aucune issue crawl forte n’alourdit la lecture actuelle de l’indexation.",
  ];

  return (
    <div className="min-h-screen">
      <Topbar
        title="Indexation"
        subtitle="La lecture Google des pages suivies par PraeviSEO : bien lues, à vérifier, ou encore en attente de lecture exploitable."
      />

      <div className="p-6 space-y-6">
        <CockpitSectionNav
          items={[
            { label: "Vue d’ensemble", href: "#vue-ensemble", count: totalIndexedPages, tone: "default" },
            { label: "URLs relues", href: "#indexees", count: syncedSites.length, tone: "success" },
            { label: "Alertes", href: "#alertes", count: indexationAlerts.length + pendingSites.length, tone: "warning" },
            { label: "Santé site", href: "#sante", count: observedHealthSites.length, tone: "secondary" },
            { label: "État Google", href: "#google", count: connectedSites.length, tone: "secondary" },
          ]}
        />

        <div className="rounded-2xl border border-brand/20 bg-brand-muted px-6 py-6">
          <h1 className="text-2xl font-bold tracking-tight text-text">Votre lecture Google des URLs relues par PraeviSEO</h1>
          <p className="mt-2 max-w-3xl text-sm leading-7 text-text-muted">
            PraeviSEO rassemble déjà les pages qu’il suit dans Google Search Console pour montrer celles que Google
            comprend déjà bien, celles qui demandent encore une vérification, et les sites encore en attente de lecture exploitable.
          </p>
          {(freshestSyncAt || freshestDataAsOf) && (
            <p className="mt-3 text-xs text-text-subtle">
              {freshestSyncAt ? `Dernière synchro GSC : ${formatDate(freshestSyncAt)}.` : "Synchronisation GSC en attente."}{" "}
              {freshestDataAsOf ? `Données arrêtées au ${formatDate(freshestDataAsOf)}.` : ""}
            </p>
          )}
        </div>

        <div id="vue-ensemble" className="scroll-mt-24">
          <CockpitMetricGrid
            items={[
              { label: "Pages déjà bien lues", value: totalIndexedPages, tone: "success" },
              { label: "Pages encore à vérifier", value: totalNonIndexedPages, tone: totalNonIndexedPages > 0 ? "warning" : "secondary" },
              { label: "Sites reliés à Google", value: connectedSites.length },
              { label: "Alertes d’indexation", value: indexationAlerts.length + pendingSites.length, tone: indexationAlerts.length + pendingSites.length > 0 ? "warning" : "secondary" },
              { label: "Issues crawl observées", value: totalObservedCrawlIssues, tone: totalObservedCrawlIssues > 0 ? "warning" : "secondary" },
            ]}
          />
        </div>

        <div className="grid gap-6 xl:grid-cols-[1.1fr_0.9fr]">
          <div className="rounded-xl border border-border bg-surface p-5">
            <p className="text-xs text-text-subtle">Lecture du moment</p>
            <p className="mt-3 text-lg font-semibold text-text">{indexationNarrative}</p>
            <p className="mt-2 text-sm text-text-muted">{indexationScopeHint}</p>
            <div className="mt-4 space-y-3 text-sm text-text-muted">
              {indexationHighlights.map((item) => (
                <p key={item}>{item}</p>
              ))}
            </div>
          </div>

          <CockpitSignalListCard
            title="Priorités Google"
            description="Les points d’indexation les plus concrets à garder sous les yeux."
            empty={indexationAlerts.length === 0 && pendingSites.length === 0}
            emptyMessage="Aucune priorité forte d’indexation pour le moment. Le cockpit surveille déjà les prochains changements Google."
          >
            {(indexationAlerts.length > 0
              ? indexationAlerts.slice(0, 4).map((item) => ({
                  key: `${item.site_name}-${item.slug}-priority`,
                  title: item.label,
                  subtitle: item.site_name,
                  badge: "À vérifier maintenant",
                  badgeTone: "warning" as const,
                  description: item.detail,
                }))
              : pendingSites.slice(0, 4).map((site) => ({
                  key: `${site.site_id}-priority`,
                  title: site.name,
                  subtitle: site.gsc_property_url ?? site.site_id,
                  badge: "Lecture en attente",
                  badgeTone: "secondary" as const,
                  description: "PraeviSEO attend encore une lecture d’indexation exploitable sur ce site.",
                }))).map((item) => (
              <CockpitSignalItem
                key={item.key}
                title={item.title}
                subtitle={item.subtitle}
                badge={item.badge}
                badgeTone={item.badgeTone}
                description={item.description}
              />
            ))}
          </CockpitSignalListCard>
        </div>

        <div id="indexees" className="grid gap-6 xl:grid-cols-2 scroll-mt-24">
          <CockpitSignalListCard
            title={`${indexationScopeLabel} par site`}
            description="La lecture actuelle des pages que PraeviSEO relit déjà dans Google et qu’il considère déjà comme bien lues ou encore fragiles."
            empty={syncedSites.length === 0}
            emptyMessage="Aucune indexation synchronisée pour le moment. Reliez d’abord Google pour ouvrir ce cockpit."
          >
            {syncedSites.map((site) => (
              <CockpitSignalItem
                key={site.site_id}
                title={site.name}
                subtitle={site.gsc_property_url ?? site.site_id}
                badge={`${site.summary.gsc_indexed_pages} bien lues / ${site.summary.gsc_non_indexed_pages} à vérifier`}
                badgeTone="success"
                description={`Dernière synchro GSC : ${site.gsc_last_sync_at ? formatDate(site.gsc_last_sync_at) : "récemment"}${site.gsc_data_as_of ? ` · données arrêtées au ${formatDate(site.gsc_data_as_of)}` : ""}.`}
              />
            ))}
          </CockpitSignalListCard>

          <CockpitSignalListCard
            id="alertes"
            className="scroll-mt-24"
            title="Alertes simples"
            description="Les sites où certaines pages demandent encore une vérification ou un renfort côté Google."
            empty={indexationAlerts.length + pendingSites.length === 0}
            emptyMessage="Aucune alerte simple d’indexation pour le moment. Le cockpit reste déjà à jour."
          >
            {indexationAlerts.map((item) => (
              <CockpitSignalItem
                key={`${item.site_name}-${item.slug}-alert`}
                title={item.label}
                subtitle={item.site_name}
                badge="À vérifier avec Google"
                badgeTone="warning"
                description={item.detail}
              />
            ))}
            {pendingSites.map((site) => (
              <CockpitSignalItem
                key={site.site_id}
                title={site.name}
                subtitle={site.gsc_property_url ?? site.site_id}
                badge="À vérifier"
                badgeTone="warning"
                description="PraeviSEO attend encore une lecture d’indexation exploitable sur ce site."
              />
            ))}
          </CockpitSignalListCard>
        </div>

        <div id="sante" className="grid gap-6 xl:grid-cols-2 scroll-mt-24">
          <CockpitSignalListCard
            title="Santé site liée à l’indexation"
            description="Les signaux structurels que PraeviSEO relie déjà à la lecture Google : pages faibles, orphelines et issues crawl."
            empty={observedHealthSites.length === 0}
            emptyMessage="Aucune faiblesse structurelle forte observée pour le moment autour de l’indexation."
          >
            {observedHealthSites.slice(0, 6).map((site) => (
              <CockpitSignalItem
                key={`${site.site_id}-health-indexation`}
                title={site.name}
                subtitle={site.summary.observed_snapshot_date ? `snapshot du ${formatDate(site.summary.observed_snapshot_date)}` : "observation récente"}
                badge={`Santé ${site.summary.observed_site_health_score || "n/a"}`}
                badgeTone={site.summary.observed_site_health_score >= 70 ? "success" : "secondary"}
                description={`${site.summary.observed_weak_pages} page(s) faible(s), ${site.summary.observed_orphan_pages} page(s) orpheline(s), ${site.summary.observed_crawl_issues} issue(s) crawl.`}
              />
            ))}
          </CockpitSignalListCard>

          <CockpitSignalListCard
            title="URLs structurellement fragiles"
            description="Les URLs que PraeviSEO surveille aussi côté structure avant même qu’elles deviennent un vrai problème Google."
            empty={connectedSites.flatMap((site) => site.summary.observed_orphan_alerts).length === 0}
            emptyMessage="Aucune URL structurellement fragile à remonter pour le moment."
          >
            {connectedSites
              .flatMap((site) =>
                site.summary.observed_orphan_alerts.slice(0, 2).map((item) => ({
                  siteName: site.name,
                  item,
                }))
              )
              .slice(0, 6)
              .map(({ siteName, item }) => (
                <CockpitSignalItem
                  key={`${siteName}-${item.slug}-fragile`}
                  title={item.label}
                  subtitle={siteName}
                badge="À mieux relier"
                  badgeTone="warning"
                  description={`Page peu reliée ou fragile : autorité ${item.authority_score}, indexabilité ${item.indexability_state}.`}
                />
              ))}
          </CockpitSignalListCard>
        </div>

        <CockpitSignalListCard
          id="google"
          className="scroll-mt-24"
          title="État Google"
          description="La vision simple de chaque site dans le cockpit Free, sans jargon technique."
          empty={dashboard.sites.length === 0}
          emptyMessage="Aucun site à afficher pour le moment."
        >
          {dashboard.sites.map((site) => (
            <CockpitSignalItem
              key={site.site_id}
              title={site.name}
              subtitle={site.readiness.gsc_connected ? "Google Search Console reliée" : "Google Search Console à relier"}
              badge={site.readiness.gsc_connected ? "Lecture active" : "En attente"}
              badgeTone={site.readiness.gsc_connected ? "success" : "secondary"}
              description="PraeviSEO garde ici une lecture simple et directement utile de l’état Google."
            />
          ))}
        </CockpitSignalListCard>
      </div>
    </div>
  );
}
