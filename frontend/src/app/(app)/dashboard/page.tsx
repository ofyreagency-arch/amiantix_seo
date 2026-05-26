import Link from "next/link";
import { Topbar } from "@/components/layout/topbar";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import {
  formatPraeviseoStatus,
  formatSitePlatform,
  getDashboard,
  getPraeviseoInstallDetail,
  getOptimizations,
  getPublications,
  getSiteConnectPath,
  getSitePath,
  hasBackendConnection,
} from "@/lib/praeviseo-api";
import { ArrowRight, CheckCircle2, Globe, SearchCheck, Sparkles, Waves } from "lucide-react";

export default async function DashboardPage() {
  const dashboard = await getDashboard();
  const optimizations = await getOptimizations();
  const publications = await getPublications();
  const backendLive = hasBackendConnection();
  const prioritySites = dashboard.sites
    .filter((site) => site.next_action.priority !== "low")
    .slice(0, 3);
  const recentPublications = publications.items.slice(0, 3);
  const recentOptimizations = optimizations.items.slice(0, 3);

  const priorityHref = (site: (typeof dashboard.sites)[number]) => {
    if (site.next_action.kind === "connect_gsc") {
      return `/sites/${site.site_id}/search-console`;
    }

    if (site.next_action.kind === "connect_bridge" || site.next_action.kind === "installation_requested") {
      return getSiteConnectPath(site.site_id);
    }

    return getSitePath(site.site_id);
  };

  const priorityLabel = (site: (typeof dashboard.sites)[number]) => {
    if (site.next_action.kind === "connect_gsc") {
      return "Connecter Search Console";
    }

    if (site.next_action.kind === "connect_bridge") {
      return "Installer PraeviSEO";
    }

    if (site.next_action.kind === "installation_requested") {
      return "Suivre l’installation";
    }

    return "Ouvrir la fiche site";
  };

  return (
    <div className="min-h-screen">
      <Topbar
        title="Dashboard client"
        subtitle="Votre cockpit client PraeviSEO : sites connectés, Google Search Console et prochaines actions."
        lastSync={backendLive ? "backend live" : "mode démonstration"}
        actions={
          <Button href="/sites/new" size="sm">
            Connecter un site
          </Button>
        }
      />

      <div className="p-6 space-y-6">
        <div className="rounded-2xl border border-brand/20 bg-brand-muted px-6 py-6">
          <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div className="max-w-2xl">
              <Badge variant="brand-subtle" className="mb-3">
                {backendLive ? "Backend relié" : "Mode démonstration"}
              </Badge>
              <h1 className="text-2xl font-bold tracking-tight text-text">
                Un vrai espace client, séparé du copilote admin
              </h1>
              <p className="mt-2 text-sm text-text-muted leading-7">
                Ici, le client voit ses sites, ses connexions, ses prochaines actions et ses installateurs.
                L’admin interne garde le moteur et les diagnostics avancés.
              </p>
            </div>
            <div className="flex flex-wrap gap-3">
              <Button href="/sites/join" variant="secondary">
                Rejoindre un site
              </Button>
              <Button href="/sites" variant="secondary">
                Voir mes sites
              </Button>
              <Button href="/sites/new">
                Ajouter un site
                <ArrowRight className="w-4 h-4" />
              </Button>
            </div>
          </div>
        </div>

        <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
          {[
            {
              label: "Impressions",
              value: new Intl.NumberFormat("fr-FR").format(dashboard.totals.impressions),
              icon: Globe,
              hint: "volume GSC agrégé du dernier snapshot",
            },
            {
              label: "Clics",
              value: new Intl.NumberFormat("fr-FR").format(dashboard.totals.clicks),
              icon: Waves,
              hint: "clics organiques remontés par GSC",
            },
            {
              label: "CTR moyen",
              value: new Intl.NumberFormat("fr-FR", {
                style: "percent",
                minimumFractionDigits: 1,
                maximumFractionDigits: 1,
              }).format(dashboard.totals.averageCtr),
              icon: Sparkles,
              hint: "calculé sur les clics et impressions GSC",
            },
            {
              label: "Pages observées",
              value: dashboard.totals.observedPages,
              icon: SearchCheck,
              hint: "couche monitoring crawl réel",
            },
            {
              label: "Pages indexées",
              value: dashboard.totals.indexedPages,
              icon: CheckCircle2,
              hint: "pages vues comme indexées dans GSC",
            },
          ].map((item) => {
            const Icon = item.icon;

            return (
              <Card key={item.label}>
                <CardHeader>
                  <div className="flex items-center justify-between">
                    <div className="w-10 h-10 rounded-xl bg-brand-subtle flex items-center justify-center">
                      <Icon className="w-4 h-4 text-[hsl(var(--brand))]" />
                    </div>
                    <span className="text-2xl font-black text-text">{item.value}</span>
                  </div>
                  <CardTitle className="pt-4">{item.label}</CardTitle>
                  <CardDescription>{item.hint}</CardDescription>
                </CardHeader>
              </Card>
            );
          })}
        </div>

        <div className="grid gap-6 xl:grid-cols-[1.2fr_0.8fr]">
          <Card>
            <CardHeader>
              <CardTitle>Sites suivis</CardTitle>
              <CardDescription>
                Vos sites, leur état d’activation PraeviSEO, leur statut Search Console et la prochaine action recommandée.
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
              {dashboard.sites.length === 0 ? (
                <div className="rounded-2xl border border-border bg-surface-2 px-4 py-4 text-sm text-text-muted">
                  Aucun site n est encore rattaché à ce compte. Créez un nouveau site ou rejoignez un site existant.
                </div>
              ) : dashboard.sites.map((site) => (
                <div
                  key={site.site_id}
                  className="rounded-2xl border border-border-subtle bg-surface-2/40 px-4 py-4 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between"
                >
                  <div>
                    <div className="flex items-center gap-2 flex-wrap">
                      <h3 className="text-base font-semibold text-text">{site.name}</h3>
                      <Badge variant="secondary">{formatSitePlatform(site.publication_mode)}</Badge>
                      <Badge variant={site.publication_bridge_status === "connected" ? "default" : "secondary"}>
                        {formatPraeviseoStatus(site.publication_bridge_status)}
                      </Badge>
                    </div>
                    <p className="mt-2 text-sm text-text-muted">{site.url}</p>
                    <div className="mt-3 flex flex-wrap gap-4 text-xs text-text-subtle">
                      <span>{site.summary.pages_total} page(s) moteur</span>
                      <span>{new Intl.NumberFormat("fr-FR").format(site.summary.gsc_impressions)} impressions</span>
                      <span>{new Intl.NumberFormat("fr-FR").format(site.summary.gsc_clicks)} clics</span>
                      <span>
                        {new Intl.NumberFormat("fr-FR", {
                          style: "percent",
                          minimumFractionDigits: 1,
                          maximumFractionDigits: 1,
                        }).format(site.summary.gsc_ctr)}{" "}
                        CTR
                      </span>
                      <span>{site.summary.gsc_indexed_pages} page(s) indexée(s)</span>
                      <span>{site.summary.observed_pages} page(s) observée(s)</span>
                      <span>{site.readiness.gsc_connected ? "GSC reliée" : "GSC non reliée"}</span>
                    </div>
                  </div>
                  <div className="flex flex-wrap gap-2">
                    <Button href={getSitePath(site.site_id)} variant="secondary" size="sm">
                      Ouvrir
                    </Button>
                    <Button href={getSiteConnectPath(site.site_id)} size="sm">
                      {site.publication_bridge_status === "requested" ? "Suivre l’installation" : "Installer PraeviSEO"}
                    </Button>
                  </div>
                </div>
              ))}
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Priorités du moment</CardTitle>
              <CardDescription>
                Ce que PraeviSEO recommande en premier au client, sans jargon technique.
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
              {prioritySites.length === 0 ? (
                <div className="rounded-2xl border border-border bg-surface-2 px-4 py-4 text-sm text-text-muted">
                  Aucun blocage fort en ce moment. Le moteur est surtout en phase de monitoring.
                </div>
              ) : (
                prioritySites.map((site) => (
                  <div key={site.site_id} className="rounded-2xl border border-border bg-surface-2 px-4 py-4">
                    <div className="flex items-center justify-between gap-3">
                      <div className="text-sm font-semibold text-text">{site.name}</div>
                      <Badge variant={site.next_action.priority === "high" ? "warning" : "secondary"}>
                        {site.next_action.priority === "high" ? "Priorité haute" : "À planifier"}
                      </Badge>
                    </div>
                    <p className="mt-2 text-sm text-text">
                      {site.next_action.kind === "connect_bridge"
                        ? "Installer PraeviSEO sur votre site"
                        : site.next_action.kind === "installation_requested"
                          ? "PraeviSEO prépare votre installation"
                          : site.next_action.label}
                    </p>
                    <p className="mt-2 text-sm text-text-muted leading-6">{site.next_action.detail}</p>
                    {site.next_action.kind === "connect_bridge" || site.next_action.kind === "installation_requested" ? (
                      <p className="mt-2 text-sm text-text-muted leading-6">{getPraeviseoInstallDetail(site)}</p>
                    ) : null}
                    <div className="mt-3">
                      <Button href={priorityHref(site)} variant="secondary" size="sm">
                        {priorityLabel(site)}
                      </Button>
                    </div>
                  </div>
                ))
              )}
            </CardContent>
          </Card>
        </div>

        <div className="grid gap-6 xl:grid-cols-2">
          <Card>
            <CardHeader>
              <CardTitle>Activité publication</CardTitle>
              <CardDescription>
                Les dernières pages passées en publication moteur ou live.
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-3">
              {recentPublications.map((item) => (
                <div key={item.id} className="rounded-xl border border-border px-4 py-3">
                  <div className="flex items-center justify-between gap-3">
                    <div>
                      <p className="text-sm font-semibold text-text">{item.title}</p>
                      <p className="text-xs text-text-subtle">{item.site_id}</p>
                    </div>
                    <Badge variant={item.published_live ? "success" : "secondary"}>
                      {item.published_live ? "live" : "moteur"}
                    </Badge>
                  </div>
                </div>
              ))}
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Activité optimisation</CardTitle>
              <CardDescription>
                Les dernières suggestions réellement ouvertes par le moteur.
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-3">
              {recentOptimizations.map((item) => (
                <div key={item.id} className="rounded-xl border border-border px-4 py-3">
                  <div className="flex items-center justify-between gap-3">
                    <div>
                      <p className="text-sm font-semibold text-text">{item.page.title}</p>
                      <p className="text-xs text-text-subtle">{item.page.site_id}</p>
                    </div>
                    <Badge variant={item.status === "pending" ? "warning" : "secondary"}>
                      {item.status}
                    </Badge>
                  </div>
                  <p className="mt-2 text-sm text-text-muted">{item.summary}</p>
                </div>
              ))}
            </CardContent>
          </Card>
        </div>
      </div>
    </div>
  );
}
