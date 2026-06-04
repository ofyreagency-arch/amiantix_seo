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

export default async function QueriesCockpitPage({ searchParams }: { searchParams?: PageSearchParams }) {
  const dashboard = await getDashboard();
  const optimizations = await getOptimizations();
  const publications = await getPublications();
  const resolvedSearchParams = searchParams ? await searchParams : {};
  const focus = getValue(resolvedSearchParams.focus);
  const focusSite = getValue(resolvedSearchParams.site);
  const focusQuery = getValue(resolvedSearchParams.query);
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
  const normalizeQuery = (value: string) => value.trim().toLowerCase();
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

  const topQueries = dashboard.sites
    .flatMap((site) => site.summary.top_queries.map((item) => ({ ...item, site_id: site.site_id, site_name: site.name })))
    .slice(0, 12);
  const visibleQueries = topQueries.filter((item) => item.position <= 10).slice(0, 6);
  const risingQueries = dashboard.sites
    .flatMap((site) => site.summary.top_rising_queries.map((item) => ({ ...item, site_id: site.site_id, site_name: site.name })))
    .slice(0, 6);
  const potentialQueries = topQueries.filter((item) => item.position > 10 || item.impressions >= 10).slice(0, 6);
  const newQueries = dashboard.sites
    .flatMap((site) => site.summary.new_queries.map((item) => ({ ...item, site_id: site.site_id, site_name: site.name })))
    .slice(0, 6);
  const emergingQueries = [
    ...newQueries,
    ...optimizations.gsc_opportunities.items
      .filter((item) => item.type === "emerging_query" && item.query)
      .map((item) => ({
        query: item.query ?? "Requête suivie",
        impressions: Number(item.metrics.impressions ?? 0),
        previous_impressions: 0,
        delta_impressions: Number(item.metrics.impressions ?? 0),
        delta_percent: 100,
        clicks: Number(item.metrics.clicks ?? 0),
        ctr: Number(item.metrics.ctr ?? 0),
        position: Number(item.metrics.position ?? 0),
        site_id: item.site_id,
        site_name: item.site_name,
      })),
  ].slice(0, 6);
  const linkedQueryItems = observedQueryMatches
    .filter((item) => !!item.observed_content?.top_query_match)
    .slice(0, 6);
  const lowQueryVolume =
    topQueries.length < 3 &&
    visibleQueries.length === 0 &&
    risingQueries.length === 0 &&
    linkedQueryItems.length === 0;
  const queryRadar = [
    ...visibleQueries.map((item) => ({
      site_id: item.site_id,
      title: item.query,
      subtitle: (() => {
        const linkedPublication = findLinkedPublication(item.query, item.site_id);

        return linkedPublication
          ? `${item.site_name} · déjà reliée à ${linkedPublication.title}`
          : `${item.site_name} · déjà visible dans Google`;
      })(),
      badge: "Priorité visibilité",
      badgeTone: "success" as const,
      description: `${item.impressions} impressions, ${item.clicks} clics, position ${item.position.toFixed(1)}.`,
    })),
    ...risingQueries.map((item) => ({
      site_id: item.site_id,
      title: item.query,
      subtitle: (() => {
        const linkedPublication = findLinkedPublication(item.query, item.site_id);

        return linkedPublication
          ? `${item.site_name} · accélération sur ${linkedPublication.title}`
          : `${item.site_name} · accélération détectée`;
      })(),
      badge: `+${item.delta_impressions} impressions`,
      badgeTone: "success" as const,
      description: `La requête monte depuis ${item.previous_impressions} impressions avec une position moyenne ${item.position.toFixed(1)}.`,
    })),
    ...potentialQueries.slice(0, 3).map((item) => ({
      site_id: item.site_id,
      title: item.query,
      subtitle: (() => {
        const linkedPublication = findLinkedPublication(item.query, item.site_id);

        return linkedPublication
          ? `${item.site_name} · potentiel déjà lié à ${linkedPublication.title}`
          : `${item.site_name} · potentiel éditorial`;
      })(),
      badge: "À développer",
      badgeTone: "warning" as const,
      description: `${item.impressions} impressions déjà visibles, position ${item.position.toFixed(1)} et marge de progression claire.`,
    })),
  ].slice(0, 6);
  const queryStory = lowQueryVolume
    ? "Google commence seulement à associer quelques recherches à votre site. Il n’y a pas encore assez de volume pour une vraie analyse détaillée."
    : risingQueries.length > 0
      ? `${risingQueries.length} recherche${risingQueries.length > 1 ? "s progressent" : " progresse"} déjà dans Google.`
      : potentialQueries.length > 0
        ? `${potentialQueries.length} recherche${potentialQueries.length > 1 ? "s montrent" : " montre"} déjà un sujet à développer.`
        : "PraeviSEO attend déjà les prochaines recherches Google qui commenceront à apporter de la visibilité.";
  const linkedQueryStory =
    linkedQueryItems.length > 0
      ? `${linkedQueryItems.length} recherche${linkedQueryItems.length > 1 ? "s sont" : " est"} déjà reliée${linkedQueryItems.length > 1 ? "s" : ""} à la bonne page de votre site.`
      : "PraeviSEO reliera ici automatiquement les prochaines recherches aux pages déjà vues sur votre site.";
  const queriesAssistantWhat = queryStory;
  const queriesAssistantWhy = linkedQueryStory;
  const leadQuery = queryRadar[0] ?? null;
  const queriesAssistantNext = leadQuery
    ? `${leadQuery.title}. ${leadQuery.description}`
    : "Quand Google ne donne encore que peu de matière, gardez cette vue comme radar et concentrez-vous surtout sur vos pages et opportunités.";
  const queriesAssistantImpact =
    lowQueryVolume
      ? "Dès que Google donnera un signal plus fort, PraeviSEO vous dira quelle recherche devient vraiment intéressante à travailler."
      : "Quand une recherche devient plus claire, PraeviSEO peut ensuite vous aider à renforcer la bonne page et gagner plus de visibilité.";
  const compactQueries = topQueries.slice(0, 3);
  const queryActions = (siteId: string, query: string) => {
    const linkedPublication = findLinkedPublication(query, siteId);
    const actions: Array<{ label: string; href: string; variant?: "primary" | "secondary" }> = [
      {
        label: linkedPublication ? "Ouvrir l’automatisation" : "Voir les optimisations",
        href: linkedPublication
          ? `/sites/${siteId}/automation?focus=query&query=${encodeURIComponent(query)}`
          : `/optimizations?focus=query&site=${encodeURIComponent(siteId)}&query=${encodeURIComponent(query)}`,
        variant: "primary",
      },
    ];

    if (linkedPublication?.live_url) {
      actions.push({
        label: "Ouvrir la page cible",
        href: linkedPublication.live_url,
        variant: "secondary",
      });
    } else if (linkedPublication) {
      actions.push({
        label: "Ouvrir le site",
        href: getSitePath(siteId),
        variant: "secondary",
      });
    } else {
      actions.push({
        label: "Voir les pages liées",
        href: `/pages?focus=query&site=${encodeURIComponent(siteId)}&target=${encodeURIComponent(query)}`,
        variant: "secondary",
      });
    }

    return actions;
  };
  const focusMessage =
    focus === "query"
      ? {
          title: "Requête ouverte depuis une action",
          detail: `${focusQuery || "Cette requête"} a été ouverte comme cible prioritaire. Vérifiez d’abord si elle pousse déjà la bonne page ou si elle doit ouvrir un nouveau contenu.`,
          href: "#meilleures",
        }
      : focus === "emerging"
        ? {
            title: "Nouvelle requête à qualifier",
            detail: `${focusQuery || "Cette requête"} vient d’un signal émergent. Regardez d’abord les nouvelles pistes puis vérifiez s’il existe déjà une page capable de la porter.`,
            href: "#emergentes",
          }
        : null;

  return (
    <div className="min-h-screen">
      <Topbar
        title="Requêtes Google"
        subtitle="Les requêtes qui représentent une opportunité réelle de visibilité ou de clics."
        actions={
          <div className="flex items-center gap-2">
            <Button href="/optimizations" size="sm">
              Exploiter les requêtes
            </Button>
            <Button href="/dashboard" variant="secondary" size="sm">
              Retour au dashboard
            </Button>
          </div>
        }
      />

      <div className="p-6 space-y-6">
        <CockpitSectionNav
          items={
            lowQueryVolume
              ? [
                  { label: "Vue d’ensemble", href: "#vue-ensemble", count: topQueries.length, tone: "default" as const },
                  { label: "Premières pistes", href: "#premieres-pistes", count: compactQueries.length, tone: "warning" as const },
                ]
              : [
                  { label: "Vue d’ensemble", href: "#vue-ensemble", count: topQueries.length, tone: "default" as const },
                  { label: "Déjà repérées", href: "#meilleures", count: visibleQueries.length, tone: "success" as const },
                  { label: "En progression", href: "#hausse", count: risingQueries.length, tone: "success" as const },
                  { label: "À développer", href: "#potentiel", count: potentialQueries.length, tone: "warning" as const },
                  { label: "Bonne page trouvée", href: "#liees", count: linkedQueryItems.length, tone: "secondary" as const },
                  { label: "Nouvelles pistes", href: "#emergentes", count: emergingQueries.length, tone: "secondary" as const },
                ]
          }
        />

        <div className="rounded-2xl border border-brand/20 bg-brand-muted px-6 py-6">
          <h1 className="text-2xl font-bold tracking-tight text-text">Quelles requêtes représentent une vraie opportunité</h1>
          <p className="mt-2 max-w-3xl text-sm leading-7 text-text-muted">
            Cette page sert à lire les requêtes à potentiel : proches du top 10, à fort volume, à faible CTR, en progression récente et déjà reliées à une bonne page.
          </p>
          <div className="mt-4 flex flex-wrap gap-3">
            <Button href="/optimizations">
              Exploiter les requêtes
            </Button>
            <Button href="/pages" variant="secondary">
              Voir les pages liées
            </Button>
          </div>
          {(freshestSyncAt || freshestDataAsOf) && (
            <p className="mt-3 text-xs text-text-subtle">
              {freshestSyncAt ? `Dernière synchro GSC : ${formatDate(freshestSyncAt)}.` : "Synchronisation GSC en attente."}{" "}
              {freshestDataAsOf ? `Données arrêtées au ${formatDate(freshestDataAsOf)}.` : ""}
            </p>
          )}
          <p className="mt-3 max-w-3xl text-xs leading-6 text-text-subtle">
            Cette vue résume une lecture GSC sur une fenêtre récente de 28 jours. Sur un site à faible volume,
            il est normal que certaines périodes restent calmes avant que Google fasse remonter de nouveaux signaux.
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

        <CockpitAssistantGuide
          title="PraeviSEO vous aide a comprendre si ces recherches comptent vraiment"
          description="Cette vue sert de radar utile : elle vous dit quand une requête devient intéressante, pourquoi elle mérite votre attention et quelle page elle peut pousser."
          whatText={queriesAssistantWhat}
          whyText={queriesAssistantWhy}
          nextText={queriesAssistantNext}
          impactText={queriesAssistantImpact}
        />

        <div id="vue-ensemble" className="scroll-mt-24">
          <CockpitMetricGrid
            items={
              lowQueryVolume
                ? [
                    { label: "Recherches repérées", value: topQueries.length },
                    { label: "À développer", value: potentialQueries.length, tone: "warning" as const },
                    { label: "Bonne page trouvée", value: linkedQueryItems.length, tone: "secondary" as const },
                  ]
                : [
                    { label: "Recherches repérées", value: topQueries.length },
                    { label: "Déjà bien comprises", value: visibleQueries.length, tone: "success" as const },
                    { label: "En progression", value: risingQueries.length, tone: "success" as const },
                    { label: "À développer", value: potentialQueries.length, tone: "warning" as const },
                    { label: "Bonne page trouvée", value: linkedQueryItems.length, tone: "secondary" as const },
                    { label: "Nouvelles pistes", value: newQueries.length, tone: "secondary" as const },
                  ]
            }
          />
        </div>

        <div className="grid gap-6 xl:grid-cols-[1.2fr_0.8fr]">
          <CockpitSignalListCard
            title="Ce que PraeviSEO repère déjà"
            description="Les recherches Google les plus utiles à comprendre ou à renforcer maintenant."
            empty={queryRadar.length === 0}
            emptyMessage="Aucune recherche encore assez lisible pour le moment. PraeviSEO attend déjà les prochains signaux utiles."
          >
            {queryRadar.map((item) => (
              <CockpitSignalItem
                key={`${item.subtitle}-${item.title}-${item.badge}`}
                title={item.title}
                subtitle={item.subtitle}
                badge={item.badge}
                badgeTone={item.badgeTone}
                description={item.description}
                actions={queryActions(item.site_id, item.title)}
              />
            ))}
          </CockpitSignalListCard>

          <div className="rounded-xl border border-border bg-surface p-5">
            <p className="text-xs text-text-subtle">Ce que cela veut dire</p>
            <p className="mt-3 text-lg font-semibold text-text">{queryStory}</p>
            <div className="mt-4 space-y-3 text-sm text-text-muted">
              <p>
                {visibleQueries.length > 0
                  ? `${visibleQueries.length} recherche${visibleQueries.length > 1 ? "s sont" : " est"} déjà bien comprise${visibleQueries.length > 1 ? "s" : ""} par Google.`
                  : "Google commence à tester quelques recherches autour de votre site, sans signal encore très fort."}
              </p>
              <p>
                {newQueries.length > 0
                  ? `${newQueries.length} nouvelle${newQueries.length > 1 ? "s" : ""} recherche${newQueries.length > 1 ? "s commencent" : " commence"} à apparaître autour de votre site.`
                  : "PraeviSEO reliera automatiquement les prochaines recherches aux bonnes pages dès que Google donnera un signal plus net."}
              </p>
              <p>{linkedQueryStory}</p>
            </div>
          </div>
        </div>

        {lowQueryVolume ? (
          <div id="premieres-pistes" className="grid gap-6 xl:grid-cols-2 scroll-mt-24">
            <CockpitSignalListCard
              title="Premières recherches repérées"
              description="Google commence seulement à associer quelques recherches à votre site. PraeviSEO garde ici les premières pistes qui pourront devenir utiles."
              empty={compactQueries.length === 0}
              emptyMessage="Google n’a pas encore donné de recherche assez claire pour ouvrir une vraie analyse dédiée. PraeviSEO vous préviendra dès qu’un signal devient utile."
            >
              {compactQueries.map((item) => (
                <CockpitSignalItem
                  key={`${item.site_name}-${item.query}-compact`}
                  title={item.query}
                  subtitle={item.site_name}
                  badge="À suivre plus tard"
                  badgeTone="warning"
                  description={`${item.impressions} affichage(s) repérés dans Google. Le signal existe, mais il reste encore trop léger pour en faire une vraie priorité.`}
                  actions={queryActions(item.site_id, item.query)}
                />
              ))}
            </CockpitSignalListCard>

            <CockpitSignalListCard
              title="Ce que cela veut dire"
              description="Avec peu de volume, cette vue sert surtout de radar."
              empty={false}
              emptyMessage=""
            >
              <div className="rounded-xl border border-border p-4 text-sm text-text-muted leading-6">
                PraeviSEO voit déjà quelques recherches liées à votre site, mais pas encore assez de matière pour en faire une grande analyse séparée.
                Pour l’instant, vos pages, vos opportunités et votre indexation restent les leviers les plus utiles à traiter.
              </div>
            </CockpitSignalListCard>
          </div>
        ) : (
        <div id="meilleures" className="grid gap-6 xl:grid-cols-2 scroll-mt-24">
          <CockpitSignalListCard
            title={visibleQueries.length > 0 ? "Recherches déjà bien repérées" : "Premières recherches à développer"}
            description={
              visibleQueries.length > 0
                ? "Les recherches où Google comprend déjà clairement votre site."
                : "Même avec peu de volume, PraeviSEO garde ici les premières recherches qui peuvent devenir utiles."
            }
            empty={queryRadar.length === 0}
            emptyMessage="Google n’a pas encore donné assez de matière sur les recherches. Pour l’instant, concentrez-vous plutôt sur vos pages et vos priorités SEO."
          >
            {(visibleQueries.length > 0 ? visibleQueries : potentialQueries.slice(0, 4)).map((item) => (
              <CockpitSignalItem
                key={`${item.site_name}-${item.query}-visible`}
                title={item.query}
                subtitle={item.site_name}
                badge={visibleQueries.length > 0 ? "Google vous voit déjà" : "À développer"}
                badgeTone={visibleQueries.length > 0 ? "success" : "warning"}
                description={`${item.impressions} affichages dans Google, ${item.clicks} visites obtenues, et une presence autour de la position ${item.position.toFixed(1)}.`}
                actions={queryActions(item.site_id, item.query)}
              />
            ))}
          </CockpitSignalListCard>

          <CockpitSignalListCard
            id="hausse"
            className="scroll-mt-24"
            title="Ce qui commence à progresser"
            description="Les recherches où Google donne un signal un peu plus fort sur la période récente."
            empty={risingQueries.length === 0}
            emptyMessage="Aucune progression nette pour l’instant. Ce n’est pas un problème : sur un petit site, Google peut rester calme plusieurs jours avant de montrer une vraie hausse."
          >
            {risingQueries.map((item) => (
              <CockpitSignalItem
                key={`${item.site_name}-${item.query}-rising`}
                title={item.query}
                subtitle={item.site_name}
                badge={`+${item.delta_impressions} impressions`}
                badgeTone="success"
                description={`Cette recherche ressort deja un peu dans Google et peut encore gagner en visibilite si la bonne page est renforcée.`}
                actions={queryActions(item.site_id, item.query)}
              />
            ))}
          </CockpitSignalListCard>
        </div>
        )}

        <div id="liees" className="grid gap-6 xl:grid-cols-2 scroll-mt-24">
          <CockpitSignalListCard
            title="Recherches déjà reliées à la bonne page"
            description="PraeviSEO ne montre pas seulement la recherche : il commence déjà à identifier quelle page de votre site répond au besoin."
            empty={linkedQueryItems.length === 0}
            emptyMessage="Google n’a pas encore donné assez de matière pour relier une recherche à une page précise. Ce bloc se remplira quand le lien sera plus clair."
          >
            {linkedQueryItems.map((item) => (
              <CockpitSignalItem
                key={`${item.id}-${item.observed_content?.top_query_match ?? "linked-query"}`}
                title={item.observed_content?.top_query_match ?? "Requête reliée"}
                subtitle={`${item.site_id} · ${item.title}`}
                badge={`${item.observed_content?.query_match_count ?? 0} lien(s) déjà vus`}
                badgeTone="secondary"
                description={
                  item.gsc_metrics.impressions > 0
                    ? `${item.gsc_metrics.impressions} affichages sur cette page et une presence autour de la position ${item.gsc_metrics.position?.toFixed(1) ?? "n/a"}.`
                    : `${item.observed_content?.snapshot_word_count ?? 0} mots deja vus sur cette page, qui commence a repondre a cette recherche.`
                }
                actions={queryActions(item.site_id, item.observed_content?.top_query_match ?? "")}
              />
            ))}
          </CockpitSignalListCard>

          <CockpitSignalListCard
            title="Pages à enrichir quand la recherche sera plus claire"
            description="Les recherches à potentiel où PraeviSEO commence déjà à savoir quelle page renforcer ensuite."
            empty={potentialQueries.every((item) => !findLinkedPublication(item.query, item.site_id))}
            emptyMessage="Aucune recherche à potentiel n’est encore reliée à une page assez clairement. PraeviSEO vous le dira dès qu’une bonne cible se dessine."
          >
            {potentialQueries
              .map((item) => ({ item, linkedPublication: findLinkedPublication(item.query, item.site_id) }))
              .filter((entry) => !!entry.linkedPublication)
              .map(({ item, linkedPublication }) => (
                <CockpitSignalItem
                  key={`${item.site_id}-${item.query}-linked-potential`}
                  title={item.query}
                  subtitle={`${item.site_name} · page cible ${linkedPublication?.title ?? "observée"}`}
                  badge="Bonne page déjà trouvée"
                  badgeTone="warning"
                  description={`${item.impressions} impressions, position ${item.position.toFixed(1)}. PraeviSEO peut déjà lier cette requête à ${linkedPublication?.slug || "/"}.`}
                  actions={queryActions(item.site_id, item.query)}
                />
              ))}
          </CockpitSignalListCard>
        </div>

        <div id="potentiel" className="grid gap-6 xl:grid-cols-2 scroll-mt-24">
          <CockpitSignalListCard
            id="potentiel"
            title="Requêtes à potentiel"
            description="Celles qui peuvent devenir un vrai levier de visibilité si on améliore la bonne page."
            empty={potentialQueries.length === 0}
            emptyMessage="Aucune recherche chaude à renforcer pour le moment. PraeviSEO attend déjà les prochains signaux utiles."
          >
            {potentialQueries.map((item) => (
              <CockpitSignalItem
                key={`${item.site_name}-${item.query}-potential`}
                title={item.query}
                subtitle={(() => {
                  const linkedPublication = findLinkedPublication(item.query, item.site_id);

                  return linkedPublication
                    ? `${item.site_name} · page observée ${linkedPublication.title}`
                    : item.site_name;
                })()}
                badge="À développer"
                badgeTone="warning"
                description={(() => {
                  const linkedPublication = findLinkedPublication(item.query, item.site_id);

                  return linkedPublication
                    ? `${item.impressions} affichages dans Google. La bonne page semble etre ${linkedPublication.slug || "/"}.`
                    : `${item.impressions} affichages dans Google et un premier signal utile sur cette recherche.`;
                })()}
                actions={queryActions(item.site_id, item.query)}
              />
            ))}
          </CockpitSignalListCard>

          <CockpitSignalListCard
            id="emergentes"
            className="scroll-mt-24"
            title="Nouvelles pistes que Google commence à tester"
            description="Les recherches que Google commence à associer à votre site et qui pourront devenir utiles plus tard."
            empty={emergingQueries.length === 0}
            emptyMessage="Aucune nouvelle piste assez nette pour le moment. Le cockpit les fera remonter automatiquement quand elles deviendront utiles."
          >
            {emergingQueries.map((item) => (
              <CockpitSignalItem
                key={`${item.site_name}-${item.query}-emerging`}
                title={item.query}
                subtitle={(() => {
                  const linkedPublication = findLinkedPublication(item.query, item.site_id);

                  return linkedPublication
                    ? `${item.site_name} · signal relié à ${linkedPublication.title}`
                    : item.site_name;
                })()}
                badge={item.previous_impressions === 0 ? "Nouvelle requête" : `+${item.delta_impressions} impressions`}
                badgeTone="secondary"
                description={(() => {
                  const linkedPublication = findLinkedPublication(item.query, item.site_id);

                  return linkedPublication
                    ? `${item.impressions} affichages dans Google. PraeviSEO commence deja a relier cette recherche a ${linkedPublication.slug || "/"}.`
                    : `${item.impressions} affichages dans Google et une piste a suivre si elle progresse.`;
                })()}
                actions={queryActions(item.site_id, item.query)}
              />
            ))}
          </CockpitSignalListCard>
        </div>
      </div>
    </div>
  );
}
