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
  const siteOpportunities = optimizations.gsc_opportunities.items
    .filter((item) => item.site_id === site.site_id)
    .slice(0, 3);
  const nearTop10Count = siteOpportunities.filter((item) => item.type === "near_top_10").length;
  const lowCtrCount = siteOpportunities.filter((item) => item.type === "low_ctr").length;
  const emergingQueryCount = siteOpportunities.filter((item) => item.type === "emerging_query").length;
  const sustainedDropCount = siteOpportunities.filter((item) => item.type === "sustained_drop").length;
  const siteSignals = [
    nearTop10Count > 0 ? `${nearTop10Count} page(s) approchent du top 10` : null,
    lowCtrCount > 0 ? `${lowCtrCount} page(s) ont un CTR a relancer` : null,
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
      detail: `+${item.delta_impressions} impressions sur la période récente, CTR ${item.ctr.toFixed(1)} %, position ${item.position.toFixed(1)}.`,
      badge: "Requête en hausse",
      badgeVariant: "success" as const,
      meta: "lecture GSC",
    })),
    ...refreshContent.map((item) => ({
      id: `site-content-${item.id}`,
      title: item.title,
      detail: item.latest_suggestion?.summary ?? "Ce contenu mérite une relance.",
      badge: "Refresh",
      badgeVariant: "warning" as const,
      meta: item.cluster ?? "contenu",
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
        ? "Activer l’automatisation premium"
        : "Connecter Search Console"
      : site.next_action.kind === "installation_requested"
        ? "Automatisation premium en préparation"
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
  ] as const;

  return (
    <div className="min-h-screen">
      <Topbar
        title={site.name}
        subtitle="Vue client : performances GSC, indexation et prochaines actions utiles."
        lastSync={backendLive ? "synchronisation active" : "données de démonstration"}
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
                  {site.summary.gsc_indexed_pages} URL(s) confirmée(s)
                </span>
                <span className="inline-flex items-center gap-1 rounded-full border border-border px-3 py-1">
                  <Sparkles className="w-3.5 h-3.5" />
                  {site.summary.gsc_non_indexed_pages} URL(s) à surveiller
                </span>
              </div>
            </div>
            <div className="flex flex-wrap gap-2">
              <Button href={site.readiness.gsc_connected ? getSiteConnectPath(site.site_id) : `/sites/${site.site_id}/search-console`}>
                {site.readiness.gsc_connected ? "Activer l’automatisation premium" : "Connecter Search Console"}
                <ArrowRight className="w-4 h-4" />
              </Button>
            </div>
          </div>
        </div>

        <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
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
            ["URLs confirmees", site.summary.gsc_indexed_pages],
            ["Clics GSC", new Intl.NumberFormat("fr-FR").format(site.summary.gsc_clicks)],
            ["Impressions GSC", new Intl.NumberFormat("fr-FR").format(site.summary.gsc_impressions)],
            ["CTR GSC", new Intl.NumberFormat("fr-FR", {
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
                    ? `Propriété reliée : ${site.gsc_property_url}. PraeviSEO suit déjà ${new Intl.NumberFormat("fr-FR").format(site.summary.gsc_impressions)} impressions, ${new Intl.NumberFormat("fr-FR").format(site.summary.gsc_clicks)} clics et ${new Intl.NumberFormat("fr-FR").format(site.summary.gsc_indexed_pages)} URL(s) confirmée(s) sur les URLs inspectées récemment.`
                    : "Reliez la propriété Search Console pour que PraeviSEO détecte les opportunités, tendances et priorités réelles."}
                </p>
                <div className="mt-4">
                  <Button href={`/sites/${site.site_id}/search-console`} variant="secondary">
                    {site.gsc_property_url ? "Mettre à jour mon Google" : "Connecter mon Google"}
                  </Button>
                </div>
                {site.gsc_last_sync_at ? (
                  <p className="mt-2 text-xs text-text-subtle">Dernière synchro : {site.gsc_last_sync_at}</p>
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
                      {item.metrics.impressions} impressions, CTR {item.metrics.ctr} %, position {item.metrics.position}
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
                      CTR {item.ctr.toFixed(1)} %, position {item.position.toFixed(1)}
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
                Les requêtes qui montent ou qui méritent déjà une réponse plus précise sur ce site.
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-3">
              {queryWatchlist.length === 0 ? (
                <div className="rounded-2xl border border-border bg-surface-2 px-4 py-4 text-sm text-text-muted">
                  Aucune requête émergente forte pour le moment. Les prochains imports GSC nourriront ce bloc
                  automatiquement.
                </div>
              ) : (
                queryWatchlist.map((item) => (
                  <div key={item.query} className="rounded-2xl border border-border bg-surface-2 px-4 py-4">
                    <div className="flex items-center justify-between gap-3">
                      <div>
                        <div className="text-sm font-semibold text-text">{item.query}</div>
                        <div className="text-xs text-text-subtle">{item.impressions} impressions récentes</div>
                      </div>
                      <Badge variant={item.position <= 10 ? "warning" : "success"}>
                        {item.position <= 10 ? "Déjà visible" : "À pousser"}
                      </Badge>
                    </div>
                    <p className="mt-2 text-sm text-text-muted leading-6">
                      {item.clicks} clic(s), CTR {item.ctr.toFixed(1)} %, position moyenne {item.position.toFixed(1)}.
                    </p>
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
                        ? `Google commence à relier cette requête à votre site. Position moyenne ${item.position.toFixed(1)}.`
                        : `+${item.delta_impressions} impressions, CTR ${item.ctr.toFixed(1)} %, position ${item.position.toFixed(1)}.`}
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
                    <div className="text-sm font-semibold text-text">URLs confirmées</div>
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
                  "La couche premium sert ensuite uniquement à exécuter des actions avancées",
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
              <CardTitle>Activité SEO récente</CardTitle>
              <CardDescription>
                Le feed chronologique de ce site : imports Google et recommandations déjà visibles dans PraeviSEO.
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-3">
              {timelineFeed.length === 0 ? (
                <div className="rounded-2xl border border-border bg-surface-2 px-4 py-4 text-sm text-text-muted">
                  Aucune activité forte pour le moment. Les prochains signaux Google feront vivre ce fil automatiquement.
                </div>
              ) : (
                timelineFeed.map((item) => (
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
              <CardTitle>Rythme du cockpit</CardTitle>
              <CardDescription>
                Ce que le client doit ressentir chaque semaine en revenant sur PraeviSEO.
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-3">
              {[
                site.summary.gsc_impressions > 0
                  ? `${new Intl.NumberFormat("fr-FR").format(site.summary.gsc_impressions)} impressions déjà lues sur la fenêtre récente.`
                  : "Les premières impressions apparaîtront ici dès les prochaines remontées GSC.",
                siteOpportunities.length > 0
                  ? `${siteOpportunities.length} opportunité(s) sont déjà ouvertes sur ce site.`
                  : "PraeviSEO guette encore les premiers leviers rapides sur ce site.",
                refreshContent.length > 0
                  ? `${refreshContent.length} contenu(s) méritent déjà un refresh ou une relance.`
                  : "Aucun contenu chaud à relancer pour le moment.",
                site.readiness.gsc_connected
                  ? "Google Search Console alimente déjà ce cockpit sans installation technique."
                  : "La connexion Search Console reste la prochaine étape pour rendre ce cockpit vraiment vivant.",
              ].map((item) => (
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
