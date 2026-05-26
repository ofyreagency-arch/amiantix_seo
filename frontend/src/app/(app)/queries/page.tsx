import { CockpitSectionNav } from "@/components/cockpit/section-nav";
import { Topbar } from "@/components/layout/topbar";
import { Badge } from "@/components/ui/badge";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
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

        <div id="vue-ensemble" className="grid gap-4 md:grid-cols-2 xl:grid-cols-4 scroll-mt-24">
          {[
            ["Requêtes suivies", topQueries.length],
            ["Déjà visibles", visibleQueries.length],
            ["À potentiel", potentialQueries.length],
            ["Émergentes", emergingQueries.length],
          ].map(([label, value]) => (
            <Card key={label}>
              <CardHeader className="pb-2">
                <CardDescription>{label}</CardDescription>
                <CardTitle className="text-3xl">{value}</CardTitle>
              </CardHeader>
            </Card>
          ))}
        </div>

        <div id="meilleures" className="grid gap-6 xl:grid-cols-2 scroll-mt-24">
          <Card>
            <CardHeader>
              <CardTitle>Meilleures requêtes</CardTitle>
              <CardDescription>Les requêtes où votre site est déjà vraiment compris par Google.</CardDescription>
            </CardHeader>
            <CardContent className="space-y-3">
              {visibleQueries.length === 0 ? (
                <div className="rounded-xl border border-border bg-surface-2 px-4 py-4 text-sm text-text-muted">
                  Aucune requête déjà bien visible pour le moment. Le cockpit les affichera dès qu’elles montent.
                </div>
              ) : (
                visibleQueries.map((item) => (
                  <div key={`${item.site_name}-${item.query}-visible`} className="rounded-xl border border-border px-4 py-3">
                    <div className="flex items-center justify-between gap-3">
                      <div>
                        <p className="text-sm font-semibold text-text">{item.query}</p>
                        <p className="text-xs text-text-subtle">{item.site_name}</p>
                      </div>
                      <Badge variant="success">Déjà visible</Badge>
                    </div>
                    <p className="mt-2 text-sm text-text-muted">
                      {item.impressions} impressions, {item.clicks} clics, CTR {item.ctr.toFixed(1)} %, position {item.position.toFixed(1)}.
                    </p>
                  </div>
                ))
              )}
            </CardContent>
          </Card>

          <Card id="potentiel" className="scroll-mt-24">
            <CardHeader>
              <CardTitle>Requêtes à potentiel</CardTitle>
              <CardDescription>Celles qui peuvent devenir un vrai levier SEO si on pousse la bonne page.</CardDescription>
            </CardHeader>
            <CardContent className="space-y-3">
              {potentialQueries.length === 0 ? (
                <div className="rounded-xl border border-border bg-surface-2 px-4 py-4 text-sm text-text-muted">
                  Aucune requête chaude à pousser pour le moment. PraeviSEO surveille déjà les prochains signaux.
                </div>
              ) : (
                potentialQueries.map((item) => (
                  <div key={`${item.site_name}-${item.query}-potential`} className="rounded-xl border border-border px-4 py-3">
                    <div className="flex items-center justify-between gap-3">
                      <div>
                        <p className="text-sm font-semibold text-text">{item.query}</p>
                        <p className="text-xs text-text-subtle">{item.site_name}</p>
                      </div>
                      <Badge variant="warning">À pousser</Badge>
                    </div>
                    <p className="mt-2 text-sm text-text-muted">
                      {item.impressions} impressions, CTR {item.ctr.toFixed(1)} %, position {item.position.toFixed(1)}.
                    </p>
                  </div>
                ))
              )}
            </CardContent>
          </Card>
        </div>

        <Card id="emergentes" className="scroll-mt-24">
          <CardHeader>
            <CardTitle>Requêtes émergentes</CardTitle>
            <CardDescription>
              Les requêtes qui progressent vite ou que PraeviSEO commence déjà à considérer comme un signal de croissance.
            </CardDescription>
          </CardHeader>
          <CardContent className="space-y-3">
            {emergingQueries.length === 0 ? (
              <div className="rounded-xl border border-border bg-surface-2 px-4 py-4 text-sm text-text-muted">
                Aucune requête émergente forte pour le moment. Ce bloc s’enrichira dès les prochaines hausses nettes.
              </div>
            ) : (
              emergingQueries.map((item) => (
                <div key={`${item.site_id}-${item.query}-emerging`} className="rounded-xl border border-border px-4 py-3">
                  <div className="flex items-center justify-between gap-3">
                    <div>
                      <p className="text-sm font-semibold text-text">{item.query}</p>
                      <p className="text-xs text-text-subtle">{item.site_name}</p>
                    </div>
                    <Badge variant={item.priority_level === "high" ? "warning" : "secondary"}>
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
  );
}
