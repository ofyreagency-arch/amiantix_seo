import Link from "next/link";
import { Topbar } from "@/components/layout/topbar";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { getDashboard, getSiteConnectPath, getSitePath, hasBackendConnection } from "@/lib/praeviseo-api";
import { ArrowRight, CheckCircle2, Globe, SearchCheck, Sparkles, Waves } from "lucide-react";

export default async function DashboardPage() {
  const dashboard = await getDashboard();
  const backendLive = hasBackendConnection();

  return (
    <div className="min-h-screen">
      <Topbar
        title="Dashboard client"
        subtitle="Votre cockpit client PraeviSEO : sites connectés, bridge, Google Search Console et prochaines actions."
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
              label: "Sites connectés",
              value: dashboard.totals.connectedSites,
              icon: Globe,
              hint: `${dashboard.sites.length} site(s) au total`,
            },
            {
              label: "Pages publiées",
              value: dashboard.totals.publishedPages,
              icon: Waves,
              hint: "contenus déjà poussés côté client",
            },
            {
              label: "Suggestions pending",
              value: dashboard.totals.pendingSuggestions,
              icon: Sparkles,
              hint: "actions en attente de validation",
            },
            {
              label: "Pages observées",
              value: dashboard.totals.observedPages,
              icon: SearchCheck,
              hint: "couche monitoring crawl réel",
            },
            {
              label: "Sites GSC reliés",
              value: dashboard.totals.gscConnectedSites,
              icon: CheckCircle2,
              hint: "Google Search Console active",
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
                Les sites réellement branchés au moteur. Chaque fiche client donne accès à la connexion bridge,
                au statut GSC et à la prochaine action.
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
              {dashboard.sites.map((site) => (
                <div
                  key={site.site_id}
                  className="rounded-2xl border border-border-subtle bg-surface-2/40 px-4 py-4 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between"
                >
                  <div>
                    <div className="flex items-center gap-2 flex-wrap">
                      <h3 className="text-base font-semibold text-text">{site.name}</h3>
                      <Badge variant="secondary">{site.publication_mode_label}</Badge>
                      <Badge variant={site.publication_bridge_status === "connected" ? "default" : "secondary"}>
                        {site.publication_bridge_status === "connected" ? "Bridge connecté" : "Connexion en attente"}
                      </Badge>
                    </div>
                    <p className="mt-2 text-sm text-text-muted">{site.url}</p>
                    <div className="mt-3 flex flex-wrap gap-4 text-xs text-text-subtle">
                      <span>{site.summary.pages_total} page(s) moteur</span>
                      <span>{site.summary.pages_published} publiée(s)</span>
                      <span>{site.summary.pending_suggestions} suggestion(s) pending</span>
                      <span>{site.gsc_connection_status === "connected" ? "GSC reliée" : "GSC non reliée"}</span>
                    </div>
                  </div>
                  <div className="flex flex-wrap gap-2">
                    <Button href={getSitePath(site.site_id)} variant="secondary" size="sm">
                      Ouvrir
                    </Button>
                    <Button href={getSiteConnectPath(site.site_id)} size="sm">
                      Connecter le site
                    </Button>
                  </div>
                </div>
              ))}
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Ce que voit le client</CardTitle>
              <CardDescription>
                Une version simple du produit, séparée du copilote interne.
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
              {[
                "Accueil produit avec proposition de valeur claire",
                "Connexion / création de compte",
                "Dashboard client orienté résultats",
                "Mes sites et statuts bridge / GSC",
                "Téléchargement de l’installateur officiel",
                "Backlog actionnable sans jargon technique",
              ].map((line) => (
                <div key={line} className="flex items-start gap-2 text-sm text-text-muted">
                  <CheckCircle2 className="w-4 h-4 text-[hsl(var(--success))] shrink-0 mt-0.5" />
                  <span>{line}</span>
                </div>
              ))}

              <div className="rounded-2xl border border-border bg-surface-2 px-4 py-4">
                <div className="text-xs uppercase tracking-[0.18em] text-text-subtle font-semibold">
                  Étape suivante
                </div>
                <p className="mt-2 text-sm text-text-muted leading-6">
                  On garde l’admin pour toi, et on fait maintenant évoluer ce front vers une vraie auth client SaaS
                  et un onboarding connecté au backend.
                </p>
              </div>
            </CardContent>
          </Card>
        </div>
      </div>
    </div>
  );
}
