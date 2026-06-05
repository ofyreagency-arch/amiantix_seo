import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { CockpitSectionNav } from "@/components/cockpit/section-nav";
import { CockpitMetricGrid } from "@/components/cockpit/metric-grid";
import { CockpitSignalItem, CockpitSignalListCard } from "@/components/cockpit/signal-list";
import { Topbar } from "@/components/layout/topbar";
import {
  launchPremiumGenerationToStudioAction,
  launchPremiumImageToStudioAction,
  launchPremiumPublicationToStudioAction,
  launchPremiumRewriteToStudioAction,
} from "@/app/(app)/sites/[siteId]/connect/actions";
import { getOptimizations, getPublications, getSitePath } from "@/lib/praeviseo-api";
import { formatDate } from "@/lib/utils";
import { Eye, ImagePlus, PenSquare, UploadCloud } from "lucide-react";

type PageSearchParams = Promise<Record<string, string | string[] | undefined>>;

function getValue(value: string | string[] | undefined, fallback = ""): string {
  if (Array.isArray(value)) {
    return value[0] ?? fallback;
  }

  return value ?? fallback;
}

function impactLabel(impact: string): string {
  return (
    {
      high: "Gain attendu fort",
      medium: "Gain attendu moyen",
      low: "Gain attendu leger",
    }[impact] ?? "Gain a confirmer"
  );
}

function difficultyLabel(difficulty: string): string {
  return (
    {
      low: "Effort leger",
      medium: "Effort modere",
      high: "Effort soutenu",
    }[difficulty] ?? "Effort a cadrer"
  );
}

function contentProgressLabel(wordDelta: number | null | undefined): string {
  if (!wordDelta) {
    return "Contenu encore stable";
  }

  return wordDelta > 0 ? `+${wordDelta} mots depuis la dernière lecture` : "Contenu encore stable";
}

