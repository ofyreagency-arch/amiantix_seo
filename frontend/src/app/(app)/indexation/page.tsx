import { CockpitSectionNav } from "@/components/cockpit/section-nav";
import { Topbar } from "@/components/layout/topbar";
import { Badge } from "@/components/ui/badge";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { getDashboard } from "@/lib/praeviseo-api";
import { formatDate } from "@/lib/utils";

export default async function IndexationCockpitPage() {
  const dashboard = await getDashboard();

  const connectedSites = dashboard.sites.filter((site) => site.readiness.gsc_connected);
  const syncedSites = connectedSites.filter((site) => site.summary.gsc_indexation_synced);
  const pendingSites = connectedSites.filter((site) => !site.summary.gsc_indexation_synced);
  const totalIndexedPages = syncedSites.reduce((sum, site) => sum + site.summary.gsc_indexed_pages, 0);

  return (
    <div className="min-h-screen">
      <Topbar
        title="Indexation"
        subtitle="La lecture Google de vos pages : indexées, à surveiller, ou encore en attente de synchronisation."
      />

      <div className="p-6 space-y-6">
        <CockpitSectionNav
          items={[
            { label: "Vue d’ensemble", href: "#vue-ensemble", count: totalIndexedPages, tone: "default" },
            { label: "Pages indexées", href: "#indexees", count: syncedSites.length, tone: "success" },
            { label: "Alertes", href: "#alertes", count: pendingSites.length, tone: "warning" },
            { label: "État Google", href: "#google", count: connectedSites.length, tone: "secondary" },
          ]}
        />

        <div className="rounded-2xl border border-brand/20 bg-brand-muted px-6 py-6">
          <h1 className="text-2xl font-bold tracking-tight text-text">Votre lecture d’indexation Google en clair</h1>
          <p className="mt-2 max-w-3xl text-sm leading-7 text-text-muted">
            PraeviSEO rassemble déjà l’indexation visible dans Google Search Console pour montrer les pages réellement
            lues, les alertes simples et les sites encore en attente de lecture.
          </p>
        </div>

        <div id="vue-ensemble" className="grid gap-4 md:grid-cols-2 xl:grid-cols-4 scroll-mt-24">
          {[
            ["Pages indexées", totalIndexedPages],
            ["Sites reliés à Google", connectedSites.length],
            ["Indexation synchronisée", syncedSites.length],
            ["Alertes d’indexation", pendingSites.length],
          ].map(([label, value]) => (
            <Card key={label}>
              <CardHeader className="pb-2">
                <CardDescription>{label}</CardDescription>
                <CardTitle className="text-3xl">{value}</CardTitle>
              </CardHeader>
            </Card>
          ))}
        </div>

        <div id="indexees" className="grid gap-6 xl:grid-cols-2 scroll-mt-24">
          <Card>
            <CardHeader>
              <CardTitle>Pages indexées par site</CardTitle>
              <CardDescription>La lecture actuelle des pages que Google voit déjà dans vos sites suivis.</CardDescription>
            </CardHeader>
            <CardContent className="space-y-3">
              {syncedSites.length === 0 ? (
                <div className="rounded-xl border border-border bg-surface-2 px-4 py-4 text-sm text-text-muted">
                  Aucune indexation synchronisée pour le moment. Reliez d’abord Google pour ouvrir ce cockpit.
                </div>
              ) : (
                syncedSites.map((site) => (
                  <div key={site.site_id} className="rounded-xl border border-border px-4 py-3">
                    <div className="flex items-center justify-between gap-3">
                      <div>
                        <p className="text-sm font-semibold text-text">{site.name}</p>
                        <p className="text-xs text-text-subtle">{site.gsc_property_url ?? site.site_id}</p>
                      </div>
                      <Badge variant="success">{site.summary.gsc_indexed_pages} indexées</Badge>
                    </div>
                    <p className="mt-2 text-sm text-text-muted">
                      Dernière lecture Google : {site.gsc_last_sync_at ? formatDate(site.gsc_last_sync_at) : "récemment"}.
                    </p>
                  </div>
                ))
              )}
            </CardContent>
          </Card>

          <Card id="alertes" className="scroll-mt-24">
            <CardHeader>
              <CardTitle>Alertes simples</CardTitle>
              <CardDescription>Les sites où l’indexation reste à surveiller ou à finaliser côté lecture Google.</CardDescription>
            </CardHeader>
            <CardContent className="space-y-3">
              {pendingSites.length === 0 ? (
                <div className="rounded-xl border border-border bg-surface-2 px-4 py-4 text-sm text-text-muted">
                  Aucune alerte simple d’indexation pour le moment. Le cockpit reste déjà à jour.
                </div>
              ) : (
                pendingSites.map((site) => (
                  <div key={site.site_id} className="rounded-xl border border-border px-4 py-3">
                    <div className="flex items-center justify-between gap-3">
                      <div>
                        <p className="text-sm font-semibold text-text">{site.name}</p>
                        <p className="text-xs text-text-subtle">{site.gsc_property_url ?? site.site_id}</p>
                      </div>
                      <Badge variant="warning">À surveiller</Badge>
                    </div>
                    <p className="mt-2 text-sm text-text-muted">
                      PraeviSEO attend encore une lecture d’indexation exploitable sur ce site.
                    </p>
                  </div>
                ))
              )}
            </CardContent>
          </Card>
        </div>

        <Card id="google" className="scroll-mt-24">
          <CardHeader>
            <CardTitle>État Google</CardTitle>
            <CardDescription>La vision simple de chaque site dans le cockpit Free, sans jargon technique.</CardDescription>
          </CardHeader>
          <CardContent className="space-y-3">
            {dashboard.sites.map((site) => (
              <div key={site.site_id} className="rounded-xl border border-border px-4 py-3">
                <div className="flex items-center justify-between gap-3">
                  <div>
                    <p className="text-sm font-semibold text-text">{site.name}</p>
                    <p className="text-xs text-text-subtle">
                      {site.readiness.gsc_connected ? "Google Search Console reliée" : "Google Search Console à relier"}
                    </p>
                  </div>
                  <Badge variant={site.readiness.gsc_connected ? "success" : "secondary"}>
                    {site.readiness.gsc_connected ? "Lecture active" : "En attente"}
                  </Badge>
                </div>
              </div>
            ))}
          </CardContent>
        </Card>
      </div>
    </div>
  );
}
