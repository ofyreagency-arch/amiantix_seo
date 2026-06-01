import { notFound } from "next/navigation";
import { Topbar } from "@/components/layout/topbar";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { connectGscAction } from "./actions";
import { formatGscStatus, getSite } from "@/lib/praeviseo-api";
import { CheckCircle2, Link2, RefreshCw, ShieldCheck } from "lucide-react";

type PageSearchParams = Promise<Record<string, string | string[] | undefined>>;

interface SiteSearchConsolePageProps {
  params: Promise<{ siteId: string }>;
  searchParams: PageSearchParams;
}

function getValue(value: string | string[] | undefined, fallback = ""): string {
  if (Array.isArray(value)) {
    return value[0] ?? fallback;
  }

  return value ?? fallback;
}

function buildPropertyChoices(siteUrl: string, currentProperty: string | null): string[] {
  const url = new URL(siteUrl);
  const host = url.hostname.replace(/^www\./, "");
  const normalizedUrl = `https://${host}`;
  const wwwUrl = `https://www.${host}`;

  return Array.from(
    new Set([
      currentProperty ?? "",
      `sc-domain:${host}`,
      `${wwwUrl}/`,
      `${normalizedUrl}/`,
    ].filter(Boolean))
  );
}

export default async function SiteSearchConsolePage({
  params,
  searchParams,
}: SiteSearchConsolePageProps) {
  const { siteId } = await params;
  const site = await getSite(siteId);

  if (!site) {
    notFound();
  }

  const query = await searchParams;
  const error = getValue(query.error);
  const step = getValue(query.step, site.gsc_property_url ? "status" : "start");
  const selectedProperty = getValue(
    query.gsc_property_url,
    site.gsc_property_url ?? buildPropertyChoices(site.url, null)[0] ?? ""
  );
  const propertyChoices = buildPropertyChoices(site.url, site.gsc_property_url);
  const isConnected = site.gsc_connection_status === "connected" || site.gsc_connection_status === "configured";

  return (
    <div className="min-h-screen">
      <Topbar
        title={`Search Console · ${site.name}`}
        subtitle="Connectez votre Google, choisissez la bonne propriété, puis laissez PraeviSEO alimenter votre cockpit SEO free."
      />

      <div className="p-6 space-y-6">
        <div className="rounded-3xl border border-brand/20 bg-brand-muted px-6 py-6">
          <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div className="max-w-3xl">
              <Badge variant="brand-subtle" className="mb-3">
                Google Search Console
              </Badge>
              <h1 className="text-2xl font-bold tracking-tight text-text">Connecter Google Search Console</h1>
              <p className="mt-2 text-sm text-text-muted leading-7">
                Une fois Google relié, PraeviSEO peut déjà suivre les performances, l indexation, les opportunités et les priorités SEO du site.
              </p>
            </div>
            <div className="rounded-2xl border border-border bg-surface px-5 py-4 min-w-[260px]">
              <div className="text-[11px] uppercase tracking-[0.18em] text-text-subtle font-semibold">Statut</div>
              <div className="mt-2 text-lg font-black text-text">{formatGscStatus(site.gsc_connection_status)}</div>
              <div className="mt-2 text-xs text-text-subtle">
                {site.gsc_last_sync_at
                  ? `Dernière synchro : ${site.gsc_last_sync_at}${site.gsc_data_as_of ? ` · données arrêtées au ${site.gsc_data_as_of}` : ""}`
                  : "Synchronisation inactive pour le moment."}
              </div>
            </div>
          </div>
        </div>

        {error ? (
          <div className="rounded-2xl border border-danger/30 bg-danger/10 px-4 py-3 text-sm text-danger">
            {error}
          </div>
        ) : null}

        <div className="grid gap-6 xl:grid-cols-[1.05fr_0.95fr]">
          <Card>
            <CardHeader>
              <CardTitle>{isConnected ? "Search Console déjà reliée" : "Connecter mon Google"}</CardTitle>
              <CardDescription>
                {isConnected
                  ? "Votre site remonte déjà des informations Search Console. Vous pouvez changer de propriété si besoin."
                  : "Le client connecte Google, choisit la propriété, puis PraeviSEO transforme déjà ces signaux en lecture SEO utile."}
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
              {step === "start" ? (
                <div className="space-y-4">
                  <div className="rounded-2xl border border-border bg-surface-2 px-4 py-4">
                    <div className="text-sm font-semibold text-text">1. Autoriser PraeviSEO</div>
                    <p className="mt-2 text-sm text-text-muted leading-6">
                      PraeviSEO ouvre ensuite une connexion Google sécurisée et prépare automatiquement les propriétés disponibles pour ce site.
                    </p>
                  </div>

                  <form action={connectGscAction.bind(null, site.site_id)}>
                    <input type="hidden" name="step" value="select-property" />
                    <Button type="submit" className="w-full">
                      Continuer avec Google
                    </Button>
                  </form>
                </div>
              ) : (
                <form action={connectGscAction.bind(null, site.site_id)} className="space-y-4">
                  <input type="hidden" name="step" value="select-property" />

                  <div className="rounded-2xl border border-border bg-surface-2 px-4 py-4">
                    <div className="text-sm font-semibold text-text">2. Choisir votre propriété</div>
                    <p className="mt-2 text-sm text-text-muted leading-6">
                      Sélectionnez la propriété Search Console que PraeviSEO devra synchroniser pour {site.name}.
                    </p>
                  </div>

                  <div className="grid gap-3">
                    {propertyChoices.map((property) => (
                      <label
                        key={property}
                        className="flex items-start gap-3 rounded-2xl border border-border bg-surface-2 px-4 py-4 cursor-pointer"
                      >
                        <input
                          type="radio"
                          name="gsc_property_url"
                          value={property}
                          defaultChecked={property === selectedProperty}
                          className="mt-1"
                        />
                        <div>
                          <div className="text-sm font-semibold text-text">{property}</div>
                          <div className="mt-1 text-xs text-text-subtle">
                            PraeviSEO utilisera ensuite cette propriété pour récupérer vos affichages, vos visites et
                            les recherches que Google commence à associer à votre site.
                          </div>
                        </div>
                      </label>
                    ))}
                  </div>

                  <div className="flex flex-wrap gap-3 pt-2">
                    <Button type="submit">
                      Activer Search Console
                    </Button>
                    <Button href={`/sites/${site.site_id}`} variant="secondary">
                      Retour au site
                    </Button>
                  </div>
                </form>
              )}
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Ce que verra le client</CardTitle>
              <CardDescription>On garde ici le ressenti SaaS, pas la plomberie technique.</CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
              {[
                {
                  icon: ShieldCheck,
                  title: "Connexion Google simple",
                  text: "Le client ne voit ni JSON, ni service account, ni chemins serveur. Il connecte juste son Google.",
                },
                {
                  icon: Link2,
                  title: "Choix de propriété clair",
                  text: "PraeviSEO propose directement les propriétés pertinentes pour le domaine du site.",
                },
                {
                  icon: RefreshCw,
                  title: "Synchronisation automatique",
                  text: "Une fois activée, la synchronisation GSC alimente ensuite le cockpit client sans autre configuration visible.",
                },
                {
                  icon: CheckCircle2,
                  title: "Lecture active",
                  text: "Le dashboard indiquera ensuite simplement si la connexion est active, quand la dernière synchro a tourné et quelles données sont déjà lisibles.",
                },
              ].map((item) => {
                const Icon = item.icon;

                return (
                  <div key={item.title} className="rounded-2xl border border-border bg-surface-2 px-4 py-4">
                    <div className="flex items-center gap-3">
                      <div className="w-10 h-10 rounded-2xl bg-brand-subtle flex items-center justify-center">
                        <Icon className="w-4 h-4 text-[hsl(var(--brand))]" />
                      </div>
                      <div className="text-sm font-semibold text-text">{item.title}</div>
                    </div>
                    <p className="mt-3 text-sm text-text-muted leading-6">{item.text}</p>
                  </div>
                );
              })}
            </CardContent>
          </Card>
        </div>
      </div>
    </div>
  );
}
