import { notFound } from "next/navigation";
import { Topbar } from "@/components/layout/topbar";
import { CockpitSectionNav } from "@/components/cockpit/section-nav";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import {
  formatGscStatus,
  getPraeviseoActivationLabel,
  getPraeviseoClientDetail,
  getPraeviseoClientStatus,
  getPraeviseoInstallLabel,
  getOptimizations,
  getPublications,
  getSite,
  getSiteConnectPath,
  hasBackendConnection,
} from "@/lib/praeviseo-api";
import { formatDate } from "@/lib/utils";
import { ArrowRight, CheckCircle2, Globe, SearchCheck, Sparkles } from "lucide-react";

interface SiteDetailPageProps {
  params: Promise<{ siteId: string }>;
}

export default async function SiteDetailPage({ params }: SiteDetailPageProps) {
  const { siteId } = await params;
  const site = await getSite(siteId);

  if (!site) {
    notFound();
  }

  const backendLive = hasBackendConnection();
  const optimizations = await getOptimizations();
  const publications = await getPublications();
  const normalizeQuery = (value: string) => value.trim().toLowerCase();
  const siteOpportunities = optimizations.gsc_opportunities.items
    .filter((item) => item.site_id === site.site_id)
    .slice(0, 3);
  const siteActionPlan = optimizations.recommendations.items
    .filter((item) => item.site_id === site.site_id)
    .slice(0, 4);
  const nearTop10Count = siteOpportunities.filter((item) => item.type === "near_top_10").length;
  const lowCtrCount = siteOpportunities.filter((item) => item.type === "low_ctr").length;
  const emergingQueryCount = siteOpportunities.filter((item) => item.type === "emerging_query").length;
  const sustainedDropCount = siteOpportunities.filter((item) => item.type === "sustained_drop").length;
  const siteSignals = [
    nearTop10Count > 0 ? `${nearTop10Count} page(s) approchent du top 10` : null,
    lowCtrCount > 0 ? `${lowCtrCount} page(s) attirent encore trop peu de clics` : null,
    emergingQueryCount > 0 ? `${emergingQueryCount} requete(s) progressent vite` : null,
    sustainedDropCount > 0 ? `${sustainedDropCount} page(s) perdent de la visibilite` : null,
  ].filter((item): item is string => item !== null);
  const queryWatchlist = site.summary.top_queries.slice(0, 3);
  const risingQueries = site.summary.top_rising_queries.slice(0, 3);
  const newQueries = site.summary.new_queries.slice(0, 3);
  const pageMomentum = [
    ...site.summary.top_rising_pages.map((item) => ({ ...item, trend: "up" as const })),
    ...site.summary.top_falling_pages.map((item) => ({ ...item, trend: "down" as const })),
  ].slice(0, 4);
  const siteIndexationAlerts = site.summary.indexation_alerts.slice(0, 4);
  const siteRecommendations = optimizations.items.filter((item) => item.page.site_id === site.site_id).slice(0, 3);
  const siteContent = publications.items.filter((item) => item.site_id === site.site_id);
  const refreshContent = siteContent.filter((item) => !!item.latest_suggestion || (item.seo_score ?? 0) < 80).slice(0, 3);
  const observedContent = siteContent.filter((item) => !!item.observed_content);
  const observedQueryMatches = observedContent
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
  const findLinkedPublication = (query: string) => {
    const normalizedQuery = normalizeQuery(query);

    return observedQueryMatches.find((item) => item.normalizedTopQueryMatch === normalizedQuery) ?? null;
  };
  const linkedQueryWatchlist = queryWatchlist
    .map((item) => ({
      ...item,
      linkedPublication: findLinkedPublication(item.query),
    }))
    .filter((item) => !!item.linkedPublication)
    .slice(0, 3);
  const linkingContent = observedContent
    .filter((item) => (item.observed_content?.internal_link_suggestions_count ?? 0) > 0)
    .slice(0, 3);
  const cannibalContent = observedContent
    .filter(
      (item) =>
        (item.observed_content?.cannibalization_count ?? 0) > 0 ||
        (item.observed_content?.overlap_count ?? 0) > 0
    )
    .slice(0, 3);
  const enrichmentContent = observedContent
    .filter((item) => {
      const observed = item.observed_content;

      if (!observed) {
        return false;
      }

      return (
        !!item.latest_suggestion ||
        observed.snapshot_word_count < 900 ||
        observed.query_match_count > 0 ||
        observed.authority_score >= 40
      );
    })
    .slice(0, 3);
  const progressMoments = [
    site.summary.gsc_delta_impressions > 0
      ? `La visibilité remonte avec +${new Intl.NumberFormat("fr-FR").format(site.summary.gsc_delta_impressions)} impression(s) depuis la lecture précédente.`
      : site.summary.gsc_delta_impressions < 0
        ? `La visibilité recule de ${new Intl.NumberFormat("fr-FR").format(Math.abs(site.summary.gsc_delta_impressions))} impression(s) sur la dernière période suivie.`
        : "Le volume d’impressions reste stable sur la dernière lecture GSC de ce site.",
    linkedQueryWatchlist.length > 0
      ? `${linkedQueryWatchlist.length} requête(s) sont déjà reliée(s) à une page observée, ce qui clarifie où agir.`
      : "PraeviSEO attend encore le prochain lien net entre requête et page pour ouvrir une cible éditoriale encore plus claire.",
    siteActionPlan.length > 0
      ? `${siteActionPlan.length} action(s) sont déjà prêtes à être traitées sur ce site.`
      : "PraeviSEO continue de préparer les prochaines actions utiles sur ce site.",
    refreshContent.length > 0
      ? `${refreshContent.length} contenu(s) méritent déjà un refresh ou une relance.`
      : "Aucun contenu chaud à relancer pour le moment sur ce site.",
  ];
  const activityFeed = [
    ...siteOpportunities.map((item) => ({
      id: `site-opportunity-${item.slug}-${item.type}`,
      title: item.label,
      detail: item.reason,
      badge: item.priority_label,
      badgeVariant: item.priority_level === "high" ? "warning" : "secondary",
      meta: item.action,
    })),
    ...risingQueries.map((item) => ({
      id: `site-query-${item.query}`,
      title: item.query,
      detail: `+${item.delta_impressions} affichage(s) récents dans Google, avec une présence qui progresse.`,
      badge: "Requête en hausse",
      badgeVariant: "success" as const,
      meta: "lecture GSC",
    })),
    ...linkedQueryWatchlist.map((item) => ({
      id: `site-linked-query-${item.query}`,
      title: item.query,
      detail: `PraeviSEO pense déjà que ${item.linkedPublication?.title} est la bonne page pour cette recherche. ${item.impressions} affichage(s) ont déjà été repérés.`,
      badge: "Page cible",
      badgeVariant: "secondary" as const,
      meta: item.linkedPublication?.observed_content?.cluster_label ?? "bonne page repérée",
    })),
    ...refreshContent.map((item) => ({
      id: `site-content-${item.id}`,
      title: item.title,
      detail: item.latest_suggestion?.summary ?? "Ce contenu mérite une relance.",
      badge: "Refresh",
      badgeVariant: "warning" as const,
      meta: "contenu à reprendre",
    })),
    ...linkingContent.map((item) => ({
      id: `site-linking-${item.id}`,
      title: item.title,
      detail: item.observed_content?.top_internal_link_target
        ? `PraeviSEO suggère déjà de mieux relier cette page à ${item.observed_content.top_internal_link_target}.`
        : `PraeviSEO voit ${item.observed_content?.internal_link_suggestions_count ?? 0} occasion(s) de mieux relier ce contenu au reste du site.`,
      badge: "À mieux relier",
      badgeVariant: "secondary" as const,
      meta: item.observed_content?.cluster_label ?? "contenu observé",
    })),
    ...cannibalContent.map((item) => ({
      id: `site-cannibal-${item.id}`,
      title: item.title,
      detail: item.observed_content?.top_cannibalization_target
        ? `Sujet à clarifier face à ${item.observed_content.top_cannibalization_target}.`
        : `PraeviSEO garde ${item.observed_content?.overlap_count ?? 0} sujet(s) très proche(s) sous surveillance sur ce contenu.`,
      badge: "Sujet à clarifier",
      badgeVariant: "warning" as const,
      meta: item.observed_content?.cluster_label ?? "contenu observé",
    })),
  ].slice(0, 4);
  const timelineFeed = [
    ...(site.gsc_last_sync_at
      ? [{
          id: `site-sync-${site.site_id}`,
          title: "Google a relu ce site",
          detail:
            site.summary.gsc_delta_impressions > 0
              ? `+${new Intl.NumberFormat("fr-FR").format(site.summary.gsc_delta_impressions)} impressions depuis la période précédente.`
              : site.summary.gsc_delta_impressions < 0
                ? `${new Intl.NumberFormat("fr-FR").format(site.summary.gsc_delta_impressions)} impressions depuis la période précédente.`
                : "Le volume d’impressions reste stable sur la période suivie.",
          badge: "Import GSC",
          badgeVariant:
            site.summary.gsc_delta_impressions < 0 ? "danger" : site.summary.gsc_delta_impressions > 0 ? "success" : "secondary",
          meta: formatDate(site.gsc_last_sync_at),
          timestamp: new Date(site.gsc_last_sync_at).getTime(),
        }]
      : []),
    ...siteRecommendations.map((item) => ({
      id: `site-reco-${item.id}`,
      title: item.page.title,
      detail: item.summary,
      badge: item.status === "pending" ? "Reco ouverte" : "Reco suivie",
      badgeVariant: item.status === "pending" ? "warning" : "secondary",
      meta: item.created_at ? formatDate(item.created_at) : "Récemment",
      timestamp: item.created_at ? new Date(item.created_at).getTime() : 0,
    })),
    ...siteIndexationAlerts.map((item) => ({
      id: `site-index-${item.slug}`,
      title: item.label,
      detail: item.detail,
      badge: "Indexation",
      badgeVariant: "warning" as const,
      meta: "signal Google",
      timestamp: 0,
    })),
  ]
    .sort((a, b) => b.timestamp - a.timestamp)
    .slice(0, 5);
  const nextActionLabel =
    site.next_action.kind === "connect_bridge"
      ? site.readiness.gsc_connected
        ? "Découvrir l’automatisation"
        : "Connecter Search Console"
      : site.next_action.kind === "installation_requested"
        ? "Automatisation en préparation"
        : site.next_action.label;
  const nextActionDetail =
    site.next_action.kind === "connect_bridge"
      ? getPraeviseoClientDetail(site)
      : site.next_action.detail;
  const cockpitMoments = [
    {
      label: "Impressions",
      value: `${site.summary.gsc_delta_impressions > 0 ? "+" : ""}${new Intl.NumberFormat("fr-FR").format(site.summary.gsc_delta_impressions)}`,
      detail:
        site.summary.gsc_delta_impressions !== 0
          ? "vs période précédente"
          : "stables sur la période suivie",
      tone: site.summary.gsc_delta_impressions < 0 ? "danger" : site.summary.gsc_delta_impressions > 0 ? "success" : "secondary",
    },
    {
      label: "Clics",
      value: `${site.summary.gsc_delta_clicks > 0 ? "+" : ""}${new Intl.NumberFormat("fr-FR").format(site.summary.gsc_delta_clicks)}`,
      detail:
        site.summary.gsc_delta_clicks !== 0
          ? "évolution récente des clics"
          : "volume stable pour le moment",
      tone: site.summary.gsc_delta_clicks < 0 ? "danger" : site.summary.gsc_delta_clicks > 0 ? "success" : "secondary",
    },
    {
      label: "Opportunités",
      value: siteOpportunities.length,
      detail: siteOpportunities.length > 0 ? "déjà visibles sur ce site" : "aucune priorité forte ouverte",
      tone: siteOpportunities.length > 0 ? "warning" : "secondary",
    },
    {
      label: "Pages qui bougent",
      value: pageMomentum.length,
      detail: pageMomentum.length > 0 ? "hausses ou chutes récentes détectées" : "mouvement encore limité",
      tone: pageMomentum.length > 0 ? "success" : "secondary",
    },
    {
      label: "Requêtes qui montent",
      value: risingQueries.length + newQueries.length,
      detail: risingQueries.length + newQueries.length > 0 ? "mouvements visibles dans Google" : "pas encore de hausse franche",
      tone: risingQueries.length + newQueries.length > 0 ? "success" : "secondary",
    },
    {
      label: "Requêtes déjà reliées",
      value: linkedQueryWatchlist.length,
      detail: linkedQueryWatchlist.length > 0 ? "bonne page déjà trouvée" : "PraeviSEO cherche encore la meilleure page à relier",
      tone: linkedQueryWatchlist.length > 0 ? "secondary" : "secondary",
    },
    {
      label: "Contenus à pousser",
      value: enrichmentContent.length + linkingContent.length + cannibalContent.length,
      detail:
        enrichmentContent.length + linkingContent.length + cannibalContent.length > 0
          ? "contenu à enrichir, pages à mieux relier ou sujets trop proches déjà visibles"
          : "contenu encore calme pour le moment",
      tone: enrichmentContent.length + linkingContent.length + cannibalContent.length > 0 ? "secondary" : "secondary",
    },
  ] as const;

  return (
    <div className="min-h-screen">
      <Topbar
        title={site.name}
        subtitle="Vue client : performances GSC, indexation et prochaines actions utiles."
        lastSync={backendLive ? "lecture GSC actualisée" : "données de démonstration"}
        actions={
          <Button href={site.readiness.gsc_connected ? getSiteConnectPath(site.site_id) : `/sites/${site.site_id}/search-console`} size="sm">
            {getPraeviseoActivationLabel(site)}
          </Button>
        }
      />

      <div className="p-6 space-y-6">
        <CockpitSectionNav
          items={[
            { label: "Vue d’ensemble", href: "#vue-ensemble", count: site.summary.gsc_impressions, tone: "default" },
            { label: "Opportunités", href: "#opportunites", count: siteOpportunities.length, tone: "warning" },
            { label: "Pages", href: "#pages", count: pageMomentum.length, tone: "secondary" },
            { label: "Requêtes Google", href: "#requetes", count: risingQueries.length + newQueries.length + queryWatchlist.length, tone: "success" },
            { label: "Blogs", href: "#blogs", count: enrichmentContent.length + linkingContent.length + cannibalContent.length, tone: "secondary" },
            { label: "Indexation", href: "#indexation", count: siteIndexationAlerts.length || site.summary.gsc_indexed_pages, tone: "secondary" },
            { label: "Activité SEO", href: "#activite", count: activityFeed.length, tone: "default" },
          ]}
        />

        <div className="rounded-2xl border border-border bg-surface px-6 py-6">
          <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
              <div className="flex flex-wrap items-center gap-2">
                <h1 className="text-2xl font-bold tracking-tight text-text">{site.name}</h1>
                <Badge variant="secondary">Copilote SEO actif</Badge>
                <Badge variant={site.publication_bridge_status === "connected" ? "default" : "secondary"}>
                  {getPraeviseoClientStatus(site)}
                </Badge>
              </div>
              <p className="mt-3 text-sm text-text-muted">{site.url}</p>
              <div className="mt-4 flex flex-wrap gap-3 text-xs text-text-subtle">
                <span className="inline-flex items-center gap-1 rounded-full border border-border px-3 py-1">
                  <Globe className="w-3.5 h-3.5" />
                  {site.niche}
                </span>
                <span className="inline-flex items-center gap-1 rounded-full border border-border px-3 py-1">
                  <SearchCheck className="w-3.5 h-3.5" />
                  {site.gsc_property_url ?? "GSC non reliée"}
                </span>
                <span className="inline-flex items-center gap-1 rounded-full border border-border px-3 py-1">
                  <Sparkles className="w-3.5 h-3.5" />
                  {siteOpportunities.length} opportunite(s) detectee(s)
                </span>
                <span className="inline-flex items-center gap-1 rounded-full border border-border px-3 py-1">
                  <CheckCircle2 className="w-3.5 h-3.5" />
                  {site.summary.gsc_indexed_pages} URL(s) relue(s) comme confirmée(s)
                </span>
                <span className="inline-flex items-center gap-1 rounded-full border border-border px-3 py-1">
                  <Sparkles className="w-3.5 h-3.5" />
                  {site.summary.gsc_non_indexed_pages} URL(s) à surveiller
                </span>
              </div>
            </div>
            <div className="flex flex-wrap gap-2">
              <Button href={site.readiness.gsc_connected ? getSiteConnectPath(site.site_id) : `/sites/${site.site_id}/search-console`}>
                {site.readiness.gsc_connected ? "Découvrir l’automatisation" : "Connecter Search Console"}
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

        <div id="vue-ensemble" className="grid gap-4 md:grid-cols-2 xl:grid-cols-6 scroll-mt-24">
          {[
            ["URLs relues comme confirmees", site.summary.gsc_indexed_pages],
            ["Clics GSC", new Intl.NumberFormat("fr-FR").format(site.summary.gsc_clicks)],
            ["Impressions GSC", new Intl.NumberFormat("fr-FR").format(site.summary.gsc_impressions)],
            ["Taux de clic GSC", new Intl.NumberFormat("fr-FR", {
              style: "percent",
              minimumFractionDigits: 1,
              maximumFractionDigits: 1,
            }).format(site.summary.gsc_ctr)],
            ["Opportunites", siteOpportunities.length],
            ["Signaux actifs", siteSignals.length],
          ].map(([label, value]) => (
            <Card key={label}>
              <CardContent className="pt-5">
                <div className="text-[11px] uppercase tracking-[0.18em] text-text-subtle font-semibold">
                  {label}
                </div>
                <div className="mt-2 text-3xl font-black text-text">{value}</div>
              </CardContent>
            </Card>
          ))}
        </div>

        <div id="opportunites" className="grid gap-6 xl:grid-cols-[1fr_0.9fr] scroll-mt-24">
          <Card>
            <CardHeader>
              <CardTitle>État réel du site</CardTitle>
              <CardDescription>
                Ce que le client doit comprendre immédiatement, sans jargon admin.
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="rounded-2xl border border-border bg-surface-2 px-4 py-4">
                <div className="text-[11px] uppercase tracking-[0.18em] text-text-subtle font-semibold">PraeviSEO</div>
                <div className="mt-2 text-sm font-semibold text-text">
                  {site.readiness.gsc_connected && site.publication_bridge_status !== "connected"
                    ? "Analyse GSC déjà active"
                    : getPraeviseoInstallLabel(site)}
                </div>
                <p className="mt-2 text-sm text-text-muted leading-6">
                  {getPraeviseoClientDetail(site)}
                </p>
                {site.publication_bridge_status !== "connected" ? (
                  <div className="mt-4">
                    <Button
                      href={site.readiness.gsc_connected ? getSiteConnectPath(site.site_id) : `/sites/${site.site_id}/search-console`}
                      variant="secondary"
                    >
                      {getPraeviseoActivationLabel(site)}
                    </Button>
                  </div>
                ) : null}
              </div>

              <div className="rounded-2xl border border-border bg-surface-2 px-4 py-4">
                <div className="text-[11px] uppercase tracking-[0.18em] text-text-subtle font-semibold">Google Search Console</div>
                <div className="mt-2 text-sm font-semibold text-text">{formatGscStatus(site.gsc_connection_status)}</div>
                <p className="mt-2 text-sm text-text-muted leading-6">
                  {site.gsc_property_url
                    ? `Propriété reliée : ${site.gsc_property_url}. PraeviSEO suit déjà ${new Intl.NumberFormat("fr-FR").format(site.summary.gsc_impressions)} impressions, ${new Intl.NumberFormat("fr-FR").format(site.summary.gsc_clicks)} clics et ${new Intl.NumberFormat("fr-FR").format(site.summary.gsc_indexed_pages)} URL(s) relue(s) comme confirmée(s) sur les URLs inspectées récemment.`
                    : "Reliez la propriété Search Console pour que PraeviSEO détecte les opportunités, tendances et priorités réelles."}
                </p>
                <div className="mt-4">
                  <Button href={`/sites/${site.site_id}/search-console`} variant="secondary">
                    {site.gsc_property_url ? "Mettre à jour mon Google" : "Connecter mon Google"}
                  </Button>
                </div>
                {site.gsc_last_sync_at ? (
                  <p className="mt-2 text-xs text-text-subtle">
                    Dernière synchro : {formatDate(site.gsc_last_sync_at)}
                    {site.gsc_data_as_of ? ` · données arrêtées au ${formatDate(site.gsc_data_as_of)}` : ""}
                  </p>
                ) : null}
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Opportunités détectées</CardTitle>
              <CardDescription>
                Les premières actions que PraeviSEO remonte déjà à partir des signaux Google de ce site.
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-3">
              {siteOpportunities.length === 0 ? (
                <div className="rounded-2xl border border-border bg-surface-2 px-4 py-4 text-sm text-text-muted">
                  Aucune opportunité forte pour le moment. Le cockpit continuera de détecter les prochains mouvements
                  utiles sur vos pages et requêtes.
                </div>
              ) : (
                siteOpportunities.map((item) => (
                  <div key={`${item.slug}-${item.type}`} className="rounded-2xl border border-border bg-surface-2 px-4 py-4">
                    <div className="flex items-center justify-between gap-3">
                      <div>
                        <div className="text-sm font-semibold text-text">{item.label}</div>
                        <div className="text-xs text-text-subtle">{item.action}</div>
                      </div>
                      <Badge variant={item.priority_level === "high" ? "warning" : "secondary"}>
                        {item.priority_label}
                      </Badge>
                    </div>
                    <p className="mt-2 text-sm text-text-muted leading-6">{item.reason}</p>
                    <p className="mt-3 text-xs text-text-subtle">
                      {item.metrics.impressions} affichage(s) dans Google, {item.metrics.clicks} clic(s), présence moyenne autour de la {Math.round(Number(item.metrics.position))}e place
                    </p>
                  </div>
                ))
              )}
            </CardContent>
          </Card>
        </div>

        <div id="pages" className="grid gap-6 xl:grid-cols-[1fr_0.9fr] scroll-mt-24">
          <Card>
            <CardHeader>
              <CardTitle>Signaux à surveiller</CardTitle>
              <CardDescription>
                Ce que PraeviSEO voit dans Google en ce moment pour ce site précis.
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-3">
              {siteSignals.length === 0 ? (
                <div className="rounded-2xl border border-border bg-surface-2 px-4 py-4 text-sm text-text-muted">
                  Aucun signal critique pour le moment. PraeviSEO continue d’agréger les prochains imports GSC.
                </div>
              ) : (
                siteSignals.map((item) => (
                  <div key={item} className="rounded-2xl border border-border bg-surface-2 px-4 py-4 text-sm text-text">
                    {item}
                  </div>
                ))
              )}
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Pages qui bougent</CardTitle>
              <CardDescription>
                Les pages qui gagnent ou perdent le plus de visibilité sur la dernière lecture Google.
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-3">
              {pageMomentum.length === 0 ? (
                <div className="rounded-2xl border border-border bg-surface-2 px-4 py-4 text-sm text-text-muted">
                  Les prochains imports GSC feront remonter ici les pages qui montent et celles qui ralentissent.
                </div>
              ) : (
                pageMomentum.map((item) => (
                  <div key={`${item.slug}-${item.trend}`} className="rounded-2xl border border-border bg-surface-2 px-4 py-4">
                    <div className="flex items-center justify-between gap-3">
                      <div>
                        <div className="text-sm font-semibold text-text">{item.label}</div>
                        <div className="text-xs text-text-subtle">/{item.slug}</div>
                      </div>
                      <Badge variant={item.trend === "down" ? "danger" : "success"}>
                        {item.trend === "down" ? "En baisse" : "En hausse"}
                      </Badge>
                    </div>
                    <p className="mt-2 text-sm text-text-muted leading-6">
                      {item.impressions} impressions récentes contre {item.previous_impressions} avant, soit{" "}
                      {item.delta_impressions > 0 ? "+" : ""}
                      {item.delta_impressions} sur la fenêtre suivie.
                    </p>
                    <p className="mt-3 text-xs text-text-subtle">
                      présence moyenne autour de la {Math.round(item.position)}e place
                    </p>
                  </div>
                ))
              )}
            </CardContent>
          </Card>
        </div>

        <div id="requetes" className="grid gap-6 xl:grid-cols-[1fr_0.9fr] scroll-mt-24">
          <Card>
            <CardHeader>
              <CardTitle>Requêtes Google</CardTitle>
              <CardDescription>
                Les requêtes qui montent ou que PraeviSEO sait déjà relier à une page observée sur ce site.
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-3">
              {queryWatchlist.length === 0 && linkedQueryWatchlist.length === 0 ? (
                <div className="rounded-2xl border border-border bg-surface-2 px-4 py-4 text-sm text-text-muted">
                  Aucune requête émergente forte pour le moment. Les prochains imports GSC nourriront ce bloc
                  automatiquement.
                </div>
              ) : (
                [
                  ...linkedQueryWatchlist,
                  ...queryWatchlist
                    .filter((item) => !findLinkedPublication(item.query))
                    .map((item) => ({
                      ...item,
                      linkedPublication: null,
                    })),
                ]
                  .slice(0, 4)
                  .map((item) => (
                  <div key={item.query} className="rounded-2xl border border-border bg-surface-2 px-4 py-4">
                    <div className="flex items-center justify-between gap-3">
                      <div>
                        <div className="text-sm font-semibold text-text">{item.query}</div>
                        <div className="text-xs text-text-subtle">
                          {item.linkedPublication
                            ? `${item.impressions} impressions · page liée ${item.linkedPublication.title}`
                            : `${item.impressions} impressions récentes`}
                        </div>
                      </div>
                      <Badge variant={item.linkedPublication ? "secondary" : item.position <= 10 ? "warning" : "success"}>
                        {item.linkedPublication ? "Bonne page trouvée" : item.position <= 10 ? "Google vous voit déjà" : "À développer"}
                      </Badge>
                    </div>
                    <p className="mt-2 text-sm text-text-muted leading-6">
                      {item.clicks} clic(s) et une présence moyenne autour de la {Math.round(item.position)}e place.
                    </p>
                    {item.linkedPublication ? (
                      <p className="mt-3 text-xs text-text-subtle">
                        Bonne page actuelle : {item.linkedPublication.slug || "/"}{item.linkedPublication.observed_content?.cluster_label ? ` · sujet ${item.linkedPublication.observed_content.cluster_label}` : ""}.
                      </p>
                    ) : null}
                  </div>
                ))
              )}
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Requêtes qui bougent</CardTitle>
              <CardDescription>
                Les requêtes en hausse ou nouvellement visibles qui rendent ce cockpit plus vivant.
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-3">
              {risingQueries.length + newQueries.length === 0 ? (
                <div className="rounded-2xl border border-border bg-surface-2 px-4 py-4 text-sm text-text-muted">
                  Aucun mouvement fort de requête pour le moment. Les prochains imports GSC feront vivre ce bloc.
                </div>
              ) : (
                [...risingQueries, ...newQueries].map((item) => (
                  <div key={`${item.query}-${item.delta_impressions}`} className="rounded-2xl border border-border bg-surface-2 px-4 py-4">
                    <div className="flex items-center justify-between gap-3">
                      <div>
                        <div className="text-sm font-semibold text-text">{item.query}</div>
                        <div className="text-xs text-text-subtle">{item.impressions} impressions récentes</div>
                      </div>
                      <Badge variant={item.previous_impressions === 0 ? "secondary" : "success"}>
                        {item.previous_impressions === 0 ? "Nouvelle requête" : "En hausse"}
                      </Badge>
                    </div>
                    <p className="mt-2 text-sm text-text-muted leading-6">
                      {item.previous_impressions === 0
                        ? `Google commence à relier cette recherche à votre site. Elle apparaît encore loin, mais le signal existe déjà.`
                        : `+${item.delta_impressions} affichage(s) récents dans Google, avec une progression visible.`}
                    </p>
                  </div>
                ))
              )}
            </CardContent>
          </Card>
        </div>

        <div id="blogs" className="grid gap-6 xl:grid-cols-3 scroll-mt-24">
          <Card>
            <CardHeader>
              <CardTitle>Contenus à enrichir</CardTitle>
              <CardDescription>
                Les pages qui ont déjà une vraie base SEO, mais qui peuvent gagner en profondeur ou en précision.
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-3">
              {enrichmentContent.length === 0 ? (
                <div className="rounded-2xl border border-border bg-surface-2 px-4 py-4 text-sm text-text-muted">
                  Aucun contenu à enrichir fortement pour le moment. PraeviSEO rouvrira ce bloc dès qu’un contenu commence à porter.
                </div>
              ) : (
                enrichmentContent.map((item) => (
                  <div key={`enrichment-${item.id}`} className="rounded-2xl border border-border bg-surface-2 px-4 py-4">
                    <div className="flex items-center justify-between gap-3">
                      <div>
                        <div className="text-sm font-semibold text-text">{item.title}</div>
                        <div className="text-xs text-text-subtle">{item.observed_content?.cluster_label ?? "contenu observé"}</div>
                      </div>
                      <Badge variant="secondary">
                        Solidité {item.observed_content?.authority_score ?? item.seo_score ?? "n/a"}
                      </Badge>
                    </div>
                    <p className="mt-2 text-sm text-text-muted leading-6">
                      {item.latest_suggestion?.summary ??
                        `${item.observed_content?.snapshot_word_count ?? 0} mots observés, ${item.observed_content?.query_match_count ?? 0} requête(s) déjà reliée(s) à cette page.`}
                    </p>
                  </div>
                ))
              )}
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Maillage à renforcer</CardTitle>
              <CardDescription>
                Les contenus où PraeviSEO voit déjà des liens internes utiles à ouvrir pour mieux pousser la page.
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-3">
              {linkingContent.length === 0 ? (
                <div className="rounded-2xl border border-border bg-surface-2 px-4 py-4 text-sm text-text-muted">
                  Aucune page ne demande encore de gros effort de liaison pour le moment.
                </div>
              ) : (
                linkingContent.map((item) => (
                  <div key={`linking-${item.id}`} className="rounded-2xl border border-border bg-surface-2 px-4 py-4">
                    <div className="flex items-center justify-between gap-3">
                      <div>
                        <div className="text-sm font-semibold text-text">{item.title}</div>
                        <div className="text-xs text-text-subtle">{item.observed_content?.internal_inlinks ?? 0} lien(s) entrant(s) observé(s)</div>
                      </div>
                      <Badge variant="warning">
                        {item.observed_content?.internal_link_suggestions_count ?? 0} piste(s)
                      </Badge>
                    </div>
                    <p className="mt-2 text-sm text-text-muted leading-6">
                      {item.observed_content?.top_internal_link_target
                        ? `PraeviSEO suggère déjà de relier cette page à ${item.observed_content.top_internal_link_target}.`
                        : "PraeviSEO voit déjà des occasions de mieux relier cette page au reste du site."}
                    </p>
                  </div>
                ))
              )}
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Sujets à clarifier</CardTitle>
              <CardDescription>
                Les sujets proches ou les recouvrements que PraeviSEO garde déjà sous contrôle.
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-3">
              {cannibalContent.length === 0 ? (
                <div className="rounded-2xl border border-border bg-surface-2 px-4 py-4 text-sm text-text-muted">
                  Aucun sujet très proche à clarifier de toute urgence pour le moment.
                </div>
              ) : (
                cannibalContent.map((item) => (
                  <div key={`cannibal-${item.id}`} className="rounded-2xl border border-border bg-surface-2 px-4 py-4">
                    <div className="flex items-center justify-between gap-3">
                      <div>
                        <div className="text-sm font-semibold text-text">{item.title}</div>
                        <div className="text-xs text-text-subtle">Recouvrement estimé : {item.observed_content?.overlap_score ?? 0} / 100</div>
                      </div>
                      <Badge variant="warning">
                        {(item.observed_content?.cannibalization_count ?? 0) + (item.observed_content?.overlap_count ?? 0)} point(s) à clarifier
                      </Badge>
                    </div>
                    <p className="mt-2 text-sm text-text-muted leading-6">
                      {item.observed_content?.top_cannibalization_target
                        ? `Sujet à clarifier face à ${item.observed_content.top_cannibalization_target}.`
                        : "PraeviSEO voit déjà des recouvrements à clarifier sur ce contenu."}
                    </p>
                  </div>
                ))
              )}
            </CardContent>
          </Card>
        </div>

        <div id="indexation" className="grid gap-6 xl:grid-cols-[1fr_0.9fr] scroll-mt-24">
          <Card>
            <CardHeader>
              <CardTitle>Indexation</CardTitle>
              <CardDescription>
                Comment Google alimente déjà le cockpit sur ce site et où PraeviSEO continue de surveiller.
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-3">
              <div className="rounded-2xl border border-border bg-surface-2 px-4 py-4">
                <div className="flex items-center justify-between gap-3">
                  <div>
                    <div className="text-sm font-semibold text-text">URLs relues comme confirmées</div>
                    <div className="text-xs text-text-subtle">Lecture issue des URLs inspectées lors de la dernière synchronisation GSC</div>
                  </div>
                  <Badge variant={site.summary.gsc_indexation_synced ? "success" : "secondary"}>
                    {site.summary.gsc_indexation_synced ? "Synchronisée" : "En attente"}
                  </Badge>
                </div>
                <div className="mt-4 text-3xl font-black text-text">{site.summary.gsc_indexed_pages}</div>
              </div>
              <div className="rounded-2xl border border-border bg-surface-2 px-4 py-4">
                <div className="text-sm font-semibold text-text">Lecture actuelle</div>
                <p className="mt-2 text-sm text-text-muted leading-6">
                  {site.summary.gsc_indexation_synced
                    ? "Google alimente déjà les URLs inspectées de ce site dans PraeviSEO. Le cockpit peut donc relier URLs confirmées, opportunités et priorités."
                    : "PraeviSEO attend encore une lecture exploitable de l’indexation Google pour enrichir ce volet."}
                </p>
              </div>
              {siteIndexationAlerts.map((item) => (
                <div key={item.slug} className="rounded-2xl border border-border bg-surface-2 px-4 py-4">
                  <div className="flex items-center justify-between gap-3">
                    <div className="text-sm font-semibold text-text">{item.label}</div>
                    <Badge variant="warning">Google surveille</Badge>
                  </div>
                  <p className="mt-2 text-sm text-text-muted leading-6">{item.detail}</p>
                </div>
              ))}
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Prochaine action recommandée</CardTitle>
              <CardDescription>
                Le dashboard client pousse la prochaine vraie étape, pas les diagnostics internes.
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="rounded-2xl border border-brand/20 bg-brand-muted px-4 py-4">
                <div className="text-sm font-semibold text-text">
                  {nextActionLabel}
                </div>
                <p className="mt-2 text-sm text-text-muted leading-6">
                  {nextActionDetail}
                </p>
              </div>

              <Button
                href={site.readiness.gsc_connected ? getSiteConnectPath(site.site_id) : `/sites/${site.site_id}/search-console`}
                className="w-full"
              >
                {getPraeviseoActivationLabel(site)}
              </Button>

              {!site.readiness.gsc_connected ? (
                <Button href={`/sites/${site.site_id}/search-console`} className="w-full" variant="secondary">
                  Connecter mon Google
                </Button>
              ) : null}

              <div className="space-y-3">
                {[
                  site.readiness.gsc_connected ? "Google Search Console déjà relié" : "Google Search Console à relier",
                  "Performances, indexation et opportunités déjà analysées",
                  "Le free reste utile sans aucune installation",
                  "Une couche d’automatisation pourra venir plus tard si vous voulez aller plus loin",
                  "Le cockpit revient déjà avec des priorités, tendances et recommandations lisibles",
                ].map((item) => (
                  <div key={item} className="flex items-start gap-2 text-sm text-text-muted">
                    <CheckCircle2 className="w-4 h-4 text-[hsl(var(--success))] shrink-0 mt-0.5" />
                    <span>{item}</span>
                  </div>
                ))}
              </div>
            </CardContent>
          </Card>
        </div>

        <div id="activite" className="grid gap-6 xl:grid-cols-2 scroll-mt-24">
          <Card>
            <CardHeader>
              <CardTitle>Quoi traiter d’abord</CardTitle>
              <CardDescription>
                Le meilleur plan d’action disponible maintenant sur ce site, entre recommandations, recherches et contenus.
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-3">
              {siteActionPlan.length === 0 && siteOpportunities.length === 0 ? (
                <div className="rounded-2xl border border-border bg-surface-2 px-4 py-4 text-sm text-text-muted">
                  Aucune action prioritaire forte pour le moment. PraeviSEO enrichira ce bloc dès que la prochaine action utile devient claire.
                </div>
              ) : (
                [
                  ...siteActionPlan.map((item) => ({
                    id: `site-action-${item.id}`,
                    title: item.title,
                    meta: site.name,
                    badge: item.priority <= 30 ? "À faire en premier" : "Action recommandée",
                    badgeVariant: item.priority <= 30 ? ("warning" as const) : ("secondary" as const),
                    detail: item.suggested_action ?? item.reasoning,
                  })),
                  ...siteOpportunities.map((item) => ({
                    id: `site-opportunity-action-${item.slug}-${item.type}`,
                    title: item.label,
                    meta: item.site_name,
                    badge: item.priority_label,
                    badgeVariant: item.priority_level === "high" ? ("warning" as const) : ("secondary" as const),
                    detail: `${item.reason} Action suggérée : ${item.action}.`,
                  })),
                ]
                  .slice(0, 4)
                  .map((item) => (
                  <div key={item.id} className="rounded-2xl border border-border bg-surface-2 px-4 py-4">
                    <div className="flex items-center justify-between gap-3">
                      <div>
                        <div className="text-sm font-semibold text-text">{item.title}</div>
                        <div className="text-xs text-text-subtle">{item.meta}</div>
                      </div>
                      <Badge variant={item.badgeVariant as "warning" | "secondary" | "success"}>
                        {item.badge}
                      </Badge>
                    </div>
                    <p className="mt-2 text-sm text-text-muted leading-6">{item.detail}</p>
                  </div>
                ))
              )}
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Ce qui a progressé depuis la dernière lecture</CardTitle>
              <CardDescription>
                La lecture la plus utile pour comprendre ce qui bouge déjà sur ce site d’une visite à l’autre.
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-3">
              {progressMoments.map((item) => (
                <div key={item} className="rounded-2xl border border-border bg-surface-2 px-4 py-4 text-sm text-text-muted">
                  {item}
                </div>
              ))}
            </CardContent>
          </Card>
        </div>
      </div>
    </div>
  );
}
