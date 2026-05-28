import { CockpitSectionNav } from "@/components/cockpit/section-nav";
import { CockpitMetricGrid } from "@/components/cockpit/metric-grid";
import { CockpitSignalItem, CockpitSignalListCard } from "@/components/cockpit/signal-list";
import { Topbar } from "@/components/layout/topbar";
import {
  getClientRecommendationBadge,
  getClientRecommendationText,
  getClientRecommendationTitle,
  getDashboard,
  getOptimizations,
  getPublications,
} from "@/lib/praeviseo-api";
import { formatDate } from "@/lib/utils";

export default async function ActivityCockpitPage() {
  const dashboard = await getDashboard();
  const optimizations = await getOptimizations();
  const publications = await getPublications();
  const normalizeQuery = (value: string) => value.trim().toLowerCase();
  const observedRecommendations = optimizations.recommendations.items;
  const observedContentFeed = publications.items.filter((item) => !!item.observed_content);
  const observedQueryMatches = observedContentFeed
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
  const queryMovementFeed = dashboard.sites.flatMap((site) => [
    ...site.summary.top_rising_queries.map((item) => ({ ...item, site_id: site.site_id, site_name: site.name, trend: "up" as const })),
    ...site.summary.new_queries.map((item) => ({ ...item, site_id: site.site_id, site_name: site.name, trend: "new" as const })),
  ]);
  const linkedQueryFeed = queryMovementFeed
    .map((item) => ({
      ...item,
      linkedPublication: findLinkedPublication(item.query, item.site_id),
    }))
    .filter((item) => !!item.linkedPublication)
    .slice(0, 6);
  const indexationFeed = dashboard.sites.flatMap((site) =>
    site.summary.indexation_alerts.map((item) => ({
      ...item,
      site_name: site.name,
    }))
  );
  const publicationSuggestionFeed = publications.items
    .filter((item) => !!item.latest_suggestion)
    .map((item) => ({
      id: `content-suggestion-${item.id}`,
      title: item.title,
      detail: item.latest_suggestion?.summary ?? "Suggestion éditoriale détectée.",
      badge: "Refresh conseillé",
      badgeVariant: "warning" as const,
      meta: item.latest_suggestion?.created_at ? formatDate(item.latest_suggestion.created_at) : item.site_id,
      timestamp: item.latest_suggestion?.created_at ? new Date(item.latest_suggestion.created_at).getTime() : 0,
    }));
  const linkingFeed = observedContentFeed
    .filter((item) => (item.observed_content?.internal_link_suggestions_count ?? 0) > 0)
    .map((item) => ({
      id: `content-linking-${item.id}`,
      title: item.title,
      detail: item.observed_content?.top_internal_link_target
        ? `PraeviSEO voit ${item.observed_content.internal_link_suggestions_count} ouverture(s) de maillage, dont ${item.observed_content.top_internal_link_target}.`
        : `PraeviSEO voit ${item.observed_content?.internal_link_suggestions_count ?? 0} ouverture(s) de maillage sur cette page.`,
      badge: "Maillage",
      badgeVariant: "warning" as const,
      meta: `${item.site_id} · contenu observé`,
      timestamp: item.observed_content?.snapshot_observed_at ? new Date(item.observed_content.snapshot_observed_at).getTime() : 0,
    }));
  const cannibalizationFeed = observedContentFeed
    .filter(
      (item) =>
        (item.observed_content?.cannibalization_count ?? 0) > 0 ||
        (item.observed_content?.overlap_count ?? 0) > 0
    )
    .map((item) => ({
      id: `content-cannibalization-${item.id}`,
      title: item.title,
      detail: item.observed_content?.top_cannibalization_target
        ? `Sujet à clarifier face à ${item.observed_content.top_cannibalization_target}. Overlap ${item.observed_content.overlap_score} / 100.`
        : `PraeviSEO garde ${item.observed_content?.overlap_count ?? 0} recouvrement(s) sous surveillance sur ce contenu.`,
      badge: "Cannibalisation",
      badgeVariant: "warning" as const,
      meta: `${item.site_id} · contenu observé`,
      timestamp: item.observed_content?.snapshot_observed_at ? new Date(item.observed_content.snapshot_observed_at).getTime() : 0,
    }));
  const enrichmentFeed = observedContentFeed
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
    .map((item) => ({
      id: `content-enrichment-${item.id}`,
      title: item.title,
      detail: item.latest_suggestion?.summary ??
        `Contenu à enrichir : ${item.observed_content?.snapshot_word_count ?? 0} mots observés, ${item.observed_content?.query_match_count ?? 0} requête(s) déjà reliée(s).`,
      badge: "Contenu à enrichir",
      badgeVariant: "secondary" as const,
      meta: `${item.site_id} · contenu observé`,
      timestamp: item.observed_content?.snapshot_observed_at ? new Date(item.observed_content.snapshot_observed_at).getTime() : 0,
    }));
  const opportunityFeed = optimizations.gsc_opportunities.items.slice(0, 6).map((item) => ({
    id: `opportunity-card-${item.site_id}-${item.slug}-${item.type}`,
    title: item.label,
    subtitle: item.site_name,
    badge: item.priority_label,
    badgeTone: item.priority_level === "high" ? "warning" : "secondary",
    description: item.reason,
  }));
  const actionPlanFeed = [
    ...observedRecommendations.slice(0, 6).map((item) => ({
      id: `plan-${item.id}`,
      title: getClientRecommendationTitle(item.title),
      subtitle: `${item.site_id}${item.cluster ? ` · ${item.cluster}` : ""}`,
      badge: getClientRecommendationBadge(item.priority),
      badgeTone: item.priority <= 30 ? ("warning" as const) : ("secondary" as const),
      description: getClientRecommendationText(item.suggested_action ?? item.reasoning),
    })),
    ...optimizations.gsc_opportunities.items.slice(0, 6).map((item) => ({
      id: `plan-opportunity-${item.site_id}-${item.slug}-${item.type}`,
      title: item.label,
      subtitle: item.site_name,
      badge: item.priority_label,
      badgeTone: item.priority_level === "high" ? ("warning" as const) : ("secondary" as const),
      description: `${item.reason} Action suggérée : ${item.action}.`,
    })),
  ].slice(0, 8);
  const totalDeltaImpressions = dashboard.sites.reduce((sum, site) => sum + site.summary.gsc_delta_impressions, 0);
  const totalDeltaClicks = dashboard.sites.reduce((sum, site) => sum + site.summary.gsc_delta_clicks, 0);
  const progressFeed = [
    totalDeltaImpressions > 0
      ? `La visibilité remonte avec +${new Intl.NumberFormat("fr-FR").format(totalDeltaImpressions)} impression(s) sur la dernière période suivie.`
      : totalDeltaImpressions < 0
        ? `La visibilité recule de ${new Intl.NumberFormat("fr-FR").format(Math.abs(totalDeltaImpressions))} impression(s) sur la dernière période suivie.`
        : "La visibilité reste stable sur la dernière lecture GSC.",
    totalDeltaClicks > 0
      ? `Les clics progressent aussi avec +${new Intl.NumberFormat("fr-FR").format(totalDeltaClicks)} clic(s) sur la période.`
      : totalDeltaClicks < 0
        ? `Les clics reculent de ${new Intl.NumberFormat("fr-FR").format(Math.abs(totalDeltaClicks))} clic(s) sur la période.`
        : "Le volume de clics reste stable pour le moment.",
    linkedQueryFeed.length > 0
      ? `${linkedQueryFeed.length} requête(s) sont déjà reliée(s) à une page observée, ce qui clarifie la cible éditoriale.`
      : "PraeviSEO attend encore les prochains liens nets entre requêtes et pages observées.",
    actionPlanFeed.length > 0
      ? `${actionPlanFeed.length} action(s) sont déjà prêtes à être traitées dans le cockpit.`
      : "Le moteur continue de préparer les prochaines actions utiles.",
    linkingFeed.length + cannibalizationFeed.length + enrichmentFeed.length > 0
      ? `${linkingFeed.length + cannibalizationFeed.length + enrichmentFeed.length} piste(s) contenu complètent déjà la simple lecture GSC.`
      : "Le bloc contenu s’enrichira dès que le moteur observera plus de matière sur les pages suivies.",
  ];

  const timelineFeed = [
    ...dashboard.sites
      .filter((site) => site.gsc_last_sync_at)
      .map((site) => ({
        id: `sync-${site.site_id}`,
        title: `${site.name} relu par Google`,
        detail:
          site.summary.gsc_delta_impressions > 0
            ? `+${new Intl.NumberFormat("fr-FR").format(site.summary.gsc_delta_impressions)} impressions sur la dernière période.`
            : site.summary.gsc_delta_impressions < 0
              ? `${new Intl.NumberFormat("fr-FR").format(site.summary.gsc_delta_impressions)} impressions sur la dernière période.`
              : "Le volume d’impressions reste stable sur la dernière période.",
        badge: "Import GSC",
        badgeVariant:
          site.summary.gsc_delta_impressions < 0 ? "danger" : site.summary.gsc_delta_impressions > 0 ? "success" : "secondary",
        meta: formatDate(site.gsc_last_sync_at as string),
        timestamp: new Date(site.gsc_last_sync_at as string).getTime(),
      })),
    ...optimizations.gsc_opportunities.items.slice(0, 6).map((item) => ({
      id: `opportunity-${item.site_id}-${item.slug}-${item.type}`,
      title: item.label,
      detail: item.reason,
      badge: item.priority_label,
      badgeVariant: item.priority_level === "high" ? "warning" : "secondary",
      meta: `${item.site_name} · opportunité détectée`,
      timestamp: 0,
    })),
    ...optimizations.items.slice(0, 6).map((item) => ({
      id: `optimization-${item.id}`,
      title: item.page.title,
      detail: item.summary,
      badge: item.status === "pending" ? "Reco ouverte" : "Reco suivie",
      badgeVariant: item.status === "pending" ? "warning" : "secondary",
      meta: item.created_at ? formatDate(item.created_at) : "Récemment",
      timestamp: item.created_at ? new Date(item.created_at).getTime() : 0,
    })),
    ...publications.items.slice(0, 6).map((item) => ({
      id: `publication-${item.id}`,
      title: item.title,
      detail: item.published_live
        ? "Le contenu est déjà visible sur le site."
        : "Le contenu reste prêt côté PraeviSEO.",
      badge: item.published_live ? "Visible" : "Préparé",
      badgeVariant: item.published_live ? "success" : "secondary",
      meta: item.published_at ? formatDate(item.published_at) : "Récemment",
      timestamp: item.published_at ? new Date(item.published_at).getTime() : 0,
    })),
    ...queryMovementFeed.slice(0, 6).map((item) => ({
      id: `query-${item.site_name}-${item.query}-${item.trend}`,
      title: item.query,
      detail:
        item.trend === "new"
          ? `Nouvelle requête détectée avec ${item.impressions} impressions et une position moyenne de ${item.position.toFixed(1)}.`
          : `La requête gagne ${item.delta_impressions} impressions sur la période récente.`,
      badge: item.trend === "new" ? "Nouvelle requête" : "Requête en hausse",
      badgeVariant: "success" as const,
      meta: (() => {
        const linkedPublication = findLinkedPublication(item.query, item.site_id);

        return linkedPublication
          ? `${item.site_name} · page liée ${linkedPublication.title}`
          : `${item.site_name} · données Google`;
      })(),
      timestamp: 0,
    })),
    ...indexationFeed.slice(0, 6).map((item) => ({
      id: `indexation-${item.site_name}-${item.slug}`,
      title: item.label,
      detail: item.detail,
      badge: "Indexation",
      badgeVariant: "warning" as const,
      meta: `${item.site_name} · point à vérifier dans Google`,
      timestamp: 0,
    })),
    ...publicationSuggestionFeed.slice(0, 6),
    ...linkingFeed.slice(0, 6),
    ...cannibalizationFeed.slice(0, 6),
    ...enrichmentFeed.slice(0, 6),
    ...observedRecommendations.slice(0, 6).map((item) => ({
      id: `observed-recommendation-${item.id}`,
      title: getClientRecommendationTitle(item.title),
      detail: getClientRecommendationText(item.suggested_action ?? item.reasoning),
      badge: getClientRecommendationBadge(item.priority),
      badgeVariant: item.priority <= 30 ? "warning" as const : "secondary" as const,
      meta: item.generated_at ? formatDate(item.generated_at) : `${item.site_id} · moteur observé`,
      timestamp: item.generated_at ? new Date(item.generated_at).getTime() : 0,
    })),
  ]
    .sort((a, b) => b.timestamp - a.timestamp)
    .slice(0, 12);

  const alertFeed = [
    ...optimizations.gsc_opportunities.items
      .filter((item) => item.type === "low_ctr" || item.type === "sustained_drop")
      .map((item) => ({
        id: `${item.site_id}-${item.slug}-${item.type}-alert`,
        title: item.label,
        subtitle: item.site_name,
        badge: item.priority_label,
        badgeTone: item.type === "sustained_drop" ? "danger" : "warning",
        description: item.reason,
      })),
    ...indexationFeed.slice(0, 4).map((item) => ({
      id: `${item.site_name}-${item.slug}-index-alert`,
      title: item.label,
      subtitle: item.site_name,
      badge: "Indexation",
      badgeTone: "warning" as const,
      description: item.detail,
    })),
    ...publications.items
      .filter((item) => !!item.latest_suggestion)
      .slice(0, 4)
      .map((item) => ({
        id: `${item.site_id}-${item.slug}-refresh-alert`,
        title: item.title,
        subtitle: item.site_id,
        badge: "Refresh",
        badgeTone: "warning" as const,
        description: item.latest_suggestion?.summary ?? "Un refresh éditorial est recommandé.",
      })),
    ...cannibalizationFeed.slice(0, 4).map((item) => ({
      id: `${item.id}-alert`,
      title: item.title,
      subtitle: item.meta,
      badge: item.badge,
      badgeTone: "warning" as const,
      description: item.detail,
    })),
    ...linkingFeed.slice(0, 4).map((item) => ({
      id: `${item.id}-alert`,
      title: item.title,
      subtitle: item.meta,
      badge: item.badge,
      badgeTone: "secondary" as const,
      description: item.detail,
    })),
    ...observedRecommendations.slice(0, 4).map((item) => ({
      id: `observed-alert-${item.id}`,
      title: item.title,
      subtitle: item.site_id,
      badge: item.priority <= 30 ? "À faire en premier" : "Action recommandée",
      badgeTone: item.priority <= 30 ? "warning" as const : "secondary" as const,
      description: item.suggested_action ?? item.reasoning,
    })),
  ].slice(0, 8);
  const movementFeed = dashboard.sites.flatMap((site) => [
    ...site.summary.top_rising_pages.map((item) => ({ ...item, site_name: site.name, trend: "up" as const })),
    ...site.summary.top_falling_pages.map((item) => ({ ...item, site_name: site.name, trend: "down" as const })),
  ]).slice(0, 8);

  return (
    <div className="min-h-screen">
      <Topbar
        title="Activité SEO"
        subtitle="Le feed vivant du cockpit : alertes, variations, mouvements et nouvelles opportunités détectées."
      />

      <div className="p-6 space-y-6">
        <CockpitSectionNav
          items={[
            { label: "Vue d’ensemble", href: "#vue-ensemble", count: timelineFeed.length, tone: "default" },
            { label: "Mouvements", href: "#mouvements", count: movementFeed.length, tone: "success" },
            { label: "Requêtes", href: "#requetes", count: queryMovementFeed.length, tone: "success" },
            { label: "Plan d’action", href: "#plan-action", count: actionPlanFeed.length, tone: "warning" },
            { label: "Alertes", href: "#alertes", count: alertFeed.length, tone: "warning" },
            { label: "Contenu", href: "#contenu", count: linkingFeed.length + cannibalizationFeed.length + enrichmentFeed.length, tone: "secondary" },
            { label: "Timeline", href: "#timeline", count: timelineFeed.length, tone: "secondary" },
          ]}
        />

        <div className="rounded-2xl border border-brand/20 bg-brand-muted px-6 py-6">
          <h1 className="text-2xl font-bold tracking-tight text-text">Le feed vivant de votre SEO</h1>
          <p className="mt-2 max-w-3xl text-sm leading-7 text-text-muted">
            PraeviSEO donne ici une sensation de mouvement continu : variations Google, opportunités détectées,
            recommandations ouvertes et activité récente du cockpit.
          </p>
          {(freshestSyncAt || freshestDataAsOf) && (
            <p className="mt-3 text-xs text-text-subtle">
              {freshestSyncAt ? `Dernière synchro GSC : ${formatDate(freshestSyncAt)}.` : "Synchronisation GSC en attente."}{" "}
              {freshestDataAsOf ? `Données arrêtées au ${formatDate(freshestDataAsOf)}.` : ""}
            </p>
          )}
          <p className="mt-3 max-w-3xl text-xs leading-6 text-text-subtle">
            L’activité affichée ici dépend du dernier import GSC disponible. Sur un site à faible volume, une période
            calme ne signifie pas une panne : elle reflète simplement peu de mouvements détectables par Google.
          </p>
        </div>

        <div id="vue-ensemble" className="scroll-mt-24">
          <CockpitMetricGrid
            items={[
              { label: "Événements récents", value: timelineFeed.length },
              { label: "Mouvements de pages", value: movementFeed.length, tone: "success" },
              { label: "Requêtes en mouvement", value: queryMovementFeed.length, tone: "success" },
              { label: "Requêtes déjà reliées", value: linkedQueryFeed.length, tone: "secondary" },
              { label: "Alertes actives", value: alertFeed.length, tone: alertFeed.length > 0 ? "warning" : "secondary" },
              { label: "Actions recommandées", value: optimizations.recommendations.summary.total, tone: optimizations.recommendations.summary.total > 0 ? "warning" : "secondary" },
              { label: "Signaux contenu", value: linkingFeed.length + cannibalizationFeed.length + enrichmentFeed.length, tone: linkingFeed.length + cannibalizationFeed.length + enrichmentFeed.length > 0 ? "secondary" : "secondary" },
            ]}
          />
        </div>

        <div id="mouvements" className="grid gap-6 xl:grid-cols-3 scroll-mt-24">
          <CockpitSignalListCard
            title="Mouvements récents"
            description="Les pages qui montent ou qui ralentissent le plus en ce moment."
            empty={movementFeed.length === 0}
            emptyMessage="Aucun mouvement fort pour le moment. Les prochains imports Google animeront ce bloc."
          >
            {movementFeed.map((item) => (
              <CockpitSignalItem
                key={`${item.site_name}-${item.slug}-${item.trend}`}
                title={item.label}
                subtitle={item.site_name}
                badge={item.trend === "down" ? "En baisse" : "En hausse"}
                badgeTone={item.trend === "down" ? "danger" : "success"}
                description={`${item.delta_impressions > 0 ? "+" : ""}${item.delta_impressions} impressions, CTR ${item.ctr.toFixed(1)} %, position ${item.position.toFixed(1)}.`}
              />
            ))}
          </CockpitSignalListCard>

          <CockpitSignalListCard
            id="requetes"
            className="scroll-mt-24"
            title="Requêtes qui bougent"
            description="Les recherches Google qui progressent ou qui commencent juste à apparaître."
            empty={queryMovementFeed.length === 0}
            emptyMessage="Aucun mouvement de requête fort pour le moment. Avec peu de volume GSC, plusieurs lectures peuvent rester calmes avant la prochaine progression."
          >
            {queryMovementFeed.slice(0, 8).map((item) => (
              <CockpitSignalItem
                key={`${item.site_name}-${item.query}-${item.trend}`}
                title={item.query}
                subtitle={item.site_name}
                badge={item.trend === "new" ? "Nouvelle requête" : "En hausse"}
                badgeTone="success"
                description={
                  item.trend === "new"
                    ? `${item.impressions} impressions, position ${item.position.toFixed(1)}.`
                    : `+${item.delta_impressions} impressions, CTR ${item.ctr.toFixed(1)} %, position ${item.position.toFixed(1)}.`
                }
              />
            ))}
          </CockpitSignalListCard>

          <CockpitSignalListCard
            title="Ce qui progresse vraiment"
            description="Les points les plus utiles pour comprendre ce qui progresse, ce qui ralentit et quoi faire ensuite."
            empty={progressFeed.length === 0}
            emptyMessage="Le cockpit remplira cette zone dès que les prochaines variations deviendront lisibles."
          >
            {progressFeed.map((item) => (
              <div key={item} className="rounded-xl border border-border px-4 py-3 text-sm text-text-muted">
                {item}
              </div>
            ))}
          </CockpitSignalListCard>

          <CockpitSignalListCard
            id="plan-action"
            className="scroll-mt-24"
            title="Quoi traiter ensuite"
            description="Le plan d’action le plus utile déjà prêt entre opportunités Google et recommandations moteur."
            empty={actionPlanFeed.length === 0}
            emptyMessage="Aucune action prioritaire forte pour le moment. Le moteur enrichira automatiquement ce bloc au prochain cycle utile."
          >
            {actionPlanFeed.map((item) => (
              <CockpitSignalItem
                key={item.id}
                title={item.title}
                subtitle={item.subtitle}
                badge={item.badge}
                badgeTone={item.badgeTone}
                description={item.description}
              />
            ))}
          </CockpitSignalListCard>

          <CockpitSignalListCard
            title="Opportunités qui viennent d’ouvrir"
            description="Les opportunités GSC les plus fraîches que PraeviSEO garde déjà en tête."
            empty={opportunityFeed.length === 0}
            emptyMessage="Aucune nouvelle opportunité forte pour le moment. Les prochains signaux utiles remonteront ici dès qu’ils dépasseront le bruit naturel."
          >
            {opportunityFeed.map((item) => (
              <CockpitSignalItem
                key={item.id}
                title={item.title}
                subtitle={item.subtitle}
                badge={item.badge}
                badgeTone={item.badgeTone as "success" | "warning" | "secondary" | "danger"}
                description={item.description}
              />
            ))}
          </CockpitSignalListCard>

          <CockpitSignalListCard
            id="alertes"
            className="scroll-mt-24"
            title="Alertes simples"
            description="CTR faible, recul durable ou baisse de visibilité : les signaux à traiter vite."
            empty={alertFeed.length === 0}
            emptyMessage="Aucune alerte forte pour le moment. Le cockpit reste en veille active et rouvrira ce bloc dès qu’un signal devient utile."
          >
            {alertFeed.map((item) => (
              <CockpitSignalItem
                key={item.id}
                title={item.title}
                subtitle={item.subtitle}
                badge={item.badge}
                badgeTone={item.badgeTone as "success" | "warning" | "secondary" | "danger"}
                description={item.description}
              />
            ))}
          </CockpitSignalListCard>
        </div>

        <div id="contenu" className="grid gap-6 xl:grid-cols-3 scroll-mt-24">
          <CockpitSignalListCard
            title="Maillage détecté"
            description="Les contenus où PraeviSEO voit déjà des liens internes utiles à ouvrir."
            empty={linkingFeed.length === 0}
            emptyMessage="Aucun besoin de maillage fort pour le moment."
          >
            {linkingFeed.slice(0, 6).map((item) => (
              <CockpitSignalItem
                key={item.id}
                title={item.title}
                subtitle={item.meta}
                badge={item.badge}
                badgeTone="warning"
                description={item.detail}
              />
            ))}
          </CockpitSignalListCard>

          <CockpitSignalListCard
            title="Cannibalisation observée"
            description="Les contenus qui se recouvrent ou qui méritent une clarification éditoriale."
            empty={cannibalizationFeed.length === 0}
            emptyMessage="Aucun recouvrement fort détecté pour le moment."
          >
            {cannibalizationFeed.slice(0, 6).map((item) => (
              <CockpitSignalItem
                key={item.id}
                title={item.title}
                subtitle={item.meta}
                badge={item.badge}
                badgeTone="warning"
                description={item.detail}
              />
            ))}
          </CockpitSignalListCard>

          <CockpitSignalListCard
            title="Contenus à enrichir"
            description="Les pages qui ont déjà une base SEO exploitable, mais qui peuvent encore gagner en profondeur."
            empty={enrichmentFeed.length === 0}
            emptyMessage="Aucun enrichissement fort détecté pour le moment."
          >
            {enrichmentFeed.slice(0, 6).map((item) => (
              <CockpitSignalItem
                key={item.id}
                title={item.title}
                subtitle={item.meta}
                badge={item.badge}
                badgeTone="secondary"
                description={item.detail}
              />
            ))}
          </CockpitSignalListCard>
        </div>

        <CockpitSignalListCard
          id="timeline"
          className="scroll-mt-24"
          title="Timeline SEO"
          description="Progression, historique et activité détectée récemment dans PraeviSEO."
          empty={timelineFeed.length === 0}
          emptyMessage="La timeline se remplira automatiquement avec les prochaines variations SEO."
        >
          {timelineFeed.map((item) => (
            <CockpitSignalItem
              key={item.id}
              title={item.title}
              subtitle={item.meta}
              badge={item.badge}
              badgeTone={item.badgeVariant as "success" | "warning" | "secondary" | "danger"}
              description={item.detail}
            />
          ))}
        </CockpitSignalListCard>
      </div>
    </div>
  );
}
