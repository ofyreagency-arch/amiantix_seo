import { Topbar } from "@/components/layout/topbar";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { formatGscStatus, formatPraeviseoStatus, formatSitePlatform, getSiteConnectPath, getSitePath, getSites } from "@/lib/praeviseo-api";
import { ArrowRight, Globe, Link2, SearchCheck } from "lucide-react";

export default async function SitesPage() {
  const sites = await getSites();

  return (
    <div className="min-h-screen">
      <Topbar
        title="Mes sites"
        subtitle="Tous les sites reliés à PraeviSEO, avec leur état d’activation, Search Console et monitoring."
        actions={
          <div className="flex gap-2">
            <Button href="/sites/join" variant="secondary" size="sm">
              Rejoindre un site
            </Button>
            <Button href="/sites/new" size="sm">
              Ajouter un site
            </Button>
          </div>
        }
      />

      <div className="p-6">
        {sites.length === 0 ? (
          <Card className="max-w-3xl">
            <CardHeader>
              <CardTitle>Aucun site rattaché pour le moment</CardTitle>
              <CardDescription>
                Créez un nouveau site si vous démarrez de zéro, ou rejoignez un site déjà existant.
              </CardDescription>
            </CardHeader>
            <CardContent className="flex flex-wrap gap-3">
              <Button href="/sites/new">Créer un nouveau site</Button>
              <Button href="/sites/join" variant="secondary">
                Rejoindre un site existant
              </Button>
            </CardContent>
          </Card>
        ) : (
          <div className="grid gap-5 md:grid-cols-2">
            {sites.map((site) => (
            <Card key={site.site_id} className="overflow-hidden">
              <CardHeader>
                <div className="flex items-start justify-between gap-3">
                  <div>
                    <CardTitle className="text-base">{site.name}</CardTitle>
                    <CardDescription className="mt-2">{site.url}</CardDescription>
                  </div>
                  <Badge variant={site.publication_bridge_status === "connected" ? "default" : "secondary"}>
                    {formatPraeviseoStatus(site.publication_bridge_status)}
                  </Badge>
                </div>
              </CardHeader>
              <CardContent className="space-y-4">
                <div className="grid grid-cols-2 gap-3">
                  <div className="rounded-xl border border-border-subtle bg-surface-2 px-3 py-3">
                    <div className="text-[11px] uppercase tracking-[0.18em] text-text-subtle font-semibold">
                      Type de site
                    </div>
                    <div className="mt-2 text-sm font-semibold text-text">{formatSitePlatform(site.publication_mode)}</div>
                    <div className="mt-1 text-xs text-text-subtle">
                      {site.publication_path_prefix ? `/${site.publication_path_prefix}` : "section par défaut"}
                    </div>
                  </div>
                  <div className="rounded-xl border border-border-subtle bg-surface-2 px-3 py-3">
                    <div className="text-[11px] uppercase tracking-[0.18em] text-text-subtle font-semibold">
                      Search Console
                    </div>
                    <div className="mt-2 text-sm font-semibold text-text">{formatGscStatus(site.gsc_connection_status)}</div>
                    <div className="mt-1 text-xs text-text-subtle">
                      {site.gsc_property_url ?? "Aucune propriété reliée"}
                    </div>
                  </div>
                </div>

                <div className="grid grid-cols-3 gap-3 text-sm">
                  <div className="rounded-xl border border-border-subtle bg-surface-2 px-3 py-3">
                    <div className="text-[11px] uppercase tracking-[0.18em] text-text-subtle font-semibold">Pages</div>
                    <div className="mt-2 text-lg font-bold text-text">{site.summary.pages_total}</div>
                  </div>
                  <div className="rounded-xl border border-border-subtle bg-surface-2 px-3 py-3">
                    <div className="text-[11px] uppercase tracking-[0.18em] text-text-subtle font-semibold">Publiées</div>
                    <div className="mt-2 text-lg font-bold text-text">{site.summary.pages_published}</div>
                  </div>
                  <div className="rounded-xl border border-border-subtle bg-surface-2 px-3 py-3">
                    <div className="text-[11px] uppercase tracking-[0.18em] text-text-subtle font-semibold">Pending</div>
                    <div className="mt-2 text-lg font-bold text-text">{site.summary.pending_suggestions}</div>
                  </div>
                </div>

                <div className="flex flex-wrap gap-2 text-xs text-text-subtle">
                  <span className="inline-flex items-center gap-1 rounded-full border border-border px-3 py-1">
                    <Globe className="w-3.5 h-3.5" />
                    {site.niche}
                  </span>
                  <span className="inline-flex items-center gap-1 rounded-full border border-border px-3 py-1">
                    <SearchCheck className="w-3.5 h-3.5" />
                    {new Intl.NumberFormat("fr-FR").format(site.summary.gsc_clicks)} clic(s) GSC (28 j)
                  </span>
                  <span className="inline-flex items-center gap-1 rounded-full border border-border px-3 py-1">
                    <Link2 className="w-3.5 h-3.5" />
                    {new Intl.NumberFormat("fr-FR").format(site.summary.gsc_impressions)} impression(s) GSC (28 j)
                  </span>
                </div>

                <div className="flex flex-wrap gap-2">
                  <Button href={getSitePath(site.site_id)} variant="secondary">
                    Ouvrir la fiche
                  </Button>
                  {!site.readiness.gsc_connected ? (
                    <Button href={`/sites/${site.site_id}/search-console`} variant="secondary">
                      Connecter mon Google
                    </Button>
                  ) : null}
                  <Button href={getSiteConnectPath(site.site_id)}>
                    {site.publication_bridge_status === "requested" ? "Suivre l’installation" : "Installer PraeviSEO"}
                    <ArrowRight className="w-4 h-4" />
                  </Button>
                </div>
              </CardContent>
            </Card>
            ))}
          </div>
        )}
      </div>
    </div>
  );
}
