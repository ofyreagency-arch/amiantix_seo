import { CockpitSectionNav } from "@/components/cockpit/section-nav";
import { CockpitMetricGrid } from "@/components/cockpit/metric-grid";
import { CockpitSignalItem, CockpitSignalListCard } from "@/components/cockpit/signal-list";
import { Topbar } from "@/components/layout/topbar";
import { getDashboard, getOptimizations, getPublications } from "@/lib/praeviseo-api";

export default async function PagesCockpitPage() {
  const dashboard = await getDashboard();
  const optimizations = await getOptimizations();
  const publications = await getPublications();

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
  const refreshPages = publications.items
    .filter(
      (item) =>
        !!item.latest_suggestion ||
        (item.seo_score ?? 0) < 80 ||
        (!item.published_live && item.status === "published") ||
        ((item.gsc_metrics.position ?? 99) >= 8 && item.gsc_metrics.impressions > 0)
    )
    .slice(0, 6);
  const scoringPages = [...publications.items]
    .sort((a, b) => {
      const aScore = (a.seo_score ?? 0) + (a.topical_score ?? 0) + (a.quality_score ?? 0);
      const bScore = (b.seo_score ?? 0) + (b.topical_score ?? 0) + (b.quality_score ?? 0);

      return bScore - aScore;
    })
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
            { label: "Potentiel SEO", href: "#potentiel", count: scoringPages.length, tone: "secondary" },
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
        </div>

        <div id="vue-ensemble" className="scroll-mt-24">
          <CockpitMetricGrid
            items={[
              { label: "Pages suivies", value: pageSignals.length },
              { label: "Pages en hausse", value: risingPages.length, tone: "success" },
              { label: "Pages en baisse", value: fallingPages.length, tone: "danger" },
              { label: "Pages à fort potentiel", value: scoringPages.length, tone: "secondary" },
              { label: "Refresh suggérés", value: refreshPages.length, tone: refreshPages.length > 0 ? "warning" : "secondary" },
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
            emptyMessage="Aucune hausse forte n’est détectée pour le moment. PraeviSEO affichera ici les prochains signaux positifs."
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
            emptyMessage="Aucune chute nette pour le moment. Le cockpit surveille déjà les prochaines baisses utiles."
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
            emptyMessage="Les meilleures pages apparaîtront ici dès que davantage de signaux GSC remonteront."
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
            description="Les pages déjà solides côté contenu ou qualité, donc les plus prometteuses à pousser ensuite."
            empty={scoringPages.length === 0}
            emptyMessage="Aucune page scorée pour le moment. Ce bloc se remplira dès que le moteur aura assez de matière."
          >
            {scoringPages.map((item) => (
              <CockpitSignalItem
                key={`${item.site_id}-${item.slug}-score`}
                title={item.title}
                subtitle={item.site_id}
                badge={`SEO ${item.seo_score ?? "n/a"}`}
                badgeTone={(item.seo_score ?? 0) >= 80 ? "success" : "secondary"}
                description={`Topical ${item.topical_score ?? "n/a"}, qualité ${item.quality_score ?? "n/a"}, indexabilité ${item.indexability_score ?? "n/a"}.`}
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
            emptyMessage="Aucun refresh conseillé pour le moment. PraeviSEO affichera ici les contenus à relancer."
          >
            {refreshPages.map((item) => (
              <CockpitSignalItem
                key={`${item.site_id}-${item.slug}-refresh`}
                title={item.title}
                subtitle={item.site_id}
                badge={item.latest_suggestion ? "refresh conseillé" : item.published_live ? "à consolider" : "à pousser"}
                badgeTone={item.latest_suggestion ? "warning" : item.published_live ? "secondary" : "success"}
                description={
                  item.latest_suggestion?.summary ??
                  `${item.gsc_metrics.impressions} impressions, CTR ${item.gsc_metrics.ctr.toFixed(1)} %, position ${item.gsc_metrics.position?.toFixed(1) ?? "n/a"}, SEO score ${item.seo_score ?? "n/a"}.`
                }
              />
            ))}
          </CockpitSignalListCard>

          <CockpitSignalListCard
            id="surveiller"
            title="Pages à surveiller"
            description="Les pages où PraeviSEO voit déjà un potentiel SEO ou un signal à traiter vite."
            empty={pagesToWatch.length === 0}
            emptyMessage="Aucune page chaude pour le moment. Les prochaines opportunités viendront enrichir ce bloc."
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
