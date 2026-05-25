import { Topbar } from "@/components/layout/topbar";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { createSiteAction } from "./actions";

type PageSearchParams = Promise<Record<string, string | string[] | undefined>>;

function getValue(value: string | string[] | undefined, fallback: string) {
  if (Array.isArray(value)) {
    return value[0] ?? fallback;
  }

  return value ?? fallback;
}

export default async function NewSitePage({ searchParams }: { searchParams: PageSearchParams }) {
  const params = await searchParams;
  const error = getValue(params.error, "");

  return (
    <div className="min-h-screen">
      <Topbar
        title="Ajouter un site"
        subtitle="Le client ajoute son site depuis l’app publique. Le copilote admin n’est plus le point d’entrée produit."
      />

      <div className="p-6">
        <div className="max-w-4xl grid gap-6 lg:grid-cols-[1.1fr_0.9fr]">
          <Card>
            <CardHeader>
              <CardTitle>Créer un site client</CardTitle>
              <CardDescription>
                On collecte juste le minimum utile, puis PraeviSEO prépare automatiquement l’installation et l’activation du site.
              </CardDescription>
            </CardHeader>
            <CardContent>
              {error ? (
                <div className="mb-4 rounded-2xl border border-danger/30 bg-danger/10 px-4 py-3 text-sm text-danger">
                  {error}
                </div>
              ) : null}

              <form action={createSiteAction} className="space-y-4">
                <div className="grid gap-4 md:grid-cols-2">
                  <Input
                    name="name"
                    label="Nom du site"
                    placeholder="Amiantix"
                    defaultValue={getValue(params.name, "")}
                    required
                  />
                  <Input
                    name="site_id"
                    label="Identifiant"
                    placeholder="amiantix"
                    defaultValue={getValue(params.site_id, "")}
                    required
                  />
                </div>

                <Input
                  name="url"
                  label="URL publique"
                  placeholder="https://amiantix.com"
                  defaultValue={getValue(params.url, "")}
                  required
                />

                <div className="grid gap-4 md:grid-cols-3">
                  <Input
                    name="niche"
                    label="Niche"
                    placeholder="amiante"
                    defaultValue={getValue(params.niche, "general")}
                  />
                  <Input
                    name="locale"
                    label="Locale"
                    placeholder="fr"
                    defaultValue={getValue(params.locale, "fr")}
                  />
                  <div className="space-y-1.5">
                    <label className="block text-sm font-medium text-text-muted">Preset</label>
                    <select
                      name="preset"
                      defaultValue={getValue(params.preset, "generic")}
                      className="flex h-10 w-full rounded-lg bg-surface-2 border border-border px-3 text-sm text-text"
                    >
                      <option value="generic">Générique</option>
                      <option value="amiantix">Amiantix</option>
                    </select>
                  </div>
                </div>

                <div className="grid gap-4 md:grid-cols-2">
                  <div className="space-y-1.5">
                    <label className="block text-sm font-medium text-text-muted">Type de site</label>
                    <select
                      name="publication_mode"
                      defaultValue={getValue(params.publication_mode, "symfony_bridge")}
                      className="flex h-10 w-full rounded-lg bg-surface-2 border border-border px-3 text-sm text-text"
                    >
                      <option value="symfony_bridge">Site Symfony</option>
                      <option value="laravel_bridge">Site Laravel</option>
                      <option value="wordpress_bridge">Site WordPress</option>
                    </select>
                  </div>
                  <Input
                    name="publication_path_prefix"
                    label="Section publique"
                    defaultValue={getValue(params.publication_path_prefix, "ressources")}
                    placeholder="ressources"
                  />
                </div>

                <div className="flex flex-wrap gap-3 pt-2">
                  <Button type="submit">Créer le site</Button>
                  <Button href="/sites/join" variant="secondary">
                    Rejoindre un site existant
                  </Button>
                  <Button href="/sites" variant="secondary">
                    Retour aux sites
                  </Button>
                </div>
              </form>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Flow client visé</CardTitle>
              <CardDescription>Ce formulaire alimente directement le produit client.</CardDescription>
            </CardHeader>
            <CardContent className="space-y-4 text-sm text-text-muted">
              <div className="rounded-2xl border border-border bg-surface-2 px-4 py-4">
                <div className="font-semibold text-text">1. Ajouter un site</div>
                <p className="mt-2 leading-6">
                  Le client renseigne son domaine et choisit simplement le type de site.
                </p>
              </div>
              <div className="rounded-2xl border border-border bg-surface-2 px-4 py-4">
                <div className="font-semibold text-text">2. Télécharger l’installateur</div>
                <p className="mt-2 leading-6">
                  PraeviSEO prépare ensuite l’installation du site avec les scripts Windows / Linux / Mac.
                </p>
              </div>
              <div className="rounded-2xl border border-border bg-surface-2 px-4 py-4">
                <div className="font-semibold text-text">3. Activer le suivi</div>
                <p className="mt-2 leading-6">
                  Une fois PraeviSEO installé, le dashboard client suit le statut du site, GSC et le monitoring.
                </p>
              </div>
            </CardContent>
          </Card>
        </div>
      </div>
    </div>
  );
}