export default async function PublicationsPage({ searchParams }: { searchParams?: PageSearchParams }) {
  const publications = await getPublications();
  const optimizations = await getOptimizations();
  const resolvedSearchParams = searchParams ? await searchParams : {};
  const feedback = getValue(resolvedSearchParams.feedback);
  const feedbackTitle = getValue(resolvedSearchParams.feedback_title);
  const feedbackDetail = getValue(resolvedSearchParams.feedback_detail);
  const focus = getValue(resolvedSearchParams.focus);
  const focusSite = getValue(resolvedSearchParams.site);
  const focusQuery = getValue(resolvedSearchParams.query);
  const focusSlug = getValue(resolvedSearchParams.slug);
  const hasRealContent = publications.items.length > 0;
  const observedItems = publications.items.filter((item) => !!item.observed_content);
  const visibleItems = publications.items.filter((item) => item.published_live);
  const draftItems = publications.items.filter((item) => !item.published_live);
  const studioItems = [...publications.items]
    .sort((a, b) => {
      if (a.published_live !== b.published_live) {
        return a.published_live ? -1 : 1;
      }

      return (b.published_at ? new Date(b.published_at).getTime() : 0)
        - (a.published_at ? new Date(a.published_at).getTime() : 0);
    })
    .slice(0, 4);
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
  const leadContent = enrichmentItems[0] ?? risingItems[0] ?? publications.items[0] ?? null;
  const leadRecommendation = recommendationItems[0] ?? null;
  const focusedContent =
    publications.items.find(
      (item) =>
        (!!focusSlug && item.slug === focusSlug) &&
        (!focusSite || item.site_id === focusSite)
    ) ?? null;
  const focusedSiteId = focusSite || focusedContent?.site_id || leadContent?.site_id || publications.items[0]?.site_id || "";
  const queryStudioAction =
    focus === "query" && focusedSiteId && focusQuery
      ? launchPremiumGenerationToStudioAction.bind(null, focusedSiteId, focusQuery)
      : null;
  const focusMessage =
    focus === "query" && focusQuery
      ? {
          title: "Sujet article ciblé",
          detail: `PraeviSEO a ouvert le studio autour de "${focusQuery}". Lance d’abord ce brouillon ici, puis génère l’image et publie depuis la même vue.`,
          href: focusedSiteId ? getSitePath(focusedSiteId) : null,
        }
      : focus === "content" && focusedContent
        ? {
            title: "Contenu ciblé dans le studio",
            detail: `${focusedContent.title} est la cible du moment. Tu peux maintenant le réécrire, générer son image, le prévisualiser puis le publier au même endroit.`,
            href: focusedContent.live_url || focusedContent.preview_url || null,
          }
        : null;

  return (
    <div className="min-h-screen">
      <Topbar
        title="Contenus"
        subtitle={
          hasRealContent
            ? "Les contenus suivis par PraeviSEO pour détecter ce qu’il faut développer, enrichir, relancer ou surveiller."
            : "Cet espace s’activera dès que PraeviSEO repère de vrais contenus éditoriaux sur votre site."
        }
      />
      <div className="p-6 space-y-6">
        {feedbackTitle ? (
          <div
            className={[
              "rounded-2xl px-5 py-4",
              feedback === "error"
                ? "border border-danger/30 bg-danger/10"
                : feedback === "warning"
                  ? "border border-warning/30 bg-warning/10"
                  : "border border-success/30 bg-success/10",
            ].join(" ")}
          >
            <div className="text-sm font-semibold text-text">{feedbackTitle}</div>
            {feedbackDetail ? (
              <p className="mt-2 text-sm leading-6 text-text-muted">{feedbackDetail}</p>
            ) : null}
          </div>
        ) : null}

        {focusMessage ? (
          <div className="rounded-2xl border border-brand/20 bg-brand-muted px-5 py-4">
            <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
              <div>
                <div className="text-sm font-semibold text-text">{focusMessage.title}</div>
                <p className="mt-2 text-sm leading-6 text-text-muted">
                  {focusMessage.detail}
                </p>
              </div>
              <div className="flex flex-wrap gap-2">
                {queryStudioAction ? (
                  <form action={queryStudioAction}>
                    <Button size="sm">Créer l’article maintenant</Button>
                  </form>
                ) : null}
                {focusMessage.href ? (
                  <Button href={focusMessage.href} variant="secondary" size="sm" external={focusMessage.href.startsWith("http")}>
                    Ouvrir la cible
                  </Button>
                ) : (
                  <Button href="#prepares" variant="secondary" size="sm">
                    Aller au studio
                  </Button>
                )}
              </div>
            </div>
          </div>
        ) : null}

        <CockpitSectionNav
          items={[
            { label: "Vue d’ensemble", href: "#vue-ensemble", count: publications.items.length, tone: "default" },
            ...(hasRealContent
              ? [
                  { label: "Articles à suivre", href: "#prepares", count: publications.items.length, tone: "secondary" as const },
                  { label: "En hausse", href: "#visibles", count: visibleItems.length, tone: "success" as const },
                  { label: "Contenu à enrichir", href: "#potentiel", count: enrichmentItems.length, tone: "secondary" as const },
                  { label: "Pages à mieux relier", href: "#maillage", count: linkingItems.length, tone: "warning" as const },
                  { label: "Sujets", href: "#clusters", count: clusterItems.length, tone: "secondary" as const },
                  { label: "Sujets à clarifier", href: "#cannibalisation", count: cannibalizationItems.length, tone: "warning" as const },
                  { label: "Plan contenu", href: "#plan-contenu", count: recommendationItems.length, tone: "warning" as const },
                  { label: "À relancer", href: "#activite", count: refreshItems.length, tone: "warning" as const },
                ]
              : [
                  { label: "Quand il y aura du contenu", href: "#prepares", count: 0, tone: "secondary" as const },
                ]),
          ]}
        />

        <div id="vue-ensemble" className="scroll-mt-24">
          <CockpitMetricGrid
            columnsClassName="grid gap-4 md:grid-cols-3"
            items={[
              { label: "Contenus suivis", value: publications.items.length },
              { label: "Contenus déjà visibles", value: publications.stats.live_published, tone: "success" },
              { label: "Pages à mieux relier", value: linkingItems.length, tone: linkingItems.length > 0 ? "warning" : "secondary" },
              { label: "Contenus à relancer", value: refreshItems.length, tone: refreshItems.length > 0 ? "warning" : "secondary" },
            ]}
          />
        </div>

        <CockpitSignalListCard
          id="prepares"
          className="scroll-mt-24"
          title="Studio éditorial"
          description="Le bon endroit pour prévisualiser un article, lancer son image, préparer sa réécriture puis le publier sur le site client."
          empty={studioItems.length === 0}
          emptyMessage="Aucun contenu n’est encore disponible dans le moteur. Dès qu’un article est généré ou repéré, il apparaîtra ici avec ses vraies actions."
        >
          {studioItems.length > 0 ? (
            <div className="grid gap-4 xl:grid-cols-2">
              {studioItems.map((item) => {
                const rewriteAction = launchPremiumRewriteToStudioAction.bind(null, item.site_id, item.slug || undefined);
                const imageAction = launchPremiumImageToStudioAction.bind(null, item.site_id, item.slug || undefined);
                const publicationAction = launchPremiumPublicationToStudioAction.bind(null, item.site_id, item.slug || undefined);
                const isFocused =
                  (focus === "content" && focusSlug && item.slug === focusSlug && (!focusSite || item.site_id === focusSite)) ||
                  (focus === "query" && focusSite && item.site_id === focusSite);

                return (
                  <article
                    key={`studio-${item.id}`}
                    className={[
                      "overflow-hidden rounded-2xl bg-surface-2",
                      isFocused ? "border border-brand/40 ring-1 ring-brand/30" : "border border-border",
                    ].join(" ")}
                  >
                    {item.image_url ? (
                      <img
                        src={item.image_url}
                        alt={item.image_alt ?? item.title}
                        className="h-52 w-full object-cover"
                      />
                    ) : (
                      <div className="flex h-52 items-center justify-center bg-[radial-gradient(circle_at_top,_hsl(var(--brand)/0.18),_transparent_55%),linear-gradient(180deg,hsl(var(--surface-2)),hsl(var(--surface)))] text-text-subtle">
                        <div className="text-center">
                          <ImagePlus className="mx-auto h-8 w-8" />
                          <div className="mt-3 text-xs font-medium uppercase tracking-[0.24em]">
                            Image à générer
                          </div>
                        </div>
                      </div>
                    )}
                    <div className="space-y-4 px-4 py-4">
                      <div className="flex flex-wrap items-center gap-2">
                        <Badge variant={item.published_live ? "success" : "secondary"}>
                          {item.published_live ? "visible sur le site" : "préparé dans le moteur"}
                        </Badge>
                        <Badge variant={item.image_url ? "secondary" : "warning"}>
                          {item.image_url ? "image prête" : "image manquante"}
                        </Badge>
                        {item.cluster ? <Badge variant="secondary">{item.cluster}</Badge> : null}
                      </div>

                      <div>
                        <h3 className="text-base font-semibold text-text">{item.title}</h3>
                        <p className="mt-1 text-xs text-text-subtle">
                          {item.site_id} · /{item.slug || ""}
                        </p>
                      </div>

                      <p className="text-sm leading-6 text-text-muted">
                        {item.excerpt}
                      </p>

                      <div className="grid gap-2 text-xs text-text-subtle sm:grid-cols-2">
                        <span>SEO : {item.seo_score ?? "n/a"}</span>
                        <span>Position : {item.gsc_metrics.position?.toFixed(1) ?? "n/a"}</span>
                        <span>Impressions : {item.gsc_metrics.impressions}</span>
                        <span>Image : {item.image_status ?? (item.image_url ? "ready" : "pending")}</span>
                      </div>

                      <div className="flex flex-wrap gap-2">
                        {item.preview_url ? (
                          <Button href={item.preview_url} external variant="secondary" size="sm">
                            <Eye className="h-4 w-4" />
                            Prévisualiser
                          </Button>
                        ) : null}
                        {item.live_url ? (
                          <Button href={item.live_url} external variant="secondary" size="sm">
                            <UploadCloud className="h-4 w-4" />
                            Voir le live
                          </Button>
                        ) : null}
                        <form action={imageAction}>
                          <Button variant="secondary" size="sm">
                            <ImagePlus className="h-4 w-4" />
                            {item.image_url ? "Regénérer l’image" : "Générer l’image"}
                          </Button>
                        </form>
                        <form action={rewriteAction}>
                          <Button variant="secondary" size="sm">
                            <PenSquare className="h-4 w-4" />
                            Réécrire
                          </Button>
                        </form>
                        {!item.published_live ? (
                          <form action={publicationAction}>
                            <Button size="sm">
                              <UploadCloud className="h-4 w-4" />
                              Publier
                            </Button>
                          </form>
                        ) : null}
                      </div>
                    </div>
                  </article>
                );
              })}
            </div>
          ) : null}
        </CockpitSignalListCard>

        <div className="grid gap-6 xl:grid-cols-2">
          <CockpitSignalListCard
            title="Pourquoi enrichir ce contenu maintenant"
            description="Le contenu qui peut débloquer le plus vite un gain SEO ou éditorial."
            empty={!leadContent}
            emptyMessage="Aucun contenu prioritaire clair pour le moment. PraeviSEO rouvrira ce bloc dès qu’un levier éditorial plus net remonte."
          >
            {leadContent ? (
              <div className="rounded-xl border border-border p-4 space-y-3">
                <div className="flex flex-wrap items-center justify-between gap-3">
                  <div>
                    <p className="text-sm font-semibold text-text">{leadContent.title}</p>
                    <p className="text-xs text-text-subtle">
                      {leadContent.site_id}
                      {leadContent.observed_content?.cluster_label ?? leadContent.cluster
                        ? ` · ${leadContent.observed_content?.cluster_label ?? leadContent.cluster}`
                        : ""}
                    </p>
                  </div>
                  <div className="flex flex-wrap gap-2">
                    <Badge variant={(leadContent.observed_content?.authority_score ?? leadContent.seo_score ?? 0) >= 60 ? "success" : "secondary"}>
                      Autorite {leadContent.observed_content?.authority_score ?? leadContent.seo_score ?? "n/a"}
                    </Badge>
                    <Badge variant={leadContent.latest_suggestion ? "warning" : "secondary"}>
                      {leadContent.latest_suggestion ? "Reco ouverte" : "A enrichir"}
                    </Badge>
                  </div>
                </div>
                <p className="text-sm text-text-muted">
                  {leadContent.latest_suggestion?.summary ??
                    `Ce contenu a deja ${leadContent.observed_content?.query_match_count ?? 0} requete(s) reliee(s), ${leadContent.observed_content?.snapshot_word_count ?? "n/a"} mots observes et un vrai potentiel a renforcer.`}
                </p>
                <div className="grid gap-2 md:grid-cols-4 text-xs text-text-subtle">
                  <span>Impressions : {leadContent.gsc_metrics.impressions}</span>
                  <span>CTR : {leadContent.gsc_metrics.ctr.toFixed(1)} %</span>
                  <span>Position : {leadContent.gsc_metrics.position?.toFixed(1) ?? "n/a"}</span>
                  <span>Maillage : {leadContent.observed_content ? `${leadContent.observed_content.internal_inlinks} entrant(s)` : "n/a"}</span>
                </div>
                {leadContent.observed_content ? (
                  <p className="text-sm text-text-muted">
                    {leadContent.observed_content.snapshot_word_delta > 0
                      ? `PraeviSEO voit déjà un enrichissement de +${leadContent.observed_content.snapshot_word_delta} mots depuis la lecture précédente.`
                      : "PraeviSEO n’a pas encore vu de vrai changement de contenu entre les deux dernières lectures."}
                  </p>
                ) : null}
              </div>
            ) : null}
          </CockpitSignalListCard>

          <CockpitSignalListCard
            title="Action contenu a ouvrir"
            description="La meilleure action moteur deja prete pour faire progresser le cockpit contenu."
            empty={!leadRecommendation}
            emptyMessage="Aucune action contenu prioritaire pour le moment. Le moteur enrichira ce bloc dès qu’un plan a bon ratio impact / effort remonte."
          >
            {leadRecommendation ? (
              <div className="rounded-xl border border-border p-4 space-y-3">
                <div className="flex flex-wrap items-center justify-between gap-3">
                  <div>
                    <p className="text-sm font-semibold text-text">{leadRecommendation.title}</p>
                    <p className="text-xs text-text-subtle">
                      {leadRecommendation.site_id}
                      {leadRecommendation.cluster ? ` · ${leadRecommendation.cluster}` : ""}
                    </p>
                  </div>
                  <Badge variant={leadRecommendation.priority <= 30 ? "warning" : "secondary"}>
                    Priorite {leadRecommendation.priority}
                  </Badge>
                </div>
                <div className="flex flex-wrap gap-2">
                  <Badge variant="secondary">{impactLabel(leadRecommendation.estimated_impact)}</Badge>
                  <Badge variant="secondary">{difficultyLabel(leadRecommendation.difficulty)}</Badge>
                </div>
                <p className="text-sm text-text-muted">{leadRecommendation.reasoning}</p>
                <p className="text-sm text-text">
                  Action a ouvrir :{" "}
                  <span className="font-medium">{leadRecommendation.suggested_action ?? "a preciser dans le moteur"}</span>
                </p>
              </div>
            ) : null}
          </CockpitSignalListCard>
        </div>

        <div className="grid gap-6 xl:grid-cols-2">
          <CockpitSignalListCard
            title="Articles à suivre"
            description="Les contenus que PraeviSEO garde dans le radar SEO, qu’ils soient déjà visibles ou encore à pousser."
            empty={publications.items.length === 0}
            emptyMessage="PraeviSEO n’a pas encore repéré de vrais contenus éditoriaux sur ce site. Dès qu’un blog, un guide ou des pages de contenu seront observés, ce cockpit se remplira automatiquement."
          >
            {publications.items.map((item) => (
              <CockpitSignalItem
                key={`draft-${item.id}`}
                title={item.title}
                subtitle={item.site_id}
                badge={item.published_live ? "déjà visible" : "à développer"}
                badgeTone={item.published_live ? "success" : "secondary"}
                description={
                    item.published_live
                      ? `${item.gsc_metrics.impressions} affichage(s) dans Google, avec une présence moyenne autour de la ${item.gsc_metrics.position ? Math.round(item.gsc_metrics.position) : "?"}e place.`
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
            emptyMessage="Aucun contenu éditorial visible pour le moment. Ce bloc s’animera dès que PraeviSEO verra de vraies pages de contenu sur le site."
          >
            {risingItems.map((item) => (
              <CockpitSignalItem
                key={`live-${item.id}`}
                title={item.title}
                subtitle={item.site_id}
                badge="à développer"
                badgeTone="success"
                description={
                  item.gsc_metrics.impressions > 0
                    ? `${item.gsc_metrics.impressions} affichage(s) dans Google et ${item.gsc_metrics.clicks} clic(s) déjà obtenus.`
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
            emptyMessage="Aucun contenu à enrichir fortement pour le moment. Quand PraeviSEO repérera des contenus plus denses, ils apparaîtront ici."
          >
            {enrichmentItems.map((item) => (
              <CockpitSignalItem
                key={`potential-${item.id}`}
                title={item.title}
                subtitle={item.site_id}
                badge={`Solidité ${item.observed_content?.authority_score ?? item.seo_score ?? "n/a"}`}
                badgeTone={(item.observed_content?.authority_score ?? item.seo_score ?? 0) >= 60 ? "success" : "secondary"}
                description={
                  item.latest_suggestion?.summary ??
                  `Sujet "${item.observed_content?.cluster_label ?? item.cluster ?? "principal"}", ${item.observed_content?.snapshot_word_count ?? "n/a"} mots observés, ${item.observed_content?.query_match_count ?? 0} recherche(s) déjà reliée(s). ${contentProgressLabel(item.observed_content?.snapshot_word_delta)}.`
                }
              />
            ))}
          </CockpitSignalListCard>

          <CockpitSignalListCard
            id="maillage"
            className="scroll-mt-24"
            title="Pages à mieux relier"
            description="Les contenus où PraeviSEO voit déjà des liens utiles à ajouter depuis le reste du site."
            empty={linkingItems.length === 0}
            emptyMessage="Aucune page de contenu ne demande encore de gros effort de liaison pour le moment."
          >
            {linkingItems.map((item) => (
              <CockpitSignalItem
                key={`linking-${item.id}`}
                title={item.title}
                subtitle={item.site_id}
                badge={`${item.observed_content?.internal_link_suggestions_count ?? 0} idée(s)`}
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
            title="Sujets et familles de contenus"
            description="Les sujets que PraeviSEO commence déjà à regrouper, avec les pages qui peuvent devenir centrales."
            empty={clusterItems.length === 0}
            emptyMessage="Aucune famille de contenus claire n’est encore visible pour le moment."
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
                  `Sujet "${item.observed_content?.cluster_label ?? item.cluster}", page potentiellement centrale, ${item.observed_content?.snapshot_word_count ?? 0} mots observés. ${contentProgressLabel(item.observed_content?.snapshot_word_delta)}.`
                }
              />
            ))}
          </CockpitSignalListCard>

          <CockpitSignalListCard
            id="cannibalisation"
            className="scroll-mt-24"
            title="Sujets à clarifier"
            description="Les contenus où PraeviSEO détecte déjà des sujets trop proches ou des doublons à mieux différencier."
            empty={cannibalizationItems.length === 0}
            emptyMessage="Aucun sujet trop proche à clarifier de toute urgence pour le moment."
          >
            {cannibalizationItems.map((item) => (
              <CockpitSignalItem
                key={`cannibalization-${item.id}`}
                title={item.title}
                subtitle={item.site_id}
                badge={`${(item.observed_content?.cannibalization_count ?? 0) + (item.observed_content?.overlap_count ?? 0)} point(s)`}
                badgeTone="warning"
                description={
                  item.observed_content?.top_cannibalization_target
                    ? `Sujet à clarifier face à ${item.observed_content.top_cannibalization_target}. Recouvrement estimé ${item.observed_content.overlap_score} / 100.`
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
          description="Les recommandations déjà prêtes pour améliorer vos contenus, mieux relier vos pages ou clarifier vos sujets."
          empty={recommendationItems.length === 0}
          emptyMessage="Aucune action contenu forte pour le moment. Le moteur enrichira ce bloc dès qu’un plan utile remonte."
        >
          {recommendationItems.map((item) => (
            <CockpitSignalItem
              key={`recommendation-${item.id}`}
              title={item.title}
              subtitle={item.site_id}
              badge={item.priority <= 30 ? "À faire en premier" : "Action recommandée"}
              badgeTone={item.priority <= 30 ? "warning" : "secondary"}
              description={item.suggested_action ?? item.reasoning}
            />
          ))}
        </CockpitSignalListCard>

        <CockpitSignalListCard
          id="activite"
          className="scroll-mt-24"
          title="Contenus à relancer"
          description="Les contenus qui méritent une relance, une meilleure visibilité ou une optimisation future."
          empty={refreshItems.length === 0}
          emptyMessage="Aucun contenu à relancer pour le moment."
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
                  <span>Évolution : {contentProgressLabel(item.observed_content.snapshot_word_delta)}</span>
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
