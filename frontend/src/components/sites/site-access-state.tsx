import { AlertTriangle, ArrowLeft, LogIn, RefreshCw } from "lucide-react";
import { Topbar } from "@/components/layout/topbar";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";

interface SiteAccessStateProps {
  siteId: string;
  areaLabel: string;
}

export function SiteAccessState({ siteId, areaLabel }: SiteAccessStateProps) {
  return (
    <div className="min-h-screen">
      <Topbar
        title="Accès au site indisponible"
        subtitle={`PraeviSEO n arrive pas à charger ${siteId} pour ${areaLabel}.`}
      />

      <div className="p-6 space-y-6">
        <div className="rounded-3xl border border-[hsl(var(--destructive)/0.22)] bg-[hsl(var(--destructive)/0.07)] px-6 py-6">
          <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div className="max-w-3xl">
              <Badge variant="danger" className="mb-3">
                Site inaccessible
              </Badge>
              <h1 className="text-2xl font-bold tracking-tight text-text">
                PraeviSEO n a pas pu recharger le site {siteId}
              </h1>
              <p className="mt-3 text-sm leading-7 text-text-muted">
                Le frontend sait encore que vous êtes connecté, mais la lecture du site rattaché a échoué. Cela arrive
                en général si le site n est plus renvoyé par l API client, si le rattachement a sauté ou si l appel
                backend répond mal.
              </p>
            </div>

            <div className="rounded-2xl border border-border bg-surface px-5 py-4 min-w-[280px]">
              <div className="flex items-center gap-2 text-sm font-semibold text-text">
                <AlertTriangle className="h-4 w-4 text-[hsl(var(--destructive))]" />
                Ce que l on sait déjà
              </div>
              <ul className="mt-3 space-y-2 text-sm leading-6 text-text-muted">
                <li>Le chargement du site {siteId} a renvoyé vide.</li>
                <li>Le sélecteur global de sites n a probablement rien reçu non plus.</li>
                <li>Le problème n est pas un crawl en cours, mais l accès au site lui-même.</li>
              </ul>
            </div>
          </div>
        </div>

        <div className="grid gap-6 xl:grid-cols-[1fr_0.95fr]">
          <Card>
            <CardHeader>
              <CardTitle>Causes probables</CardTitle>
              <CardDescription>
                Les trois cas qui expliquent le plus souvent un écran vide ou un 404 sur une page site.
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-3">
              {[
                "Le site n est plus rattaché au compte client utilisé dans cette session.",
                "L API /api/client/sites ou /api/client/sites/{siteId} répond avec une erreur backend.",
                "La session frontend est encore ouverte, mais le token n est plus accepté pour la lecture des sites.",
              ].map((item) => (
                <div key={item} className="rounded-2xl border border-border bg-surface-2 px-4 py-4 text-sm leading-6 text-text-muted">
                  {item}
                </div>
              ))}
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Que faire maintenant</CardTitle>
              <CardDescription>
                Les actions les plus utiles pour récupérer l accès sans rester bloqué sur un 404 sec.
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-3">
              <Button href="/sites" className="w-full">
                <ArrowLeft className="h-4 w-4" />
                Revenir à la liste des sites
              </Button>
              <Button href="/dashboard" variant="secondary" className="w-full">
                <RefreshCw className="h-4 w-4" />
                Revenir au cockpit global
              </Button>
              <Button href="/login" variant="secondary" className="w-full">
                <LogIn className="h-4 w-4" />
                Recharger la session client
              </Button>
            </CardContent>
          </Card>
        </div>
      </div>
    </div>
  );
}
