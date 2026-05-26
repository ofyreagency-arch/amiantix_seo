import { CockpitSectionNav } from "@/components/cockpit/section-nav";
import { CockpitMetricGrid } from "@/components/cockpit/metric-grid";
import { CockpitSignalItem, CockpitSignalListCard } from "@/components/cockpit/signal-list";
import { Topbar } from "@/components/layout/topbar";
import { Card } from "@/components/ui/card";
import { getDashboard, getOptimizations } from "@/lib/praeviseo-api";

export default async function PagesCockpitPage() {
  const dashboard = await getDashboard();
  const optimizations = await getOptimizations();

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
            id="surveiller"
            className="scroll-mt-24"
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
