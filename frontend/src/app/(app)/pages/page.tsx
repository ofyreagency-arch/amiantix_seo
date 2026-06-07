export const dynamic = "force-dynamic";

import { CockpitSectionNav } from "@/components/cockpit/section-nav";
import { CockpitMetricGrid } from "@/components/cockpit/metric-grid";
import { CockpitAssistantGuide } from "@/components/cockpit/assistant-guide";
import { CockpitSignalItem, CockpitSignalListCard } from "@/components/cockpit/signal-list";
import { Topbar } from "@/components/layout/topbar";
import { Button } from "@/components/ui/button";
import { getDashboard, getOptimizations, getPublications, getSitePath } from "@/lib/praeviseo-api";
import { formatDate } from "@/lib/utils";

type PageSearchParams = Promise<Record<string, string | string[] | undefined>>;

function getValue(value: string | string[] | undefined, fallback = ""): string {
  if (Array.isArray(value)) {
    return value[0] ?? fallback;
  }

  return value ?? fallback;
}

function hasReliableSeoSignal(item: {
  seo_score: number | null;
  gsc_metrics?: { impressions: number };
  observed_content?: {
    observed_http_status?: number | null;
    snapshot_word_count?: number;
    internal_inlinks?: number;
    query_match_count?: number;
  } | null;
}) {
  const observed = item.observed_content;
  const impressions = item.gsc_metrics?.impressions ?? 0;

  if (!observed) {
    return impressions > 0;
  }

  return (
    impressions > 0
    || (observed.snapshot_word_count ?? 0) >= 300
    || (observed.internal_inlinks ?? 0) > 0
    || (observed.query_match_count ?? 0) > 0
    || (((observed.observed_http_status ?? 0) >= 200) && ((observed.observed_http_status ?? 0) < 400))
  );
}

function seoSignalLabel(item: {
  seo_score: number | null;
  gsc_metrics?: { impressions: number };
  observed_content?: {
    observed_http_status?: number | null;
    snapshot_word_count?: number;
    internal_inlinks?: number;
    query_match_count?: number;
  } | null;
}) {
  if (!hasReliableSeoSignal(item)) {
    return "Signal SEO insuffisant";
  }

  return item.seo_score !== null ? `SEO observé : ${item.seo_score}` : "Score en calcul";
}

