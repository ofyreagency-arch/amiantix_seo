import { CockpitSectionNav } from "@/components/cockpit/section-nav";
import { CockpitMetricGrid } from "@/components/cockpit/metric-grid";
import { CockpitSignalItem, CockpitSignalListCard } from "@/components/cockpit/signal-list";
import { Topbar } from "@/components/layout/topbar";
import { getDashboard, getOptimizations, getPublications } from "@/lib/praeviseo-api";
import { formatDate } from "@/lib/utils";

export default async function PagesCockpitPage() {
  const dashboard = await getDashboard();
  const optimizations = await getOptimizations();
  const publications = await getPublications();
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

  const pageSignals = dashboard.sites.flatMap((site) => [
    ...site.summary.top_rising_pages.map((item) => ({ ...item, site_name: site.name, trend: "up" as const })),
    ...site.summary.top_falling_pages.map((item) => ({ ...item, site_name: site.name, trend: "down" as const })),
  ]);
  const risingPages = pageSignals.filter((item) => item.trend === "up").slice(0, 6);
  const fallingPages = pageSignals.filter((item) => item.trend === "down").slice(0, 6);
  const pagesToWatch = optimizations.gsc_opportunities.items
    .filter((item) => item.type === "near_top_10" || item.type === "low_ctr" || item.type === "sustained_drop")
    .slice(0, 6);
  const bestPages = [...risingPages]
    .sort((a, b) => b.impressions - a.impressions)
    .slice(0, 6);
  const explicitRefreshPages = publications.items
    .filter(
      (item) =>
        !!item.latest_suggestion ||
        (item.seo_score ?? 0) < 80 ||
        (!item.published_live && item.status === "published") ||
        ((item.gsc_metrics.position ?? 99) >= 8 && item.gsc_metrics.impressions > 0)
    )
    .slice(0, 6);
  const scoredPages = [...publications.items]
    .sort((a, b) => {
      const aScore = (a.seo_score ?? 0) + (a.topical_score ?? 0) + (a.quality_score ?? 0);
      const bScore = (b.seo_score ?? 0) + (b.topical_score ?? 0) + (b.quality_score ?? 0);

      return bScore - aScore;
    })
    .filter((item) => (item.seo_score ?? 0) > 0 || (item.topical_score ?? 0) > 0 || (item.quality_score ?? 0) > 0)
    .slice(0, 6);
  const potentialPages =
    scoredPages.length > 0
      ? scoredPages
      : pagesToWatch
          .filter((item) => item.type === "near_top_10" || item.type === "low_ctr")
          .map((item) => ({
            id: `${item.site_id}-${item.slug}`,
            title: item.label,
            site_id: item.site_id,
            seo_score: null,
            topical_score: null,
            quality_score: null,
            indexability_score: null,
            reason: item.reason,
          }))
          .slice(0, 6);
  const refreshPages =
    explicitRefreshPages.length > 0
      ? explicitRefreshPages
      : pagesToWatch
          .filter((item) => item.type === "near_top_10" || item.type === "sustained_drop")
          .map((item) => ({
            id: `${item.site_id}-${item.slug}`,
            title: item.label,
            site_id: item.site_id,
            latest_suggestion: null,
            published_live: true,
            gsc_metrics: {
              impressions: Number(item.metrics.impressions ?? 0),
              ctr: Number(item.metrics.ctr ?? 0),
              position: Number(item.metrics.position ?? 0),
            },
            seo_score: null,
            reason: item.reason,
          }))
          .slice(0, 6);
  const totalDeltaImpressions = pageSignals.reduce((sum, item) => sum + item.delta_impressions, 0);

  return (
    <div className="min-h-screen">
      <Topbar
        title="Pages"
        subtitle="La lecture page par page de votre SEO : progression, baisses, potentiel et pages à surveiller."
      />

      <div className="p-6 space-y-6">
        <CockpitSectionNav
          items={[
            { label: "Vue d’ensemble", href: "#vue-ensemble", count: pageSignals.length, tone: "default" },
            { label: "Pages qui montent", href: "#montent", count: risingPages.length, tone: "success" },
            { label: "Pages qui chutent", href: "#chutent", count: fallingPages.length, tone: "warning" },
            { label: "Meilleures pages", href: "#meilleures", count: bestPages.length, tone: "secondary" },
            { label: "Potentiel SEO", href: "#potentiel", count: potentialPages.length, tone: "secondary" },
            { label: "À refresh", href: "#refresh", count: refreshPages.length, tone: "warning" },
            { label: "À surveiller", href: "#surveiller", count: pagesToWatch.length, tone: "danger" },
          ]}
        />

        <div className="rounded-2xl border border-brand/20 bg-brand-muted px-6 py-6">
          <h1 className="text-2xl font-bold tracking-tight text-text">Vos pages SEO les plus importantes en un coup d’œil</h1>
          <p className="mt-2 max-w-3xl text-sm leading-7 text-text-muted">
            PraeviSEO transforme déjà Google Search Console en lecture claire : quelles pages progressent, lesquelles
            ralentissent, et où agir vite.
          </p>
          {(freshestSyncAt || freshestDataAsOf) && (
            <p className="mt-3 text-xs text-text-subtle">
              {freshestSyncAt ? `Dernière synchro GSC : ${formatDate(freshestSyncAt)}.` : "Synchronisation GSC en attente."}{" "}
              {freshestDataAsOf ? `Données arrêtées au ${formatDate(freshestDataAsOf)}.` : ""}
            </p>
          )}
          <p className="mt-3 max-w-3xl text-xs leading-6 text-text-subtle">
            Cette vue compare les pages relues sur une fenêtre GSC récente de 28 jours. Sur un petit site, il est
            normal que seules quelques pages remontent fortement d’une période à l’autre.
          </p>
        </div>

        <div className="rounded-xl border border-border bg-surface p-5">
          <p className="text-xs text-text-subtle">Lecture du moment</p>
          <p className="mt-3 text-lg font-semibold text-text">
            {risingPages.length > 0
              ? `${risingPages.length} page${risingPages.length > 1 ? "s" : ""} gagne${risingPages.length > 1 ? "nt" : ""} déjà du terrain dans Google.`
              : pagesToWatch.length > 0
                ? `${pagesToWatch.length} page${pagesToWatch.length > 1 ? "s" : ""} mérite${pagesToWatch.length > 1 ? "nt" : ""} déjà une surveillance prioritaire.`
                : "PraeviSEO garde ici une lecture calme mais utile des pages, en attendant plus de matière côté Google."}
          </p>
          <div className="mt-4 space-y-3 text-sm text-text-muted">
            <p>
              {bestPages.length > 0
                ? `${bestPages.length} page${bestPages.length > 1 ? "s" : ""} ressort${bestPages.length > 1 ? "ent" : ""} déjà comme vos appuis les plus lisibles dans Google.`
                : "Le cockpit attend encore que davantage de pages accumulent assez d’impressions pour devenir des appuis clairs."}
            </p>
            <p>
              {refreshPages.length > 0
                ? `${refreshPages.length} piste${refreshPages.length > 1 ? "s" : ""} de refresh ou de consolidation sont déjà identifiées.`
                : "Aucune relance éditoriale forte pour le moment : cela peut simplement vouloir dire que Google remonte encore peu de signaux exploitables."}
            </p>
          </div>
        </div>

        <div id="vue-ensemble" className="scroll-mt-24">
          <CockpitMetricGrid
            items={[
              { label: "Pages suivies", value: pageSignals.length },
              { label: "Pages en hausse", value: risingPages.length, tone: "success" },
              { label: "Pages en baisse", value: fallingPages.length, tone: "danger" },
              { label: "Pages à fort potentiel", value: potentialPages.length, tone: potentialPages.length > 0 ? "secondary" : "warning" },
              { label: "Pistes de refresh", value: refreshPages.length, tone: refreshPages.length > 0 ? "warning" : "secondary" },
              {
                label: "Variation globale",
                value: `${totalDeltaImpressions > 0 ? "+" : ""}${new Intl.NumberFormat("fr-FR").format(totalDeltaImpressions)}`,
                tone: totalDeltaImpressions < 0 ? "danger" : totalDeltaImpressions > 0 ? "success" : "secondary",
              },
            ]}
          />
        </div>

        <div id="montent" className="grid gap-6 xl:grid-cols-2 scroll-mt-24">
          <CockpitSignalListCard
            title="Pages qui montent"
            description="Les pages qui gagnent le plus de visibilité récemment dans Google."
            empty={risingPages.length === 0}
            emptyMessage="Aucune hausse forte n’est détectée pour le moment. Sur un petit site, plusieurs lectures peuvent rester stables avant la prochaine progression nette."
          >
            {risingPages.map((item) => (
              <CockpitSignalItem
                key={`${item.site_name}-${item.slug}-up`}
                title={item.label}
                subtitle={item.site_name}
                badge="En hausse"
                badgeTone="success"
                description={`+${item.delta_impressions} impressions, CTR ${item.ctr.toFixed(1)} %, position ${item.position.toFixed(1)}.`}
              />
            ))}
          </CockpitSignalListCard>

          <CockpitSignalListCard
            id="chutent"
            className="scroll-mt-24"
            title="Pages qui chutent"
            description="Les pages qui méritent une relance, un refresh ou une meilleure réponse SEO."
            empty={fallingPages.length === 0}
            emptyMessage="Aucune chute nette pour le moment. C’est sain : le cockpit surveille déjà les prochaines baisses vraiment utiles à traiter."
          >
            {fallingPages.map((item) => (
              <CockpitSignalItem
                key={`${item.site_name}-${item.slug}-down`}
                title={item.label}
                subtitle={item.site_name}
                badge="En baisse"
                badgeTone="danger"
                description={`${item.delta_impressions} impressions, CTR ${item.ctr.toFixed(1)} %, position ${item.position.toFixed(1)}.`}
              />
            ))}
          </CockpitSignalListCard>
        </div>

        <div id="meilleures" className="grid gap-6 xl:grid-cols-2 scroll-mt-24">
          <CockpitSignalListCard
            title="Meilleures pages"
            description="Les pages déjà visibles qui portent le plus votre présence SEO."
            empty={bestPages.length === 0}
            emptyMessage="Les meilleures pages apparaîtront ici dès que davantage de signaux GSC remonteront. Avec peu de volume, il est normal que cette lecture prenne un peu de temps."
          >
            {bestPages.map((item) => (
              <CockpitSignalItem
                key={`${item.site_name}-${item.slug}-best`}
                title={item.label}
                subtitle={item.site_name}
                badge={`${item.impressions} impressions`}
                description={`CTR ${item.ctr.toFixed(1)} %, position ${item.position.toFixed(1)}, ${item.previous_impressions} impressions avant.`}
              />
            ))}
          </CockpitSignalListCard>

          <CockpitSignalListCard
            id="potentiel"
            className="scroll-mt-24"
            title="Pages à fort potentiel SEO"
            description={
              scoredPages.length > 0
                ? "Les pages déjà solides côté contenu ou qualité, donc les plus prometteuses à pousser ensuite."
                : "Même quand le scoring détaillé est encore léger, PraeviSEO garde ici les pages à consolider en priorité."
            }
            empty={potentialPages.length === 0}
            emptyMessage="Aucune page à fort potentiel pour le moment. PraeviSEO les affichera dès qu’une page combine assez de signaux pour mériter une vraie poussée."
          >
            {potentialPages.map((item) => (
              <CockpitSignalItem
                key={`${item.site_id}-${item.title}-score`}
                title={item.title}
                subtitle={item.site_id}
                badge={item.seo_score ? `SEO ${item.seo_score}` : "À consolider"}
                badgeTone={item.seo_score ? ((item.seo_score ?? 0) >= 80 ? "success" : "secondary") : "warning"}
                description={
                  item.seo_score
                    ? `Topical ${item.topical_score ?? "n/a"}, qualité ${item.quality_score ?? "n/a"}, indexabilité ${item.indexability_score ?? "n/a"}.`
                    : "Cette page montre déjà un signal utile dans Google et mérite une consolidation éditoriale."
                }
              />
            ))}
          </CockpitSignalListCard>
        </div>

        <div id="refresh" className="grid gap-6 xl:grid-cols-2 scroll-mt-24">
          <CockpitSignalListCard
            id="refresh"
            title="Pages à refresh"
            description="Les contenus qui méritent une relance éditoriale, un enrichissement ou une meilleure exécution."
            empty={refreshPages.length === 0}
            emptyMessage="Aucune piste de refresh pour le moment. Cela peut simplement signifier que Google ne remonte pas encore assez de matière pour justifier une relance nette."
          >
            {refreshPages.map((item) => (
              <CockpitSignalItem
                key={`${item.site_id}-${item.title}-refresh`}
                title={item.title}
                subtitle={item.site_id}
                badge={item.latest_suggestion ? "refresh conseillé" : item.published_live ? "à consolider" : "à pousser"}
                badgeTone={item.latest_suggestion ? "warning" : item.published_live ? "secondary" : "success"}
                description={
                  item.latest_suggestion?.summary ??
                  ("reason" in item && typeof item.reason === "string" && item.reason.length > 0
                    ? item.reason
                    :
                  `${item.gsc_metrics.impressions} impressions, CTR ${item.gsc_metrics.ctr.toFixed(1)} %, position ${item.gsc_metrics.position?.toFixed(1) ?? "n/a"}, SEO score ${item.seo_score ?? "n/a"}.`
                  )
                }
              />
            ))}
          </CockpitSignalListCard>

          <CockpitSignalListCard
            id="surveiller"
            title="Pages à surveiller"
            description="Les pages où PraeviSEO voit déjà un potentiel SEO ou un signal à traiter vite."
            empty={pagesToWatch.length === 0}
            emptyMessage="Aucune page chaude pour le moment. Les prochaines opportunités viendront enrichir ce bloc dès qu’un signal dépasse le bruit naturel du site."
          >
            {pagesToWatch.map((item) => (
              <CockpitSignalItem
                key={`${item.site_id}-${item.slug}-${item.type}`}
                title={item.label}
                subtitle={item.site_name}
                badge={item.priority_label}
                badgeTone={item.priority_level === "high" ? "warning" : item.type === "sustained_drop" ? "danger" : "secondary"}
                description={item.reason}
              />
            ))}
          </CockpitSignalListCard>
        </div>
      </div>
    </div>
  );
}
