import { Topbar } from "@/components/layout/topbar";
import { CockpitSectionNav } from "@/components/cockpit/section-nav";
import { CockpitAssistantGuide } from "@/components/cockpit/assistant-guide";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import {
  getClientOpportunityStateLabel,
  getClientOpportunityWhy,
  getClientQueryBadge,
  getClientRecommendationBadge,
  getClientRecommendationText,
  getClientRecommendationTitle,
  getPraeviseoActivationLabel,
  getPraeviseoClientDetail,
  getPraeviseoClientStatus,
  getDashboard,
  getOptimizations,
  getPublications,
  getSiteConnectPath,
  getSitePath,
  hasBackendConnection,
} from "@/lib/praeviseo-api";
import { formatDate } from "@/lib/utils";
import { ArrowRight, CheckCircle2, Globe, SearchCheck, Sparkles, Waves } from "lucide-react";

export default async function DashboardPage() {
  const dashboard = await getDashboard();
  const optimizations = await getOptimizations();
  const publications = await getPublications();
  const normalizeQuery = (value: string) => value.trim().toLowerCase();
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
  const backendLive = hasBackendConnection();
  const gscConnectedSites = dashboard.sites.filter((site) => site.readiness.gsc_connected).length;
  const observedQueryMatches = publications.items
    .filter(
      (item) =>
        !!item.observed_content &&
        (!!item.observed_content.top_query_match || (item.observed_content.query_match_count ?? 0) > 0)
    )
    .map((item) => ({
      ...item,
      normalizedTopQueryMatch: item.observed_content?.top_query_match
        ? normalizeQuery(item.observed_content.top_query_match)
        : null,
    }));
  const findLinkedPublication = (query: string, siteId?: string | null) => {
    const normalizedQuery = normalizeQuery(query);

    return (
      observedQueryMatches.find(
        (item) => item.site_id === siteId && item.normalizedTopQueryMatch === normalizedQuery
      ) ??
      observedQueryMatches.find((item) => item.normalizedTopQueryMatch === normalizedQuery) ??
      null
    );
  };
  const activeAlerts =
    optimizations.gsc_opportunities.summary.low_ctr + optimizations.gsc_opportunities.summary.sustained_drop;
  const prioritySites = dashboard.sites
    .filter((site) => site.next_action.priority !== "low")
    .slice(0, 3);
  const recentPublications = publications.items.slice(0, 3);
  const recentOptimizations = optimizations.items.slice(0, 3);
  const topOpportunities = optimizations.gsc_opportunities.items.slice(0, 4);
  const actionPlan = optimizations.recommendations.items.slice(0, 4);
  const freePriorityFeed = [
    ...actionPlan.map((item) => ({
      id: `recommendation-${item.id}`,
      title: getClientRecommendationTitle(item.title),
      description: getClientRecommendationText(item.suggested_action ?? item.reasoning),
      tone: item.priority <= 30 ? ("warning" as const) : ("secondary" as const),
      badge: getClientRecommendationBadge(item.priority),
      siteLabel: `${item.site_id}${item.cluster ? ` · ${item.cluster}` : ""}`,
    })),
    ...topOpportunities.map((item) => ({
      id: `opportunity-${item.site_id}-${item.slug}-${item.type}`,
      title: item.label,
      description: `${getClientOpportunityWhy(item.type)} ${item.action}`,
      tone: item.priority_level === "high" ? ("warning" as const) : ("secondary" as const),
      badge: getClientOpportunityStateLabel(item.action_state_label),
      siteLabel: item.site_name,
    })),
  ].slice(0, 4);
  const pageWatchlist = dashboard.sites
    .flatMap((site) => [
      ...site.summary.top_rising_pages.map((item) => ({ ...item, site_name: site.name, trend: "up" as const })),
      ...site.summary.top_falling_pages.map((item) => ({ ...item, site_name: site.name, trend: "down" as const })),
    ])
    .slice(0, 6);
  const queryWatchlist = dashboard.sites
    .flatMap((site) => site.summary.top_queries.map((item) => ({ ...item, site_id: site.site_id, site_name: site.name })))
    .slice(0, 6);
  const risingQueryWatchlist = dashboard.sites
    .flatMap((site) => site.summary.top_rising_queries.map((item) => ({ ...item, site_id: site.site_id, site_name: site.name })))
    .slice(0, 6);
  const newQueryWatchlist = dashboard.sites
    .flatMap((site) => site.summary.new_queries.map((item) => ({ ...item, site_id: site.site_id, site_name: site.name })))
    .slice(0, 6);
  const linkedQueryWatchlist = queryWatchlist
    .map((item) => ({
      ...item,
      linkedPublication: findLinkedPublication(item.query, item.site_id),
    }))
    .filter((item) => !!item.linkedPublication)
    .slice(0, 6);
  const dashboardQuerySignals = [
    ...linkedQueryWatchlist,
    ...queryWatchlist
      .filter((item) => !findLinkedPublication(item.query, item.site_id))
      .map((item) => ({
        ...item,
        linkedPublication: null,
      })),
  ].slice(0, 6);
  const totalDeltaImpressions = dashboard.sites.reduce((sum, site) => sum + site.summary.gsc_delta_impressions, 0);
  const totalDeltaClicks = dashboard.sites.reduce((sum, site) => sum + site.summary.gsc_delta_clicks, 0);
  const risingSitesCount = dashboard.sites.filter((site) => site.summary.gsc_delta_impressions > 0).length;
  const slippingSitesCount = dashboard.sites.filter((site) => site.summary.gsc_delta_impressions < 0).length;
  const indexationAlerts = dashboard.sites
    .flatMap((site) => site.summary.indexation_alerts.map((item) => ({ ...item, site_name: site.name, site_id: site.site_id })))
    .slice(0, 6);
  const contentRefreshFeed = publications.items
    .filter((item) => !!item.latest_suggestion)
    .slice(0, 4);
  const totalObservedCrawlIssues = dashboard.sites.reduce((sum, site) => sum + site.summary.observed_crawl_issues, 0);
  const healthTrackedSites = dashboard.sites.filter((site) => site.summary.observed_site_health_score > 0);
  const averageObservedHealth =
    healthTrackedSites.length > 0
      ? Math.round(
          healthTrackedSites.reduce((sum, site) => sum + site.summary.observed_site_health_score, 0) / healthTrackedSites.length
        )
      : 0;
  const assistantNextStep = freePriorityFeed[0];
  const dashboardAssistantWhat =
    totalDeltaImpressions > 0
      ? `Votre visibilité remonte avec ${new Intl.NumberFormat("fr-FR").format(totalDeltaImpressions)} impression(s) supplémentaires sur la période suivie.`
      : topOpportunities.length > 0
        ? `${topOpportunities.length} opportunité${topOpportunities.length > 1 ? "s sont" : " est"} déjà visible${topOpportunities.length > 1 ? "s" : ""} dans Google.`
        : "PraeviSEO continue de surveiller votre visibilité Google, même quand la période reste calme.";
  const dashboardAssistantWhy =
    totalObservedCrawlIssues > 0
      ? `${new Intl.NumberFormat("fr-FR").format(totalObservedCrawlIssues)} point(s) de structure ou de lecture Google restent à surveiller, ce qui peut freiner votre progression.`
      : linkedQueryWatchlist.length > 0
        ? `${linkedQueryWatchlist.length} recherche${linkedQueryWatchlist.length > 1 ? "s sont" : " est"} déjà reliée${linkedQueryWatchlist.length > 1 ? "s" : ""} à une bonne page, ce qui clarifie mieux où agir.`
        : "Le principal enjeu est maintenant de renforcer les pages et contenus qui ont déjà commencé à bouger dans Google.";
  const dashboardAssistantNext =
    assistantNextStep?.description
      ? `${assistantNextStep.title}. ${assistantNextStep.description}`
      : "Ouvrez d’abord la priorité en haut du cockpit : c’est là que PraeviSEO voit le meilleur gain concret à court terme.";
  const dashboardAssistantImpact =
    averageObservedHealth > 0
      ? `Votre site présente aujourd’hui une santé SEO observée de ${averageObservedHealth}/100. En traitant les priorités du moment, vous aidez Google à mieux comprendre et mieux faire remonter votre site.`
      : "Chaque action bien traitée aide PraeviSEO à repérer ensuite plus clairement ce qui progresse réellement dans Google.";
  const healthWatchlist = dashboard.sites
    .filter(
      (site) =>
        site.summary.observed_crawl_issues > 0 ||
        site.summary.observed_weak_pages > 0 ||
        site.summary.observed_orphan_pages > 0
    )
    .slice(0, 6);
  const progressMoments = [
    totalDeltaImpressions > 0
      ? `La visibilité remonte avec ${new Intl.NumberFormat("fr-FR").format(totalDeltaImpressions)} impression(s) supplémentaires sur la période.`
      : totalDeltaImpressions < 0
        ? `La visibilité recule de ${new Intl.NumberFormat("fr-FR").format(Math.abs(totalDeltaImpressions))} impression(s) sur la période suivie.`
        : "La visibilité reste stable sur la dernière lecture GSC.",
    linkedQueryWatchlist.length > 0
      ? `${linkedQueryWatchlist.length} requête(s) sont déjà reliée(s) à une page observée, ce qui clarifie où agir.`
      : "PraeviSEO attend encore le prochain lien net entre requête et page pour ouvrir une cible éditoriale plus claire.",
    actionPlan.length > 0
      ? `${actionPlan.length} action(s) moteur sont déjà prêtes à être traitées en priorité.`
      : "Le moteur continue de préparer les prochains plans d’action utiles.",
    contentRefreshFeed.length > 0
      ? `${contentRefreshFeed.length} contenu(s) méritent déjà un refresh ou un enrichissement.`
      : "Aucun refresh chaud n’est remonté pour le moment sur les contenus suivis.",
  ];
  const activityFeed = [
    ...optimizations.gsc_opportunities.items.slice(0, 3).map((item) => ({
      id: `opportunity-${item.site_id}-${item.slug}-${item.type}`,
      title: item.label,
      detail: item.reason,
      badge: item.priority_label,
      badgeVariant: item.priority_level === "high" ? "warning" : "secondary",
      meta: `${item.site_name} · ${item.action}`,
    })),
    ...recentOptimizations.slice(0, 2).map((item) => ({
      id: `suggestion-${item.id}`,
      title: item.page.title,
      detail: item.summary,
      badge: item.status,
      badgeVariant: item.status === "pending" ? "warning" : "secondary",
      meta: `${item.page.site_id} · recommandation`,
    })),
    ...recentPublications.slice(0, 2).map((item) => ({
      id: `publication-${item.id}`,
      title: item.title,
      detail: item.published_live
        ? "Le contenu est déjà visible sur le site."
        : "Le contenu reste prêt côté PraeviSEO en attendant la publication live.",
      badge: item.published_live ? "visible" : "préparé",
      badgeVariant: item.published_live ? "success" : "secondary",
      meta: `${item.site_id} · activité contenu`,
    })),
    ...contentRefreshFeed.slice(0, 2).map((item) => ({
      id: `content-refresh-${item.id}`,
      title: item.title,
      detail: item.latest_suggestion?.summary ?? "Un refresh éditorial est recommandé.",
      badge: "Refresh",
      badgeVariant: "warning" as const,
      meta: `${item.site_id} · contenu`,
    })),
    ...healthWatchlist.slice(0, 2).map((site) => ({
      id: `health-${site.site_id}`,
      title: site.name,
      detail:
        site.summary.observed_crawl_issues > 0
          ? `${site.summary.observed_crawl_issues} issue(s) crawl observée(s), ${site.summary.observed_weak_pages} page(s) faible(s), ${site.summary.observed_orphan_pages} page(s) orpheline(s).`
          : `${site.summary.observed_weak_pages} page(s) faible(s) et ${site.summary.observed_orphan_pages} page(s) orpheline(s) déjà observées.`,
      badge: "Santé SEO",
      badgeVariant: "secondary" as const,
      meta: site.summary.observed_snapshot_date ? `snapshot du ${formatDate(site.summary.observed_snapshot_date)}` : "observation récente",
    })),
  ].slice(0, 6);
  const timelineFeed = [
    ...dashboard.sites
      .filter((site) => site.gsc_last_sync_at)
      .map((site) => ({
        id: `sync-${site.site_id}`,
        title: `${site.name} relu par Google`,
        detail:
          site.summary.gsc_delta_impressions > 0
            ? `+${new Intl.NumberFormat("fr-FR").format(site.summary.gsc_delta_impressions)} impressions sur la dernière période suivie.`
            : site.summary.gsc_delta_impressions < 0
              ? `${new Intl.NumberFormat("fr-FR").format(site.summary.gsc_delta_impressions)} impressions sur la dernière période suivie.`
              : "Volume d’impressions stable sur la dernière période suivie.",
        badge: "Import GSC",
        badgeVariant:
          site.summary.gsc_delta_impressions < 0 ? "danger" : site.summary.gsc_delta_impressions > 0 ? "success" : "secondary",
        meta: formatDate(site.gsc_last_sync_at as string),
        timestamp: new Date(site.gsc_last_sync_at as string).getTime(),
      })),
    ...recentOptimizations.map((item) => ({
      id: `timeline-optimization-${item.id}`,
      title: item.page.title,
      detail: item.summary,
      badge: item.status === "pending" ? "Reco ouverte" : "Reco suivie",
      badgeVariant: item.status === "pending" ? "warning" : "secondary",
      meta: item.created_at ? formatDate(item.created_at) : "Récemment",
      timestamp: item.created_at ? new Date(item.created_at).getTime() : 0,
    })),
    ...recentPublications.map((item) => ({
      id: `timeline-publication-${item.id}`,
      title: item.title,
      detail: item.published_live
        ? "Le contenu est déjà visible sur le site."
        : "Le contenu reste prêt côté PraeviSEO.",
      badge: item.published_live ? "Visible" : "Préparé",
      badgeVariant: item.published_live ? "success" : "secondary",
      meta: item.published_at ? formatDate(item.published_at) : "Récemment",
      timestamp: item.published_at ? new Date(item.published_at).getTime() : 0,
    })),
    ...risingQueryWatchlist.map((item) => ({
      id: `timeline-query-${item.site_name}-${item.query}`,
      title: item.query,
      detail: `+${item.delta_impressions} impressions, CTR ${item.ctr.toFixed(1)} %, position ${item.position.toFixed(1)}.`,
      badge: "Requête en hausse",
      badgeVariant: "success" as const,
      meta: `${item.site_name} · données Google`,
      timestamp: 0,
    })),
    ...indexationAlerts.map((item) => ({
      id: `timeline-index-${item.site_id}-${item.slug}`,
      title: item.label,
      detail: item.detail,
      badge: "Indexation",
      badgeVariant: "warning" as const,
      meta: `${item.site_name} · point à vérifier dans Google`,
      timestamp: 0,
    })),
  ]
    .sort((a, b) => b.timestamp - a.timestamp)
    .slice(0, 6);
  const insightSignals = [
    optimizations.gsc_opportunities.summary.near_top_10 > 0
      ? `${optimizations.gsc_opportunities.summary.near_top_10} page(s) approchent du top 10`
      : null,
    optimizations.gsc_opportunities.summary.low_ctr > 0
      ? `${optimizations.gsc_opportunities.summary.low_ctr} page(s) ont un CTR a relancer`
      : null,
    optimizations.gsc_opportunities.summary.emerging_queries > 0
      ? `${optimizations.gsc_opportunities.summary.emerging_queries} requete(s) progressent rapidement`
      : null,
    newQueryWatchlist.length > 0
      ? `${newQueryWatchlist.length} nouvelle(s) requete(s) apparaissent dans Google`
      : null,
    linkedQueryWatchlist.length > 0
      ? `${linkedQueryWatchlist.length} requête(s) sont déjà reliée(s) à une page observée`
      : null,
    optimizations.gsc_opportunities.summary.sustained_drop > 0
      ? `${optimizations.gsc_opportunities.summary.sustained_drop} page(s) perdent de la visibilite`
      : null,
    indexationAlerts.length > 0
      ? `${indexationAlerts.length} page(s) demandent encore une vérification côté Google`
      : null,
    totalObservedCrawlIssues > 0
      ? `${totalObservedCrawlIssues} problème(s) techniques observés demandent encore une vérification`
      : null,
  ].filter((item): item is string => item !== null);
  const cockpitMoments = [
    {
      label: "Impressions cette période",
      value: `${totalDeltaImpressions > 0 ? "+" : ""}${new Intl.NumberFormat("fr-FR").format(totalDeltaImpressions)}`,
      detail:
        totalDeltaImpressions !== 0
          ? "vs période précédente sur les sites connectés"
          : "volume stable sur les sites connectés",
      tone: totalDeltaImpressions < 0 ? "danger" : totalDeltaImpressions > 0 ? "success" : "secondary",
    },
    {
      label: "Clics cette période",
      value: `${totalDeltaClicks > 0 ? "+" : ""}${new Intl.NumberFormat("fr-FR").format(totalDeltaClicks)}`,
      detail:
        totalDeltaClicks !== 0
          ? "les clics organiques évoluent aussi"
          : "pas de variation forte côté clics",
      tone: totalDeltaClicks < 0 ? "danger" : totalDeltaClicks > 0 ? "success" : "secondary",
    },
    {
      label: "Sites en hausse",
      value: risingSitesCount,
      detail: "au moins un signe de progression remonte sur ces sites",
      tone: risingSitesCount > 0 ? "success" : "secondary",
    },
    {
      label: "Points à vérifier",
      value: activeAlerts + slippingSitesCount,
      detail: "CTR faible, baisse durable ou recul d’impressions",
      tone: activeAlerts + slippingSitesCount > 0 ? "warning" : "secondary",
    },
    {
      label: "Santé SEO moyenne",
      value: averageObservedHealth,
      detail: healthTrackedSites.length > 0 ? "lecture globale de la santé des sites relus" : "aucune lecture santé encore disponible",
      tone: averageObservedHealth >= 70 ? "success" : averageObservedHealth > 0 ? "secondary" : "secondary",
    },
    {
      label: "Requêtes déjà reliées",
      value: linkedQueryWatchlist.length,
      detail: linkedQueryWatchlist.length > 0 ? "PraeviSEO connaît déjà la page cible" : "les prochains liens requête -> page apparaîtront ici",
      tone: linkedQueryWatchlist.length > 0 ? "secondary" : "secondary",
    },
  ] as const;

  const priorityHref = (site: (typeof dashboard.sites)[number]) => {
    if (site.next_action.kind === "connect_gsc") {
      return `/sites/${site.site_id}/search-console`;
    }

    if (site.next_action.kind === "connect_bridge" || site.next_action.kind === "installation_requested") {
      return getSiteConnectPath(site.site_id);
    }

    return getSitePath(site.site_id);
  };

  const priorityLabel = (site: (typeof dashboard.sites)[number]) => {
    if (site.next_action.kind === "connect_gsc") {
      return "Connecter Search Console";
    }

    if (site.next_action.kind === "connect_bridge") {
      return site.readiness.gsc_connected ? "Découvrir l’automatisation" : "Connecter Search Console";
    }

    if (site.next_action.kind === "installation_requested") {
      return "Suivre l’activation";
    }

    return "Ouvrir la fiche site";
  };

  const indexedPagesValue = dashboard.totals.indexedPagesSynced
    ? dashboard.totals.indexedPages
    : "—";

  return (
    <div className="min-h-screen">
      <Topbar
        title="Vue d'ensemble SEO"
        subtitle="Votre cockpit client PraeviSEO : performances GSC, indexation Google et prochaines actions utiles."
        lastSync={backendLive ? "données Google actualisées" : "mode démonstration"}
        actions={
          <Button href="/sites/new" size="sm">
            Connecter un site
          </Button>
        }
      />

      <div className="p-6 space-y-6">
        <CockpitSectionNav
          items={[
            { label: "Vue d’ensemble", href: "#vue-ensemble", count: dashboard.sites.length, tone: "default" },
            { label: "Opportunités", href: "#opportunites", count: optimizations.gsc_opportunities.summary.total, tone: "warning" },
            { label: "Pages", href: "#pages", count: pageWatchlist.length, tone: "secondary" },
            { label: "Requêtes Google", href: "#requetes", count: risingQueryWatchlist.length + newQueryWatchlist.length + linkedQueryWatchlist.length, tone: "success" },
            { label: "Santé SEO", href: "#sante", count: healthWatchlist.length, tone: "secondary" },
            { label: "Indexation", href: "#indexation", count: indexationAlerts.length || indexedPagesValue, tone: "secondary" },
            { label: "Activité SEO", href: "#activite", count: activityFeed.length, tone: "default" },
          ]}
        />

        <div className="rounded-2xl border border-brand/20 bg-brand-muted px-6 py-6">
          <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div className="max-w-2xl">
              <Badge variant="brand-subtle" className="mb-3">
                {backendLive ? "Analyse GSC active" : "Mode démonstration"}
              </Badge>
              <h1 className="text-2xl font-bold tracking-tight text-text">
                PraeviSEO analyse déjà votre SEO avec Google
              </h1>
              <p className="mt-2 text-sm text-text-muted leading-7">
                Sans installer quoi que ce soit, PraeviSEO transforme déjà Google Search Console en priorités,
                opportunités, recommandations et signaux utiles à suivre régulièrement.
              </p>
              {(freshestSyncAt || freshestDataAsOf) && (
                <p className="mt-3 text-xs text-text-subtle">
                  {freshestSyncAt ? `Dernière synchro GSC : ${formatDate(freshestSyncAt)}.` : "Synchronisation GSC en attente."}{" "}
                  {freshestDataAsOf ? `Données arrêtées au ${formatDate(freshestDataAsOf)}.` : ""}
                </p>
              )}
            </div>
            <div className="flex flex-wrap gap-3">
              <Button href="/sites/join" variant="secondary">
                Rejoindre un site
              </Button>
              <Button href="/sites" variant="secondary">
                Voir mes sites
              </Button>
              <Button href="/sites/new">
                Ajouter un site
                <ArrowRight className="w-4 h-4" />
              </Button>
            </div>
          </div>
        </div>

        <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
          {cockpitMoments.map((item) => (
            <Card key={item.label} className="border-border-subtle bg-surface/80">
              <CardHeader className="pb-3">
                <div className="flex items-center justify-between gap-3">
                  <CardDescription>{item.label}</CardDescription>
                  <Badge variant={item.tone}>{String(item.value)}</Badge>
                </div>
                <CardTitle className="text-sm leading-6 text-text-muted font-medium">{item.detail}</CardTitle>
              </CardHeader>
            </Card>
          ))}
        </div>

        <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-6">
          {[
            {
              label: "Impressions",
              value: new Intl.NumberFormat("fr-FR").format(dashboard.totals.impressions),
              icon: Globe,
              hint: "volume GSC agrégé sur le dernier import 28 jours",
            },
            {
              label: "Clics",
              value: new Intl.NumberFormat("fr-FR").format(dashboard.totals.clicks),
              icon: Waves,
              hint: "clics organiques remontés par GSC sur 28 jours",
            },
            {
              label: "CTR moyen",
              value: new Intl.NumberFormat("fr-FR", {
                style: "percent",
                minimumFractionDigits: 1,
                maximumFractionDigits: 1,
              }).format(dashboard.totals.averageCtr),
              icon: Sparkles,
              hint: "calculé sur les clics et impressions GSC sur 28 jours",
            },
            {
              label: "Opportunités actives",
              value: optimizations.gsc_opportunities.summary.total,
              icon: SearchCheck,
              hint: "priorités SEO déjà détectées à partir de Google Search Console",
            },
            {
              label: "URLs relues",
              value: indexedPagesValue,
              icon: CheckCircle2,
              hint: dashboard.totals.indexedPagesSynced
                ? "pages déjà bien relues par PraeviSEO dans Google"
                : "lecture des pages Google encore en attente dans PraeviSEO",
            },
            {
              label: "Issues crawl",
              value: totalObservedCrawlIssues,
              icon: SearchCheck,
              hint: totalObservedCrawlIssues > 0
                ? "problèmes structurels déjà observés lors des dernières lectures du site"
                : "aucune issue crawl forte observée pour le moment",
            },
          ].map((item) => {
            const Icon = item.icon;

            return (
              <Card key={item.label}>
                <CardHeader>
                  <div className="flex items-center justify-between">
                    <div className="w-10 h-10 rounded-xl bg-brand-subtle flex items-center justify-center">
                      <Icon className="w-4 h-4 text-[hsl(var(--brand))]" />
                    </div>
                    <span className="text-2xl font-black text-text">{item.value}</span>
                  </div>
                  <CardTitle className="pt-4">{item.label}</CardTitle>
                  <CardDescription>{item.hint}</CardDescription>
                </CardHeader>
              </Card>
            );
          })}
        </div>

        <div id="vue-ensemble" className="grid gap-4 md:grid-cols-3 scroll-mt-24">
          <Card>
            <CardHeader>
              <CardTitle>Pages proches du top 10</CardTitle>
              <CardDescription>Levier le plus rapide à pousser dans Google en ce moment.</CardDescription>
            </CardHeader>
            <CardContent className="text-3xl font-black text-text">
              {optimizations.gsc_opportunities.summary.near_top_10}
            </CardContent>
          </Card>
          <Card>
            <CardHeader>
              <CardTitle>Alertes simples</CardTitle>
              <CardDescription>Les points qui méritent une vérification rapide pour éviter de perdre en visibilité.</CardDescription>
            </CardHeader>
            <CardContent className="text-3xl font-black text-text">{activeAlerts}</CardContent>
          </Card>
          <Card>
            <CardHeader>
              <CardTitle>Sites GSC actifs</CardTitle>
              <CardDescription>Sites déjà alimentés en données Google dans le cockpit free.</CardDescription>
            </CardHeader>
            <CardContent className="text-3xl font-black text-text">{gscConnectedSites}</CardContent>
          </Card>
        </div>

        <p className="text-xs text-text-subtle">
          Les métriques Google Search Console affichées ici correspondent au dernier import PraeviSEO sur une
          fenetre de 28 jours. Elles peuvent donc differer d un ecran Search Console regle sur 3 mois ou une autre
          periode.
        </p>

        <CockpitAssistantGuide
          title="PraeviSEO vous guide, pas a pas"
          description="Cette vue resume ce qui bouge, pourquoi cela compte et quelle action merite votre attention en premier."
          whatText={dashboardAssistantWhat}
          whyText={dashboardAssistantWhy}
          nextText={dashboardAssistantNext}
          impactText={dashboardAssistantImpact}
        />

        <div id="opportunites" className="grid gap-6 xl:grid-cols-[1.2fr_0.8fr] scroll-mt-24">
          <Card>
            <CardHeader>
              <CardTitle>Sites suivis</CardTitle>
              <CardDescription>
                Vos sites, ce que Google comprend déjà, et les prochains gains visibles dans le cockpit.
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
              {dashboard.sites.length === 0 ? (
                <div className="rounded-2xl border border-border bg-surface-2 px-4 py-4 text-sm text-text-muted">
                  Aucun site n est encore rattaché à ce compte. Créez un nouveau site ou rejoignez un site existant.
                </div>
              ) : dashboard.sites.map((site) => (
                <div
                  key={site.site_id}
                  className="rounded-2xl border border-border-subtle bg-surface-2/40 px-4 py-4 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between"
                >
                  <div>
                    <div className="flex items-center gap-2 flex-wrap">
                      <h3 className="text-base font-semibold text-text">{site.name}</h3>
                      <Badge variant="secondary">Copilote SEO actif</Badge>
                      <Badge variant={site.publication_bridge_status === "connected" ? "default" : "secondary"}>
                        {getPraeviseoClientStatus(site)}
                      </Badge>
                    </div>
                    <p className="mt-2 text-sm text-text-muted">{site.url}</p>
                    <div className="mt-3 flex flex-wrap gap-4 text-xs text-text-subtle">
                      <span>{site.summary.pages_total} page(s) suivie(s)</span>
                      <span>{new Intl.NumberFormat("fr-FR").format(site.summary.gsc_impressions)} impressions GSC (28 j)</span>
                      <span>{new Intl.NumberFormat("fr-FR").format(site.summary.gsc_clicks)} clics GSC (28 j)</span>
                      <span>
                        {new Intl.NumberFormat("fr-FR", {
                          style: "percent",
                          minimumFractionDigits: 1,
                          maximumFractionDigits: 1,
                        }).format(site.summary.gsc_ctr)}{" "}
                        CTR
                      </span>
                      <span>
                        {site.summary.gsc_indexation_synced
                          ? `${site.summary.gsc_indexed_pages} page(s) déjà bien lue(s) dans Google`
                          : "Lecture des pages Google encore en attente"}
                      </span>
                      <span>{site.summary.pending_suggestions} recommandation(s) ouverte(s)</span>
                      <span>{site.summary.new_queries.length} nouvelle(s) requête(s)</span>
                      <span>{site.summary.gsc_non_indexed_pages} page(s) à vérifier</span>
                      <span>{site.readiness.gsc_connected ? "GSC reliée" : "GSC non reliée"}</span>
                      <span>
                        {site.summary.gsc_delta_impressions > 0 ? "+" : ""}
                        {new Intl.NumberFormat("fr-FR").format(site.summary.gsc_delta_impressions)} impressions vs avant
                      </span>
                    </div>
                  </div>
                  <div className="flex flex-wrap gap-2">
                    <Button href={getSitePath(site.site_id)} variant="secondary" size="sm">
                      Ouvrir
                    </Button>
                    <Button
                      href={site.readiness.gsc_connected ? getSiteConnectPath(site.site_id) : `/sites/${site.site_id}/search-console`}
                      size="sm"
                    >
                      {getPraeviseoActivationLabel(site)}
                    </Button>
                  </div>
                </div>
              ))}
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Priorités du moment</CardTitle>
              <CardDescription>
                Ce que PraeviSEO recommande en premier au client, sans jargon technique.
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
              {freePriorityFeed.length === 0 ? (
                <div className="rounded-2xl border border-border bg-surface-2 px-4 py-4 text-sm text-text-muted">
                  Aucun point bloquant fort en ce moment. PraeviSEO continue de vérifier les prochains signaux utiles.
                </div>
              ) : (
                freePriorityFeed.map((item) => (
                  <div key={item.id} className="rounded-2xl border border-border bg-surface-2 px-4 py-4">
                    <div className="flex items-center justify-between gap-3">
                      <div className="text-sm font-semibold text-text">{item.title}</div>
                      <Badge variant={item.tone}>
                        {item.badge}
                      </Badge>
                    </div>
                    <p className="mt-1 text-xs text-text-subtle">{item.siteLabel}</p>
                    <p className="mt-2 text-sm text-text-muted leading-6">{item.description}</p>
                  </div>
                ))
              )}
            </CardContent>
          </Card>
        </div>

        <div id="pages" className="grid gap-6 xl:grid-cols-2 scroll-mt-24">
          <Card>
            <CardHeader>
              <CardTitle>Pages à suivre</CardTitle>
              <CardDescription>
                Les pages qui bougent le plus ou qui méritent un refresh rapide dans le cockpit free.
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-3">
              {pageWatchlist.length === 0 ? (
                <div className="rounded-xl border border-border bg-surface-2 px-4 py-4 text-sm text-text-muted">
                  Aucune page sensible pour le moment. PraeviSEO remontera ici les prochaines hausses, chutes et zones
                  à relancer.
                </div>
              ) : (
                pageWatchlist.map((item) => (
                  <div key={`${item.site_name}-${item.slug}-${item.trend}-page`} className="rounded-xl border border-border px-4 py-3">
                    <div className="flex items-center justify-between gap-3">
                      <div>
                        <p className="text-sm font-semibold text-text">{item.label}</p>
                        <p className="text-xs text-text-subtle">{item.site_name}</p>
                      </div>
                      <Badge variant={item.trend === "down" ? "danger" : "success"}>
                        {item.trend === "down" ? "En baisse" : "En hausse"}
                      </Badge>
                    </div>
                    <p className="mt-2 text-sm text-text-muted">
                      {item.trend === "down"
                        ? "La page perd de la visibilité récente et mérite une relance."
                        : "La page progresse et peut devenir une vraie opportunité si on la renforce."}
                    </p>
                    <div className="mt-3 flex flex-wrap gap-2 text-xs text-text-subtle">
                      <span className="rounded-full border border-border px-2.5 py-1">
                        {item.impressions} impressions
                      </span>
                      <span className="rounded-full border border-border px-2.5 py-1">
                        {item.delta_impressions > 0 ? "+" : ""}
                        {item.delta_impressions} impressions
                      </span>
                      <span className="rounded-full border border-border px-2.5 py-1">
                        Position {item.position}
                      </span>
                    </div>
                  </div>
                ))
              )}
            </CardContent>
          </Card>

          <Card id="requetes" className="scroll-mt-24">
            <CardHeader>
              <CardTitle>Requêtes Google</CardTitle>
              <CardDescription>
                Les requêtes qui progressent, émergent ou que PraeviSEO sait déjà rattacher à une page observée.
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-3">
              {queryWatchlist.length === 0 && linkedQueryWatchlist.length === 0 ? (
                <div className="rounded-xl border border-border bg-surface-2 px-4 py-4 text-sm text-text-muted">
                  Aucune requête émergente forte pour l’instant. Le cockpit affichera ici les prochaines requêtes à
                  potentiel dès qu’elles montent dans GSC.
                </div>
              ) : (
                dashboardQuerySignals.map((item) => {
                  const linkedPublication = item.linkedPublication;

                  return (
                  <div key={`${item.site_name}-${item.query}-query`} className="rounded-xl border border-border px-4 py-3">
                    <div className="flex items-center justify-between gap-3">
                      <div>
                        <p className="text-sm font-semibold text-text">{item.query}</p>
                        <p className="text-xs text-text-subtle">
                          {linkedPublication ? `${item.site_name} · page liée ${linkedPublication.title}` : item.site_name}
                        </p>
                      </div>
                      <Badge variant={linkedPublication ? "secondary" : item.position <= 10 ? "warning" : "success"}>
                        {getClientQueryBadge(item.position, Boolean(linkedPublication))}
                      </Badge>
                    </div>
                    <p className="mt-2 text-sm text-text-muted">
                      {item.impressions} impressions, {item.clicks} clics, CTR {item.ctr.toFixed(1)} %, position {item.position.toFixed(1)}.
                    </p>
                    <div className="mt-3 flex flex-wrap gap-2 text-xs text-text-subtle">
                      <span className="rounded-full border border-border px-2.5 py-1">
                        {linkedPublication ? `Cible : ${linkedPublication.slug || "/"}` : "Requête à potentiel"}
                      </span>
                      {linkedPublication?.observed_content?.cluster_label ? (
                        <span className="rounded-full border border-border px-2.5 py-1">
                          Cluster {linkedPublication.observed_content.cluster_label}
                        </span>
                      ) : null}
                    </div>
                  </div>
                  );
                })
              )}
            </CardContent>
          </Card>
        </div>

        <div id="indexation" className="grid gap-6 xl:grid-cols-2 scroll-mt-24">
          <Card>
            <CardHeader>
              <CardTitle>Indexation</CardTitle>
              <CardDescription>
                Les pages que PraeviSEO voit déjà dans Google et celles qui demandent encore une vérification.
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-3">
              {indexationAlerts.length === 0 ? (
                <div className="rounded-xl border border-border bg-surface-2 px-4 py-4 text-sm text-text-muted">
                  Connectez au moins un site à Google Search Console pour ouvrir la lecture d’URLs suivies.
                </div>
              ) : (
                indexationAlerts.map((item) => (
                  <div key={`${item.site_id}-${item.slug}`} className="rounded-xl border border-border px-4 py-3">
                    <div className="flex items-center justify-between gap-3">
                      <div>
                        <p className="text-sm font-semibold text-text">{item.label}</p>
                        <p className="text-xs text-text-subtle">{item.site_name}</p>
                      </div>
                      <Badge variant="warning">
                        À vérifier avec Google
                      </Badge>
                    </div>
                    <p className="mt-2 text-sm text-text-muted">{item.detail}</p>
                  </div>
                ))
              )}
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Blogs et contenus</CardTitle>
              <CardDescription>
                Les articles suivis par PraeviSEO pour garder un cockpit contenu déjà utile en free.
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-3">
              {recentPublications.length === 0 ? (
                <div className="rounded-xl border border-border bg-surface-2 px-4 py-4 text-sm text-text-muted">
                  Amiantix n’a pas encore de contenus suivis ici. Le cockpit free reste déjà utile grâce aux pages,
                  requêtes et opportunités GSC. Dès que PraeviSEO observe des contenus éditoriaux, ce bloc montrera
                  les refresh, maillages et enrichissements à ouvrir.
                </div>
              ) : (
                recentPublications.map((item) => (
                  <div key={item.id} className="rounded-xl border border-border px-4 py-3">
                    <div className="flex items-center justify-between gap-3">
                      <div>
                        <p className="text-sm font-semibold text-text">{item.title}</p>
                        <p className="text-xs text-text-subtle">{item.site_id}</p>
                      </div>
                      <Badge variant={item.published_live ? "success" : "secondary"}>
                        {item.published_live ? "visible sur le site" : "en preparation"}
                      </Badge>
                    </div>
                    <p className="mt-2 text-sm text-text-muted">
                      {item.latest_suggestion?.summary ??
                        `${item.gsc_metrics.impressions} impressions, CTR ${item.gsc_metrics.ctr.toFixed(1)} %, position ${item.gsc_metrics.position?.toFixed(1) ?? "n/a"}.`}
                    </p>
                  </div>
                ))
              )}
            </CardContent>
          </Card>
        </div>

        <div id="sante" className="grid gap-6 xl:grid-cols-2 scroll-mt-24">
          <Card>
            <CardHeader>
              <CardTitle>Santé SEO observée</CardTitle>
              <CardDescription>
                Le moteur relit aussi la structure réelle des sites : qualité moyenne, pages faibles, orphelines et issues crawl.
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-3">
              {healthWatchlist.length === 0 ? (
                <div className="rounded-xl border border-border bg-surface-2 px-4 py-4 text-sm text-text-muted">
                  Aucune faiblesse structurelle forte observée pour le moment. PraeviSEO continue de relire le site en arrière-plan.
                </div>
              ) : (
                healthWatchlist.map((site) => (
                  <div key={`health-watch-${site.site_id}`} className="rounded-xl border border-border px-4 py-3">
                    <div className="flex items-center justify-between gap-3">
                      <div>
                        <p className="text-sm font-semibold text-text">{site.name}</p>
                        <p className="text-xs text-text-subtle">
                          {site.summary.observed_snapshot_date
                            ? `snapshot du ${formatDate(site.summary.observed_snapshot_date)}`
                            : "observation récente"}
                        </p>
                      </div>
                      <Badge variant={site.summary.observed_site_health_score >= 70 ? "success" : "secondary"}>
                        Santé {site.summary.observed_site_health_score || "n/a"}
                      </Badge>
                    </div>
                    <p className="mt-2 text-sm text-text-muted">
                      {site.summary.observed_weak_pages} page(s) faible(s), {site.summary.observed_orphan_pages} page(s) orpheline(s),
                      {` ${site.summary.observed_crawl_issues}`} issue(s) crawl, autorité moyenne {site.summary.observed_avg_authority}.
                    </p>
                  </div>
                ))
              )}
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Pages structurellement à renforcer</CardTitle>
              <CardDescription>
                Les pages repérées par PraeviSEO comme piliers potentiels, sous-maillées ou encore trop faibles.
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-3">
              {dashboard.sites.flatMap((site) => site.summary.observed_link_gap_pages).length === 0 &&
              dashboard.sites.flatMap((site) => site.summary.observed_orphan_alerts).length === 0 ? (
                <div className="rounded-xl border border-border bg-surface-2 px-4 py-4 text-sm text-text-muted">
                  Aucune faiblesse structurelle forte pour le moment. Ce bloc se remplira dès qu’un maillage ou une page orpheline devient utile à traiter.
                </div>
              ) : (
                [
                  ...dashboard.sites.flatMap((site) =>
                    site.summary.observed_link_gap_pages.slice(0, 1).map((item) => ({
                      siteName: site.name,
                      label: item.label,
                      detail: `${item.internal_inlinks} lien(s) entrant(s), autorité ${item.authority_score}, cluster ${item.cluster_label ?? "n/a"}.`,
                      badge: "Maillage",
                      variant: "warning" as const,
                    }))
                  ),
                  ...dashboard.sites.flatMap((site) =>
                    site.summary.observed_orphan_alerts.slice(0, 1).map((item) => ({
                      siteName: site.name,
                      label: item.label,
                      detail: `Page orpheline ou quasi orpheline, autorité ${item.authority_score}, indexabilité ${item.indexability_state}.`,
                      badge: "Orpheline",
                      variant: "secondary" as const,
                    }))
                  ),
                ]
                  .slice(0, 6)
                  .map((item) => (
                    <div key={`${item.siteName}-${item.label}-${item.badge}`} className="rounded-xl border border-border px-4 py-3">
                      <div className="flex items-center justify-between gap-3">
                        <div>
                          <p className="text-sm font-semibold text-text">{item.label}</p>
                          <p className="text-xs text-text-subtle">{item.siteName}</p>
                        </div>
                        <Badge variant={item.variant}>{item.badge}</Badge>
                      </div>
                      <p className="mt-2 text-sm text-text-muted">{item.detail}</p>
                    </div>
                  ))
              )}
            </CardContent>
          </Card>
        </div>

        <div id="activite" className="grid gap-6 xl:grid-cols-2 scroll-mt-24">
          <Card>
            <CardHeader>
              <CardTitle>Signaux GSC à vérifier</CardTitle>
              <CardDescription>
                Les mouvements utiles déjà détectés par PraeviSEO à partir des données Google les plus récentes.
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-3">
              {insightSignals.length === 0 ? (
                <div className="rounded-xl border border-border bg-surface-2 px-4 py-4 text-sm text-text-muted">
                  Aucun signal fort à vérifier pour le moment. Le cockpit continue de relire Google au fil des prochains imports.
                </div>
              ) : (
                insightSignals.map((item) => (
                  <div key={item} className="rounded-xl border border-border px-4 py-3 text-sm text-text">
                    {item}
                  </div>
                ))
              )}
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Quoi traiter d’abord</CardTitle>
              <CardDescription>
                Le plan d’action le plus utile à ouvrir maintenant, à partir des opportunités Google et des recommandations moteur.
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-3">
              {actionPlan.length === 0 && topOpportunities.length === 0 ? (
                <div className="rounded-xl border border-border bg-surface-2 px-4 py-4 text-sm text-text-muted">
                  Aucune action prioritaire forte pour le moment. Les prochaines remontées GSC ou recommandations moteur viendront enrichir ce bloc.
                </div>
              ) : (
                [
                  ...actionPlan.map((item) => ({
                    key: `action-${item.id}`,
                    title: item.title,
                    subtitle: `${item.site_id}${item.cluster ? ` · ${item.cluster}` : ""}`,
                    badge: item.priority <= 30 ? "À faire en premier" : "Action recommandée",
                    badgeVariant: item.priority <= 30 ? ("warning" as const) : ("secondary" as const),
                    description: item.suggested_action ?? item.reasoning,
                  })),
                  ...topOpportunities.map((item) => ({
                    key: `opportunity-${item.site_id}-${item.slug}-${item.type}`,
                    title: item.label,
                    subtitle: item.site_name,
                    badge: item.priority_label,
                    badgeVariant: item.priority_level === "high" ? ("warning" as const) : ("secondary" as const),
                    description: `${item.reason} Action conseillée : ${item.action}.`,
                  })),
                ]
                  .slice(0, 4)
                  .map((item) => (
                  <div key={item.key} className="rounded-xl border border-border px-4 py-3">
                    <div className="flex items-center justify-between gap-3">
                      <div>
                        <p className="text-sm font-semibold text-text">{item.title}</p>
                        <p className="text-xs text-text-subtle">{item.subtitle}</p>
                      </div>
                      <Badge variant={item.badgeVariant}>
                        {item.badge}
                      </Badge>
                    </div>
                    <p className="mt-2 text-sm text-text-muted">{item.description}</p>
                  </div>
                ))
              )}
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Ce qui a bougé depuis votre dernière visite</CardTitle>
              <CardDescription>
                Les points qui montrent clairement ce qui progresse, ce qui ralentit et ce qu’il faut ouvrir ensuite.
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-3">
              {progressMoments.length === 0 ? (
                <div className="rounded-xl border border-border bg-surface-2 px-4 py-4 text-sm text-text-muted">
                  Le cockpit fera remonter ici les premiers signes de progression dès les prochains imports GSC.
                </div>
              ) : (
                progressMoments.map((item) => (
                  <div key={item} className="rounded-xl border border-border px-4 py-3 text-sm text-text-muted">
                    {item}
                  </div>
                ))
              )}
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Activité SEO récente</CardTitle>
              <CardDescription>
                Le feed chronologique du cockpit : imports Google, recommandations et mouvements visibles.
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-3">
              {timelineFeed.length === 0 ? (
                <div className="rounded-xl border border-border bg-surface-2 px-4 py-4 text-sm text-text-muted">
                  Le feed se remplira automatiquement dès les prochains imports GSC et les prochaines recommandations.
                </div>
              ) : (
                timelineFeed.map((item) => (
                  <div key={item.id} className="rounded-xl border border-border px-4 py-3">
                    <div className="flex items-center justify-between gap-3">
                      <div>
                        <p className="text-sm font-semibold text-text">{item.title}</p>
                        <p className="text-xs text-text-subtle">{item.meta}</p>
                      </div>
                      <Badge variant={item.badgeVariant as "secondary" | "warning" | "success"}>
                        {item.badge}
                      </Badge>
                    </div>
                    <p className="mt-2 text-sm text-text-muted">{item.detail}</p>
                  </div>
                ))
              )}
            </CardContent>
          </Card>
        </div>
      </div>
    </div>
  );
}
