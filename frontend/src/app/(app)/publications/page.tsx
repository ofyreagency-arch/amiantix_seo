import { Badge } from "@/components/ui/badge";
import { CockpitSectionNav } from "@/components/cockpit/section-nav";
import { Topbar } from "@/components/layout/topbar";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { getPublications } from "@/lib/praeviseo-api";

export default async function PublicationsPage() {
  const publications = await getPublications();

  return (
    <div className="min-h-screen">
      <Topbar
        title="Activité SEO"
        subtitle="Le flux récent des contenus, des publications et des signaux visibles dans le cockpit PraeviSEO."
      />
      <div className="p-6 space-y-6">
        <CockpitSectionNav
          items={[
            { label: "Vue d’ensemble", href: "#vue-ensemble", count: publications.items.length, tone: "default" },
            { label: "Contenus préparés", href: "#prepares", count: publications.stats.engine_published, tone: "secondary" },
            { label: "Visible sur le site", href: "#visibles", count: publications.stats.live_published, tone: "success" },
            { label: "Flux récent", href: "#activite", count: publications.items.length, tone: "warning" },
          ]}
        />

        <div id="vue-ensemble" className="grid gap-4 md:grid-cols-3 scroll-mt-24">
          {[
            ["Contenus prepares", publications.stats.engine_published],
            ["Contenus visibles sur le site", publications.stats.live_published],
            ["URLs detectees", publications.stats.with_live_url],
          ].map(([label, value]) => (
            <Card key={label}>
              <CardHeader className="pb-2">
                <CardDescription>{label}</CardDescription>
                <CardTitle className="text-3xl">{value}</CardTitle>
              </CardHeader>
            </Card>
          ))}
        </div>

        <div className="grid gap-6 xl:grid-cols-2">
          <Card id="prepares" className="scroll-mt-24">
            <CardHeader>
              <CardTitle>Contenus préparés</CardTitle>
              <CardDescription>
                Les contenus que PraeviSEO a déjà prêts, même si tout n’est pas encore visible en live.
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-3">
              {publications.items.filter((item) => !item.published_live).length === 0 ? (
                <div className="rounded-xl border border-border bg-surface-2 px-4 py-4 text-sm text-text-muted">
                  Aucun contenu en attente. Le flux repassera ici dès qu’un contenu sera préparé avant mise en ligne.
                </div>
              ) : (
                publications.items
                  .filter((item) => !item.published_live)
                  .map((item) => (
                    <div key={`draft-${item.id}`} className="rounded-xl border border-border p-4 space-y-2">
                      <div className="flex items-center justify-between gap-3">
                        <div>
                          <p className="text-sm font-semibold text-text">{item.title}</p>
                          <p className="text-xs text-text-subtle">{item.site_id}</p>
                        </div>
                        <Badge variant="secondary">en preparation</Badge>
                      </div>
                      <p className="text-sm text-text-muted">
                        PraeviSEO garde ce contenu prêt pour la prochaine passe de publication.
                      </p>
                    </div>
                  ))
              )}
            </CardContent>
          </Card>

          <Card id="visibles" className="scroll-mt-24">
            <CardHeader>
              <CardTitle>Contenus visibles</CardTitle>
              <CardDescription>
                Les contenus déjà en ligne, pour donner un vrai signal de mouvement dans le cockpit.
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-3">
              {publications.items.filter((item) => item.published_live).length === 0 ? (
                <div className="rounded-xl border border-border bg-surface-2 px-4 py-4 text-sm text-text-muted">
                  Aucun contenu encore visible en live. Ce bloc s’animera dès les premières mises en ligne.
                </div>
              ) : (
                publications.items
                  .filter((item) => item.published_live)
                  .map((item) => (
                    <div key={`live-${item.id}`} className="rounded-xl border border-border p-4 space-y-2">
                      <div className="flex items-center justify-between gap-3">
                        <div>
                          <p className="text-sm font-semibold text-text">{item.title}</p>
                          <p className="text-xs text-text-subtle">{item.site_id}</p>
                        </div>
                        <Badge variant="success">visible sur le site</Badge>
                      </div>
                      <p className="text-sm text-text-muted">
                        Le contenu est déjà détecté comme visible sur le site client.
                      </p>
                    </div>
                  ))
              )}
            </CardContent>
          </Card>
        </div>

        <Card id="activite" className="scroll-mt-24">
          <CardHeader>
            <CardTitle>Flux d’activité récent</CardTitle>
            <CardDescription>
              Cette timeline montre les contenus publiés et l’état réel de leur visibilité.
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
                      {item.published_live ? "visible sur le site" : "en preparation"}
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
