import { Topbar } from "@/components/layout/topbar";
import { CockpitSectionNav } from "@/components/cockpit/section-nav";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import {
  getPraeviseoActivationLabel,
  getPraeviseoClientDetail,
  getPraeviseoClientStatus,
  formatSitePlatform,
  getDashboard,
  getOptimizations,
  getPublications,
  getSiteConnectPath,
  getSitePath,
  hasBackendConnection,
} from "@/lib/praeviseo-api";
import { ArrowRight, CheckCircle2, Globe, SearchCheck, Sparkles, Waves } from "lucide-react";

export default async function DashboardPage() {
  const dashboard = await getDashboard();
  const optimizations = await getOptimizations();
  const publications = await getPublications();
  const backendLive = hasBackendConnection();
  const gscConnectedSites = dashboard.sites.filter((site) => site.readiness.gsc_connected).length;
  const activeAlerts =
    optimizations.gsc_opportunities.summary.low_ctr + optimizations.gsc_opportunities.summary.sustained_drop;
  const prioritySites = dashboard.sites
    .filter((site) => site.next_action.priority !== "low")
    .slice(0, 3);
  const recentPublications = publications.items.slice(0, 3);
  const recentOptimizations = optimizations.items.slice(0, 3);
  const topOpportunities = optimizations.gsc_opportunities.items.slice(0, 4);
  const pageWatchlist = dashboard.sites
    .flatMap((site) => [
      ...site.summary.top_rising_pages.map((item) => ({ ...item, site_name: site.name, trend: "up" as const })),
      ...site.summary.top_falling_pages.map((item) => ({ ...item, site_name: site.name, trend: "down" as const })),
    ])
    .slice(0, 6);
  const queryWatchlist = dashboard.sites
    .flatMap((site) => site.summary.top_queries.map((item) => ({ ...item, site_name: site.name })))
    .slice(0, 6);
  const indexationAlerts = dashboard.sites
    .filter((site) => site.readiness.gsc_connected)
    .map((site) => ({
      siteId: site.site_id,
      siteName: site.name,
      indexedPages: site.summary.gsc_indexed_pages,
      synced: site.summary.gsc_indexation_synced,
    }))
    .slice(0, 4);
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
  ].slice(0, 6);
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
    optimizations.gsc_opportunities.summary.sustained_drop > 0
      ? `${optimizations.gsc_opportunities.summary.sustained_drop} page(s) perdent de la visibilite`
      : null,
  ].filter((item): item is string => item !== null);

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
      return site.readiness.gsc_connected ? "Activer l’automatisation premium" : "Connecter Search Console";
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
        lastSync={backendLive ? "backend live" : "mode démonstration"}
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
            { label: "Requêtes Google", href: "#requetes", count: queryWatchlist.length, tone: "success" },
            { label: "Indexation", href: "#indexation", count: indexedPagesValue, tone: "secondary" },
            { label: "Activité SEO", href: "#activite", count: activityFeed.length, tone: "default" },
          ]}
        />

        <div className="rounded-2xl border border-brand/20 bg-brand-muted px-6 py-6">
          <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div className="max-w-2xl">
              <Badge variant="brand-subtle" className="mb-3">
                {backendLive ? "Backend relié" : "Mode démonstration"}
              </Badge>
              <h1 className="text-2xl font-bold tracking-tight text-text">
                PraeviSEO analyse déjà votre SEO avec Google
              </h1>
              <p className="mt-2 text-sm text-text-muted leading-7">
                Sans installer quoi que ce soit, PraeviSEO transforme déjà Google Search Console en priorités,
                opportunités, recommandations et signaux utiles à suivre régulièrement.
              </p>
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

        <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
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
              label: "Pages indexées",
              value: indexedPagesValue,
              icon: CheckCircle2,
              hint: dashboard.totals.indexedPagesSynced
                ? "pages vues comme indexées dans GSC"
                : "indexation Google pas encore synchronisée dans PraeviSEO",
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
              <CardDescription>CTR faible ou visibilité qui glisse : les signaux à surveiller vite.</CardDescription>
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

        <div id="opportunites" className="grid gap-6 xl:grid-cols-[1.2fr_0.8fr] scroll-mt-24">
          <Card>
            <CardHeader>
              <CardTitle>Sites suivis</CardTitle>
              <CardDescription>
                Vos sites, leur lecture SEO actuelle via Google Search Console et les prochains gains visibles.
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
                      <Badge variant="secondary">{formatSitePlatform(site.publication_mode)}</Badge>
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
                          ? `${site.summary.gsc_indexed_pages} page(s) indexée(s)`
                          : "Indexation Google non synchronisée"}
                      </span>
                      <span>{site.summary.pending_suggestions} recommandation(s) ouverte(s)</span>
                      <span>{site.readiness.gsc_connected ? "GSC reliée" : "GSC non reliée"}</span>
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
              {prioritySites.length === 0 ? (
                <div className="rounded-2xl border border-border bg-surface-2 px-4 py-4 text-sm text-text-muted">
                  Aucun blocage fort en ce moment. PraeviSEO continue de surveiller les signaux GSC utiles.
                </div>
              ) : (
                prioritySites.map((site) => (
                  <div key={site.site_id} className="rounded-2xl border border-border bg-surface-2 px-4 py-4">
                    <div className="flex items-center justify-between gap-3">
                      <div className="text-sm font-semibold text-text">{site.name}</div>
                      <Badge variant={site.next_action.priority === "high" ? "warning" : "secondary"}>
                        {site.next_action.priority === "high" ? "Priorité haute" : "À planifier"}
                      </Badge>
                    </div>
                    <p className="mt-2 text-sm text-text">
                      {site.next_action.kind === "connect_bridge"
                        ? site.readiness.gsc_connected
                          ? "Activer l automatisation premium"
                          : "Connecter Google Search Console"
                        : site.next_action.kind === "installation_requested"
                          ? "Automatisation premium en preparation"
                          : site.next_action.label}
                    </p>
                    <p className="mt-2 text-sm text-text-muted leading-6">
                      {site.next_action.kind === "connect_bridge" ? getPraeviseoClientDetail(site) : site.next_action.detail}
                    </p>
                    {site.next_action.kind === "connect_bridge" || site.next_action.kind === "installation_requested" ? (
                      <p className="mt-2 text-sm text-text-muted leading-6">
                        {site.readiness.gsc_connected
                          ? "Google est déjà branché. Cette étape sert seulement à laisser PraeviSEO agir directement sur votre site."
                          : "Commencez par connecter Google Search Console pour débloquer les premières opportunités et priorités SEO."}
                      </p>
                    ) : null}
                    <div className="mt-3">
                      <Button href={priorityHref(site)} variant="secondary" size="sm">
                        {priorityLabel(site)}
                      </Button>
                    </div>
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
                Les requêtes qui progressent, émergent ou méritent déjà une meilleure réponse.
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-3">
              {queryWatchlist.length === 0 ? (
                <div className="rounded-xl border border-border bg-surface-2 px-4 py-4 text-sm text-text-muted">
                  Aucune requête émergente forte pour l’instant. Le cockpit affichera ici les prochaines requêtes à
                  potentiel dès qu’elles montent dans GSC.
                </div>
              ) : (
                queryWatchlist.map((item) => (
                  <div key={`${item.site_name}-${item.query}-query`} className="rounded-xl border border-border px-4 py-3">
                    <div className="flex items-center justify-between gap-3">
                      <div>
                        <p className="text-sm font-semibold text-text">{item.query}</p>
                        <p className="text-xs text-text-subtle">{item.site_name}</p>
                      </div>
                      <Badge variant={item.position <= 10 ? "warning" : "success"}>
                        {item.position <= 10 ? "Déjà visible" : "À pousser"}
                      </Badge>
                    </div>
                    <p className="mt-2 text-sm text-text-muted">
                      {item.impressions} impressions, {item.clicks} clics, CTR {item.ctr.toFixed(1)} %, position {item.position.toFixed(1)}.
                    </p>
                    <div className="mt-3 flex flex-wrap gap-2 text-xs text-text-subtle">
                      <span className="rounded-full border border-border px-2.5 py-1">
                        Requête à potentiel
                      </span>
                    </div>
                  </div>
                ))
              )}
            </CardContent>
          </Card>
        </div>

        <div id="indexation" className="grid gap-6 xl:grid-cols-2 scroll-mt-24">
          <Card>
            <CardHeader>
              <CardTitle>Indexation</CardTitle>
              <CardDescription>
                Les pages que Google voit déjà et les sites où l’indexation reste à surveiller.
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-3">
              {indexationAlerts.length === 0 ? (
                <div className="rounded-xl border border-border bg-surface-2 px-4 py-4 text-sm text-text-muted">
                  Connectez au moins un site à Google Search Console pour ouvrir la lecture d’indexation.
                </div>
              ) : (
                indexationAlerts.map((item) => (
                  <div key={item.siteId} className="rounded-xl border border-border px-4 py-3">
                    <div className="flex items-center justify-between gap-3">
                      <div>
                        <p className="text-sm font-semibold text-text">{item.siteName}</p>
                        <p className="text-xs text-text-subtle">{item.siteId}</p>
                      </div>
                      <Badge variant={item.synced ? "success" : "secondary"}>
                        {item.synced ? "Indexation lue" : "En attente"}
                      </Badge>
                    </div>
                    <p className="mt-2 text-sm text-text-muted">
                      {item.synced
                        ? `${item.indexedPages} page(s) sont déjà vues comme indexées dans le cockpit PraeviSEO.`
                        : "L’indexation n’est pas encore synchronisée pour ce site."}
                    </p>
                  </div>
                ))
              )}
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Activité publication</CardTitle>
              <CardDescription>
                Les derniers contenus préparés par PraeviSEO ou déjà visibles sur le site.
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-3">
              {recentPublications.length === 0 ? (
                <div className="rounded-xl border border-border bg-surface-2 px-4 py-4 text-sm text-text-muted">
                  Aucune publication récente pour le moment. Le free reste déjà utile grâce aux signaux GSC, même
                  sans exécution sur le site.
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
                  </div>
                ))
              )}
            </CardContent>
          </Card>
        </div>

        <div id="activite" className="grid gap-6 xl:grid-cols-2 scroll-mt-24">
          <Card>
            <CardHeader>
              <CardTitle>Signaux GSC à surveiller</CardTitle>
              <CardDescription>
                Les mouvements utiles déjà détectés par PraeviSEO à partir des données Google les plus récentes.
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-3">
              {insightSignals.length === 0 ? (
                <div className="rounded-xl border border-border bg-surface-2 px-4 py-4 text-sm text-text-muted">
                  Aucun signal fort à surveiller pour le moment. Le cockpit continue de lire Google au fil des prochains imports.
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
              <CardTitle>Meilleures opportunités du moment</CardTitle>
              <CardDescription>
                Les pages ou requêtes qui donnent déjà envie de revenir sur PraeviSEO pour agir.
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-3">
              {topOpportunities.length === 0 ? (
                <div className="rounded-xl border border-border bg-surface-2 px-4 py-4 text-sm text-text-muted">
                  Aucune opportunité forte pour le moment. Les prochaines remontées GSC viendront enrichir ce bloc.
                </div>
              ) : (
                topOpportunities.map((item) => (
                  <div key={`${item.site_id}-${item.slug}-${item.type}`} className="rounded-xl border border-border px-4 py-3">
                    <div className="flex items-center justify-between gap-3">
                      <div>
                        <p className="text-sm font-semibold text-text">{item.label}</p>
                        <p className="text-xs text-text-subtle">{item.site_name}</p>
                      </div>
                      <Badge variant={item.priority_level === "high" ? "warning" : "secondary"}>
                        {item.priority_label}
                      </Badge>
                    </div>
                    <p className="mt-2 text-sm text-text-muted">{item.reason}</p>
                  </div>
                ))
              )}
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Activité SEO récente</CardTitle>
              <CardDescription>
                Le feed le plus vivant du cockpit : opportunités, recommandations et mouvements détectés par PraeviSEO.
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-3">
              {activityFeed.length === 0 ? (
                <div className="rounded-xl border border-border bg-surface-2 px-4 py-4 text-sm text-text-muted">
                  Le feed se remplira automatiquement dès les prochains imports GSC et les prochaines recommandations.
                </div>
              ) : (
                activityFeed.map((item) => (
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

          <Card>
            <CardHeader>
              <CardTitle>Recommandations récentes</CardTitle>
              <CardDescription>
                Les dernières recommandations déjà ouvertes à partir des signaux Google Search Console.
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-3">
              {recentOptimizations.map((item) => (
                <div key={item.id} className="rounded-xl border border-border px-4 py-3">
                  <div className="flex items-center justify-between gap-3">
                    <div>
                      <p className="text-sm font-semibold text-text">{item.page.title}</p>
                      <p className="text-xs text-text-subtle">{item.page.site_id}</p>
                    </div>
                    <Badge variant={item.status === "pending" ? "warning" : "secondary"}>
                      {item.status}
                    </Badge>
                  </div>
                  <p className="mt-2 text-sm text-text-muted">{item.summary}</p>
                </div>
              ))}
            </CardContent>
          </Card>
        </div>
      </div>
    </div>
  );
}
