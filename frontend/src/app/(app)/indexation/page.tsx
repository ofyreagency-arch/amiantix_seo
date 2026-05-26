import { CockpitSectionNav } from "@/components/cockpit/section-nav";
import { CockpitMetricGrid } from "@/components/cockpit/metric-grid";
import { CockpitSignalItem, CockpitSignalListCard } from "@/components/cockpit/signal-list";
import { Topbar } from "@/components/layout/topbar";
import { Card } from "@/components/ui/card";
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

        <div id="vue-ensemble" className="scroll-mt-24">
          <CockpitMetricGrid
            items={[
              { label: "Pages indexées", value: totalIndexedPages, tone: "success" },
              { label: "Sites reliés à Google", value: connectedSites.length },
              { label: "Indexation synchronisée", value: syncedSites.length, tone: "success" },
              { label: "Alertes d’indexation", value: pendingSites.length, tone: pendingSites.length > 0 ? "warning" : "secondary" },
            ]}
          />
        </div>

        <div id="indexees" className="grid gap-6 xl:grid-cols-2 scroll-mt-24">
          <CockpitSignalListCard
            title="Pages indexées par site"
            description="La lecture actuelle des pages que Google voit déjà dans vos sites suivis."
            empty={syncedSites.length === 0}
            emptyMessage="Aucune indexation synchronisée pour le moment. Reliez d’abord Google pour ouvrir ce cockpit."
          >
            {syncedSites.map((site) => (
              <CockpitSignalItem
                key={site.site_id}
                title={site.name}
                subtitle={site.gsc_property_url ?? site.site_id}
                badge={`${site.summary.gsc_indexed_pages} indexées`}
                badgeTone="success"
                description={`Dernière lecture Google : ${site.gsc_last_sync_at ? formatDate(site.gsc_last_sync_at) : "récemment"}.`}
              />
            ))}
          </CockpitSignalListCard>

          <CockpitSignalListCard
            id="alertes"
            className="scroll-mt-24"
            title="Alertes simples"
            description="Les sites où l’indexation reste à surveiller ou à finaliser côté lecture Google."
            empty={pendingSites.length === 0}
            emptyMessage="Aucune alerte simple d’indexation pour le moment. Le cockpit reste déjà à jour."
          >
            {pendingSites.map((site) => (
              <CockpitSignalItem
                key={site.site_id}
                title={site.name}
                subtitle={site.gsc_property_url ?? site.site_id}
                badge="À surveiller"
                badgeTone="warning"
                description="PraeviSEO attend encore une lecture d’indexation exploitable sur ce site."
              />
            ))}
          </CockpitSignalListCard>
        </div>

        <CockpitSignalListCard
          id="google"
          className="scroll-mt-24"
          title="État Google"
          description="La vision simple de chaque site dans le cockpit Free, sans jargon technique."
          empty={dashboard.sites.length === 0}
          emptyMessage="Aucun site à afficher pour le moment."
        >
          {dashboard.sites.map((site) => (
            <CockpitSignalItem
              key={site.site_id}
              title={site.name}
              subtitle={site.readiness.gsc_connected ? "Google Search Console reliée" : "Google Search Console à relier"}
              badge={site.readiness.gsc_connected ? "Lecture active" : "En attente"}
              badgeTone={site.readiness.gsc_connected ? "success" : "secondary"}
              description="PraeviSEO garde ici une lecture simple et directement utile de l’état Google."
            />
          ))}
        </CockpitSignalListCard>
      </div>
    </div>
  );
}
