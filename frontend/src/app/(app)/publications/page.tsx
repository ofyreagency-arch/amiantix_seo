import { Badge } from "@/components/ui/badge";
import { CockpitSectionNav } from "@/components/cockpit/section-nav";
import { CockpitMetricGrid } from "@/components/cockpit/metric-grid";
import { CockpitSignalItem, CockpitSignalListCard } from "@/components/cockpit/signal-list";
import { Topbar } from "@/components/layout/topbar";
import { getPublications } from "@/lib/praeviseo-api";
import { formatDate } from "@/lib/utils";

export default async function PublicationsPage() {
  const publications = await getPublications();
  const visibleItems = publications.items.filter((item) => item.published_live);
  const draftItems = publications.items.filter((item) => !item.published_live);
  const risingItems = [...visibleItems]
    .sort((a, b) => b.gsc_metrics.impressions - a.gsc_metrics.impressions)
    .slice(0, 4);
  const strongestContent = [...publications.items]
    .sort((a, b) => {
      const aScore = (a.seo_score ?? 0) + (a.topical_score ?? 0) + (a.quality_score ?? 0);
      const bScore = (b.seo_score ?? 0) + (b.topical_score ?? 0) + (b.quality_score ?? 0);

      return bScore - aScore;
    })
    .slice(0, 4);
  const clusterItems = publications.items.filter((item) => !!item.cluster).slice(0, 4);
  const refreshItems = [
    ...draftItems,
    ...visibleItems.filter(
      (item) => (item.seo_score ?? 0) < 80 || !!item.latest_suggestion || (item.gsc_metrics.position ?? 0) >= 8
    ),
  ].slice(0, 4);

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
            { label: "Potentiel contenu", href: "#potentiel", count: strongestContent.length, tone: "secondary" },
            { label: "Clusters", href: "#clusters", count: clusterItems.length, tone: "secondary" },
            { label: "À refresh", href: "#activite", count: refreshItems.length, tone: "warning" },
          ]}
        />

        <div id="vue-ensemble" className="scroll-mt-24">
          <CockpitMetricGrid
            columnsClassName="grid gap-4 md:grid-cols-3"
            items={[
              { label: "Articles suivis", value: publications.items.length },
              { label: "Articles visibles", value: publications.stats.live_published, tone: "success" },
              { label: "Articles à fort potentiel", value: strongestContent.length, tone: "secondary" },
              { label: "Articles à relancer", value: refreshItems.length, tone: refreshItems.length > 0 ? "warning" : "secondary" },
            ]}
          />
        </div>

        <div className="grid gap-6 xl:grid-cols-2">
          <CockpitSignalListCard
            id="prepares"
            className="scroll-mt-24"
            title="Articles à suivre"
            description="Les contenus que PraeviSEO garde dans le radar SEO, qu’ils soient déjà visibles ou encore à pousser."
            empty={publications.items.length === 0}
            emptyMessage="Aucun article suivi pour le moment. Ce bloc se remplira dès les prochains contenus observés."
          >
            {publications.items.map((item) => (
              <CockpitSignalItem
                key={`draft-${item.id}`}
                title={item.title}
                subtitle={item.site_id}
                badge={item.published_live ? "déjà visible" : "à pousser"}
                badgeTone={item.published_live ? "success" : "secondary"}
                description={
                  item.published_live
                    ? `${item.gsc_metrics.impressions} impressions, CTR ${item.gsc_metrics.ctr.toFixed(1)} %, position ${item.gsc_metrics.position?.toFixed(1) ?? "n/a"}.`
                    : item.latest_suggestion?.summary ?? "PraeviSEO garde ce contenu dans le radar pour la prochaine phase d’exécution."
                }
              />
            ))}
          </CockpitSignalListCard>

          <CockpitSignalListCard
            id="visibles"
            className="scroll-mt-24"
            title="Articles en hausse"
            description="Les contenus déjà visibles qui donnent au free une vraie lecture de mouvement et de potentiel."
            empty={visibleItems.length === 0}
            emptyMessage="Aucun article encore visible. Ce bloc s’animera dès les premiers contenus détectés sur le site."
          >
            {risingItems.map((item) => (
              <CockpitSignalItem
                key={`live-${item.id}`}
                title={item.title}
                subtitle={item.site_id}
                badge="à renforcer"
                badgeTone="success"
                description={
                  item.gsc_metrics.impressions > 0
                    ? `${item.gsc_metrics.impressions} impressions, ${item.gsc_metrics.clicks} clics, CTR ${item.gsc_metrics.ctr.toFixed(1)} %, position ${item.gsc_metrics.position?.toFixed(1) ?? "n/a"}.`
                    : item.seo_score
                      ? `SEO score ${item.seo_score}. PraeviSEO peut aider à prolonger sa traction organique.`
                      : "Le contenu est déjà visible et reste une base exploitable pour le cockpit SEO."
                }
              />
            ))}
          </CockpitSignalListCard>
        </div>

        <div className="grid gap-6 xl:grid-cols-2">
          <CockpitSignalListCard
            id="potentiel"
            className="scroll-mt-24"
            title="Potentiel contenu"
            description="Les articles déjà les plus solides côté contenu, qualité et SEO score."
            empty={strongestContent.length === 0}
            emptyMessage="Aucun contenu assez scoré pour le moment."
          >
            {strongestContent.map((item) => (
              <CockpitSignalItem
                key={`potential-${item.id}`}
                title={item.title}
                subtitle={item.site_id}
                badge={`SEO ${item.seo_score ?? "n/a"}`}
                badgeTone={(item.seo_score ?? 0) >= 80 ? "success" : "secondary"}
                description={`Topical ${item.topical_score ?? "n/a"}, qualité ${item.quality_score ?? "n/a"}, indexabilité ${item.indexability_score ?? "n/a"}.`}
              />
            ))}
          </CockpitSignalListCard>

          <CockpitSignalListCard
            id="clusters"
            className="scroll-mt-24"
            title="Clusters et sujets"
            description="Les sujets que PraeviSEO a déjà structurés et qui peuvent devenir un vrai levier contenu."
            empty={clusterItems.length === 0}
            emptyMessage="Aucun cluster contenu visible pour le moment."
          >
            {clusterItems.map((item) => (
              <CockpitSignalItem
                key={`cluster-${item.id}`}
                title={item.title}
                subtitle={item.site_id}
                badge={item.cluster ?? "Sujet"}
                badgeTone="secondary"
                description={
                  item.latest_suggestion?.summary ??
                  `Cluster ${item.cluster}, ${item.gsc_metrics.impressions} impressions, position ${item.gsc_metrics.position?.toFixed(1) ?? "n/a"}.`
                }
              />
            ))}
          </CockpitSignalListCard>
        </div>

        <CockpitSignalListCard
          id="activite"
          className="scroll-mt-24"
          title="Articles à refresh"
          description="Les contenus qui méritent une relance, une meilleure visibilité ou une optimisation future."
          empty={refreshItems.length === 0}
          emptyMessage="Aucun article à refresh pour le moment."
        >
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
                  {item.latest_suggestion && (
                    <Badge variant={item.latest_suggestion.status === "pending" ? "warning" : "secondary"}>
                      reco {item.latest_suggestion.status === "pending" ? "ouverte" : "récente"}
                    </Badge>
                  )}
                </div>
              </div>
              <div className="grid gap-2 md:grid-cols-4 text-xs text-text-subtle">
                <span>SEO score : {item.seo_score ?? "n/a"}</span>
                <span>Indexabilite : {item.indexability_score ?? "n/a"}</span>
                <span>Cluster : {item.cluster ?? "n/a"}</span>
                <span>Vu le : {item.published_at ? formatDate(item.published_at) : "n/a"}</span>
              </div>
              <div className="grid gap-2 md:grid-cols-4 text-xs text-text-subtle">
                <span>Impressions : {item.gsc_metrics.impressions}</span>
                <span>CTR : {item.gsc_metrics.ctr.toFixed(1)} %</span>
                <span>Position : {item.gsc_metrics.position?.toFixed(1) ?? "n/a"}</span>
                <span>Qualité : {item.quality_score ?? "n/a"}</span>
              </div>
              {item.latest_suggestion && (
                <p className="text-sm text-text-muted">{item.latest_suggestion.summary}</p>
              )}
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
        </CockpitSignalListCard>
      </div>
    </div>
  );
}
