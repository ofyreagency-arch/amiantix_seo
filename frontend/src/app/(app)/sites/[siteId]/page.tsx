import { notFound } from "next/navigation";
import { Topbar } from "@/components/layout/topbar";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import {
  formatGscStatus,
  formatPraeviseoStatus,
  formatSitePlatform,
  getPraeviseoInstallDetail,
  getPraeviseoInstallLabel,
  getSite,
  getSiteConnectPath,
  hasBackendConnection,
} from "@/lib/praeviseo-api";
import { ArrowRight, CheckCircle2, Globe, SearchCheck, Sparkles } from "lucide-react";

interface SiteDetailPageProps {
  params: Promise<{ siteId: string }>;
}

export default async function SiteDetailPage({ params }: SiteDetailPageProps) {
  const { siteId } = await params;
  const site = await getSite(siteId);

  if (!site) {
    notFound();
  }

  const backendLive = hasBackendConnection();
  const nextActionLabel =
    site.next_action.kind === "connect_bridge"
      ? "Installer PraeviSEO sur votre site"
      : site.next_action.label;
  const nextActionDetail =
    site.next_action.kind === "connect_bridge"
      ? "Installez PraeviSEO pour activer le monitoring SEO, les optimisations automatiques, les publications et l’analyse du site."
      : site.next_action.detail;

  return (
    <div className="min-h-screen">
      <Topbar
        title={site.name}
        subtitle="Vue client : publication, Search Console, installateur et prochaines actions."
        lastSync={backendLive ? "backend live" : "données de démonstration"}
        actions={
          <Button href={getSiteConnectPath(site.site_id)} size="sm">
            Installer PraeviSEO
          </Button>
        }
      />

      <div className="p-6 space-y-6">
        <div className="rounded-2xl border border-border bg-surface px-6 py-6">
          <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
              <div className="flex flex-wrap items-center gap-2">
                <h1 className="text-2xl font-bold tracking-tight text-text">{site.name}</h1>
                <Badge variant="secondary">{formatSitePlatform(site.publication_mode)}</Badge>
                <Badge variant={site.publication_bridge_status === "connected" ? "default" : "secondary"}>
                  {formatPraeviseoStatus(site.publication_bridge_status)}
                </Badge>
              </div>
              <p className="mt-3 text-sm text-text-muted">{site.url}</p>
              <div className="mt-4 flex flex-wrap gap-3 text-xs text-text-subtle">
                <span className="inline-flex items-center gap-1 rounded-full border border-border px-3 py-1">
                  <Globe className="w-3.5 h-3.5" />
                  {site.niche}
                </span>
                <span className="inline-flex items-center gap-1 rounded-full border border-border px-3 py-1">
                  <SearchCheck className="w-3.5 h-3.5" />
                  {site.gsc_property_url ?? "GSC non reliée"}
                </span>
                <span className="inline-flex items-center gap-1 rounded-full border border-border px-3 py-1">
                  <Sparkles className="w-3.5 h-3.5" />
                  {site.summary.pending_suggestions} suggestion(s) pending
                </span>
                <span className="inline-flex items-center gap-1 rounded-full border border-border px-3 py-1">
                  <CheckCircle2 className="w-3.5 h-3.5" />
                  {site.summary.pages_live} page(s) live
                </span>
              </div>
            </div>
              <div className="flex flex-wrap gap-2">
                <Button href={getSiteConnectPath(site.site_id)}>
                  Télécharger l’installateur
                <ArrowRight className="w-4 h-4" />
              </Button>
            </div>
          </div>
        </div>

        <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
          {[
            ["Pages moteur", site.summary.pages_total],
            ["Clics GSC", new Intl.NumberFormat("fr-FR").format(site.summary.gsc_clicks)],
            ["Impressions GSC", new Intl.NumberFormat("fr-FR").format(site.summary.gsc_impressions)],
            ["CTR GSC", new Intl.NumberFormat("fr-FR", {
              style: "percent",
              minimumFractionDigits: 1,
              maximumFractionDigits: 1,
            }).format(site.summary.gsc_ctr)],
          ].map(([label, value]) => (
            <Card key={label}>
              <CardContent className="pt-5">
                <div className="text-[11px] uppercase tracking-[0.18em] text-text-subtle font-semibold">
                  {label}
                </div>
                <div className="mt-2 text-3xl font-black text-text">{value}</div>
              </CardContent>
            </Card>
          ))}
        </div>

        <div className="grid gap-6 xl:grid-cols-[1fr_0.9fr]">
          <Card>
            <CardHeader>
              <CardTitle>État réel du site</CardTitle>
              <CardDescription>
                Ce que le client doit comprendre immédiatement, sans jargon admin.
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="rounded-2xl border border-border bg-surface-2 px-4 py-4">
                <div className="text-[11px] uppercase tracking-[0.18em] text-text-subtle font-semibold">PraeviSEO</div>
                <div className="mt-2 text-sm font-semibold text-text">
                  {getPraeviseoInstallLabel(site)}
                </div>
                <p className="mt-2 text-sm text-text-muted leading-6">
                  {getPraeviseoInstallDetail(site)}
                </p>
                {site.publication_bridge_status !== "connected" ? (
                  <div className="mt-4">
                    <Button href={getSiteConnectPath(site.site_id)} variant="secondary">
                      Activer PraeviSEO sur mon site
                    </Button>
                  </div>
                ) : null}
              </div>

              <div className="rounded-2xl border border-border bg-surface-2 px-4 py-4">
                <div className="text-[11px] uppercase tracking-[0.18em] text-text-subtle font-semibold">Google Search Console</div>
                <div className="mt-2 text-sm font-semibold text-text">{formatGscStatus(site.gsc_connection_status)}</div>
                <p className="mt-2 text-sm text-text-muted leading-6">
                  {site.gsc_property_url
                    ? `Propriété reliée : ${site.gsc_property_url}`
                    : "Reliez la propriété Search Console pour que le moteur détecte les opportunités réelles."}
                </p>
                <div className="mt-4">
                  <Button href={`/sites/${site.site_id}/search-console`} variant="secondary">
                    {site.gsc_property_url ? "Mettre à jour mon Google" : "Connecter mon Google"}
                  </Button>
                </div>
                {site.gsc_last_sync_at ? (
                  <p className="mt-2 text-xs text-text-subtle">Dernière synchro : {site.gsc_last_sync_at}</p>
                ) : null}
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Prochaine action recommandée</CardTitle>
              <CardDescription>
                Le dashboard client pousse la prochaine vraie étape, pas les diagnostics internes.
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="rounded-2xl border border-brand/20 bg-brand-muted px-4 py-4">
                <div className="text-sm font-semibold text-text">
                  {nextActionLabel}
                </div>
                <p className="mt-2 text-sm text-text-muted leading-6">
                  {nextActionDetail}
                </p>
              </div>

              <Button href={getSiteConnectPath(site.site_id)} className="w-full">
                Activer PraeviSEO sur mon site
              </Button>

              {!site.readiness.gsc_connected ? (
                <Button href={`/sites/${site.site_id}/search-console`} className="w-full" variant="secondary">
                  Connecter mon Google
                </Button>
              ) : null}

              <div className="space-y-3">
                {[
                  "Installateur préparé pour ce site",
                  "Téléchargement Windows / Linux / Mac",
                  "Installation officielle PraeviSEO",
                  "Monitoring SEO activé après l’installation",
                ].map((item) => (
                  <div key={item} className="flex items-start gap-2 text-sm text-text-muted">
                    <CheckCircle2 className="w-4 h-4 text-[hsl(var(--success))] shrink-0 mt-0.5" />
                    <span>{item}</span>
                  </div>
                ))}
              </div>
            </CardContent>
          </Card>
        </div>
      </div>
    </div>
  );
}
