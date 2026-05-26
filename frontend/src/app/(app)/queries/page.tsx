import { CockpitSectionNav } from "@/components/cockpit/section-nav";
import { CockpitMetricGrid } from "@/components/cockpit/metric-grid";
import { CockpitSignalItem, CockpitSignalListCard } from "@/components/cockpit/signal-list";
import { Topbar } from "@/components/layout/topbar";
import { Card } from "@/components/ui/card";
import { getDashboard, getOptimizations } from "@/lib/praeviseo-api";

export default async function QueriesCockpitPage() {
  const dashboard = await getDashboard();
  const optimizations = await getOptimizations();

  const topQueries = dashboard.sites
    .flatMap((site) => site.summary.top_queries.map((item) => ({ ...item, site_name: site.name })))
    .slice(0, 12);
  const visibleQueries = topQueries.filter((item) => item.position <= 10).slice(0, 6);
  const risingQueries = dashboard.sites
    .flatMap((site) => site.summary.top_rising_queries.map((item) => ({ ...item, site_name: site.name })))
    .slice(0, 6);
  const potentialQueries = topQueries.filter((item) => item.position > 10 || item.impressions >= 10).slice(0, 6);
  const newQueries = dashboard.sites
    .flatMap((site) => site.summary.new_queries.map((item) => ({ ...item, site_name: site.name })))
    .slice(0, 6);
  const emergingQueries = [
    ...newQueries,
    ...optimizations.gsc_opportunities.items
      .filter((item) => item.type === "emerging_query" && item.query)
      .map((item) => ({
        query: item.query ?? "Requête suivie",
        impressions: Number(item.metrics.impressions ?? 0),
        previous_impressions: 0,
        delta_impressions: Number(item.metrics.impressions ?? 0),
        delta_percent: 100,
        clicks: Number(item.metrics.clicks ?? 0),
        ctr: Number(item.metrics.ctr ?? 0),
        position: Number(item.metrics.position ?? 0),
        site_name: item.site_name,
      })),
  ].slice(0, 6);

  return (
    <div className="min-h-screen">
      <Topbar
        title="Requêtes Google"
        subtitle="Comprendre ce que Google associe vraiment à votre site, et quelles requêtes gagnent en potentiel."
      />

      <div className="p-6 space-y-6">
        <CockpitSectionNav
          items={[
            { label: "Vue d’ensemble", href: "#vue-ensemble", count: topQueries.length, tone: "default" },
            { label: "Meilleures requêtes", href: "#meilleures", count: visibleQueries.length, tone: "success" },
            { label: "En hausse", href: "#hausse", count: risingQueries.length, tone: "success" },
            { label: "À potentiel", href: "#potentiel", count: potentialQueries.length, tone: "warning" },
            { label: "Émergentes", href: "#emergentes", count: emergingQueries.length, tone: "secondary" },
          ]}
        />

        <div className="rounded-2xl border border-brand/20 bg-brand-muted px-6 py-6">
          <h1 className="text-2xl font-bold tracking-tight text-text">La lecture Search Console intelligente de vos requêtes</h1>
          <p className="mt-2 max-w-3xl text-sm leading-7 text-text-muted">
            PraeviSEO montre déjà les requêtes qui portent votre visibilité, celles qui émergent, et celles qui
            méritent une meilleure réponse éditoriale.
          </p>
        </div>

        <div id="vue-ensemble" className="scroll-mt-24">
          <CockpitMetricGrid
            items={[
              { label: "Requêtes suivies", value: topQueries.length },
              { label: "Déjà visibles", value: visibleQueries.length, tone: "success" },
              { label: "En hausse", value: risingQueries.length, tone: "success" },
              { label: "À potentiel", value: potentialQueries.length, tone: "warning" },
              { label: "Émergentes", value: newQueries.length, tone: "secondary" },
            ]}
          />
        </div>

        <div id="meilleures" className="grid gap-6 xl:grid-cols-2 scroll-mt-24">
          <CockpitSignalListCard
            title="Meilleures requêtes"
            description="Les requêtes où votre site est déjà vraiment compris par Google."
            empty={visibleQueries.length === 0}
            emptyMessage="Aucune requête déjà bien visible pour le moment. Le cockpit les affichera dès qu’elles montent."
          >
            {visibleQueries.map((item) => (
              <CockpitSignalItem
                key={`${item.site_name}-${item.query}-visible`}
                title={item.query}
                subtitle={item.site_name}
                badge="Déjà visible"
                badgeTone="success"
                description={`${item.impressions} impressions, ${item.clicks} clics, CTR ${item.ctr.toFixed(1)} %, position ${item.position.toFixed(1)}.`}
              />
            ))}
          </CockpitSignalListCard>

          <CockpitSignalListCard
            id="hausse"
            className="scroll-mt-24"
            title="Requêtes en hausse"
            description="Les requêtes qui gagnent le plus d’impressions sur la période récente."
            empty={risingQueries.length === 0}
            emptyMessage="Aucune hausse franche de requête pour le moment. PraeviSEO affichera ici les prochaines progressions."
          >
            {risingQueries.map((item) => (
              <CockpitSignalItem
                key={`${item.site_name}-${item.query}-rising`}
                title={item.query}
                subtitle={item.site_name}
                badge={`+${item.delta_impressions} impressions`}
                badgeTone="success"
                description={`CTR ${item.ctr.toFixed(1)} %, position ${item.position.toFixed(1)}, ${item.previous_impressions} impressions avant.`}
              />
            ))}
          </CockpitSignalListCard>
        </div>

        <div id="potentiel" className="grid gap-6 xl:grid-cols-2 scroll-mt-24">
          <CockpitSignalListCard
            id="potentiel"
            title="Requêtes à potentiel"
            description="Celles qui peuvent devenir un vrai levier SEO si on pousse la bonne page."
            empty={potentialQueries.length === 0}
            emptyMessage="Aucune requête chaude à pousser pour le moment. PraeviSEO surveille déjà les prochains signaux."
          >
            {potentialQueries.map((item) => (
              <CockpitSignalItem
                key={`${item.site_name}-${item.query}-potential`}
                title={item.query}
                subtitle={item.site_name}
                badge="À pousser"
                badgeTone="warning"
                description={`${item.impressions} impressions, CTR ${item.ctr.toFixed(1)} %, position ${item.position.toFixed(1)}.`}
              />
            ))}
          </CockpitSignalListCard>

          <CockpitSignalListCard
            id="emergentes"
            className="scroll-mt-24"
            title="Nouvelles requêtes"
            description="Les requêtes que Google commence à associer à votre site et qui ouvrent de nouvelles pistes."
            empty={emergingQueries.length === 0}
            emptyMessage="Aucune nouvelle requête forte pour le moment. Le cockpit les fera remonter automatiquement."
          >
            {emergingQueries.map((item) => (
              <CockpitSignalItem
                key={`${item.site_name}-${item.query}-emerging`}
                title={item.query}
                subtitle={item.site_name}
                badge={item.previous_impressions === 0 ? "Nouvelle requête" : `+${item.delta_impressions} impressions`}
                badgeTone="secondary"
                description={`${item.impressions} impressions, CTR ${item.ctr.toFixed(1)} %, position ${item.position.toFixed(1)}.`}
              />
            ))}
          </CockpitSignalListCard>
        </div>
      </div>
    </div>
  );
}
