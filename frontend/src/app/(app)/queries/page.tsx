import { CockpitSectionNav } from "@/components/cockpit/section-nav";
import { CockpitMetricGrid } from "@/components/cockpit/metric-grid";
import { CockpitSignalItem, CockpitSignalListCard } from "@/components/cockpit/signal-list";
import { Topbar } from "@/components/layout/topbar";
import { getDashboard, getOptimizations } from "@/lib/praeviseo-api";

export default async function QueriesCockpitPage() {
  const dashboard = await getDashboard();
  const optimizations = await getOptimizations();
  const freshestSyncAt = dashboard.sites
    .map((site) => site.gsc_last_sync_at)
    .filter((value): value is string => Boolean(value))
    .sort()
    .at(-1);
  const freshestDataAsOf = dashboard.sites
    .map((site) => site.gsc_data_as_of)
    .filter((value): value is string => Boolean(value))
    .sort()
    .at(-1);

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
  const queryRadar = [
    ...visibleQueries.map((item) => ({
      title: item.query,
      subtitle: `${item.site_name} · déjà visible dans Google`,
      badge: "Priorité visibilité",
      badgeTone: "success" as const,
      description: `${item.impressions} impressions, ${item.clicks} clics, position ${item.position.toFixed(1)}.`,
    })),
    ...risingQueries.map((item) => ({
      title: item.query,
      subtitle: `${item.site_name} · accélération détectée`,
      badge: `+${item.delta_impressions} impressions`,
      badgeTone: "success" as const,
      description: `La requête monte depuis ${item.previous_impressions} impressions avec une position moyenne ${item.position.toFixed(1)}.`,
    })),
    ...potentialQueries.slice(0, 3).map((item) => ({
      title: item.query,
      subtitle: `${item.site_name} · potentiel éditorial`,
      badge: "À pousser",
      badgeTone: "warning" as const,
      description: `${item.impressions} impressions déjà visibles, position ${item.position.toFixed(1)} et marge de progression claire.`,
    })),
  ].slice(0, 6);
  const queryStory =
    risingQueries.length > 0
      ? `${risingQueries.length} requête${risingQueries.length > 1 ? "s" : ""} gagne${risingQueries.length > 1 ? "nt" : ""} déjà du terrain dans Google.`
      : potentialQueries.length > 0
        ? `${potentialQueries.length} requête${potentialQueries.length > 1 ? "s" : ""} montre${potentialQueries.length > 1 ? "nt" : ""} un potentiel éditorial à travailler.`
        : "PraeviSEO surveille déjà les prochaines requêtes qui commenceront à porter votre visibilité.";

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
          {(freshestSyncAt || freshestDataAsOf) && (
            <p className="mt-3 text-xs text-text-subtle">
              {freshestSyncAt ? `Dernière synchro GSC : ${freshestSyncAt}.` : "Synchronisation GSC en attente."}{" "}
              {freshestDataAsOf ? `Données arrêtées au ${freshestDataAsOf}.` : ""}
            </p>
          )}
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

        <div className="grid gap-6 xl:grid-cols-[1.2fr_0.8fr]">
          <CockpitSignalListCard
            title="Lecture du moment"
            description="Les requêtes que PraeviSEO considère déjà comme les plus parlantes pour comprendre votre présence Google."
            empty={queryRadar.length === 0}
            emptyMessage="Aucune requête suffisamment lisible pour le moment. PraeviSEO surveille déjà les prochains signaux."
          >
            {queryRadar.map((item) => (
              <CockpitSignalItem
                key={`${item.subtitle}-${item.title}-${item.badge}`}
                title={item.title}
                subtitle={item.subtitle}
                badge={item.badge}
                badgeTone={item.badgeTone}
                description={item.description}
              />
            ))}
          </CockpitSignalListCard>

          <div className="rounded-xl border border-border bg-surface p-5">
            <p className="text-xs text-text-subtle">Ce que PraeviSEO comprend déjà</p>
            <p className="mt-3 text-lg font-semibold text-text">{queryStory}</p>
            <div className="mt-4 space-y-3 text-sm text-text-muted">
              <p>
                {visibleQueries.length > 0
                  ? `${visibleQueries.length} requête${visibleQueries.length > 1 ? "s" : ""} sont déjà visibles en première zone.`
                  : "La visibilité reste encore légère, mais les premières requêtes à potentiel sont déjà détectées."}
              </p>
              <p>
                {newQueries.length > 0
                  ? `${newQueries.length} nouvelle${newQueries.length > 1 ? "s" : ""} association${newQueries.length > 1 ? "s" : ""} de Google ouvre${newQueries.length > 1 ? "nt" : ""} de nouvelles pistes.`
                  : "Le cockpit transformera automatiquement les prochaines nouvelles requêtes en recommandations lisibles."}
              </p>
            </div>
          </div>
        </div>

        <div id="meilleures" className="grid gap-6 xl:grid-cols-2 scroll-mt-24">
          <CockpitSignalListCard
            title={visibleQueries.length > 0 ? "Meilleures requêtes" : "Requêtes déjà prometteuses"}
            description={
              visibleQueries.length > 0
                ? "Les requêtes où votre site est déjà vraiment compris par Google."
                : "Même avec peu de volume, PraeviSEO garde ici les requêtes les plus prometteuses à pousser."
            }
            empty={queryRadar.length === 0}
            emptyMessage="Aucune requête déjà lisible pour le moment. Le cockpit les affichera dès qu’elles montent."
          >
            {(visibleQueries.length > 0 ? visibleQueries : potentialQueries.slice(0, 4)).map((item) => (
              <CockpitSignalItem
                key={`${item.site_name}-${item.query}-visible`}
                title={item.query}
                subtitle={item.site_name}
                badge={visibleQueries.length > 0 ? "Déjà visible" : "À pousser"}
                badgeTone={visibleQueries.length > 0 ? "success" : "warning"}
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
            title="Nouvelles requêtes et signaux émergents"
            description="Les requêtes que Google commence à associer à votre site et celles qui ouvrent de nouvelles pistes éditoriales."
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
