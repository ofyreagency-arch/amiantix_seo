import { Badge } from "@/components/ui/badge";
import { CockpitSectionNav } from "@/components/cockpit/section-nav";
import { Topbar } from "@/components/layout/topbar";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { getPublications } from "@/lib/praeviseo-api";
import { formatDate } from "@/lib/utils";

export default async function PublicationsPage() {
  const publications = await getPublications();
  const visibleItems = publications.items.filter((item) => item.published_live);
  const draftItems = publications.items.filter((item) => !item.published_live);
  const risingItems = visibleItems.slice(0, 4);
  const refreshItems = [...draftItems, ...visibleItems.filter((item) => (item.seo_score ?? 0) < 80)].slice(0, 4);

  return (
    <div className="min-h-screen">
      <Topbar
        title="Blogs"
        subtitle="Les contenus suivis par PraeviSEO pour détecter les articles à pousser, à refresh ou à surveiller."
      />
      <div className="p-6 space-y-6">
        <CockpitSectionNav
          items={[
            { label: "Vue d’ensemble", href: "#vue-ensemble", count: publications.items.length, tone: "default" },
            { label: "Articles à suivre", href: "#prepares", count: publications.items.length, tone: "secondary" },
            { label: "En hausse", href: "#visibles", count: visibleItems.length, tone: "success" },
            { label: "À refresh", href: "#activite", count: refreshItems.length, tone: "warning" },
          ]}
        />

        <div id="vue-ensemble" className="grid gap-4 md:grid-cols-3 scroll-mt-24">
          {[
            ["Articles suivis", publications.items.length],
            ["Articles visibles", publications.stats.live_published],
            ["Articles à relancer", refreshItems.length],
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
              <CardTitle>Articles à suivre</CardTitle>
              <CardDescription>
                Les contenus que PraeviSEO garde dans le radar SEO, qu’ils soient déjà visibles ou encore à pousser.
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-3">
              {publications.items.length === 0 ? (
                <div className="rounded-xl border border-border bg-surface-2 px-4 py-4 text-sm text-text-muted">
                  Aucun article suivi pour le moment. Ce bloc se remplira dès les prochains contenus observés.
                </div>
              ) : (
                publications.items
                  .map((item) => (
                    <div key={`draft-${item.id}`} className="rounded-xl border border-border p-4 space-y-2">
                      <div className="flex items-center justify-between gap-3">
                        <div>
                          <p className="text-sm font-semibold text-text">{item.title}</p>
                          <p className="text-xs text-text-subtle">{item.site_id}</p>
                        </div>
                        <Badge variant={item.published_live ? "success" : "secondary"}>
                          {item.published_live ? "déjà visible" : "à pousser"}
                        </Badge>
                      </div>
                      <p className="text-sm text-text-muted">
                        {item.published_live
                          ? "PraeviSEO surveille déjà les signaux SEO de cet article."
                          : "PraeviSEO garde ce contenu dans le radar pour la prochaine phase d’exécution."}
                      </p>
                    </div>
                  ))
              )}
            </CardContent>
          </Card>

          <Card id="visibles" className="scroll-mt-24">
            <CardHeader>
              <CardTitle>Articles en hausse</CardTitle>
              <CardDescription>
                Les contenus déjà visibles qui donnent au free une vraie lecture de mouvement et de potentiel.
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-3">
              {visibleItems.length === 0 ? (
                <div className="rounded-xl border border-border bg-surface-2 px-4 py-4 text-sm text-text-muted">
                  Aucun article encore visible. Ce bloc s’animera dès les premiers contenus détectés sur le site.
                </div>
              ) : (
                risingItems
                  .map((item) => (
                    <div key={`live-${item.id}`} className="rounded-xl border border-border p-4 space-y-2">
                      <div className="flex items-center justify-between gap-3">
                        <div>
                          <p className="text-sm font-semibold text-text">{item.title}</p>
                          <p className="text-xs text-text-subtle">{item.site_id}</p>
                        </div>
                        <Badge variant="success">à renforcer</Badge>
                      </div>
                      <p className="text-sm text-text-muted">
                        {item.seo_score
                          ? `SEO score ${item.seo_score}. PraeviSEO peut aider à prolonger sa traction organique.`
                          : "Le contenu est déjà visible et reste une base exploitable pour le cockpit SEO."}
                      </p>
                    </div>
                  ))
              )}
            </CardContent>
          </Card>
        </div>

        <Card id="activite" className="scroll-mt-24">
          <CardHeader>
            <CardTitle>Articles à refresh</CardTitle>
            <CardDescription>
              Les contenus qui méritent une relance, une meilleure visibilité ou une optimisation future.
            </CardDescription>
          </CardHeader>
          <CardContent className="space-y-4">
            {refreshItems.map((item) => (
              <div key={item.id} className="rounded-xl border border-border p-4 space-y-3">
                <div className="flex flex-wrap items-center gap-2 justify-between">
                  <div>
                    <p className="text-sm font-semibold text-text">{item.title}</p>
                    <p className="text-xs text-text-subtle">
                      {item.site_id} / {item.slug || "/"}
                    </p>
                  </div>
                  <div className="flex items-center gap-2">
                    <Badge variant={item.seo_score && item.seo_score >= 80 ? "success" : "warning"}>
                      {item.seo_score && item.seo_score >= 80 ? "à consolider" : "refresh conseillé"}
                    </Badge>
                    <Badge variant={item.published_live ? "secondary" : "warning"}>
                      {item.published_live ? "déjà publié" : "pas encore visible"}
                    </Badge>
                  </div>
                </div>
                <div className="grid gap-2 md:grid-cols-3 text-xs text-text-subtle">
                  <span>SEO score : {item.seo_score ?? "n/a"}</span>
                  <span>Indexabilite : {item.indexability_score ?? "n/a"}</span>
                  <span>Vu le : {item.published_at ? formatDate(item.published_at) : "n/a"}</span>
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
