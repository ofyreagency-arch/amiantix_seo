import { notFound } from "next/navigation";
import { Topbar } from "@/components/layout/topbar";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { getSite } from "@/lib/praeviseo-api";
import { connectGscAction } from "./actions";

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
  const gscPropertyUrl = getValue(query.gsc_property_url, site.gsc_property_url ?? `sc-domain:${new URL(site.url).hostname.replace(/^www\./, "")}`);
  const gscCredentialsPath = getValue(query.gsc_credentials_path, "");
  const gscAccountEmail = getValue(query.gsc_account_email, site.gsc_account_email ?? "");

  return (
    <div className="min-h-screen">
      <Topbar
        title={`Connecter Search Console pour ${site.name}`}
        subtitle="Reliez la propriété Google Search Console du site pour activer les signaux SEO réels dans PraeviSEO."
      />

      <div className="p-6">
        <div className="max-w-5xl grid gap-6 lg:grid-cols-[1.05fr_0.95fr]">
          <Card>
            <CardHeader>
              <CardTitle>Connecter Google Search Console</CardTitle>
              <CardDescription>
                Pour l’instant, le mode le plus simple et le plus stable côté client est le compte de service.
              </CardDescription>
            </CardHeader>
            <CardContent>
              {error ? (
                <div className="mb-4 rounded-2xl border border-danger/30 bg-danger/10 px-4 py-3 text-sm text-danger">
                  {error}
                </div>
              ) : null}

              <form action={connectGscAction.bind(null, site.site_id)} className="space-y-4">
                <input type="hidden" name="gsc_connection_mode" value="service_account" />

                <div className="rounded-2xl border border-border bg-surface-2 px-4 py-4">
                  <div className="text-[11px] uppercase tracking-[0.18em] text-text-subtle font-semibold">Mode de connexion</div>
                  <div className="mt-3 rounded-xl border border-border bg-surface px-4 py-3 text-sm font-semibold text-text">
                    Compte de service Google
                  </div>
                </div>

                <Input
                  name="gsc_property_url"
                  label="Propriété Search Console"
                  defaultValue={gscPropertyUrl}
                  placeholder="sc-domain:monsite.com"
                  hint="Exemple : sc-domain:monsite.com ou https://www.monsite.com/"
                  required
                />

                <Input
                  name="gsc_credentials_path"
                  label="Chemin du fichier credentials"
                  defaultValue={gscCredentialsPath}
                  placeholder="/var/www/seo-engine/seo-engine-app/storage/google/site.json"
                  hint="Chemin du JSON de compte de service présent sur le serveur PraeviSEO."
                  required
                />

                <Input
                  name="gsc_account_email"
                  label="Compte Google"
                  defaultValue={gscAccountEmail}
                  placeholder="service-account@project.iam.gserviceaccount.com"
                  hint="Adresse du compte de service à ajouter dans Search Console."
                />

                <div className="flex flex-wrap gap-3 pt-2">
                  <Button type="submit">Connecter Search Console</Button>
                  <Button href={`/sites/${site.site_id}`} variant="secondary">
                    Retour au site
                  </Button>
                </div>
              </form>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Ce que le client doit faire</CardTitle>
              <CardDescription>PraeviSEO doit rester compréhensible, même quand la connexion passe par Google.</CardDescription>
            </CardHeader>
            <CardContent className="space-y-4 text-sm text-text-muted">
              <div className="rounded-2xl border border-border bg-surface-2 px-4 py-4">
                <div className="font-semibold text-text">1. Choisir la propriété</div>
                <p className="mt-2 leading-6">
                  Indiquez la propriété Search Console du site que PraeviSEO doit suivre.
                </p>
              </div>
              <div className="rounded-2xl border border-border bg-surface-2 px-4 py-4">
                <div className="font-semibold text-text">2. Renseigner le compte Google</div>
                <p className="mt-2 leading-6">
                  En mode compte de service, ajoutez ce compte dans Search Console avec accès à la propriété.
                </p>
              </div>
              <div className="rounded-2xl border border-border bg-surface-2 px-4 py-4">
                <div className="font-semibold text-text">3. Laisser PraeviSEO synchroniser</div>
                <p className="mt-2 leading-6">
                  Une fois la connexion enregistrée, PraeviSEO pourra lire impressions, CTR, positions et opportunités.
                </p>
              </div>
            </CardContent>
          </Card>
        </div>
      </div>
    </div>
  );
}
