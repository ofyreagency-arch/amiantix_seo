import { Topbar } from "@/components/layout/topbar";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { claimSiteAction } from "./actions";

type PageSearchParams = Promise<Record<string, string | string[] | undefined>>;

function getValue(value: string | string[] | undefined, fallback = "") {
  if (Array.isArray(value)) {
    return value[0] ?? fallback;
  }

  return value ?? fallback;
}

export default async function JoinSitePage({ searchParams }: { searchParams: PageSearchParams }) {
  const params = await searchParams;
  const error = getValue(params.error);
  const siteId = getValue(params.site_id);
  const name = getValue(params.name);
  const url = getValue(params.url);

  return (
    <div className="min-h-screen">
      <Topbar
        title="Rejoindre un site existant"
        subtitle="Si le site existe déjà dans PraeviSEO, le client le rattache à son compte avec le code de connexion."
      />

      <div className="p-6">
        <div className="max-w-4xl grid gap-6 lg:grid-cols-[1.1fr_0.9fr]">
          <Card>
            <CardHeader>
              <CardTitle>Rattacher un site déjà créé</CardTitle>
              <CardDescription>
                Utilisez le code de connexion du site pour le faire apparaître dans votre espace client sans le recréer.
              </CardDescription>
            </CardHeader>
            <CardContent>
              {error ? (
                <div className="mb-4 rounded-2xl border border-danger/30 bg-danger/10 px-4 py-3 text-sm text-danger">
                  {error}
                </div>
              ) : null}

              <form action={claimSiteAction} className="space-y-4">
                <Input
                  name="name"
                  label="Nom du site"
                  defaultValue={name}
                  placeholder="Amiantix"
                  disabled
                />

                <div className="grid gap-4 md:grid-cols-2">
                  <Input
                    name="site_id"
                    label="Identifiant du site"
                    defaultValue={siteId}
                    placeholder="amiantix"
                    required
                  />
                  <Input
                    name="url"
                    label="URL publique"
                    defaultValue={url}
                    placeholder="https://amiantix.com"
                    disabled
                  />
                </div>

                <Input
                  name="connect_code"
                  label="Code de connexion"
                  placeholder="ABCD-EFGH-IJKL"
                  hint="Optionnel si le site n est encore rattaché à aucun compte client. Recommandé si le site appartient deja a quelqu un."
                />

                <div className="flex flex-wrap gap-3 pt-2">
                  <Button type="submit">Rattacher ce site</Button>
                  <Button href="/sites/new" variant="secondary">
                    Créer un nouveau site
                  </Button>
                </div>
              </form>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Quand utiliser ce parcours</CardTitle>
              <CardDescription>Le client ne recrée pas un site qui existe déjà.</CardDescription>
            </CardHeader>
            <CardContent className="space-y-4 text-sm text-text-muted">
              <div className="rounded-2xl border border-border bg-surface-2 px-4 py-4">
                <div className="font-semibold text-text">1. Le site existe déjà</div>
                <p className="mt-2 leading-6">
                  PraeviSEO connaît déjà ce site, sa connexion Google Search Console ou son éventuelle couche premium.
                </p>
              </div>
              <div className="rounded-2xl border border-border bg-surface-2 px-4 py-4">
                <div className="font-semibold text-text">2. Le client récupère l’accès</div>
                <p className="mt-2 leading-6">
                  Si le site n est encore rattaché à personne, PraeviSEO peut le basculer directement vers ce compte. Sinon, le code de connexion reste nécessaire.
                </p>
              </div>
              <div className="rounded-2xl border border-border bg-surface-2 px-4 py-4">
                <div className="font-semibold text-text">3. Les vrais statuts remontent</div>
                <p className="mt-2 leading-6">
                  Une fois rattaché, le client voit le vrai statut du site, le vrai GSC et les prochaines actions réelles.
                </p>
              </div>
            </CardContent>
          </Card>
        </div>
      </div>
    </div>
  );
}
