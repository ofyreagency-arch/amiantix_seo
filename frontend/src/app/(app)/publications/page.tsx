import { Badge } from "@/components/ui/badge";
import { CockpitSectionNav } from "@/components/cockpit/section-nav";
import { CockpitMetricGrid } from "@/components/cockpit/metric-grid";
import { CockpitSignalItem, CockpitSignalListCard } from "@/components/cockpit/signal-list";
import { Topbar } from "@/components/layout/topbar";
import { getOptimizations, getPublications } from "@/lib/praeviseo-api";
import { formatDate } from "@/lib/utils";

export default async function PublicationsPage() {
  const publications = await getPublications();
  const optimizations = await getOptimizations();
  const observedItems = publications.items.filter((item) => !!item.observed_content);
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
  const clusterItems = publications.items
    .filter((item) => !!item.observed_content?.cluster_label || !!item.cluster)
    .slice(0, 4);
  const linkingItems = observedItems
    .filter((item) => (item.observed_content?.internal_link_suggestions_count ?? 0) > 0)
    .sort(
      (a, b) =>
        (b.observed_content?.internal_link_suggestions_count ?? 0) -
        (a.observed_content?.internal_link_suggestions_count ?? 0)
    )
    .slice(0, 4);
  const cannibalizationItems = observedItems
    .filter(
      (item) =>
        (item.observed_content?.cannibalization_count ?? 0) > 0 ||
        (item.observed_content?.overlap_count ?? 0) > 0
    )
    .sort(
      (a, b) =>
        (b.observed_content?.cannibalization_count ?? 0) + (b.observed_content?.overlap_count ?? 0) -
        ((a.observed_content?.cannibalization_count ?? 0) + (a.observed_content?.overlap_count ?? 0))
    )
    .slice(0, 4);
  const recommendationItems = optimizations.recommendations.items.filter((item) =>
    item.type === "refresh_page" || item.type === "add_internal_links" || item.type === "create_page" || item.type === "expand_cluster"
  ).slice(0, 4);
  const refreshItems = [
    ...draftItems,
    ...visibleItems.filter(
        (item) => (item.seo_score ?? 0) < 80 || !!item.latest_suggestion || (item.gsc_metrics.position ?? 0) >= 8
      ),
  ].slice(0, 4);
  const enrichmentItems = observedItems
    .filter((item) => {
      const observed = item.observed_content;

      if (!observed) {
        return false;
      }

      return (
        !!item.latest_suggestion ||
        observed.snapshot_word_count < 900 ||
        observed.internal_inlinks <= 1 ||
        observed.query_match_count > 0 ||
        observed.authority_score >= 40
      );
    })
    .sort((a, b) => (b.observed_content?.authority_score ?? 0) - (a.observed_content?.authority_score ?? 0))
    .slice(0, 4);

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
              { label: "Contenu à enrichir", href: "#potentiel", count: enrichmentItems.length, tone: "secondary" },
              { label: "Maillage", href: "#maillage", count: linkingItems.length, tone: "warning" },
              { label: "Clusters", href: "#clusters", count: clusterItems.length, tone: "secondary" },
              { label: "Cannibalisation", href: "#cannibalisation", count: cannibalizationItems.length, tone: "warning" },
              { label: "Plan contenu", href: "#plan-contenu", count: recommendationItems.length, tone: "warning" },
              { label: "À refresh", href: "#activite", count: refreshItems.length, tone: "warning" },
            ]}
        />

        <div id="vue-ensemble" className="scroll-mt-24">
          <CockpitMetricGrid
            columnsClassName="grid gap-4 md:grid-cols-3"
            items={[
              { label: "Articles suivis", value: publications.items.length },
              { label: "Articles visibles", value: publications.stats.live_published, tone: "success" },
              { label: "Maillages à ouvrir", value: linkingItems.length, tone: linkingItems.length > 0 ? "warning" : "secondary" },
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
                      : item.latest_suggestion?.summary ??
                        (item.observed_content?.snapshot_word_count
                          ? `${item.observed_content.snapshot_word_count} mots observés, ${item.observed_content.internal_inlinks} lien${item.observed_content.internal_inlinks > 1 ? "s" : ""} entrant${item.observed_content.internal_inlinks > 1 ? "s" : ""}.`
                          : "PraeviSEO garde ce contenu dans le radar pour la prochaine phase d’exécution.")
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
            title="Contenu à enrichir"
            description="Les contenus qui ont déjà de la matière ou un vrai signal SEO, mais qui méritent encore plus de profondeur."
            empty={enrichmentItems.length === 0}
            emptyMessage="Aucun contenu à enrichir fortement pour le moment."
          >
            {enrichmentItems.map((item) => (
              <CockpitSignalItem
                key={`potential-${item.id}`}
                title={item.title}
                subtitle={item.site_id}
                badge={`Autorité ${item.observed_content?.authority_score ?? item.seo_score ?? "n/a"}`}
                badgeTone={(item.observed_content?.authority_score ?? item.seo_score ?? 0) >= 60 ? "success" : "secondary"}
                description={
                  item.latest_suggestion?.summary ??
                  `Cluster ${item.observed_content?.cluster_label ?? item.cluster ?? "n/a"}, ${item.observed_content?.snapshot_word_count ?? "n/a"} mots observés, ${item.observed_content?.query_match_count ?? 0} requête(s) déjà reliée(s).`
                }
              />
            ))}
          </CockpitSignalListCard>

          <CockpitSignalListCard
            id="maillage"
            className="scroll-mt-24"
            title="Maillage à renforcer"
            description="Les contenus où PraeviSEO voit déjà des liens internes utiles à ouvrir pour mieux pousser la page."
            empty={linkingItems.length === 0}
            emptyMessage="Aucun besoin de maillage fort pour le moment."
          >
            {linkingItems.map((item) => (
              <CockpitSignalItem
                key={`linking-${item.id}`}
                title={item.title}
                subtitle={item.site_id}
                badge={`${item.observed_content?.internal_link_suggestions_count ?? 0} piste(s)`}
                badgeTone="warning"
                description={
                  item.observed_content?.top_internal_link_target
                    ? `PraeviSEO suggère de relier cette page à ${item.observed_content.top_internal_link_target}. ${item.observed_content.internal_inlinks} lien(s) entrant(s) observé(s).`
                    : `Seulement ${item.observed_content?.internal_inlinks ?? 0} lien(s) entrant(s) observé(s) pour l’instant.`
                }
              />
            ))}
          </CockpitSignalListCard>
        </div>

        <div className="grid gap-6 xl:grid-cols-2">
          <CockpitSignalListCard
            id="clusters"
            className="scroll-mt-24"
            title="Clusters et sujets"
            description="Les sujets que PraeviSEO commence déjà à structurer, avec les pages qui peuvent devenir des piliers."
            empty={clusterItems.length === 0}
            emptyMessage="Aucun cluster contenu visible pour le moment."
          >
            {clusterItems.map((item) => (
              <CockpitSignalItem
                key={`cluster-${item.id}`}
                title={item.title}
                subtitle={item.site_id}
                badge={item.observed_content?.cluster_label ?? item.cluster ?? "Sujet"}
                badgeTone="secondary"
                description={
                  item.latest_suggestion?.summary ??
                  `Cluster ${item.observed_content?.cluster_label ?? item.cluster}, potentiel pilier ${item.observed_content?.pillar_likelihood ?? 0}, ${item.observed_content?.snapshot_word_count ?? 0} mots observés.`
                }
              />
            ))}
          </CockpitSignalListCard>

          <CockpitSignalListCard
            id="cannibalisation"
            className="scroll-mt-24"
            title="Cannibalisation à surveiller"
            description="Les contenus où PraeviSEO détecte déjà des recouvrements, des sujets proches ou des collisions à clarifier."
            empty={cannibalizationItems.length === 0}
            emptyMessage="Aucun risque de cannibalisation fort détecté pour le moment."
          >
            {cannibalizationItems.map((item) => (
              <CockpitSignalItem
                key={`cannibalization-${item.id}`}
                title={item.title}
                subtitle={item.site_id}
                badge={`${(item.observed_content?.cannibalization_count ?? 0) + (item.observed_content?.overlap_count ?? 0)} signal(s)`}
                badgeTone="warning"
                description={
                  item.observed_content?.top_cannibalization_target
                    ? `Sujet à clarifier face à ${item.observed_content.top_cannibalization_target}. Overlap ${item.observed_content.overlap_score} / 100.`
                    : `PraeviSEO voit ${item.observed_content?.overlap_count ?? 0} zone(s) de recouvrement à garder sous surveillance.`
                }
              />
            ))}
          </CockpitSignalListCard>
        </div>

        <CockpitSignalListCard
          id="plan-contenu"
          className="scroll-mt-24"
          title="Plan contenu recommandé"
          description="Les recommandations du moteur déjà prêtes pour améliorer les contenus, les clusters ou le maillage."
          empty={recommendationItems.length === 0}
          emptyMessage="Aucune recommandation contenu forte pour le moment. Le moteur enrichira ce bloc dès qu’un plan d’action devient utile."
        >
          {recommendationItems.map((item) => (
            <CockpitSignalItem
              key={`recommendation-${item.id}`}
              title={item.title}
              subtitle={`${item.site_id}${item.cluster ? ` · ${item.cluster}` : ""}`}
              badge={item.priority <= 30 ? "Action prioritaire" : "Reco moteur"}
              badgeTone={item.priority <= 30 ? "warning" : "secondary"}
              description={item.suggested_action ?? item.reasoning}
            />
          ))}
        </CockpitSignalListCard>

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
                  {item.observed_content && (
                    <Badge variant="secondary">
                      {item.observed_content.internal_inlinks <= 1 ? "maillage léger" : "déjà maillé"}
                    </Badge>
                  )}
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
                <span>Cluster : {item.observed_content?.cluster_label ?? item.cluster ?? "n/a"}</span>
                <span>Vu le : {item.published_at ? formatDate(item.published_at) : "n/a"}</span>
              </div>
              <div className="grid gap-2 md:grid-cols-4 text-xs text-text-subtle">
                <span>Impressions : {item.gsc_metrics.impressions}</span>
                <span>CTR : {item.gsc_metrics.ctr.toFixed(1)} %</span>
                <span>Position : {item.gsc_metrics.position?.toFixed(1) ?? "n/a"}</span>
                <span>
                  Maillage : {item.observed_content ? `${item.observed_content.internal_inlinks} entrant(s)` : "n/a"}
                </span>
              </div>
              {item.observed_content && (
                <div className="grid gap-2 md:grid-cols-4 text-xs text-text-subtle">
                  <span>Autorité : {item.observed_content.authority_score}</span>
                  <span>Mots observés : {item.observed_content.snapshot_word_count}</span>
                  <span>Cannibalisation : {item.observed_content.cannibalization_count}</span>
                  <span>Requêtes reliées : {item.observed_content.query_match_count}</span>
                </div>
              )}
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
