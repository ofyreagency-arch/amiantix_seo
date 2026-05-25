import { Badge } from "@/components/ui/badge";
import { Topbar } from "@/components/layout/topbar";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { getOptimizations } from "@/lib/praeviseo-api";

export default async function OptimizationsPage() {
  const optimizations = await getOptimizations();

  return (
    <div className="min-h-screen">
      <Topbar
        title="Optimisations"
        subtitle="Vue client des suggestions, réécritures et prochaines actions moteur."
      />
      <div className="p-6 space-y-6">
        <div className="grid gap-4 md:grid-cols-4">
          {[
            ["En attente", optimizations.stats.pending],
            ["Appliquées", optimizations.stats.applied],
            ["Rejetées", optimizations.stats.rejected],
            ["Total", optimizations.stats.total],
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
            <CardTitle>Suggestions recentes</CardTitle>
            <CardDescription>
              Le client voit ici les suggestions reelles du moteur sur ses propres pages.
            </CardDescription>
          </CardHeader>
          <CardContent className="space-y-4">
            {optimizations.items.map((item) => (
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
            ))}
          </CardContent>
        </Card>
      </div>
    </div>
  );
}
