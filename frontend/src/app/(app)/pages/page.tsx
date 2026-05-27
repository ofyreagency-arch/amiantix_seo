import { CockpitSectionNav } from "@/components/cockpit/section-nav";
import { CockpitMetricGrid } from "@/components/cockpit/metric-grid";
import { CockpitSignalItem, CockpitSignalListCard } from "@/components/cockpit/signal-list";
import { Topbar } from "@/components/layout/topbar";
import { getDashboard, getOptimizations, getPublications } from "@/lib/praeviseo-api";
import { formatDate } from "@/lib/utils";

export default async function PagesCockpitPage() {
  const dashboard = await getDashboard();
  const optimizations = await getOptimizations();
  const publications = await getPublications();
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
      title: item.label,
      site_name: item.site_name,
      badge: "Pilier potentiel",
      badgeTone: "success" as const,
      description: item.cluster_label
        ? `Cluster ${item.cluster_label}, autorité ${item.authority_score}, potentiel pilier ${item.pillar_likelihood}, ${item.internal_inlinks} lien(s) entrant(s).`
        : `Autorité ${item.authority_score}, potentiel pilier ${item.pillar_likelihood}, ${item.internal_inlinks} lien(s) entrant(s).`,
    })),
    ...observedLinkGapPages.map((item) => ({
      id: `${item.site_id}-${item.slug}-link-gap-priority`,
      title: item.label,
      site_name: item.site_name,
      badge: "Sous-maillée",
      badgeTone: "warning" as const,
      description: `Autorité ${item.authority_score}, seulement ${item.internal_inlinks} lien(s) entrant(s), ${item.internal_outlinks} sortant(s).`,
    })),
    ...observedOrphanAlerts.map((item) => ({
      id: `${item.site_id}-${item.slug}-orphan-priority`,
      title: item.label,
      site_name: item.site_name,
      badge: "Orpheline",
      badgeTone: "danger" as const,
      description: `Score orphelin ${item.orphan_score}, autorité ${item.authority_score}, indexabilité ${item.indexability_state}.`,
    })),
    ...observedWeakPages.map((item) => ({
      id: `${item.site_id}-${item.slug}-weak-priority`,
      title: item.label,
      site_name: item.site_name,
      badge: "Faible",
      badgeTone: "secondary" as const,
      description:
        item.indexability_state !== "indexable"
          ? `Page encore peu claire pour Google (${item.indexability_state}), ${item.latest_word_count} mots observés.`
          : `${item.latest_word_count} mots observés, autorité ${item.authority_score}, maillage encore discret.`,
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
      reason: `Autorité ${item.authority_score}, score orphelin ${item.orphan_score}, ${item.internal_inlinks} lien(s) interne(s).`,
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
        (item.seo_score ?? 0) < 80 ||
        (!item.published_live && item.status === "published") ||
        ((item.gsc_metrics.position ?? 99) >= 8 && item.gsc_metrics.impressions > 0)
    )
    .slice(0, 6);
  const scoredPages = [...publications.items]
    .sort((a, b) => {
      const aScore = (a.seo_score ?? 0) + (a.topical_score ?? 0) + (a.quality_score ?? 0);
      const bScore = (b.seo_score ?? 0) + (b.topical_score ?? 0) + (b.quality_score ?? 0);

      return bScore - aScore;
    })
    .filter((item) => (item.seo_score ?? 0) > 0 || (item.topical_score ?? 0) > 0 || (item.quality_score ?? 0) > 0)
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
        ? `Cluster ${item.cluster_label}, autorité ${item.authority_score}, potentiel pilier ${item.pillar_likelihood}.`
        : `Autorité ${item.authority_score}, potentiel pilier ${item.pillar_likelihood}.`,
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
        ? `Google ne confirme pas encore clairement cette page (${item.indexability_state}).`
        : `La page reste légère ou trop discrète (${item.latest_word_count} mots, autorité ${item.authority_score}).`,
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
            reason: `+${leadRisingPage.delta_impressions} impressions, CTR ${leadRisingPage.ctr.toFixed(1)} %, position ${leadRisingPage.position.toFixed(1)}.`,
            whyNow: "la page gagne déjà du terrain et mérite d’être consolidée pendant qu’elle monte",
          }
        : leadBestPage
          ? {
              title: leadBestPage.label,
              site_name: leadBestPage.site_name,
              badge: `${leadBestPage.impressions} impressions`,
              reason: `CTR ${leadBestPage.ctr.toFixed(1)} %, position ${leadBestPage.position.toFixed(1)}, ${leadBestPage.previous_impressions} impressions avant.`,
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
              ? `Cluster ${observedPillarPages[0].cluster_label ?? "n/a"}, autorité ${observedPillarPages[0].authority_score}, potentiel pilier ${observedPillarPages[0].pillar_likelihood}.`
              : ""),
        }
      : null;

  return (
    <div className="min-h-screen">
      <Topbar
        title="Pages"
        subtitle="La lecture page par page de votre SEO : progression, baisses, potentiel et pages à surveiller."
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
          <h1 className="text-2xl font-bold tracking-tight text-text">Vos pages SEO les plus importantes en un coup d’œil</h1>
          <p className="mt-2 max-w-3xl text-sm leading-7 text-text-muted">
            PraeviSEO transforme déjà Google Search Console en lecture claire : quelles pages progressent, lesquelles
            ralentissent, et où agir vite.
          </p>
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
                ? `${observedPagesTotal} URL${observedPagesTotal > 1 ? "s" : ""} observée${observedPagesTotal > 1 ? "s" : ""} permettent déjà de lire l’autorité, le maillage et les pages encore fragiles.`
                : "PraeviSEO enrichira encore cette vue dès qu’il aura observé plus de pages directement sur le site."}
            </p>
            <p>
              {observedPriorityPages.length > 0
                ? `${observedPriorityPages.length} page${observedPriorityPages.length > 1 ? "s" : ""} ont déjà une vraie priorité structurelle : pilier à pousser, maillage à ouvrir ou faiblesse à corriger.`
                : "Aucune priorité structurelle forte n’apparaît encore : la lecture reste surtout portée par Google Search Console pour le moment."}
            </p>
          </div>
        </div>

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
                label: "Pages orphelines",
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
                      ? "une page plus forte pour porter un cluster entier"
                      : leadStructuralPage.badge === "Sous-maillée"
                        ? "un meilleur transfert d’autorité depuis le reste du site"
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
            description="Les pages que PraeviSEO priorise déjà par structure réelle : piliers, sous-maillage, orphelines et faiblesses de fond."
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
              />
            ))}
          </CockpitSignalListCard>

          <CockpitSignalListCard
            title="Pages piliers et sous-maillées"
            description="La lecture la plus produit utile : quelles pages peuvent porter un cluster, et lesquelles manquent encore de soutien interne."
            empty={observedPillarPages.length + observedLinkGapPages.length === 0}
            emptyMessage="Aucune page pilier ou sous-maillée forte pour le moment."
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
                    ? `Potentiel pilier ${item.pillar_likelihood}, autorité ${item.authority_score}, cluster ${item.cluster_label ?? "n/a"}.`
                    : `Seulement ${item.internal_inlinks} lien(s) entrant(s) pour une page déjà utile, autorité ${item.authority_score}.`
                }
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
                description={`+${item.delta_impressions} impressions, CTR ${item.ctr.toFixed(1)} %, position ${item.position.toFixed(1)}.`}
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
                description={`${item.delta_impressions} impressions, CTR ${item.ctr.toFixed(1)} %, position ${item.position.toFixed(1)}.`}
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
                badge={`${item.impressions} impressions`}
                description={`CTR ${item.ctr.toFixed(1)} %, position ${item.position.toFixed(1)}, ${item.previous_impressions} impressions avant.`}
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
                badge={item.seo_score ? `SEO ${item.seo_score}` : "À consolider"}
                badgeTone={item.seo_score ? ((item.seo_score ?? 0) >= 80 ? "success" : "secondary") : "warning"}
                description={
                  item.seo_score
                    ? `Topical ${item.topical_score ?? "n/a"}, qualité ${item.quality_score ?? "n/a"}, indexabilité ${item.indexability_score ?? "n/a"}.`
                    : "Cette page montre déjà un signal utile dans Google et mérite une consolidation éditoriale."
                }
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
                badge={item.latest_suggestion ? "refresh conseillé" : item.published_live ? "à consolider" : "à pousser"}
                badgeTone={item.latest_suggestion ? "warning" : item.published_live ? "secondary" : "success"}
                description={
                  item.latest_suggestion?.summary ??
                  ("reason" in item && typeof item.reason === "string" && item.reason.length > 0
                    ? item.reason
                    : `${item.gsc_metrics.impressions} impressions, CTR ${item.gsc_metrics.ctr.toFixed(1)} %, position ${item.gsc_metrics.position?.toFixed(1) ?? "n/a"}, SEO score ${item.seo_score ?? "n/a"}.`
                  )
                }
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
              />
            ))}
          </CockpitSignalListCard>
        </div>
      </div>
    </div>
  );
}
