import { Badge } from "@/components/ui/badge";
import { Topbar } from "@/components/layout/topbar";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { getOptimizations } from "@/lib/praeviseo-api";

function opportunityTypeLabel(type: string): string {
  return (
    {
      low_ctr: "CTR faible",
      near_top_10: "Proche du top 10",
      emerging_query: "Requête émergente",
      sustained_drop: "Baisse durable",
    }[type] ?? "Opportunité SEO"
  );
}

function opportunityMetricLine(metrics: Record<string, number | string>): string {
  const impressions = Number(metrics.impressions ?? 0);
  const ctr = Number(metrics.ctr ?? 0);
  const position = Number(metrics.position ?? 0);
  const previousImpressions = Number(metrics.previous_impressions ?? 0);

  if (previousImpressions > 0) {
    return `${new Intl.NumberFormat("fr-FR").format(impressions)} impressions recentes, ${new Intl.NumberFormat("fr-FR").format(previousImpressions)} avant, position ${position.toFixed(1)}`;
  }

  return `${new Intl.NumberFormat("fr-FR").format(impressions)} impressions, CTR ${ctr.toFixed(1)} %, position ${position.toFixed(1)}`;
}

export default async function OptimizationsPage() {
  const optimizations = await getOptimizations();
  const opportunities = optimizations.gsc_opportunities.items;

  return (
    <div className="min-h-screen">
      <Topbar
        title="Optimisations"
        subtitle="Les opportunités SEO détectées par PraeviSEO à partir de Google Search Console."
        actions={
          <Button href="/dashboard" variant="secondary" size="sm">
            Retour au dashboard
          </Button>
        }
      />
      <div className="p-6 space-y-6">
        <div className="rounded-2xl border border-brand/20 bg-brand-muted px-6 py-6">
          <h1 className="text-2xl font-bold tracking-tight text-text">Vos meilleures opportunités SEO, sans installer quoi que ce soit</h1>
          <p className="mt-2 max-w-3xl text-sm leading-7 text-text-muted">
            PraeviSEO lit déjà Google Search Console pour repérer les pages proches du top 10, les CTR faibles,
            les baisses de visibilité et les requêtes qui méritent une réponse plus forte.
          </p>
        </div>

        <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
          {[
            ["Actionnables maintenant", optimizations.gsc_opportunities.summary.ready],
            ["Priorité haute", optimizations.gsc_opportunities.summary.high_priority],
            ["Proches du top 10", optimizations.gsc_opportunities.summary.near_top_10],
            ["CTR à relancer", optimizations.gsc_opportunities.summary.low_ctr],
          ].map(([label, value]) => (
            <Card key={label}>
              <CardHeader className="pb-2">
                <CardDescription>{label}</CardDescription>
                <CardTitle className="text-3xl">{value}</CardTitle>
              </CardHeader>
            </Card>
          ))}
        </div>

        <Card>
          <CardHeader>
            <CardTitle>Opportunités détectées dans Google</CardTitle>
            <CardDescription>
              Ce sont les pages où PraeviSEO voit un gain réaliste à court terme à partir des signaux GSC.
            </CardDescription>
          </CardHeader>
          <CardContent className="space-y-4">
            {opportunities.length === 0 ? (
              <div className="rounded-xl border border-border bg-surface-2 px-4 py-4 text-sm text-text-muted">
                Aucune opportunité GSC forte pour le moment. PraeviSEO continue de surveiller les performances et
                rouvrira des actions dès qu’un signal utile remonte.
              </div>
            ) : (
              opportunities.map((item) => (
                <div key={`${item.site_id}-${item.type}-${item.page_id ?? item.slug}-${item.query ?? "none"}`} className="rounded-xl border border-border p-4 space-y-3">
                  <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                      <p className="text-sm font-semibold text-text">{item.label || "Page suivie"}</p>
                      <p className="text-xs text-text-subtle">
                        {item.site_name} / {item.slug || "/"}
                      </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                      <Badge variant={item.priority_level === "high" ? "warning" : item.priority_level === "medium" ? "brand-subtle" : "secondary"}>
                        {item.priority_label}
                      </Badge>
                      <Badge variant={item.action_state === "ready" ? "success" : item.action_state === "pending" ? "warning" : "secondary"}>
                        {item.action_state_label}
                      </Badge>
                      <Badge variant="secondary">{opportunityTypeLabel(item.type)}</Badge>
                    </div>
                  </div>

                  <p className="text-sm text-text-muted">{item.reason}</p>

                  {item.query ? (
                    <div className="rounded-lg bg-surface-2 px-3 py-2 text-xs text-text-subtle">
                      Requête observée : <span className="font-medium text-text">{item.query}</span>
                    </div>
                  ) : null}

                  <div className="rounded-lg bg-surface-2 px-3 py-2 text-xs text-text-subtle">
                    {opportunityMetricLine(item.metrics)}
                  </div>

                  <div className="flex flex-wrap items-center justify-between gap-3">
                    <p className="text-sm text-text">
                      Action suggérée : <span className="font-medium">{item.action}</span>
                    </p>
                    <Button href={`/sites/${item.site_id}`} variant="secondary" size="sm">
                      Ouvrir le site
                    </Button>
                  </div>
                </div>
              ))
            )}
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Suggestions moteur récentes</CardTitle>
            <CardDescription>
              Les suggestions déjà ouvertes par le moteur restent visibles ici, mais elles viennent après les priorités GSC.
            </CardDescription>
          </CardHeader>
          <CardContent className="space-y-4">
            {optimizations.items.length === 0 ? (
              <div className="rounded-xl border border-border bg-surface-2 px-4 py-4 text-sm text-text-muted">
                Aucune suggestion moteur récente pour le moment.
              </div>
            ) : (
              optimizations.items.map((item) => (
                <div key={item.id} className="rounded-xl border border-border p-4 space-y-3">
                  <div className="flex flex-wrap items-center gap-2 justify-between">
                    <div>
                      <p className="text-sm font-semibold text-text">{item.page.title}</p>
                      <p className="text-xs text-text-subtle">
                        {item.page.site_id} / {item.page.slug || "/"}
                      </p>
                    </div>
                    <div className="flex items-center gap-2">
                      <Badge variant={item.status === "pending" ? "warning" : item.status === "applied" ? "success" : "secondary"}>
                        {item.status}
                      </Badge>
                      <Badge variant="brand-subtle">{item.source}</Badge>
                    </div>
                  </div>
                  <p className="text-sm text-text-muted">{item.summary}</p>
                  <div className="rounded-lg bg-surface-2 px-3 py-2 text-xs text-text-subtle">
                    Impact attendu : {item.impact_expected}
                  </div>
                </div>
              ))
            )}
          </CardContent>
        </Card>
      </div>
    </div>
  );
}
