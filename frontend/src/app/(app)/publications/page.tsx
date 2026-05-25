import { Badge } from "@/components/ui/badge";
import { Topbar } from "@/components/layout/topbar";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { getPublications } from "@/lib/praeviseo-api";

export default async function PublicationsPage() {
  const publications = await getPublications();

  return (
    <div className="min-h-screen">
      <Topbar
        title="Publications"
        subtitle="Suivi client des publications runtime, bridge et site public réel."
      />
      <div className="p-6 space-y-6">
        <div className="grid gap-4 md:grid-cols-3">
          {[
            ["Publie cote moteur", publications.stats.engine_published],
            ["Publie en live", publications.stats.live_published],
            ["URL live detectees", publications.stats.with_live_url],
          ].map(([label, value]) => (
            <Card key={label}>
              <CardHeader className="pb-2">
                <CardDescription>{label}</CardDescription>
                <CardTitle className="text-3xl">{value}</CardTitle>
              </CardHeader>
            </Card>
          ))}
        </div>

        <Card>
          <CardHeader>
            <CardTitle>Historique recent</CardTitle>
            <CardDescription>
              Cette liste montre les pages publiees et si la vraie publication live a deja pris la main.
            </CardDescription>
          </CardHeader>
          <CardContent className="space-y-4">
            {publications.items.map((item) => (
              <div key={item.id} className="rounded-xl border border-border p-4 space-y-3">
                <div className="flex flex-wrap items-center gap-2 justify-between">
                  <div>
                    <p className="text-sm font-semibold text-text">{item.title}</p>
                    <p className="text-xs text-text-subtle">
                      {item.site_id} / {item.slug || "/"}
                    </p>
                  </div>
                  <div className="flex items-center gap-2">
                    <Badge variant="secondary">{item.status}</Badge>
                    <Badge variant={item.published_live ? "success" : "warning"}>
                      {item.published_live ? "live" : "moteur seulement"}
                    </Badge>
                  </div>
                </div>
                <div className="grid gap-2 md:grid-cols-3 text-xs text-text-subtle">
                  <span>SEO score : {item.seo_score ?? "n/a"}</span>
                  <span>Indexabilite : {item.indexability_score ?? "n/a"}</span>
                  <span>Publie le : {item.published_at ? new Date(item.published_at).toLocaleDateString("fr-FR") : "n/a"}</span>
                </div>
                {item.live_url && (
                  <a
                    href={item.live_url}
                    target="_blank"
                    rel="noreferrer"
                    className="inline-flex text-sm text-brand hover:underline"
                  >
                    Voir la page publique
                  </a>
                )}
              </div>
            ))}
          </CardContent>
        </Card>
      </div>
    </div>
  );
}
