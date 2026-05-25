import { notFound } from "next/navigation";
import { Topbar } from "@/components/layout/topbar";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { formatRelativeStatus, getSite, getSiteConnectPath, hasBackendConnection } from "@/lib/praeviseo-api";
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

  return (
    <div className="min-h-screen">
      <Topbar
        title={site.name}
        subtitle="Vue client : publication, Search Console, installateur et prochaines actions."
        lastSync={backendLive ? "backend live" : "données de démonstration"}
        actions={
          <Button href={getSiteConnectPath(site.site_id)} size="sm">
            Connecter le site
          </Button>
        }
      />

      <div className="p-6 space-y-6">
        <div className="rounded-2xl border border-border bg-surface px-6 py-6">
          <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
              <div className="flex flex-wrap items-center gap-2">
                <h1 className="text-2xl font-bold tracking-tight text-text">{site.name}</h1>
                <Badge variant="secondary">{site.publication_mode_label}</Badge>
                <Badge variant={site.publication_bridge_status === "connected" ? "default" : "secondary"}>
                  {site.publication_bridge_status === "connected" ? "Bridge connecté" : "Bridge à connecter"}
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
            ["Publiées", site.summary.pages_published],
            ["Pages observées", site.summary.observed_pages],
            ["Métriques GSC", site.summary.search_console_metrics],
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
                <div className="text-[11px] uppercase tracking-[0.18em] text-text-subtle font-semibold">Bridge</div>
                <div className="mt-2 text-sm font-semibold text-text">
                  {site.publication_bridge_status === "connected" ? "Publication distante active" : "Connexion bridge en attente"}
                </div>
                <p className="mt-2 text-sm text-text-muted leading-6">
                  {site.publication_bridge_status === "connected"
                    ? "Le vrai site client peut maintenant recevoir les publications PraeviSEO."
                    : "Téléchargez l’installateur et collez le code de connexion pour activer la publication réelle."}
                </p>
              </div>

              <div className="rounded-2xl border border-border bg-surface-2 px-4 py-4">
                <div className="text-[11px] uppercase tracking-[0.18em] text-text-subtle font-semibold">Google Search Console</div>
                <div className="mt-2 text-sm font-semibold text-text">
                  {formatRelativeStatus(site.gsc_connection_status)}
                </div>
                <p className="mt-2 text-sm text-text-muted leading-6">
                  {site.gsc_property_url
                    ? `Propriété reliée : ${site.gsc_property_url}`
                    : "Reliez la propriété Search Console pour que le moteur détecte les opportunités réelles."}
                </p>
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
                  {site.publication_bridge_status === "connected"
                    ? "Bridge déjà actif"
                    : "Connecter le bridge officiel"}
                </div>
                <p className="mt-2 text-sm text-text-muted leading-6">
                  {site.publication_bridge_status === "connected"
                    ? "Le site est prêt pour les publications réelles. La suite logique est de lier GSC si ce n’est pas déjà fait."
                    : "Le produit client doit maintenant guider l’installation, pas renvoyer vers le copilote admin."}
                </p>
              </div>

              <Button href={getSiteConnectPath(site.site_id)} className="w-full">
                Télécharger l’installateur
              </Button>

              <div className="space-y-3">
                {[
                  "Code de connexion unique par site",
                  "Téléchargement Windows / Linux / Mac",
                  "Bridge Packagist officiel",
                  "Monitoring réel activé après la connexion",
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
