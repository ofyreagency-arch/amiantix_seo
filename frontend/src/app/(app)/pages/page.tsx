import { CockpitSectionNav } from "@/components/cockpit/section-nav";
import { Topbar } from "@/components/layout/topbar";
import { Badge } from "@/components/ui/badge";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
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

        <div id="vue-ensemble" className="grid gap-4 md:grid-cols-2 xl:grid-cols-4 scroll-mt-24">
          {[
            ["Pages suivies", pageSignals.length],
            ["Pages en hausse", risingPages.length],
            ["Pages en baisse", fallingPages.length],
            [
              "Variation globale",
              `${totalDeltaImpressions > 0 ? "+" : ""}${new Intl.NumberFormat("fr-FR").format(totalDeltaImpressions)}`,
            ],
          ].map(([label, value]) => (
            <Card key={label}>
              <CardHeader className="pb-2">
                <CardDescription>{label}</CardDescription>
                <CardTitle className="text-3xl">{value}</CardTitle>
              </CardHeader>
            </Card>
          ))}
        </div>

        <div id="montent" className="grid gap-6 xl:grid-cols-2 scroll-mt-24">
          <Card>
            <CardHeader>
              <CardTitle>Pages qui montent</CardTitle>
              <CardDescription>Les pages qui gagnent le plus de visibilité récemment dans Google.</CardDescription>
            </CardHeader>
            <CardContent className="space-y-3">
              {risingPages.length === 0 ? (
                <div className="rounded-xl border border-border bg-surface-2 px-4 py-4 text-sm text-text-muted">
                  Aucune hausse forte n’est détectée pour le moment. PraeviSEO affichera ici les prochains signaux positifs.
                </div>
              ) : (
                risingPages.map((item) => (
                  <div key={`${item.site_name}-${item.slug}-up`} className="rounded-xl border border-border px-4 py-3">
                    <div className="flex items-center justify-between gap-3">
                      <div>
                        <p className="text-sm font-semibold text-text">{item.label}</p>
                        <p className="text-xs text-text-subtle">{item.site_name}</p>
                      </div>
                      <Badge variant="success">En hausse</Badge>
                    </div>
                    <p className="mt-2 text-sm text-text-muted">
                      +{item.delta_impressions} impressions, CTR {item.ctr.toFixed(1)} %, position {item.position.toFixed(1)}.
                    </p>
                  </div>
                ))
              )}
            </CardContent>
          </Card>

          <Card id="chutent" className="scroll-mt-24">
            <CardHeader>
              <CardTitle>Pages qui chutent</CardTitle>
              <CardDescription>Les pages qui méritent une relance, un refresh ou une meilleure réponse SEO.</CardDescription>
            </CardHeader>
            <CardContent className="space-y-3">
              {fallingPages.length === 0 ? (
                <div className="rounded-xl border border-border bg-surface-2 px-4 py-4 text-sm text-text-muted">
                  Aucune chute nette pour le moment. Le cockpit surveille déjà les prochaines baisses utiles.
                </div>
              ) : (
                fallingPages.map((item) => (
                  <div key={`${item.site_name}-${item.slug}-down`} className="rounded-xl border border-border px-4 py-3">
                    <div className="flex items-center justify-between gap-3">
                      <div>
                        <p className="text-sm font-semibold text-text">{item.label}</p>
                        <p className="text-xs text-text-subtle">{item.site_name}</p>
                      </div>
                      <Badge variant="danger">En baisse</Badge>
                    </div>
                    <p className="mt-2 text-sm text-text-muted">
                      {item.delta_impressions} impressions, CTR {item.ctr.toFixed(1)} %, position {item.position.toFixed(1)}.
                    </p>
                  </div>
                ))
              )}
            </CardContent>
          </Card>
        </div>

        <div id="meilleures" className="grid gap-6 xl:grid-cols-2 scroll-mt-24">
          <Card>
            <CardHeader>
              <CardTitle>Meilleures pages</CardTitle>
              <CardDescription>Les pages déjà visibles qui portent le plus votre présence SEO.</CardDescription>
            </CardHeader>
            <CardContent className="space-y-3">
              {bestPages.length === 0 ? (
                <div className="rounded-xl border border-border bg-surface-2 px-4 py-4 text-sm text-text-muted">
                  Les meilleures pages apparaîtront ici dès que davantage de signaux GSC remonteront.
                </div>
              ) : (
                bestPages.map((item) => (
                  <div key={`${item.site_name}-${item.slug}-best`} className="rounded-xl border border-border px-4 py-3">
                    <div className="flex items-center justify-between gap-3">
                      <div>
                        <p className="text-sm font-semibold text-text">{item.label}</p>
                        <p className="text-xs text-text-subtle">{item.site_name}</p>
                      </div>
                      <Badge variant="secondary">{item.impressions} impressions</Badge>
                    </div>
                    <p className="mt-2 text-sm text-text-muted">
                      CTR {item.ctr.toFixed(1)} %, position {item.position.toFixed(1)}, {item.previous_impressions} impressions avant.
                    </p>
                  </div>
                ))
              )}
            </CardContent>
          </Card>

          <Card id="surveiller" className="scroll-mt-24">
            <CardHeader>
              <CardTitle>Pages à surveiller</CardTitle>
              <CardDescription>Les pages où PraeviSEO voit déjà un potentiel SEO ou un signal à traiter vite.</CardDescription>
            </CardHeader>
            <CardContent className="space-y-3">
              {pagesToWatch.length === 0 ? (
                <div className="rounded-xl border border-border bg-surface-2 px-4 py-4 text-sm text-text-muted">
                  Aucune page chaude pour le moment. Les prochaines opportunités viendront enrichir ce bloc.
                </div>
              ) : (
                pagesToWatch.map((item) => (
                  <div key={`${item.site_id}-${item.slug}-${item.type}`} className="rounded-xl border border-border px-4 py-3">
                    <div className="flex items-center justify-between gap-3">
                      <div>
                        <p className="text-sm font-semibold text-text">{item.label}</p>
                        <p className="text-xs text-text-subtle">{item.site_name}</p>
                      </div>
                      <Badge variant={item.priority_level === "high" ? "warning" : item.type === "sustained_drop" ? "danger" : "secondary"}>
                        {item.priority_label}
                      </Badge>
                    </div>
                    <p className="mt-2 text-sm text-text-muted">{item.reason}</p>
                  </div>
                ))
              )}
            </CardContent>
          </Card>
        </div>
      </div>
    </div>
  );
}
