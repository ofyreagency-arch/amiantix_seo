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
  const potentialQueries = topQueries.filter((item) => item.position > 10 || item.impressions >= 10).slice(0, 6);
  const emergingQueries = optimizations.gsc_opportunities.items.filter((item) => item.type === "emerging_query" && item.query).slice(0, 6);

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
              { label: "À potentiel", value: potentialQueries.length, tone: "warning" },
              { label: "Émergentes", value: emergingQueries.length, tone: "secondary" },
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
            id="potentiel"
            className="scroll-mt-24"
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
        </div>

        <CockpitSignalListCard
          id="emergentes"
          className="scroll-mt-24"
          title="Requêtes émergentes"
          description="Les requêtes qui progressent vite ou que PraeviSEO commence déjà à considérer comme un signal de croissance."
          empty={emergingQueries.length === 0}
          emptyMessage="Aucune requête émergente forte pour le moment. Ce bloc s’enrichira dès les prochaines hausses nettes."
        >
          {emergingQueries.map((item) => (
            <CockpitSignalItem
              key={`${item.site_id}-${item.query}-emerging`}
              title={item.query ?? "Requête suivie"}
              subtitle={item.site_name}
              badge={item.priority_label}
              badgeTone={item.priority_level === "high" ? "warning" : "secondary"}
              description={item.reason}
            />
          ))}
        </CockpitSignalListCard>
      </div>
    </div>
  );
}