export default async function PagesCockpitPage({ searchParams }: { searchParams?: PageSearchParams }) {
  const dashboard = await getDashboard();
  const optimizations = await getOptimizations();
  const publications = await getPublications();
  const resolvedSearchParams = searchParams ? await searchParams : {};
  const focus = getValue(resolvedSearchParams.focus);
  const focusSite = getValue(resolvedSearchParams.site);
  const focusTarget = getValue(resolvedSearchParams.target);
  const freshestSyncAt = dashboard.sites
    .map((site) => site.gsc_last_sync_at)
    .filter((value): value is string => Boolean(value))
    .sort()
    .at(-1);
  const freshestDataAsOf = dashboard.sites
    .map((site) => site.gsc_data_as_of)
    .filter((value): value is string => Boolean(value))
    .sort()
    .at(-1);

  const pageSignals = dashboard.sites.flatMap((site) => [
    ...site.summary.top_rising_pages.map((item) => ({ ...item, site_name: site.name, trend: "up" as const })),
    ...site.summary.top_falling_pages.map((item) => ({ ...item, site_name: site.name, trend: "down" as const })),
  ]);
  const observedPillarPages = dashboard.sites.flatMap((site) =>
    site.summary.observed_pillar_pages.map((item) => ({ ...item, site_name: site.name, site_id: site.site_id }))
  );
  const observedLinkGapPages = dashboard.sites.flatMap((site) =>
    site.summary.observed_link_gap_pages.map((item) => ({ ...item, site_name: site.name, site_id: site.site_id }))
  );
  const observedOrphanAlerts = dashboard.sites.flatMap((site) =>
    site.summary.observed_orphan_alerts.map((item) => ({ ...item, site_name: site.name, site_id: site.site_id }))
  );
  const observedWeakPages = dashboard.sites.flatMap((site) =>
    site.summary.observed_weak_page_details.map((item) => ({ ...item, site_name: site.name, site_id: site.site_id }))
  );
  const observedPriorityPages = [
    ...observedPillarPages.map((item) => ({
      id: `${item.site_id}-${item.slug}-pillar-priority`,
      site_id: item.site_id,
      slug: item.slug,
      title: item.label,
      site_name: item.site_name,
      badge: "Pilier potentiel",
      badgeTone: "success" as const,
      description: item.cluster_label
        ? `Cette page porte deja bien le sujet "${item.cluster_label}" et recoit ${item.internal_inlinks} lien(s) depuis le reste du site.`
        : `Cette page a deja un role central possible et recoit ${item.internal_inlinks} lien(s) depuis le reste du site.`,
    })),
    ...observedLinkGapPages.map((item) => ({
      id: `${item.site_id}-${item.slug}-link-gap-priority`,
      site_id: item.site_id,
      slug: item.slug,
      title: item.label,
      site_name: item.site_name,
      badge: "Sous-maillée",
      badgeTone: "warning" as const,
      description: `Cette page utile reste encore trop peu soutenue par le reste du site avec seulement ${item.internal_inlinks} lien(s) recus.`,
    })),
    ...observedOrphanAlerts.map((item) => ({
      id: `${item.site_id}-${item.slug}-orphan-priority`,
      site_id: item.site_id,
      slug: item.slug,
      title: item.label,
      site_name: item.site_name,
      badge: "Orpheline",
      badgeTone: "danger" as const,
      description: "Cette page est trop isolee dans le site et Google la comprend encore mal sans meilleur contexte autour d elle.",
    })),
    ...observedWeakPages.map((item) => ({
      id: `${item.site_id}-${item.slug}-weak-priority`,
      site_id: item.site_id,
      slug: item.slug,
      title: item.label,
      site_name: item.site_name,
      badge: "Faible",
      badgeTone: "secondary" as const,
      description:
        item.indexability_state !== "indexable"
          ? "Google ne comprend pas encore clairement cette page. Son contenu et ses liens meritent encore une verification."
          : "Le contenu existe deja, mais cette page reste encore trop peu soutenue par le reste du site.",
    })),
  ]
    .filter((item, index, items) => items.findIndex((candidate) => candidate.id === item.id) === index)
    .slice(0, 8);
  const risingPages = pageSignals.filter((item) => item.trend === "up").slice(0, 6);
  const fallingPages = pageSignals.filter((item) => item.trend === "down").slice(0, 6);
  const watchOpportunityPages = optimizations.gsc_opportunities.items
    .filter((item) => item.type === "near_top_10" || item.type === "low_ctr" || item.type === "sustained_drop");
  const pagesToWatch = [
    ...watchOpportunityPages,
    ...observedOrphanAlerts.map((item) => ({
      site_id: item.site_id,
      site_name: item.site_name,
      slug: item.slug,
      label: item.label,
      type: "observed_orphan",
      priority_label: "Sous-maillée",
      priority_level: "medium" as const,
      reason: "Cette page recoit encore trop peu de liens utiles depuis vos autres pages, donc elle reste isolee.",
    })),
    ...observedLinkGapPages.map((item) => ({
      site_id: item.site_id,
      site_name: item.site_name,
      slug: item.slug,
      label: item.label,
      type: "observed_link_gap",
      priority_label: "Maillage faible",
      priority_level: "medium" as const,
      reason: `Page indexable mais encore sous-maillée avec ${item.internal_inlinks} lien(s) interne(s).`,
    })),
  ]
    .filter((item, index, items) => items.findIndex((candidate) => candidate.site_id === item.site_id && candidate.slug === item.slug) === index)
    .slice(0, 6);
  const bestPages = [...risingPages]
    .sort((a, b) => b.impressions - a.impressions)
    .slice(0, 6);
  const explicitRefreshPages = publications.items
    .filter(
      (item) =>
        !!item.latest_suggestion ||
        (hasReliableSeoSignal(item) && (item.seo_score ?? 0) < 80) ||
        (!item.published_live && item.status === "published") ||
        ((item.gsc_metrics.position ?? 99) >= 8 && item.gsc_metrics.impressions > 0)
    )
    .slice(0, 6);
  const scoredPages = [...publications.items]
    .sort((a, b) => {
      const aScore = hasReliableSeoSignal(a) ? (a.seo_score ?? 0) + (a.topical_score ?? 0) + (a.quality_score ?? 0) : -1;
      const bScore = hasReliableSeoSignal(b) ? (b.seo_score ?? 0) + (b.topical_score ?? 0) + (b.quality_score ?? 0) : -1;

      return bScore - aScore;
    })
    .filter((item) => hasReliableSeoSignal(item) && ((item.seo_score ?? 0) > 0 || (item.topical_score ?? 0) > 0 || (item.quality_score ?? 0) > 0))
    .slice(0, 6);
  const observedPotentialPages = [
    ...observedPillarPages.map((item) => ({
      id: `${item.site_id}-${item.slug}-pillar`,
      title: item.label,
      site_id: item.site_id,
      seo_score: item.authority_score,
      topical_score: item.pillar_likelihood,
      quality_score: item.internal_outlinks,
      indexability_score: item.indexability_state === "indexable" ? 100 : 40,
      reason: item.cluster_label
        ? `Cette page peut devenir la page principale autour du sujet "${item.cluster_label}".`
        : "Cette page peut devenir un point d appui important sur votre site.",
    })),
    ...observedLinkGapPages.map((item) => ({
      id: `${item.site_id}-${item.slug}-link-gap`,
      title: item.label,
      site_id: item.site_id,
      seo_score: item.authority_score,
      topical_score: item.pillar_likelihood,
      quality_score: item.internal_inlinks,
      indexability_score: item.indexability_state === "indexable" ? 100 : 40,
      reason: `Page déjà utile mais encore sous-maillée avec ${item.internal_inlinks} lien(s) interne(s).`,
    })),
  ]
    .filter((item, index, items) => items.findIndex((candidate) => candidate.site_id === item.site_id && candidate.title === item.title) === index)
    .slice(0, 6);
  const potentialPages =
    observedPotentialPages.length > 0
      ? observedPotentialPages
      : scoredPages.length > 0
        ? scoredPages
      : pagesToWatch
          .filter((item) => item.type === "near_top_10" || item.type === "low_ctr")
          .map((item) => ({
            id: `${item.site_id}-${item.slug}`,
            title: item.label,
            site_id: item.site_id,
            seo_score: null,
            topical_score: null,
            quality_score: null,
            indexability_score: null,
            reason: item.reason,
          }))
          .slice(0, 6);
  const observedRefreshPages = observedWeakPages.map((item) => ({
    id: `${item.site_id}-${item.slug}-weak`,
    title: item.label,
    site_id: item.site_id,
    latest_suggestion: null,
    published_live: item.indexability_state === "indexable",
    gsc_metrics: {
      impressions: 0,
      ctr: 0,
      position: 0,
    },
    seo_score: item.authority_score,
    reason:
      item.indexability_state !== "indexable"
        ? "Google ne confirme pas encore clairement cette page."
        : "La page existe deja, mais elle reste encore trop legere ou trop discrete pour peser davantage.",
  }));
  const refreshPages =
    observedRefreshPages.length > 0
      ? observedRefreshPages
      : explicitRefreshPages.length > 0
        ? explicitRefreshPages
      : pagesToWatch
          .filter(
            (item): item is (typeof optimizations.gsc_opportunities.items)[number] =>
              "metrics" in item && (item.type === "near_top_10" || item.type === "sustained_drop")
          )
          .map((item) => ({
            id: `${item.site_id}-${item.slug}`,
            title: item.label,
            site_id: item.site_id,
            latest_suggestion: null,
            published_live: true,
            gsc_metrics: {
              impressions: Number(item.metrics.impressions ?? 0),
              ctr: Number(item.metrics.ctr ?? 0),
              position: Number(item.metrics.position ?? 0),
            },
            seo_score: null,
            reason: item.reason,
          }))
          .slice(0, 6);
  const observedPagesTotal = dashboard.sites.reduce((sum, site) => sum + site.summary.observed_pages, 0);
  const observedWeakTotal = dashboard.sites.reduce((sum, site) => sum + site.summary.observed_weak_pages, 0);
  const observedOrphanTotal = dashboard.sites.reduce((sum, site) => sum + site.summary.observed_orphan_pages, 0);
  const totalDeltaImpressions = pageSignals.reduce((sum, item) => sum + item.delta_impressions, 0);
  const leadWatchPage = pagesToWatch[0] ?? null;
  const leadPriorityPage = observedPriorityPages[0] ?? null;
  const leadRisingPage = risingPages[0] ?? null;
  const leadBestPage = bestPages[0] ?? null;
  const leadPageSummary = leadWatchPage
    ? {
        title: leadWatchPage.label,
        site_name: leadWatchPage.site_name,
        badge: leadWatchPage.priority_label,
        reason: leadWatchPage.reason,
        whyNow:
          leadWatchPage.type === "near_top_10"
            ? "la page est déjà proche d’un gain visible dans Google"
            : leadWatchPage.type === "low_ctr"
              ? "la page est vue mais sous-cliquée"
              : leadWatchPage.type === "sustained_drop"
                ? "la page perd déjà de la traction"
                : "elle remonte déjà comme une page à garder sous surveillance prioritaire",
      }
    : leadPriorityPage
      ? {
          title: leadPriorityPage.title,
          site_name: leadPriorityPage.site_name,
          badge: leadPriorityPage.badge,
          reason: leadPriorityPage.description,
          whyNow:
            leadPriorityPage.badge === "Orpheline"
              ? "la structure du site la laisse encore trop seule"
              : leadPriorityPage.badge === "Sous-maillée"
                ? "elle a déjà de la valeur mais manque encore de soutien interne"
                : "elle peut devenir un vrai appui SEO si on l’ouvre dans le bon ordre",
        }
      : leadRisingPage
        ? {
            title: leadRisingPage.label,
            site_name: leadRisingPage.site_name,
            badge: "En hausse",
            reason: `La page gagne deja ${leadRisingPage.delta_impressions} impression(s) et commence a remonter dans Google.`,
            whyNow: "la page gagne déjà du terrain et mérite d’être consolidée pendant qu’elle monte",
          }
        : leadBestPage
          ? {
              title: leadBestPage.label,
              site_name: leadBestPage.site_name,
              badge: `${leadBestPage.impressions} impressions`,
              reason: "Cette page est deja visible dans Google et peut encore attirer plus de visites si on la renforce.",
              whyNow: "c’est déjà un appui visible qui peut encore être renforcé",
            }
          : null;
  const leadStructuralPage =
    observedPriorityPages[0] ??
    observedPillarPages[0]
      ? {
          title: observedPriorityPages[0]?.title ?? observedPillarPages[0]?.label ?? "",
          site_name: observedPriorityPages[0]?.site_name ?? observedPillarPages[0]?.site_name ?? "",
          badge: observedPriorityPages[0]?.badge ?? "Pilier potentiel",
          badgeTone: observedPriorityPages[0]?.badgeTone ?? ("success" as const),
          description:
            observedPriorityPages[0]?.description ??
            (observedPillarPages[0]
              ? `Cette page peut devenir la plus forte sur le sujet "${observedPillarPages[0].cluster_label ?? "principal"}".`
              : ""),
        }
      : null;
  const pagesAssistantWhat = leadPageSummary
    ? `${leadPageSummary.title} est la page la plus utile a ouvrir maintenant, car elle montre deja un signal concret dans Google.`
    : risingPages.length > 0
      ? `${risingPages.length} page${risingPages.length > 1 ? "s progressent" : " progresse"} deja dans Google.`
      : `${pagesToWatch.length} page${pagesToWatch.length > 1 ? "s restent" : " reste"} a surveiller meme si la periode reste calme.`;
  const pagesAssistantWhy = leadStructuralPage
    ? `${leadStructuralPage.title} montre aussi un levier structurel : une page à mieux relier, à renforcer, ou qui peut devenir très importante pour son sujet.`
    : observedWeakTotal > 0
      ? `${observedWeakTotal} page(s) meritent encore un refresh ou une consolidation, ce qui peut freiner votre progression.`
      : "Le principal enjeu est de renforcer les pages qui commencent deja a remonter dans Google.";
  const pagesAssistantNext = leadPageSummary
    ? `Commencez par ${leadPageSummary.title}. ${leadPageSummary.whyNow}`
    : "Commencez par la premiere page de la section Pages a surveiller : PraeviSEO y voit le prochain gain le plus clair.";
  const pagesAssistantImpact = leadStructuralPage
    ? `En traitant cette page, vous pouvez renforcer plus vite la structure du site : ${leadStructuralPage.description.toLowerCase()}`
    : "Une page mieux reliee, mieux enrichie ou mieux clarifiee aide Google a comprendre plus vite l'ensemble du site.";
  const siteIdByName = new Map(dashboard.sites.map((site) => [site.name, site.site_id]));
  const studioHref = (
    siteId: string,
    slug: string,
    action: "rewrite" | "image" | "publish" | "preview" | "linking"
  ) =>
    `/publications?focus=content&site=${encodeURIComponent(siteId)}&slug=${encodeURIComponent(slug)}&action=${encodeURIComponent(action)}`;
  const pageActions = (
    siteId: string,
    options?: {
      slug?: string | null;
      preferredAction?: "rewrite" | "image" | "publish" | "preview" | "linking";
      includeSearchConsole?: boolean;
      includeSite?: boolean;
    }
  ) => {
    if (!siteId) {
      return [];
    }

    const actions: Array<{ label: string; href: string; variant?: "primary" | "secondary" }> = [];

    if (options?.slug && options?.preferredAction) {
      actions.push({
        label:
          options.preferredAction === "rewrite"
            ? "Ouvrir la réécriture"
            : options.preferredAction === "image"
              ? "Ouvrir l’image SEO"
              : options.preferredAction === "publish"
                ? "Ouvrir la publication"
                : options.preferredAction === "preview"
                  ? "Ouvrir la preview"
                  : "Ouvrir le maillage",
        href: studioHref(siteId, options.slug, options.preferredAction),
        variant: "primary",
      });
    } else {
      actions.push({
        label: "Ouvrir l’automatisation",
        href: `/sites/${siteId}/automation`,
        variant: "primary",
      });
    }

    if (options?.includeSearchConsole) {
      actions.push({
        label: "Voir l’indexation",
        href: `/sites/${siteId}/search-console`,
        variant: "secondary",
      });
    }

    if (options?.includeSite) {
      actions.push({
        label: "Ouvrir le site",
        href: getSitePath(siteId),
        variant: "secondary",
      });
    }

    return actions;
  };
  const focusMessage =
    focus === "refresh"
      ? {
          title: "Page à retravailler en priorité",
          detail: `${focusTarget || "Cette page"} est la cible ouverte depuis une opportunité de refresh. Commencez par la section Pages à refresh ou Pages à surveiller.`,
          href: focusSite && focusTarget
            ? `/publications?focus=content&site=${encodeURIComponent(focusSite)}&slug=${encodeURIComponent(focusTarget)}&action=rewrite`
            : "#refresh",
        }
      : focus === "linking"
        ? {
          title: "Page à mieux relier maintenant",
          detail: `${focusTarget || "Cette page"} a été ouverte comme cible de maillage. Commencez par les priorités structurelles et les pages importantes encore trop peu reliées.`,
          href: focusSite && focusTarget
            ? `/publications?focus=content&site=${encodeURIComponent(focusSite)}&slug=${encodeURIComponent(focusTarget)}&action=linking`
            : "#priorites",
        }
        : focus === "query"
          ? {
              title: "Page cible à vérifier pour cette requête",
              detail: `${focusTarget || "La cible sélectionnée"} vient d’une requête ou d’une optimisation. Vérifiez d’abord si la bonne page existe déjà et si elle mérite un refresh ou un meilleur maillage.`,
              href: "#surveiller",
            }
          : null;

  return (
    <div className="min-h-screen">
      <Topbar
        title="Pages"
        subtitle="Les pages à travailler maintenant : celles qui montent, chutent, stagnent ou méritent un enrichissement."
        actions={
          <div className="flex items-center gap-2">
            <Button href="/optimizations" size="sm">
              Voir les actions
            </Button>
            <Button href="/dashboard" variant="secondary" size="sm">
              Retour au dashboard
            </Button>
          </div>
        }
      />

      <div className="p-6 space-y-6">
        <CockpitSectionNav
          items={[
            { label: "Vue d’ensemble", href: "#vue-ensemble", count: pageSignals.length, tone: "default" },
            { label: "Pages qui montent", href: "#montent", count: risingPages.length, tone: "success" },
            { label: "Pages qui chutent", href: "#chutent", count: fallingPages.length, tone: "warning" },
            { label: "Meilleures pages", href: "#meilleures", count: bestPages.length, tone: "secondary" },
            { label: "Priorités structurelles", href: "#priorites", count: observedPriorityPages.length, tone: "secondary" },
            { label: "Potentiel SEO", href: "#potentiel", count: potentialPages.length, tone: "secondary" },
            { label: "À refresh", href: "#refresh", count: refreshPages.length, tone: "warning" },
            { label: "À surveiller", href: "#surveiller", count: pagesToWatch.length, tone: "danger" },
          ]}
        />

        <div className="rounded-2xl border border-brand/20 bg-brand-muted px-6 py-6">
          <h1 className="text-2xl font-bold tracking-tight text-text">Quelles pages méritent d’être travaillées maintenant</h1>
          <p className="mt-2 max-w-3xl text-sm leading-7 text-text-muted">
            Cette page sert à lire le site page par page : quelles pages montent, lesquelles chutent, lesquelles sont proches d’un gain et lesquelles doivent être enrichies.
          </p>
          <div className="mt-4 flex flex-wrap gap-3">
            <Button href="/optimizations">
              Travailler les pages
            </Button>
            <Button href="/queries" variant="secondary">
              Voir les requêtes liées
            </Button>
          </div>
          {(freshestSyncAt || freshestDataAsOf) && (
            <p className="mt-3 text-xs text-text-subtle">
              {freshestSyncAt ? `Dernière synchro GSC : ${formatDate(freshestSyncAt)}.` : "Synchronisation GSC en attente."}{" "}
              {freshestDataAsOf ? `Données arrêtées au ${formatDate(freshestDataAsOf)}.` : ""}
            </p>
          )}
          <p className="mt-3 max-w-3xl text-xs leading-6 text-text-subtle">
            Cette vue compare les pages relues sur une fenêtre GSC récente de 28 jours. Sur un petit site, il est
            normal que seules quelques pages remontent fortement d’une période à l’autre.
          </p>
        </div>

        {focusMessage ? (
          <div className="rounded-2xl border border-brand/20 bg-brand-muted px-5 py-4">
            <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
              <div>
                <div className="text-sm font-semibold text-text">{focusMessage.title}</div>
                <p className="mt-2 text-sm leading-6 text-text-muted">
                  {focusMessage.detail}
                  {focusSite ? ` Site ciblé : ${focusSite}.` : ""}
                </p>
              </div>
              <Button href={focusMessage.href} size="sm">
                Ouvrir la bonne section
              </Button>
            </div>
          </div>
        ) : null}

        <div className="rounded-xl border border-border bg-surface p-5">
          <p className="text-xs text-text-subtle">Lecture du moment</p>
          <p className="mt-3 text-lg font-semibold text-text">
            {risingPages.length > 0
              ? `${risingPages.length} page${risingPages.length > 1 ? "s" : ""} gagne${risingPages.length > 1 ? "nt" : ""} déjà du terrain dans Google.`
              : pagesToWatch.length > 0
                ? `${pagesToWatch.length} page${pagesToWatch.length > 1 ? "s" : ""} mérite${pagesToWatch.length > 1 ? "nt" : ""} déjà une surveillance prioritaire.`
                : "PraeviSEO garde ici une lecture calme mais utile des pages, en attendant plus de matière côté Google."}
          </p>
          <div className="mt-4 space-y-3 text-sm text-text-muted">
            <p>
              {bestPages.length > 0
                ? `${bestPages.length} page${bestPages.length > 1 ? "s" : ""} ressort${bestPages.length > 1 ? "ent" : ""} déjà comme vos appuis les plus lisibles dans Google.`
                : "Le cockpit attend encore que davantage de pages accumulent assez d’impressions pour devenir des appuis clairs."}
            </p>
            <p>
              {refreshPages.length > 0
                ? `${refreshPages.length} piste${refreshPages.length > 1 ? "s" : ""} de refresh ou de consolidation sont déjà identifiées.`
                : "Aucune relance éditoriale forte pour le moment : cela peut simplement vouloir dire que Google remonte encore peu de signaux exploitables."}
            </p>
            <p>
              {observedPagesTotal > 0
                ? `${observedPagesTotal} page${observedPagesTotal > 1 ? "s" : ""} deja relue${observedPagesTotal > 1 ? "s" : ""} permettent de voir quelles pages sont solides, discretes ou encore fragiles.`
                : "PraeviSEO enrichira encore cette vue dès qu’il aura observé plus de pages directement sur le site."}
            </p>
            <p>
              {observedPriorityPages.length > 0
                ? `${observedPriorityPages.length} page${observedPriorityPages.length > 1 ? "s demandent" : " demande"} deja une action claire : mieux les relier, les renforcer ou les clarifier.`
                : "Aucune priorité structurelle forte n’apparaît encore : la lecture reste surtout portée par Google Search Console pour le moment."}
            </p>
          </div>
        </div>

        <CockpitAssistantGuide
          title="PraeviSEO vous explique quoi faire sur vos pages"
          description="Cette vue ne montre qu’une chose : quelles pages méritent une action, pourquoi elles comptent et ce que vous débloquez en les travaillant."
          whatText={pagesAssistantWhat}
          whyText={pagesAssistantWhy}
          nextText={pagesAssistantNext}
          impactText={pagesAssistantImpact}
        />

        <div id="vue-ensemble" className="scroll-mt-24">
          <CockpitMetricGrid
            items={[
              { label: "Pages suivies", value: observedPagesTotal > 0 ? observedPagesTotal : pageSignals.length },
              { label: "Pages en hausse", value: risingPages.length, tone: "success" },
              { label: "Pages en baisse", value: fallingPages.length, tone: "danger" },
              { label: "Pages à fort potentiel", value: potentialPages.length, tone: potentialPages.length > 0 ? "secondary" : "warning" },
              { label: "Pistes de refresh", value: observedWeakTotal > 0 ? observedWeakTotal : refreshPages.length, tone: (observedWeakTotal > 0 || refreshPages.length > 0) ? "warning" : "secondary" },
              {
                label: "Pages sous-maillées",
                value: observedLinkGapPages.length > 0 ? observedLinkGapPages.length : observedOrphanTotal,
                tone: observedLinkGapPages.length > 0 || observedOrphanTotal > 0 ? "warning" : "secondary",
              },
              {
                label: "Pages trop isolees",
                value: observedOrphanTotal > 0 ? observedOrphanTotal : `${totalDeltaImpressions > 0 ? "+" : ""}${new Intl.NumberFormat("fr-FR").format(totalDeltaImpressions)}`,
                tone: observedOrphanTotal > 0 ? "danger" : totalDeltaImpressions < 0 ? "danger" : totalDeltaImpressions > 0 ? "success" : "secondary",
              },
            ]}
          />
        </div>

        <div className="grid gap-6 xl:grid-cols-2">
          <CockpitSignalListCard
            title="Quelle page traiter d’abord"
            description="La page qui donne déjà le signal le plus utile à ouvrir maintenant."
            empty={!leadPageSummary}
            emptyMessage="Aucune page n’émerge encore assez clairement pour devenir la priorité numéro un."
          >
            {leadPageSummary ? (
              <div className="rounded-xl border border-border p-4 space-y-3">
                <div className="flex flex-wrap items-center justify-between gap-3">
                  <div>
                    <p className="text-sm font-semibold text-text">{leadPageSummary.title}</p>
                    <p className="text-xs text-text-subtle">{leadPageSummary.site_name}</p>
                  </div>
                  <div className="flex flex-wrap gap-2">
                    <span className="inline-flex rounded-full border border-border bg-surface-2 px-3 py-1 text-xs text-text">
                      {leadPageSummary.badge}
                    </span>
                  </div>
                </div>
                <p className="text-sm text-text-muted">{leadPageSummary.reason}</p>
                <p className="text-sm text-text">
                  Pourquoi maintenant :{" "}
                  <span className="font-medium">{leadPageSummary.whyNow}</span>
                </p>
                <div className="flex flex-wrap gap-2">
                  {pageActions(siteIdByName.get(leadPageSummary.site_name) ?? "", { includeSearchConsole: true }).map((action) => (
                    <Button key={`${leadPageSummary.title}-${action.label}`} href={action.href} size="sm" variant={action.variant ?? "secondary"}>
                      {action.label}
                    </Button>
                  ))}
                </div>
              </div>
            ) : null}
          </CockpitSignalListCard>

          <CockpitSignalListCard
            title="Levier structurel débloqué"
            description="Ce que PraeviSEO peut améliorer au niveau structurel si on traite la bonne page."
            empty={!leadStructuralPage}
            emptyMessage="Aucun levier structurel fort n’émerge encore : la lecture reste surtout portée par Google Search Console."
          >
            {leadStructuralPage ? (
              <div className="rounded-xl border border-border p-4 space-y-3">
                <div className="flex flex-wrap items-center justify-between gap-3">
                  <div>
                    <p className="text-sm font-semibold text-text">{leadStructuralPage.title}</p>
                    <p className="text-xs text-text-subtle">{leadStructuralPage.site_name}</p>
                  </div>
                  <span className="inline-flex rounded-full border border-border bg-surface-2 px-3 py-1 text-xs text-text">
                    {leadStructuralPage.badge}
                  </span>
                </div>
                <p className="text-sm text-text-muted">{leadStructuralPage.description}</p>
                <p className="text-sm text-text">
                  Ce que ça débloque :{" "}
                  <span className="font-medium">
                    {leadStructuralPage.badge === "Pilier potentiel"
                      ? "une page plus forte pour porter un sujet entier"
                      : leadStructuralPage.badge === "Sous-maillée"
                        ? "un meilleur soutien depuis le reste du site"
                        : leadStructuralPage.badge === "Orpheline"
                          ? "une page enfin reliée à la structure réelle du site"
                          : "une base plus saine avant les prochaines optimisations éditoriales"}
                  </span>
                </p>
              </div>
            ) : null}
          </CockpitSignalListCard>
        </div>

        <div id="priorites" className="grid gap-6 xl:grid-cols-2 scroll-mt-24">
          <CockpitSignalListCard
            title="Priorités structurelles"
            description="Les pages que PraeviSEO priorise deja pour mieux organiser le site, renforcer les pages centrales et sortir les pages trop isolees."
            empty={observedPriorityPages.length === 0}
            emptyMessage="Aucune priorité structurelle forte pour le moment. Le cockpit reviendra ici dès que le moteur détecte un vrai besoin de fond."
          >
            {observedPriorityPages.map((item) => (
              <CockpitSignalItem
                key={item.id}
                title={item.title}
                subtitle={item.site_name}
                badge={item.badge}
                badgeTone={item.badgeTone}
                description={item.description}
                actions={pageActions(item.site_id, {
                  slug: item.slug,
                  preferredAction:
                    item.badge === "Sous-maillée" || item.badge === "Orpheline"
                      ? "linking"
                      : "rewrite",
                  includeSite: true,
                })}
              />
            ))}
          </CockpitSignalListCard>

          <CockpitSignalListCard
            title="Pages importantes et encore trop peu reliées"
            description="Les pages qui peuvent devenir tres importantes, et celles qui manquent encore de soutien depuis le reste du site."
            empty={observedPillarPages.length + observedLinkGapPages.length === 0}
            emptyMessage="Aucune page très importante ou encore trop peu reliée ne ressort fortement pour le moment."
          >
            {[...observedPillarPages.slice(0, 3), ...observedLinkGapPages.slice(0, 3)].map((item) => (
              <CockpitSignalItem
                key={`${item.site_id}-${item.slug}-pillar-gap`}
                title={item.label}
                subtitle={item.site_name}
                badge={observedPillarPages.some((candidate) => candidate.site_id === item.site_id && candidate.slug === item.slug) ? "Pilier" : "Maillage"}
                badgeTone={observedPillarPages.some((candidate) => candidate.site_id === item.site_id && candidate.slug === item.slug) ? "success" : "warning"}
                description={
                  observedPillarPages.some((candidate) => candidate.site_id === item.site_id && candidate.slug === item.slug)
                    ? `Cette page peut devenir centrale sur son sujet. Signal structurel observé ${item.authority_score}, sujet "${item.cluster_label ?? "principal"}".`
                    : `Seulement ${item.internal_inlinks} lien(s) reçus pour une page déjà utile. Signal structurel actuel ${item.authority_score}.`
                }
                actions={pageActions(item.site_id, {
                  slug: item.slug,
                  preferredAction:
                    observedPillarPages.some((candidate) => candidate.site_id === item.site_id && candidate.slug === item.slug)
                      ? "rewrite"
                      : "linking",
                  includeSite: true,
                })}
              />
            ))}
          </CockpitSignalListCard>
        </div>

        <div id="montent" className="grid gap-6 xl:grid-cols-2 scroll-mt-24">
          <CockpitSignalListCard
            title="Pages qui montent"
            description="Les pages qui gagnent le plus de visibilité récemment dans Google."
            empty={risingPages.length === 0}
            emptyMessage="Aucune hausse forte n’est détectée pour le moment. Sur un petit site, plusieurs lectures peuvent rester stables avant la prochaine progression nette."
          >
            {risingPages.map((item) => (
              <CockpitSignalItem
                key={`${item.site_name}-${item.slug}-up`}
                title={item.label}
                subtitle={item.site_name}
                badge="En hausse"
                badgeTone="success"
                description={`+${item.delta_impressions} affichage(s) dans Google, avec une présence moyenne autour de la ${Math.round(item.position)}e place.`}
                actions={pageActions(siteIdByName.get(item.site_name) ?? "", {
                  slug: item.slug,
                  preferredAction: "preview",
                  includeSearchConsole: true,
                })}
              />
            ))}
          </CockpitSignalListCard>

          <CockpitSignalListCard
            id="chutent"
            className="scroll-mt-24"
            title="Pages qui chutent"
            description="Les pages qui méritent une relance, un refresh ou une meilleure réponse SEO."
            empty={fallingPages.length === 0}
            emptyMessage="Aucune chute nette pour le moment. C’est sain : le cockpit surveille déjà les prochaines baisses vraiment utiles à traiter."
          >
            {fallingPages.map((item) => (
              <CockpitSignalItem
                key={`${item.site_name}-${item.slug}-down`}
                title={item.label}
                subtitle={item.site_name}
                badge="En baisse"
                badgeTone="danger"
                description={`${Math.abs(item.delta_impressions)} affichage(s) de moins dans Google, avec une présence moyenne autour de la ${Math.round(item.position)}e place.`}
                actions={pageActions(siteIdByName.get(item.site_name) ?? "", {
                  slug: item.slug,
                  preferredAction: "rewrite",
                  includeSearchConsole: true,
                })}
              />
            ))}
          </CockpitSignalListCard>
        </div>

        <div id="meilleures" className="grid gap-6 xl:grid-cols-2 scroll-mt-24">
          <CockpitSignalListCard
            title="Meilleures pages"
            description="Les pages déjà visibles qui portent le plus votre présence SEO."
            empty={bestPages.length === 0}
            emptyMessage="Les meilleures pages apparaîtront ici dès que davantage de signaux GSC remonteront. Avec peu de volume, il est normal que cette lecture prenne un peu de temps."
          >
            {bestPages.map((item) => (
              <CockpitSignalItem
                key={`${item.site_name}-${item.slug}-best`}
                title={item.label}
                subtitle={item.site_name}
                badge={item.impressions > 0 ? `${item.impressions} impressions` : "Signal léger"}
                description={`${item.previous_impressions} affichage(s) observés auparavant, avec une présence moyenne autour de la ${Math.round(item.position)}e place.`}
                actions={pageActions(siteIdByName.get(item.site_name) ?? "", {
                  slug: item.slug,
                  preferredAction: "preview",
                  includeSite: true,
                })}
              />
            ))}
          </CockpitSignalListCard>

          <CockpitSignalListCard
            id="potentiel"
            className="scroll-mt-24"
            title="Pages à fort potentiel SEO"
            description={
              scoredPages.length > 0
                ? "Les pages déjà solides côté contenu ou qualité, donc les plus prometteuses à pousser ensuite."
                : "Même quand le scoring détaillé est encore léger, PraeviSEO garde ici les pages à consolider en priorité."
            }
            empty={potentialPages.length === 0}
            emptyMessage="Aucune page à fort potentiel pour le moment. PraeviSEO les affichera dès qu’une page combine assez de signaux pour mériter une vraie poussée."
          >
            {potentialPages.map((item) => (
              <CockpitSignalItem
                key={`${item.site_id}-${item.title}-score`}
                title={item.title}
                subtitle={item.site_id}
                badge={seoSignalLabel(item)}
                badgeTone={hasReliableSeoSignal(item) ? ((item.seo_score ?? 0) >= 80 ? "success" : "secondary") : "warning"}
                description={
                  hasReliableSeoSignal(item)
                    ? "Cette page a deja de bonnes bases et peut encore devenir plus forte sur son sujet."
                    : "Cette page montre déjà un signal utile dans Google et mérite une consolidation éditoriale."
                }
                actions={pageActions(item.site_id, {
                  slug: "slug" in item ? item.slug : null,
                  preferredAction: "rewrite",
                  includeSite: true,
                })}
              />
            ))}
          </CockpitSignalListCard>
        </div>

        <div id="refresh" className="grid gap-6 xl:grid-cols-2 scroll-mt-24">
          <CockpitSignalListCard
            id="refresh"
            title="Pages à refresh"
            description="Les contenus qui méritent une relance éditoriale, un enrichissement ou une meilleure exécution."
            empty={refreshPages.length === 0}
            emptyMessage="Aucune piste de refresh pour le moment. Cela peut simplement signifier que Google ne remonte pas encore assez de matière pour justifier une relance nette."
          >
            {refreshPages.map((item) => (
              <CockpitSignalItem
                key={`${item.site_id}-${item.title}-refresh`}
                title={item.title}
                subtitle={item.site_id}
                badge={
                  item.latest_suggestion
                    ? "refresh conseillé"
                    : hasReliableSeoSignal(item)
                      ? item.published_live
                        ? "à consolider"
                        : "à pousser"
                      : "signal à confirmer"
                }
                badgeTone={item.latest_suggestion ? "warning" : hasReliableSeoSignal(item) ? (item.published_live ? "secondary" : "success") : "secondary"}
                description={
                  item.latest_suggestion?.summary ??
                  ("reason" in item && typeof item.reason === "string" && item.reason.length > 0
                    ? item.reason
                    : `${item.gsc_metrics.impressions} impression(s) montrent deja un signal utile dans Google. Cette page peut encore etre renforcee.`
                  )
                }
                actions={pageActions(item.site_id, {
                  slug: "slug" in item ? item.slug : null,
                  preferredAction: "rewrite",
                  includeSite: true,
                })}
              />
            ))}
          </CockpitSignalListCard>

          <CockpitSignalListCard
            id="surveiller"
            title="Pages à surveiller"
            description="Les pages où PraeviSEO voit déjà un potentiel SEO ou un signal à traiter vite."
            empty={pagesToWatch.length === 0}
            emptyMessage="Aucune page chaude pour le moment. Les prochaines opportunités viendront enrichir ce bloc dès qu’un signal dépasse le bruit naturel du site."
          >
            {pagesToWatch.map((item) => (
              <CockpitSignalItem
                key={`${item.site_id}-${item.slug}-${item.type}`}
                title={item.label}
                subtitle={item.site_name}
                badge={item.priority_label}
                badgeTone={item.priority_level === "high" ? "warning" : item.type === "sustained_drop" ? "danger" : "secondary"}
                description={item.reason}
                actions={pageActions(item.site_id, {
                  slug: item.slug,
                  preferredAction:
                    item.type === "observed_orphan" || item.type === "observed_link_gap"
                      ? "linking"
                      : item.type === "low_ctr"
                        ? "preview"
                        : "rewrite",
                  includeSearchConsole: true,
                  includeSite: true,
                })}
              />
            ))}
          </CockpitSignalListCard>
        </div>
      </div>
    </div>
  );
}
