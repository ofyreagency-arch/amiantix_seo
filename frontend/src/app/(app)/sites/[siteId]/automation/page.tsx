import { Topbar } from "@/components/layout/topbar";
import { SiteAccessState } from "@/components/sites/site-access-state";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import {
  getPublications,
  getSettings,
  getSite,
} from "@/lib/praeviseo-api";
import { formatDate } from "@/lib/utils";
import {
  launchPremiumCrawlAction,
  launchPremiumGenerationAction,
  launchPremiumGenerationForKeywordAction,
  launchPremiumImageAction,
  launchPremiumLinkingAction,
  launchPremiumPublicationAction,
  launchPremiumRewriteAction,
} from "../connect/actions";

interface SiteAutomationPageProps {
  params: Promise<{ siteId: string }>;
  searchParams?: Promise<Record<string, string | string[] | undefined>>;
}

type ExecutionHistoryEntry = {
  at: string;
  label: string;
  detail: string;
  tone: "default" | "secondary" | "danger";
  kind: string;
  repeat_count?: number;
};

const ACTION_NEXT_PASSES: Record<string, string> = {
  generation: "Relance lors du prochain passage premium si une nouvelle requête utile se confirme.",
  crawl: "Relance au prochain contrôle du site ou juste après une publication importante.",
  rewrite: "Relance dès qu’une page prioritaire redevient le meilleur levier SEO.",
  linking: "Relance dès que PraeviSEO voit assez de pages à mieux relier.",
  publication: "Relance dès qu’un contenu prêt à sortir ou à republier est validé.",
  images: "Relance dès qu’une page ou un article devient prêt à être enrichi visuellement.",
  monitoring: "Contrôle continu à chaque boucle premium et après chaque action importante.",
};

function slugFromUrl(url: string | null | undefined): string | null {
  if (!url) {
    return null;
  }

  try {
    const parsed = new URL(url);
    const segments = parsed.pathname.split("/").filter(Boolean);
    return segments.length > 0 ? segments[segments.length - 1] : null;
  } catch {
    const normalized = url.split("?")[0]?.split("#")[0] ?? "";
    const segments = normalized.split("/").filter(Boolean);
    return segments.length > 0 ? segments[segments.length - 1] : null;
  }
}

export default async function SiteAutomationPage({ params, searchParams }: SiteAutomationPageProps) {
  const { siteId } = await params;
  const resolvedSearchParams = searchParams ? await searchParams : {};
  const site = await getSite(siteId);
  const publications = await getPublications();
  const settings = await getSettings();

  if (!site) {
    return <SiteAccessState siteId={siteId} areaLabel="les automatisations" />;
  }

  console.info("[praeviseo][automation] page_crawl_trace", {
    site_id: site.site_id,
    route: `/sites/${site.site_id}/automation`,
    crawl: site.crawl,
    last_successful_crawl: site.last_successful_crawl,
    recent_crawls_count: site.recent_crawls.length,
    action_status_crawl: site.action_statuses.crawl,
  });

  const totalConnectedSites = settings.sites.filter(
    (item) => item.publication_bridge_status === "connected" || item.gsc_connection_status === "connected" || item.gsc_connection_status === "configured"
  ).length;
  const sitePublications = publications.items.filter((item) => item.site_id === site.site_id);
  const bridgeConnected = site.readiness.bridge_connected || site.publication_bridge_status === "connected";
  const gscConnected = site.readiness.gsc_connected;
  const leadRisingPage = site.summary.top_rising_pages[0] ?? null;
  const leadRefresh = sitePublications.find((item) => item.latest_suggestion || item.observed_content) ?? null;
  const leadIndexationAlert = site.summary.indexation_alerts[0] ?? null;
  const livePublishedCount = Math.max(
    sitePublications.filter((item) => item.published_live).length,
    site.readiness.has_live_pages ? 1 : 0
  );
  const monitoredContentCount = sitePublications.filter((item) => item.observed_content).length;
  const publicationReady = bridgeConnected && site.publication_target.engine_actionable;
  const hasPublishedPages = site.readiness.has_published_pages || sitePublications.length > 0;
  const hasLivePages = site.readiness.has_live_pages || livePublishedCount > 0;
  const latestPublishedContent = sitePublications.find((item) => item.published_live) ?? null;
  const currentCrawl = site.crawl;
  const lastSuccessfulCrawl = site.last_successful_crawl;
  const recentCrawls = site.recent_crawls;
  const loopStatus =
    site.action_statuses.monitoring.state === "failed"
      ? "À revoir"
      : bridgeConnected && (gscConnected || hasPublishedPages || Boolean(lastSuccessfulCrawl))
        ? "Active"
        : "En attente";
  const crawlReport = site.crawl_report;
  const displayCrawl =
    currentCrawl &&
    lastSuccessfulCrawl &&
    currentCrawl.id === lastSuccessfulCrawl.id &&
    currentCrawl.status !== "completed" &&
    lastSuccessfulCrawl.status === "completed"
      ? lastSuccessfulCrawl
      : currentCrawl;
  const crawlReportPages = crawlReport?.pages ?? [];
  const crawlReportIssues = crawlReport?.issues ?? [];
  const crawlReportIssueSummary = crawlReport?.issue_summary ?? [];
  const crawlReportChanges = crawlReport?.changes ?? [];
  const crawlReportProducedData = crawlReport?.produced_data ?? {
    observed_pages: 0,
    weak_pages: 0,
    orphan_pages: 0,
    link_gap_pages: 0,
    pillar_candidates: 0,
    health_score: 0,
    crawl_issues: 0,
    indexed_pages: 0,
    non_indexed_pages: 0,
  };
  const feedbackType = typeof resolvedSearchParams.feedback === "string" ? resolvedSearchParams.feedback : null;
  const feedbackTitle = typeof resolvedSearchParams.feedback_title === "string" ? resolvedSearchParams.feedback_title : null;
  const feedbackDetail = typeof resolvedSearchParams.feedback_detail === "string" ? resolvedSearchParams.feedback_detail : null;
  const crawlImpactItems = [
    lastSuccessfulCrawl && lastSuccessfulCrawl.issues_count > 0 ? `${lastSuccessfulCrawl.issues_count} point(s) à surveiller détecté(s)` : null,
    site.summary.top_rising_pages.length > 0 ? `${site.summary.top_rising_pages.length} page(s) montrent déjà un potentiel de progression` : null,
    site.summary.indexation_alerts.length > 0 ? `${site.summary.indexation_alerts.length} alerte(s) d’indexation restent à traiter` : null,
    site.summary.observed_link_gap_pages.length > 0 ? `${site.summary.observed_link_gap_pages.length} page(s) peuvent être mieux reliées ensuite` : null,
  ].filter(Boolean) as string[];
  const crawlImpactSummary =
    crawlImpactItems.length > 0
      ? crawlImpactItems.join(" · ")
      : "Le crawl alimente surtout la lecture du site et prépare les prochaines opportunités exploitables.";
  const latestSuccessfulCrawlAt = lastSuccessfulCrawl?.completed_at ?? null;
  const activeCrawlId = displayCrawl?.id ?? crawlReport?.reference_crawl_id ?? null;
  const crawlDisplayState =
    displayCrawl?.status === "completed"
      ? "completed"
      : displayCrawl?.status === "running"
        ? "running"
        : displayCrawl?.status === "failed"
          ? "failed"
          : displayCrawl?.status === "pending"
            ? "pending"
            : site.action_statuses.crawl.state;
  const crawlDisplayLabel =
    crawlDisplayState === "completed"
      ? "Terminé"
      : crawlDisplayState === "running"
        ? "En cours"
        : crawlDisplayState === "failed"
          ? "Erreur"
          : crawlDisplayState === "pending"
            ? "Planifié"
            : site.action_statuses.crawl.label;
  const crawlDisplayDetail =
    crawlDisplayState === "completed" && displayCrawl
      ? `La dernière relecture a parcouru ${displayCrawl.crawled_url_count} page(s) et remonté ${displayCrawl.issues_count} point(s) à surveiller.`
      : crawlDisplayState === "running" && displayCrawl
        ? `PraeviSEO relit actuellement ${displayCrawl.crawled_url_count} page(s) sur ${displayCrawl.max_pages} maximum.`
        : crawlDisplayState === "pending"
          ? "Une nouvelle relecture premium a été demandée et va démarrer automatiquement."
          : crawlDisplayState === "failed" && displayCrawl?.error
            ? displayCrawl.error
            : site.action_statuses.crawl.detail || "PraeviSEO n’a pas encore lancé de lecture visible sur ce site.";
  const crawlDisplayTitle =
    crawlDisplayState === "completed"
      ? "Dernier crawl lancé"
      : crawlDisplayState === "running"
        ? "Crawl en cours"
        : crawlDisplayState === "pending"
          ? "Crawl en attente"
          : crawlDisplayState === "failed"
            ? "Crawl à revoir"
            : "Crawl";
  const crawlProgressValue =
    crawlDisplayState === "completed"
      ? 100
      : displayCrawl && displayCrawl.max_pages > 0
      ? Math.min(
          100,
          Math.round(
            (Math.max(displayCrawl.crawled_url_count, displayCrawl.discovered_url_count, 0) / displayCrawl.max_pages) * 100
          )
        )
      : 0;

  const idleActionLabel = (state: string, label: string, fallback: string) =>
    state === "idle" && (label === "À ouvrir" || label === "A ouvrir") ? fallback : label;

  const recommendedAction = (() => {
    switch (site.next_action.kind) {
      case "connect_bridge":
      case "installation_requested":
      case "installation_failed":
        return {
          label: "Ouvrir la santé technique",
          href: `/sites/${site.site_id}/connect`,
          actionKey: null,
        };
      case "connect_gsc":
        return {
          label: "Relier Search Console",
          href: `/sites/${site.site_id}/search-console`,
          actionKey: null,
        };
      case "review_optimizations":
        return {
          label: "Ouvrir les optimisations",
          href: "/optimizations",
          actionKey: null,
        };
      case "publish_first_page":
      case "publish_live":
        return {
          label: "Publier maintenant",
          href: null,
          actionKey: "publication",
        };
      case "monitor":
        return {
          label: "Revenir au cockpit SEO",
          href: `/sites/${site.site_id}`,
          actionKey: null,
        };
      default:
        return {
          label: site.next_action.label,
          href: null,
          actionKey: null,
        };
    }
  })();

  const recommendedActionKey = recommendedAction.actionKey;
  const nextPassStatus =
    site.next_action.priority === "high"
      ? "Priorité haute"
      : site.next_action.priority === "medium"
        ? "À préparer"
        : "Sous contrôle";
  const nextPassDetail = `${site.next_action.label}. ${site.next_action.detail}`.trim();

  const describeResult = (state: string, detail?: string | null, error?: string | null) => {
    if (state === "failed") {
      return error || "La dernière tentative s’est arrêtée avant la fin et demande une reprise.";
    }

    if (state === "completed") {
      return detail || "La dernière exécution est allée au bout sans blocage remonté.";
    }

    if (state === "pending" || state === "requested") {
      return detail || "PraeviSEO a déjà préparé cette action et la reprendra au prochain passage utile.";
    }

    return detail || "PraeviSEO n’a pas encore exécuté cette action de manière visible sur ce site.";
  };

  const executionCenter = [
    {
      key: "generation",
      title: "Nouvel article",
      status: idleActionLabel(site.action_statuses.generation.state, site.action_statuses.generation.label, "En veille"),
      detail:
        site.action_statuses.generation.detail ||
        (site.summary.new_queries.length > 0
          ? "PraeviSEO a déjà repéré de nouvelles recherches Google qui peuvent devenir de vrais articles sur le site."
          : "Dès qu'une nouvelle recherche utile se confirme, PraeviSEO pourra ouvrir un nouvel article automatiquement."),
      updatedAt: site.action_statuses.generation.updated_at,
      nextPass: ACTION_NEXT_PASSES.generation,
      result: describeResult(site.action_statuses.generation.state, site.action_statuses.generation.detail, site.action_statuses.generation.error),
      impact:
        site.summary.new_queries.length > 0
          ? `${site.summary.new_queries.length} nouvelle(s) requête(s) peuvent déjà devenir de nouveaux contenus visibles.`
          : "Prépare la prochaine ouverture éditoriale utile dès qu’un sujet assez net apparaît.",
    },
    {
      key: "crawl",
      title: "Crawl automatique",
      status: site.action_statuses.crawl.label,
      detail:
        site.action_statuses.crawl.detail ||
        (monitoredContentCount > 0
          ? `${monitoredContentCount} page(s) sont déjà relues par PraeviSEO.`
          : "Le premier crawl premium relira le site pour préparer les prochaines actions automatiques."),
      updatedAt: site.action_statuses.crawl.updated_at,
      nextPass: ACTION_NEXT_PASSES.crawl,
      result: describeResult(site.action_statuses.crawl.state, site.action_statuses.crawl.detail, site.action_statuses.crawl.error),
      impact:
        monitoredContentCount > 0
          ? `${monitoredContentCount} page(s) sont déjà relues et comparées par PraeviSEO.`
          : "Ouvre la lecture du site et alimente les prochaines décisions automatiques.",
    },
    {
      key: "rewrite",
      title: "Réécriture SEO",
      status: idleActionLabel(site.action_statuses.rewrite.state, site.action_statuses.rewrite.label, "En veille"),
      detail:
        site.action_statuses.rewrite.detail ||
        (leadRefresh
          ? "PraeviSEO a déjà repéré un contenu à retravailler. L’automatisation peut reprendre cette amélioration."
          : "La couche payante préparera les premières réécritures dès qu’un contenu utile sera détecté."),
      updatedAt: site.action_statuses.rewrite.updated_at,
      nextPass: ACTION_NEXT_PASSES.rewrite,
      result: describeResult(site.action_statuses.rewrite.state, site.action_statuses.rewrite.detail, site.action_statuses.rewrite.error),
      impact:
        leadRefresh
          ? "Peut améliorer un contenu déjà utile sans repartir d’une page blanche."
          : "Prépare la prochaine amélioration éditoriale dès qu’un contenu devient prioritaire.",
    },
    {
      key: "linking",
      title: "Maillage interne",
      status: idleActionLabel(site.action_statuses.linking.state, site.action_statuses.linking.label, "En veille"),
      detail:
        site.action_statuses.linking.detail ||
        (site.summary.observed_link_gap_pages.length > 0
          ? "Le site contient déjà des pages à mieux relier."
          : "Le maillage interne sera préparé dès que PraeviSEO aura assez de pages à relier proprement."),
      updatedAt: site.action_statuses.linking.updated_at,
      nextPass: ACTION_NEXT_PASSES.linking,
      result: describeResult(site.action_statuses.linking.state, site.action_statuses.linking.detail, site.action_statuses.linking.error),
      impact:
        site.summary.observed_link_gap_pages.length > 0
          ? `${site.summary.observed_link_gap_pages.length} page(s) peuvent déjà recevoir un meilleur maillage interne.`
          : "Renforcera la circulation interne dès que PraeviSEO repère assez de pages à relier.",
    },
    {
      key: "publication",
      title: "Publication automatique",
      status: hasLivePages
        ? "Active"
        : publicationReady
          ? "Prête"
          : bridgeConnected
            ? "Bridge actif"
            : idleActionLabel(site.action_statuses.publication.state, site.action_statuses.publication.label, "À préparer"),
      detail:
        (hasLivePages
          ? `${livePublishedCount} contenu(s) sont déjà visibles et peuvent être repris automatiquement.`
          : publicationReady
            ? site.publication_target.detail || "Le bridge est prêt. PraeviSEO peut pousser le premier contenu utile dès qu’il est prêt."
            : bridgeConnected
              ? "Le bridge répond déjà. Il reste à pousser un premier contenu visible pour démarrer la boucle live."
              : site.action_statuses.publication.detail ||
                site.publication_target.detail ||
                "La publication démarrera juste après l’activation complète de la connexion premium."),
      updatedAt: site.action_statuses.publication.updated_at,
      nextPass: ACTION_NEXT_PASSES.publication,
      result: hasLivePages
        ? `Le site a déjà ${livePublishedCount} contenu(s) visible(s) en live.`
        : publicationReady
          ? "Le site peut déjà recevoir une première publication live."
          : describeResult(site.action_statuses.publication.state, site.action_statuses.publication.detail, site.action_statuses.publication.error),
      impact:
        hasLivePages
          ? `${livePublishedCount} contenu(s) sont déjà visibles et peuvent maintenant être suivis en conditions réelles.`
          : "Transforme les contenus préparés en pages réellement visibles sur le site.",
    },
    {
      key: "images",
      title: "Images SEO",
      status: idleActionLabel(site.action_statuses.images.state, site.action_statuses.images.label, "En veille"),
      detail:
        site.action_statuses.images.detail ||
        (leadRisingPage
          ? "PraeviSEO peut déjà préparer une image claire pour la page qui a le plus de potentiel visible."
          : "Les premières images SEO seront préparées dès qu’une page assez utile et stable sera priorisée."),
      updatedAt: site.action_statuses.images.updated_at,
      nextPass: ACTION_NEXT_PASSES.images,
      result: describeResult(site.action_statuses.images.state, site.action_statuses.images.detail, site.action_statuses.images.error),
      impact:
        leadRisingPage
          ? `Peut renforcer visuellement ${leadRisingPage.label}, la page qui a déjà le plus de potentiel visible.`
          : "Ajoute une couche visuelle SEO dès qu’une page mérite d’être enrichie.",
    },
    {
      key: "monitoring",
      title: "Monitoring continu",
      status: bridgeConnected && gscConnected
        ? "Actif"
        : site.action_statuses.monitoring.state === "failed"
          ? site.action_statuses.monitoring.label
          : idleActionLabel(site.action_statuses.monitoring.state, site.action_statuses.monitoring.label, "En veille"),
      detail:
        (bridgeConnected && gscConnected
          ? "PraeviSEO suit déjà la santé du site, les crawls, les contenus live et les signaux Google sans action manuelle."
          : site.action_statuses.monitoring.detail ||
            (site.summary.observed_site_health_score > 0
          ? "PraeviSEO suit déjà la santé du site et peut relancer les prochaines priorités utiles."
          : "Le monitoring premium suivra les actions exécutées, les retours Google et les prochaines priorités utiles.")),
      updatedAt: site.action_statuses.monitoring.updated_at,
      nextPass: ACTION_NEXT_PASSES.monitoring,
      result: bridgeConnected && gscConnected
        ? "Le site est déjà branché des deux côtés. PraeviSEO continue maintenant à surveiller et relancer si besoin."
        : describeResult(site.action_statuses.monitoring.state, site.action_statuses.monitoring.detail, site.action_statuses.monitoring.error),
      impact:
        site.summary.observed_site_health_score > 0
          ? `Surveille déjà la santé observée du site autour de ${site.summary.observed_site_health_score}/100.`
          : "Mesure les effets réels et relance la prochaine action utile sans intervention manuelle.",
    },
  ] as const;

  const automationOverview = [
    {
      title: "Boucle premium",
      status: loopStatus,
      detail:
        loopStatus === "Active"
          ? "PraeviSEO surveille déjà le site et peut relancer les prochaines actions utiles."
          : loopStatus === "À revoir"
            ? "La dernière boucle a remonté un blocage. Regardez l’historique et les points à revoir."
            : "La boucle premium redémarrera dès que les prochaines actions deviendront utiles.",
    },
    {
      title: "Lecture Google",
      status: gscConnected ? "Active" : "À reconnecter",
      detail: gscConnected
        ? "Les signaux Search Console guident déjà les prochaines pages et requêtes à traiter."
        : "Sans lecture Google, PraeviSEO perd une partie de sa capacité à prioriser les gains visibles.",
    },
    {
      title: "Contenus suivis",
      status: `${monitoredContentCount} page(s)`,
      detail:
        monitoredContentCount > 0
          ? "PraeviSEO relit déjà ces pages pour comparer les gains, repérer les liens utiles et préparer les relances."
          : "Les premiers contenus suivis apparaîtront après les prochains crawls et publications utiles.",
    },
    {
      title: "Publications live",
      status: `${livePublishedCount} live`,
      detail:
        latestPublishedContent
          ? `Dernier contenu visible : ${latestPublishedContent.title}. PraeviSEO peut maintenant le suivre, le relier et le faire évoluer.`
          : publicationReady
            ? `${site.publication_target.detail} La première publication live visible apparaîtra ici.`
            : bridgeConnected
              ? "Le bridge est actif, mais aucun contenu n’a encore été poussé en live depuis cette vue."
              : "Aucun contenu premium n’est encore visible en ligne. Les prochaines publications apparaîtront ici.",
    },
    {
      title: "Prochain passage",
      status: nextPassStatus,
      detail: nextPassDetail || "PraeviSEO attend la prochaine priorité assez claire pour relancer la boucle.",
    },
    {
      title: "Parc actif",
      status: totalConnectedSites > 1 ? `${totalConnectedSites} sites` : "1 site",
      detail:
        totalConnectedSites > 1
          ? `La même logique d’automatisation tourne déjà sur ${totalConnectedSites} sites suivis dans votre espace.`
          : "Ce site sert de base active. Les prochains sites pourront reprendre la même couche d’automatisation.",
    },
  ] as const;

  const rawCrawlDerivedHistory: Array<ExecutionHistoryEntry | null> = currentCrawl
    ? [
        currentCrawl.requested_at
          ? {
              at: currentCrawl.requested_at,
              label: "Crawl lancé",
              detail: "PraeviSEO a bien planifié une nouvelle lecture complète du site.",
              tone: "secondary" as const,
              kind: "crawl_requested",
            }
          : null,
        currentCrawl.started_at
          ? {
              at: currentCrawl.started_at,
              label: "Crawl en cours",
              detail: `PraeviSEO relit actuellement ${currentCrawl.crawled_url_count} page(s) sur ${currentCrawl.max_pages} maximum.`,
              tone: "secondary" as const,
              kind: "crawl_running",
            }
          : null,
        lastSuccessfulCrawl?.completed_at
          ? {
              at: lastSuccessfulCrawl.completed_at,
              label: "Crawl terminé",
              detail: `${lastSuccessfulCrawl.crawled_url_count} page(s) relues et ${lastSuccessfulCrawl.issues_count} point(s) à surveiller remonté(s).`,
              tone: "default" as const,
              kind: "crawl_completed",
            }
          : null,
        lastSuccessfulCrawl?.completed_at
          ? {
              at: lastSuccessfulCrawl.completed_at,
              label: "Impact du crawl détecté",
              detail: crawlImpactSummary,
              tone: "secondary" as const,
              kind: "crawl_impact",
            }
          : null,
      ]
    : [];
  const crawlDerivedHistory: ExecutionHistoryEntry[] = rawCrawlDerivedHistory.filter(
    (entry): entry is ExecutionHistoryEntry => entry !== null
  );

  const compactExecutionHistory = [...crawlDerivedHistory, ...site.execution_history].reduce<ExecutionHistoryEntry[]>(
    (carry, entry) => {
      const existingIndex = carry.findIndex(
        (item) => item.kind === entry.kind && item.label === entry.label && item.detail === entry.detail
      );

      if (existingIndex === -1) {
        carry.push({ ...entry, repeat_count: 1 });

        return carry;
      }

      const existing = carry[existingIndex];
      const existingDate = new Date(existing.at).getTime();
      const currentDate = new Date(entry.at).getTime();

      carry[existingIndex] = {
        ...(currentDate >= existingDate ? entry : existing),
        repeat_count: (existing.repeat_count ?? 1) + 1,
      };

      return carry;
    },
    []
  );

  const executionHistory =
    compactExecutionHistory.length > 0
      ? compactExecutionHistory
          .sort((left, right) => new Date(right.at).getTime() - new Date(left.at).getTime())
          .slice(0, 8)
      : [
          {
            at: new Date().toISOString(),
            label: "Historique en préparation",
            detail: "PraeviSEO affichera ici les prochaines actions dès qu’une première exécution premium sera réellement lancée.",
            tone: "secondary" as const,
            kind: "empty",
            repeat_count: 1,
          },
        ];

  const executionIssues = Object.entries(site.action_statuses)
    .filter(([, status]) => status.state === "failed" && status.error)
    .map(([key, status]) => ({
      key,
      title:
        key === "crawl"
          ? "Crawl à vérifier"
          : key === "generation"
            ? "Nouvel article à vérifier"
            : key === "rewrite"
              ? "Réécriture à vérifier"
              : key === "linking"
                ? "Maillage à vérifier"
                : key === "images"
                  ? "Image SEO à vérifier"
                  : key === "publication"
                    ? "Publication à vérifier"
                    : "Monitoring à vérifier",
      detail: status.error as string,
      updatedAt: status.updated_at,
    }));

  const generationReady = site.summary.new_queries.length > 0;
  const rewriteReady = Boolean(leadRefresh || leadRisingPage);
  const linkingReady =
    site.summary.observed_link_gap_pages.length > 0 || site.summary.observed_orphan_alerts.length > 0;
  const imageReady = Boolean(leadRisingPage || leadRefresh);
  const publicationContentReady = Boolean(leadRefresh || latestPublishedContent || hasPublishedPages);
  const publicationLaunchReady = publicationReady && publicationContentReady;
  const crawlIssueLead = crawlReportIssues.find((issue) => Boolean(issue.url)) ?? null;
  const leadIndexationSlug = slugFromUrl(leadIndexationAlert?.url);
  const weakPageLead = site.summary.observed_weak_page_details[0] ?? null;
  const weakPageSlug = weakPageLead?.slug || slugFromUrl(weakPageLead?.url);
  const linkGapLead = site.summary.observed_link_gap_pages[0] ?? null;
  const linkGapSlug = linkGapLead?.slug || slugFromUrl(linkGapLead?.url);
  const crawlIssueSlug = slugFromUrl(crawlIssueLead?.url ?? null);
  const risingPageSlug = leadRisingPage?.slug || slugFromUrl(leadRisingPage?.url ?? null);
  const refreshPageSlug = leadRefresh?.slug || null;
  const generationKeyword = site.summary.new_queries[0]?.query?.trim() || null;
  const runCrawlAction = launchPremiumCrawlAction.bind(null, site.site_id);
  const runGenerationAction = launchPremiumGenerationAction.bind(null, site.site_id);
  const runGenerationKeywordAction = generationKeyword
    ? launchPremiumGenerationForKeywordAction.bind(null, site.site_id, generationKeyword)
    : runGenerationAction;
  const runRewriteAction = (slug?: string | null) => launchPremiumRewriteAction.bind(null, site.site_id, slug ?? undefined);
  const runLinkingAction = (slug?: string | null) => launchPremiumLinkingAction.bind(null, site.site_id, slug ?? undefined);
  const runImageAction = (slug?: string | null) => launchPremiumImageAction.bind(null, site.site_id, slug ?? undefined);
  const runPublicationAction = (slug?: string | null) =>
    launchPremiumPublicationAction.bind(null, site.site_id, slug ?? undefined);
  const queryFocusHref = generationKeyword
    ? `/queries?focus=${encodeURIComponent(generationKeyword)}`
    : "/queries";
  const rewriteFocusHref = refreshPageSlug
    ? `/pages?focus=${encodeURIComponent(refreshPageSlug)}`
    : "/pages";
  const linkingFocusHref = linkGapSlug
    ? `/pages?focus=${encodeURIComponent(linkGapSlug)}`
    : "/pages";
  const imageFocusTarget = risingPageSlug ?? refreshPageSlug;
  const imageFocusHref = imageFocusTarget
    ? `/pages?focus=${encodeURIComponent(imageFocusTarget)}`
    : "/pages";
  const publicationFocusTarget = latestPublishedContent?.slug ?? refreshPageSlug;
  const publicationFocusHref = publicationFocusTarget
    ? `/publications?focus=${encodeURIComponent(publicationFocusTarget)}`
    : "/publications";

  const actionButtons = [
    {
      key: "crawl",
      label: "Lancer un crawl",
      stage: "now",
      ctaLabel: "Lancer un crawl",
      description:
        crawlDisplayState === "completed"
          ? "Relancer une lecture propre du site pour mettre à jour les signaux observés."
          : "Démarrer ou relancer la lecture premium du site.",
      recommended: recommendedActionKey === "crawl",
      available: true,
      blockedReason: null,
      helperHref: `/sites/${site.site_id}/automation#suivi-crawl`,
      helperLabel: "Voir le suivi du crawl",
      action: runCrawlAction,
    },
    {
      key: "generation",
      label: "Créer un article",
      stage: generationReady ? "now" : gscConnected ? "soon" : "prep",
      ctaLabel: generationReady ? "Créer l’article ciblé" : "Sujet encore trop flou",
      description:
        generationReady
          ? `Une requête montante est déjà visible : ${site.summary.new_queries[0].query}.`
          : "Ouvrir un nouveau contenu dès qu’un sujet assez net mérite une vraie page.",
      recommended: recommendedActionKey === "generation",
      available: generationReady,
      blockedReason: generationReady
        ? null
        : "PraeviSEO n’a pas encore trouvé une recherche Google assez utile et distincte pour ouvrir un article fiable.",
      helperHref: queryFocusHref,
      helperLabel: "Voir les requêtes utiles",
      action: generationReady ? runGenerationKeywordAction : runGenerationAction,
    },
    {
      key: "rewrite",
      label: "Préparer une réécriture",
      stage: rewriteReady ? "now" : gscConnected || Boolean(lastSuccessfulCrawl) ? "soon" : "prep",
      ctaLabel: rewriteReady ? "Relancer la réécriture" : "Aucune page prête",
      description: leadRefresh
        ? `Un contenu est déjà à retravailler : ${leadRefresh.title}.`
        : "Relancer un contenu existant qui peut progresser sans repartir de zéro.",
      recommended: recommendedActionKey === "rewrite",
      available: rewriteReady,
      blockedReason: rewriteReady
        ? null
        : "Aucune page n’a encore assez de matière ou de signal pour justifier une vraie réécriture utile.",
      helperHref: rewriteFocusHref,
      helperLabel: "Voir les pages à retravailler",
      action: runRewriteAction(refreshPageSlug ?? risingPageSlug),
    },
    {
      key: "linking",
      label: "Renforcer le maillage",
      stage: linkingReady ? "now" : gscConnected || Boolean(lastSuccessfulCrawl) ? "soon" : "prep",
      ctaLabel: linkingReady ? "Lancer le maillage utile" : "Pas encore de cible nette",
      description: linkGapLead
        ? `Une page manque déjà de soutien interne : ${linkGapLead.label}.`
        : "Ouvrir des liens internes utiles dès que PraeviSEO voit une vraie cible à soutenir.",
      recommended: recommendedActionKey === "linking",
      available: linkingReady,
      blockedReason: linkingReady
        ? null
        : "PraeviSEO n’a pas encore trouvé assez de pages prioritaires avec assez de contexte pour ouvrir des liens internes utiles.",
      helperHref: linkingFocusHref,
      helperLabel: "Voir les pages sous-maillées",
      action: runLinkingAction(linkGapSlug),
    },
    {
      key: "images",
      label: "Générer l’image SEO",
      stage: imageReady ? "now" : monitoredContentCount > 0 || Boolean(lastSuccessfulCrawl) ? "soon" : "prep",
      ctaLabel: imageReady ? "Préparer l’image utile" : "Aucune page assez stable",
      description: leadRisingPage
        ? `Une page à potentiel visible peut déjà recevoir un renfort visuel : ${leadRisingPage.label}.`
        : "Préparer une image SEO dès qu’une page assez stable mérite un enrichissement visuel.",
      recommended: recommendedActionKey === "images",
      available: imageReady,
      blockedReason: imageReady
        ? null
        : "PraeviSEO attend encore une page assez stable, assez utile et assez prioritaire avant de générer une image SEO propre.",
      helperHref: imageFocusHref,
      helperLabel: "Voir les pages candidates",
      action: runImageAction(risingPageSlug ?? refreshPageSlug),
    },
    {
      key: "publication",
      label: "Publier",
      stage: publicationLaunchReady ? "now" : bridgeConnected ? "soon" : "prep",
      ctaLabel: publicationLaunchReady
        ? "Publier en live"
        : !bridgeConnected
          ? "Bridge à connecter"
          : "Contenu encore à préparer",
      description: hasLivePages
        ? "Pousser un nouveau contenu ou une mise à jour sur le site déjà connecté."
        : publicationReady
          ? "Le bridge répond déjà : une première publication live peut partir."
          : bridgeConnected
            ? "Le bridge est actif, mais PraeviSEO attend encore un contenu prêt à pousser."
            : "La publication live restera limitée tant que le bridge n’est pas complètement prêt.",
      recommended: recommendedActionKey === "publication",
      available: publicationLaunchReady,
      blockedReason: publicationLaunchReady
        ? null
        : !bridgeConnected
          ? "Le bridge client n’est pas encore assez branché pour pousser une vraie publication live."
          : !publicationReady
            ? "Le bridge répond, mais PraeviSEO n’a pas encore validé une publication réellement actionnable."
            : "Le bridge est prêt, mais aucun contenu assez propre n’est encore prêt à partir en live.",
      helperHref: !bridgeConnected ? `/sites/${site.site_id}/connect` : publicationFocusHref,
      helperLabel: !bridgeConnected ? "Ouvrir la santé technique" : "Voir les contenus prêts",
      action: runPublicationAction(latestPublishedContent?.slug ?? refreshPageSlug),
    },
  ] as const;
  const actionableNowButtons = actionButtons.filter((item) => item.stage === "now");
  const comingSoonButtons = actionButtons.filter((item) => item.stage === "soon");
  const preparationButtons = actionButtons.filter((item) => item.stage === "prep");
  const recommendedActionButton =
    recommendedActionKey ? actionButtons.find((item) => item.key === recommendedActionKey) ?? null : null;
  const executionHighlights = executionCenter.filter((item) =>
    ["crawl", "publication", "rewrite", "monitoring"].includes(item.key)
  );
  const problemActions = [
    leadIndexationAlert
      ? {
          key: "indexation",
          title: "Débloquer une page hors index",
          detail: `${leadIndexationAlert.label} reste hors index Google.`,
          whyNow:
            leadIndexationAlert.state === "URL is unknown to Google"
              ? "Google ne connaît pas encore bien cette URL. Il faut d’abord vérifier qu’elle répond proprement et qu’elle est bien exposée."
              : leadIndexationAlert.detail || "Le signal d’indexation demande une vérification avant toute publication ou réécriture.",
          primaryHref: `/sites/${site.site_id}/search-console`,
          primaryLabel: "Voir l’indexation",
          action: rewriteReady && leadIndexationSlug ? runRewriteAction(leadIndexationSlug) : null,
          actionLabel: rewriteReady && leadIndexationSlug ? "Préparer la correction SEO" : null,
          secondaryHref: leadIndexationAlert.url || null,
          secondaryLabel: leadIndexationAlert.url ? "Ouvrir la page" : null,
        }
      : null,
    weakPageLead
      ? {
          key: "weak-page",
          title: "Retravailler une page faible",
          detail: `${weakPageLead.label} manque encore de force SEO visible.`,
          whyNow: "Cette page existe déjà. Une reprise éditoriale propre peut produire un gain plus vite qu’un nouveau contenu.",
          primaryHref: "/pages",
          primaryLabel: "Voir les pages à retravailler",
          action: rewriteReady && weakPageSlug ? runRewriteAction(weakPageSlug) : null,
          actionLabel: rewriteReady && weakPageSlug ? "Lancer la réécriture" : null,
          secondaryHref: weakPageLead.url,
          secondaryLabel: "Ouvrir la page",
        }
      : null,
    linkGapLead
      ? {
          key: "link-gap",
          title: "Renforcer une page sous-maillée",
          detail: `${linkGapLead.label} manque de soutien interne.`,
          whyNow: "Un meilleur maillage peut aider Google à recrawler et faire progresser une page déjà utile.",
          primaryHref: "/pages",
          primaryLabel: "Voir les pages sous-maillées",
          action: linkingReady && linkGapSlug ? runLinkingAction(linkGapSlug) : null,
          actionLabel: linkingReady && linkGapSlug ? "Lancer le maillage" : null,
          secondaryHref: linkGapLead.url,
          secondaryLabel: "Ouvrir la cible",
        }
      : null,
    crawlIssueLead
      ? {
          key: "crawl-issue",
          title: "Corriger un problème de crawl",
          detail: `${crawlIssueLead.type} détecté sur ${crawlIssueLead.url}.`,
          whyNow: "Le crawl a déjà trouvé un problème concret. C’est un bon point d’entrée pour une correction visible.",
          primaryHref: crawlIssueLead.url || null,
          primaryLabel: "Ouvrir la page concernée",
          action:
            crawlIssueLead.type === "http_error"
              ? runCrawlAction
              : (crawlIssueLead.type === "missing_meta_description" || crawlIssueLead.type === "thin_content") && crawlIssueSlug
                ? runRewriteAction(crawlIssueSlug)
                : null,
          actionLabel:
            crawlIssueLead.type === "http_error"
              ? "Relancer un crawl propre"
              : (crawlIssueLead.type === "missing_meta_description" || crawlIssueLead.type === "thin_content") && crawlIssueSlug
                ? "Préparer la correction"
                : null,
          secondaryHref: `/sites/${site.site_id}/automation#problemes-crawl`,
          secondaryLabel: "Voir les problèmes du crawl",
        }
      : null,
  ].filter(Boolean) as Array<{
    key: string;
    title: string;
    detail: string;
    whyNow: string;
    primaryHref: string | null;
    primaryLabel: string;
    action: ((formData: FormData) => void | Promise<void>) | null;
    actionLabel: string | null;
    secondaryHref: string | null;
    secondaryLabel: string | null;
  }>;

  const starterPlan = [
    leadIndexationAlert
      ? {
          title: "Page non indexée à débloquer",
          detail: `${leadIndexationAlert.label} reste en dehors de l’index Google : ${leadIndexationAlert.state}.`,
          impact: "La remettre dans un état indexable peut rouvrir une vraie porte d’entrée SEO sur le site.",
          action:
            leadIndexationAlert.state === "URL is unknown to Google"
              ? "Vérifier qu’elle renvoie bien 200, qu’elle est liée depuis le site et qu’elle est visible dans le sitemap."
              : leadIndexationAlert.detail || "Vérifier le statut HTTP, le canonique, le robots et le maillage interne avant republication.",
          targetLabel: leadIndexationAlert.label,
          targetUrl: leadIndexationAlert.url,
          ctaLabel: "Voir la page Search Console",
          ctaHref: `/sites/${site.site_id}/search-console`,
          ctaAction: null,
        }
      : null,
    linkGapLead
      ? {
          title: "Page à mieux relier ensuite",
          detail: `${linkGapLead.label} manque encore de soutien interne malgré son potentiel observé.`,
          impact: "Un meilleur maillage aide Google à recrawler, contextualiser puis renforcer la page plus vite.",
          action: "Ouvrir des liens internes utiles depuis les pages déjà fortes du site.",
          targetLabel: linkGapLead.label,
          targetUrl: linkGapLead.url,
          ctaLabel: "Renforcer le maillage",
          ctaHref: null,
          ctaAction: linkGapSlug ? runLinkingAction(linkGapSlug) : null,
        }
      : null,
    leadRisingPage
      ? {
          title: "Page déjà proche d’un gain visible",
          detail: `${leadRisingPage.label} commence déjà à gagner du terrain dans Google et peut être renforcée automatiquement.`,
          impact: "Une page déjà visible peut progresser plus vite si PraeviSEO la relit, l’enrichit puis relance sa publication.",
          action: "Choisir cette page comme priorité, clarifier son angle utile puis relancer une version enrichie.",
          targetLabel: leadRisingPage.label,
          targetUrl: leadRisingPage.url,
          ctaLabel: "Préparer une réécriture",
          ctaHref: null,
          ctaAction: risingPageSlug ? runRewriteAction(risingPageSlug) : null,
        }
      : null,
    leadRefresh
      ? {
          title: "Contenu à enrichir ensuite",
          detail:
            leadRefresh.latest_suggestion?.summary ??
            "PraeviSEO pourra reprendre ce contenu, l’enrichir puis le republier si vous activez l’automatisation.",
          impact:
            leadRefresh.latest_suggestion?.impact_expected ??
            "Un contenu plus clair, plus solide et plus facile à faire progresser dans Google.",
          action: "Préparer une réécriture ciblée puis republier la version enrichie quand le sujet est prêt.",
          targetLabel: leadRefresh.title,
          targetUrl: leadRefresh.live_url || null,
          ctaLabel: leadRefresh.live_url ? "Voir la page live" : "Préparer une réécriture",
          ctaHref: leadRefresh.live_url || null,
          ctaAction: leadRefresh.live_url || !refreshPageSlug ? null : runRewriteAction(refreshPageSlug),
        }
      : null,
    site.summary.new_queries[0]
      ? {
          title: "Nouvelle requête à transformer",
          detail: `La requête "${site.summary.new_queries[0].query}" commence à émerger et peut ouvrir un nouveau contenu utile.`,
          impact: "Transformer vite une requête montante en page claire aide à capter les premières impressions avant les concurrents.",
          action: "Créer un premier contenu dédié ou enrichir une page existante qui répond exactement à cette intention.",
          targetLabel: site.summary.new_queries[0].query,
          targetUrl: null,
          ctaLabel: "Créer un article",
          ctaHref: null,
          ctaAction: runGenerationKeywordAction,
        }
      : null,
  ]
    .filter(Boolean)
    .slice(0, 3) as Array<{
      title: string;
      detail: string;
      impact: string;
      action: string;
      targetLabel: string;
      targetUrl: string | null;
      ctaLabel: string;
      ctaHref: string | null;
      ctaAction: ((formData: FormData) => void | Promise<void>) | null;
    }>;

  return (
    <div className="min-h-screen">
      <Topbar
        title={`Automatisations · ${site.name}`}
        subtitle="Ce que PraeviSEO fait pour ce site, quand il le fait et ce que cela produit."
        actions={
          <div className="flex items-center gap-2">
            <Button href={`/sites/${site.site_id}`} variant="secondary" size="sm">
              Retour au cockpit SEO
            </Button>
            <Button href={`/sites/${site.site_id}/connect`} size="sm">
              Santé technique
            </Button>
          </div>
        }
      />

      <div className="p-6 space-y-6">
        <div className="rounded-3xl border border-brand/20 bg-brand-muted px-6 py-6">
          <h1 className="text-2xl font-bold tracking-tight text-text">Automatisations du site</h1>
          <p className="mt-2 max-w-3xl text-sm leading-7 text-text-muted">
            Cette page répond à une seule question : qu’est-ce que PraeviSEO fait déjà pour votre site, à quel rythme et avec quel résultat.
            Toute la partie SSH, Doctor et installation reste dans la santé technique.
          </p>
        </div>

        {feedbackType && feedbackTitle && feedbackDetail ? (
          <div
            className={
              feedbackType === "success"
                ? "rounded-2xl border border-emerald-500/20 bg-emerald-500/10 px-5 py-4"
                : feedbackType === "warning"
                  ? "rounded-2xl border border-amber-500/20 bg-amber-500/10 px-5 py-4"
                  : "rounded-2xl border border-[hsl(var(--destructive)/0.2)] bg-[hsl(var(--destructive)/0.06)] px-5 py-4"
            }
          >
            <div className="text-sm font-semibold text-text">{feedbackTitle}</div>
            <p className="mt-2 text-sm leading-6 text-text-muted">{feedbackDetail}</p>
          </div>
        ) : null}

        <Card className="border-brand/20 bg-brand-muted">
          <CardHeader>
            <CardTitle>Cap recommandé maintenant</CardTitle>
            <CardDescription>
              L’action la plus utile à lancer tout de suite pour débloquer le site ou faire repartir la boucle.
            </CardDescription>
          </CardHeader>
          <CardContent className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
              <div className="text-base font-semibold text-text">{site.next_action.label}</div>
              <p className="mt-2 max-w-3xl text-sm leading-6 text-text-muted">{site.next_action.detail}</p>
              {recommendedActionButton?.blockedReason ? (
                <p className="mt-3 text-xs leading-6 text-text-subtle">
                  Pourquoi l’action ne part pas encore : {recommendedActionButton.blockedReason}
                </p>
              ) : null}
            </div>
            {recommendedAction.href ? (
              <Button href={recommendedAction.href}>{recommendedAction.label}</Button>
            ) : recommendedActionButton?.available ? (
              <form action={recommendedActionButton.action}>
                <Button type="submit">{recommendedActionButton.ctaLabel}</Button>
              </form>
            ) : recommendedActionButton?.helperHref ? (
              <Button href={recommendedActionButton.helperHref}>{recommendedActionButton.helperLabel}</Button>
            ) : null}
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Suivi du crawl</CardTitle>
            <CardDescription>
              Le parcours réel du crawl : quand il part, ce qu’il relit, quand il finit et ce qu’il a détecté.
            </CardDescription>
          </CardHeader>
          <CardContent className="grid gap-4 xl:grid-cols-3">
              <div className="rounded-2xl border border-border bg-surface-2 px-4 py-4">
                <div className="flex items-center justify-between gap-3">
                  <div className="text-sm font-semibold text-text">{crawlDisplayTitle}</div>
                  <Badge variant="secondary">{crawlDisplayLabel}</Badge>
                </div>
              <p className="mt-3 text-sm leading-6 text-text-muted">
                {crawlDisplayDetail}
              </p>
              <div className="mt-4 h-2 overflow-hidden rounded-full bg-surface-3">
                <div
                  className="h-full rounded-full bg-[hsl(var(--brand))] transition-all"
                  style={{ width: `${crawlProgressValue}%` }}
                />
              </div>
              <div className="mt-4 grid gap-3 text-sm text-text-muted sm:grid-cols-2">
                <div>
                  <span className="font-semibold text-text">Crawl lancé :</span>{" "}
                  {activeCrawlId ? `#${activeCrawlId}` : "pas encore de crawl identifié"}
                </div>
                <div>
                  <span className="font-semibold text-text">Pages découvertes :</span>{" "}
                  {displayCrawl ? displayCrawl.discovered_url_count : 0}
                </div>
                <div>
                  <span className="font-semibold text-text">Pages analysées :</span>{" "}
                  {displayCrawl ? displayCrawl.crawled_url_count : 0}
                </div>
                <div>
                  <span className="font-semibold text-text">Heure de lancement :</span>{" "}
                  {displayCrawl?.requested_at ? formatDate(displayCrawl.requested_at) : "pas encore lancé"}
                </div>
                <div>
                  <span className="font-semibold text-text">Heure de début :</span>{" "}
                  {displayCrawl?.started_at ? formatDate(displayCrawl.started_at) : "pas encore démarré"}
                </div>
                <div>
                  <span className="font-semibold text-text">Progression :</span>{" "}
                  {crawlProgressValue}%
                </div>
                <div>
                  <span className="font-semibold text-text">Résultat du crawl :</span>{" "}
                  {displayCrawl ? displayCrawl.issues_count : 0} point(s) remonté(s)
                </div>
              </div>
            </div>

            <div className="rounded-2xl border border-border bg-surface-2 px-4 py-4">
              <div className="flex items-center justify-between gap-3">
                <div className="text-sm font-semibold text-text">Dernier crawl réussi</div>
                <Badge variant="secondary">{lastSuccessfulCrawl ? "Disponible" : "Aucun"}</Badge>
              </div>
              <p className="mt-3 text-sm leading-6 text-text-muted">
                {lastSuccessfulCrawl
                  ? "Le dernier crawl terminé reste visible ici, même si un nouveau crawl est maintenant en attente ou en cours."
                  : "Dès qu’un premier crawl complet sera terminé, PraeviSEO gardera ici son dernier résultat réussi."}
              </p>
              <div className="mt-4 grid gap-3 text-sm text-text-muted sm:grid-cols-2">
                <div>
                  <span className="font-semibold text-text">Date :</span>{" "}
                  {latestSuccessfulCrawlAt ? formatDate(latestSuccessfulCrawlAt) : "pas encore de crawl réussi"}
                </div>
                <div>
                  <span className="font-semibold text-text">Pages analysées :</span>{" "}
                  {lastSuccessfulCrawl ? lastSuccessfulCrawl.crawled_url_count : 0}
                </div>
                <div>
                  <span className="font-semibold text-text">Pages découvertes :</span>{" "}
                  {lastSuccessfulCrawl ? lastSuccessfulCrawl.discovered_url_count : 0}
                </div>
                <div>
                  <span className="font-semibold text-text">Durée :</span>{" "}
                  {lastSuccessfulCrawl?.started_at && lastSuccessfulCrawl?.completed_at
                    ? `${Math.max(
                        0,
                        Math.round(
                          (new Date(lastSuccessfulCrawl.completed_at).getTime() -
                            new Date(lastSuccessfulCrawl.started_at).getTime()) /
                            1000
                        )
                      )} sec`
                    : "indisponible"}
                </div>
                <div className="sm:col-span-2">
                  <span className="font-semibold text-text">Résultats :</span>{" "}
                  {lastSuccessfulCrawl
                    ? `${lastSuccessfulCrawl.issues_count} point(s) détecté(s), ${lastSuccessfulCrawl.crawled_url_count} page(s) relue(s).`
                    : "Aucun résultat disponible pour le moment."}
                </div>
              </div>
            </div>

            <div className="rounded-2xl border border-border bg-surface-2 px-4 py-4">
              <div className="text-sm font-semibold text-text">Impact détecté</div>
              <p className="mt-3 text-sm leading-6 text-text-muted">{crawlImpactSummary}</p>
              <div className="mt-4 grid gap-3 sm:grid-cols-2">
                {crawlImpactItems.length > 0 ? (
                  crawlImpactItems.map((item) => (
                    <div key={item} className="rounded-xl border border-brand/20 bg-brand-muted px-3 py-3 text-sm text-text">
                      {item}
                    </div>
                  ))
                ) : (
                  <div className="sm:col-span-2 rounded-xl border border-border bg-surface-3 px-3 py-3 text-sm text-text-muted">
                    Dès que le crawl a relu assez de pages, PraeviSEO affichera ici les signaux vraiment utiles détectés sur le site.
                  </div>
                )}
              </div>
            </div>
          </CardContent>
        </Card>

        <div className="grid gap-6 xl:grid-cols-[1.05fr_0.95fr]">
          <Card>
            <CardHeader>
              <CardTitle>Pages relues par le crawl</CardTitle>
              <CardDescription>
                Les pages réellement relues lors du dernier crawl de référence, pour comprendre ce que PraeviSEO a observé.
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-3">
              {crawlReportPages.length > 0 ? (
                crawlReportPages.map((page) => (
                  <div key={page.url} className="rounded-2xl border border-border bg-surface-2 px-4 py-4">
                    <div className="flex items-center justify-between gap-3">
                      <div className="text-sm font-semibold text-text">{page.label}</div>
                      <Badge variant="secondary">{page.indexability_state}</Badge>
                    </div>
                    <p className="mt-2 break-all text-xs text-text-subtle">{page.url}</p>
                    <div className="mt-3 grid gap-3 text-sm text-text-muted sm:grid-cols-2 xl:grid-cols-4">
                      <div>
                        <span className="font-semibold text-text">Mots :</span> {page.latest_word_count}
                      </div>
                      <div>
                        <span className="font-semibold text-text">Autorité :</span> {page.authority_score}/100
                      </div>
                      <div>
                        <span className="font-semibold text-text">Orphelinage :</span> {page.orphan_score}/100
                      </div>
                      <div>
                        <span className="font-semibold text-text">Inlinks :</span> {page.internal_inlinks}
                      </div>
                    </div>
                  </div>
                ))
              ) : (
                <div className="rounded-2xl border border-border bg-surface-2 px-4 py-4 text-sm leading-6 text-text-muted">
                  Les pages relues apparaîtront ici dès qu’un crawl complet aura fini d’analyser le site.
                </div>
              )}
            </CardContent>
          </Card>

          <Card id="problemes-crawl">
            <CardHeader>
              <CardTitle>Problèmes trouvés</CardTitle>
              <CardDescription>
                Les points réellement remontés par le crawl, sans passer par les logs ou la base.
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
              {crawlReportIssueSummary.length > 0 ? (
                <div className="grid gap-3 sm:grid-cols-2">
                  {crawlReportIssueSummary.map((issue) => (
                    <div key={`${issue.type}-${issue.count}`} className="rounded-xl border border-border bg-surface-2 px-3 py-3 text-sm text-text">
                      <span className="font-semibold">{issue.type}</span> : {issue.count}
                    </div>
                  ))}
                </div>
              ) : null}

              {crawlReportIssues.length > 0 ? (
                <div className="space-y-3">
                  {crawlReportIssues.map((issue, index) => (
                    <div key={`${issue.type}-${issue.url ?? index}`} className="rounded-2xl border border-border bg-surface-2 px-4 py-4">
                      <div className="flex items-center justify-between gap-3">
                        <div className="text-sm font-semibold text-text">{issue.type}</div>
                        <Badge variant={issue.severity === "critical" || issue.severity === "high" ? "danger" : "secondary"}>
                          {issue.severity}
                        </Badge>
                      </div>
                      <p className="mt-2 text-sm leading-6 text-text-muted">{issue.details}</p>
                      {issue.url ? <p className="mt-2 break-all text-xs text-text-subtle">{issue.url}</p> : null}
                    </div>
                  ))}
                </div>
              ) : (
                <div className="rounded-2xl border border-border bg-surface-2 px-4 py-4 text-sm leading-6 text-text-muted">
                  Aucun problème concret n’a encore été remonté par le crawl de référence.
                </div>
              )}
            </CardContent>
          </Card>
        </div>

        <div className="grid gap-6 xl:grid-cols-[0.95fr_1.05fr]">
          <Card>
            <CardHeader>
              <CardTitle>Données produites par le crawl</CardTitle>
              <CardDescription>
                Ce que PraeviSEO a réellement ajouté ou mis à jour après la lecture du site. Les chiffres d indexation
                ci-dessous portent sur les {site.summary.gsc_indexation_scope_label.toLowerCase()}, pas sur le rapport
                Pages complet de Google Search Console.
              </CardDescription>
            </CardHeader>
            <CardContent className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
              {[
                ["Pages observées", crawlReportProducedData.observed_pages],
                ["Pages faibles", crawlReportProducedData.weak_pages],
                ["Pages orphelines", crawlReportProducedData.orphan_pages],
                ["Pages à mieux relier", crawlReportProducedData.link_gap_pages],
                ["Piliers candidats", crawlReportProducedData.pillar_candidates],
                ["Score santé", crawlReportProducedData.health_score],
                ["Issues crawl", crawlReportProducedData.crawl_issues],
                ["URLs inspectées indexées", crawlReportProducedData.indexed_pages],
                ["URLs inspectées non indexées", crawlReportProducedData.non_indexed_pages],
              ].map(([label, value]) => (
                <div key={label} className="rounded-2xl border border-border bg-surface-2 px-4 py-4">
                  <div className="text-xs uppercase tracking-[0.22em] text-text-subtle">{label}</div>
                  <div className="mt-2 text-2xl font-semibold text-text">{value}</div>
                </div>
              ))}
            </CardContent>
            <CardContent className="pt-0">
              <div className="rounded-2xl border border-border bg-surface-2 px-4 py-4 text-sm leading-6 text-text-muted">
                {site.summary.gsc_indexation_scope_hint}
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Ce qui a changé depuis le crawl précédent</CardTitle>
              <CardDescription>
                La différence avant / après pour voir si le nouveau passage a réellement produit quelque chose.
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-3">
              {crawlReportChanges.length > 0 ? (
                crawlReportChanges.map((change) => (
                  <div key={change.label} className="rounded-2xl border border-border bg-surface-2 px-4 py-4">
                    <div className="flex items-center justify-between gap-3">
                      <div className="text-sm font-semibold text-text">{change.label}</div>
                      <Badge variant={change.delta > 0 ? "default" : change.delta < 0 ? "danger" : "secondary"}>
                        {change.delta > 0 ? `+${change.delta}` : `${change.delta}`}
                      </Badge>
                    </div>
                    <div className="mt-3 grid gap-3 text-sm text-text-muted sm:grid-cols-3">
                      <div>
                        <span className="font-semibold text-text">Avant :</span> {change.previous}
                      </div>
                      <div>
                        <span className="font-semibold text-text">Après :</span> {change.current}
                      </div>
                      <div>
                        <span className="font-semibold text-text">Écart :</span> {change.delta > 0 ? `+${change.delta}` : change.delta}
                      </div>
                    </div>
                  </div>
                ))
              ) : (
                <div className="rounded-2xl border border-border bg-surface-2 px-4 py-4 text-sm leading-6 text-text-muted">
                  PraeviSEO affichera ici les différences dès qu’il pourra comparer ce crawl à un précédent crawl terminé.
                </div>
              )}
            </CardContent>
          </Card>
        </div>

        <Card>
          <CardHeader>
            <CardTitle>Historique des 10 derniers crawls</CardTitle>
            <CardDescription>
              Les derniers passages du moteur pour que l’utilisateur voie immédiatement ce que PraeviSEO a réellement fait.
            </CardDescription>
          </CardHeader>
          <CardContent className="space-y-3">
            {recentCrawls.length > 0 ? (
              recentCrawls.map((crawl) => {
                const durationSeconds =
                  crawl.started_at && crawl.completed_at
                    ? Math.max(0, Math.round((new Date(crawl.completed_at).getTime() - new Date(crawl.started_at).getTime()) / 1000))
                    : null;

                return (
                  <div key={crawl.id} className="rounded-2xl border border-border bg-surface-2 px-4 py-4">
                    <div className="flex items-center justify-between gap-3">
                      <div className="text-sm font-semibold text-text">Crawl #{crawl.id}</div>
                      <Badge variant="secondary">{crawl.status}</Badge>
                    </div>
                    <div className="mt-3 grid gap-3 text-sm text-text-muted sm:grid-cols-2 xl:grid-cols-5">
                      <div>
                        <span className="font-semibold text-text">Date :</span>{" "}
                        {crawl.requested_at ? formatDate(crawl.requested_at) : "indisponible"}
                      </div>
                      <div>
                        <span className="font-semibold text-text">Pages analysées :</span>{" "}
                        {crawl.crawled_url_count}
                      </div>
                      <div>
                        <span className="font-semibold text-text">Pages découvertes :</span>{" "}
                        {crawl.discovered_url_count}
                      </div>
                      <div>
                        <span className="font-semibold text-text">Durée :</span>{" "}
                        {durationSeconds !== null ? `${durationSeconds} sec` : "indisponible"}
                      </div>
                      <div>
                        <span className="font-semibold text-text">Résultat :</span>{" "}
                        {crawl.status === "completed"
                          ? `${crawl.issues_count} point(s)`
                          : crawl.status === "pending"
                            ? "En attente"
                            : crawl.status === "running"
                              ? "En cours"
                              : crawl.error ?? "À vérifier"}
                      </div>
                    </div>
                  </div>
                );
              })
            ) : (
              <div className="rounded-2xl border border-border bg-surface-2 px-4 py-4 text-sm leading-6 text-text-muted">
                Aucun crawl n’a encore été enregistré pour ce site.
              </div>
            )}
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
              <div>
                <CardTitle>Actions à lancer maintenant</CardTitle>
                <CardDescription>
                  Toutes les actions SEO sont ici, rangées par niveau réel de disponibilité pour éviter les faux espoirs.
                </CardDescription>
              </div>
            </div>
          </CardHeader>
          <CardContent className="space-y-4">
            <Tabs defaultValue="now" className="space-y-4">
              <TabsList className="w-full justify-start overflow-x-auto">
                <TabsTrigger value="now">Disponible maintenant ({actionableNowButtons.length})</TabsTrigger>
                <TabsTrigger value="soon">Bientôt prêt ({comingSoonButtons.length})</TabsTrigger>
                <TabsTrigger value="prep">Encore en préparation ({preparationButtons.length})</TabsTrigger>
              </TabsList>

              {[
                {
                  value: "now",
                  items: actionableNowButtons,
                  empty: "Aucune action n’est encore assez prête pour partir tout de suite. Commencez par relancer un crawl propre.",
                },
                {
                  value: "soon",
                  items: comingSoonButtons,
                  empty: "Aucune action intermédiaire n’attend encore de petit déblocage sur ce site.",
                },
                {
                  value: "prep",
                  items: preparationButtons,
                  empty: "Tout le reste est déjà branché ou bientôt prêt.",
                },
              ].map((group) => (
                <TabsContent key={group.value} value={group.value} className="mt-0">
                  {group.items.length > 0 ? (
                    <div className="grid gap-3 xl:grid-cols-3">
                      {group.items.map((item) => (
                        <div
                          key={item.key}
                          className={
                            item.recommended
                              ? "rounded-2xl border border-brand/30 bg-brand-muted px-4 py-4"
                              : "rounded-2xl border border-border bg-surface-2 px-4 py-4"
                          }
                        >
                          <div className="flex items-center justify-between gap-3">
                            <div className="text-sm font-semibold text-text">{item.label}</div>
                            <Badge
                              variant={
                                item.stage === "now"
                                  ? item.recommended
                                    ? "default"
                                    : "secondary"
                                  : item.stage === "soon"
                                    ? "warning"
                                    : "secondary"
                              }
                            >
                              {item.stage === "now"
                                ? item.recommended
                                  ? "Recommandé"
                                  : "Disponible"
                                : item.stage === "soon"
                                  ? "Bientôt prêt"
                                  : "En préparation"}
                            </Badge>
                          </div>
                          <p className="mt-2 text-sm leading-6 text-text-muted">{item.description}</p>
                          {item.blockedReason ? (
                            <p className="mt-3 text-xs leading-6 text-text-subtle">{item.blockedReason}</p>
                          ) : null}
                          <div className="mt-4 flex flex-wrap gap-2">
                            {item.available ? (
                              <form action={item.action} className="grow">
                                <Button type="submit" variant={item.recommended ? "primary" : "secondary"} className="w-full">
                                  {item.ctaLabel}
                                </Button>
                              </form>
                            ) : null}
                            {item.helperHref ? (
                              <Button href={item.helperHref} variant={item.available ? "secondary" : "primary"} className="grow">
                                {item.helperLabel}
                              </Button>
                            ) : null}
                          </div>
                        </div>
                      ))}
                    </div>
                  ) : (
                    <div className="rounded-2xl border border-border bg-surface-2 px-4 py-4 text-sm leading-6 text-text-muted">
                      {group.empty}
                    </div>
                  )}
                </TabsContent>
              ))}
            </Tabs>

            <div className="grid gap-4 xl:grid-cols-4">
            {executionHighlights.map((item) => (
              <div key={item.title} className="rounded-2xl border border-border bg-surface-2 px-4 py-4">
                <div className="flex items-center justify-between gap-3">
                  <div className="text-sm font-semibold text-text">{item.title}</div>
                  <Badge variant="secondary">{item.status}</Badge>
                </div>
                <p className="mt-3 text-sm leading-6 text-text-muted">{item.detail}</p>
                <div className="mt-4 space-y-3 text-xs leading-6 text-text-subtle">
                  <div>
                    <span className="font-semibold text-text">Dernière exécution :</span>{" "}
                    {item.updatedAt ? formatDate(item.updatedAt) : "pas encore de passage visible"}
                  </div>
                  <div>
                    <span className="font-semibold text-text">Prochain passage :</span> {item.nextPass}
                  </div>
                  <div>
                    <span className="font-semibold text-text">Résultat :</span> {item.result}
                  </div>
                  <div>
                    <span className="font-semibold text-text">Impact généré :</span> {item.impact}
                  </div>
                </div>
              </div>
            ))}
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Actions SEO par problème</CardTitle>
            <CardDescription>
              Les prochains vrais sujets à traiter sur le site, avec un point d’entrée clair pour agir dessus.
            </CardDescription>
          </CardHeader>
          <CardContent className="grid gap-4 xl:grid-cols-2">
            {problemActions.length > 0 ? (
              problemActions.map((item) => (
                <div key={item.key} className="rounded-2xl border border-border bg-surface-2 px-4 py-4">
                  <div className="text-sm font-semibold text-text">{item.title}</div>
                  <p className="mt-2 text-sm leading-6 text-text-muted">{item.detail}</p>
                  <div className="mt-3 rounded-xl border border-border bg-surface-3 px-3 py-3 text-sm text-text-muted">
                    <span className="font-semibold text-text">Pourquoi maintenant :</span> {item.whyNow}
                  </div>
                  <div className="mt-4 flex flex-wrap gap-2">
                    {item.action && item.actionLabel ? (
                      <form action={item.action}>
                        <Button type="submit">{item.actionLabel}</Button>
                      </form>
                    ) : null}
                    {item.primaryHref ? (
                      <Button href={item.primaryHref} variant={item.action ? "secondary" : "primary"}>
                        {item.primaryLabel}
                      </Button>
                    ) : null}
                    {item.secondaryHref && item.secondaryLabel ? (
                      <Button href={item.secondaryHref} variant="secondary">
                        {item.secondaryLabel}
                      </Button>
                    ) : null}
                  </div>
                </div>
              ))
            ) : (
              <div className="xl:col-span-2 rounded-2xl border border-border bg-surface-2 px-4 py-4 text-sm leading-6 text-text-muted">
                Dès qu’un problème concret d’indexation, de contenu ou de maillage ressortira, PraeviSEO listera ici les actions SEO à traiter en premier.
              </div>
            )}
          </CardContent>
        </Card>

        <div className="grid gap-6 xl:grid-cols-[0.95fr_1.05fr]">
          <Card>
            <CardHeader>
              <CardTitle>Rythme d’automatisation</CardTitle>
              <CardDescription>
                La vue synthétique du moteur : ce qui tourne déjà, ce qui nourrit les priorités et ce qui repartira ensuite.
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-3">
              {automationOverview.map((item) => (
                <div key={item.title} className="rounded-2xl border border-border bg-surface-2 px-4 py-4">
                  <div className="flex items-center justify-between gap-3">
                    <div className="text-sm font-semibold text-text">{item.title}</div>
                    <Badge variant="secondary">{item.status}</Badge>
                  </div>
                  <p className="mt-2 text-sm leading-6 text-text-muted">{item.detail}</p>
                </div>
              ))}
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Historique d’exécution</CardTitle>
              <CardDescription>
                Ce que PraeviSEO a déjà lancé, confirmé ou relancé sur ce site.
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-3">
              {executionHistory.map((entry, index) => (
                <div key={`${entry.at}-${index}`} className="rounded-2xl border border-border bg-surface-2 px-4 py-4">
                  <div className="flex items-center justify-between gap-3">
                    <div className="text-sm font-semibold text-text">
                      {entry.label}
                      {(entry.repeat_count ?? 1) > 1 ? ` (${entry.repeat_count} fois)` : ""}
                    </div>
                    <Badge variant={entry.tone}>{entry.at.slice(0, 10)}</Badge>
                  </div>
                  <p className="mt-2 text-sm leading-6 text-text-muted">{entry.detail}</p>
                </div>
              ))}
            </CardContent>
          </Card>
        </div>

        <Card>
          <CardHeader>
            <CardTitle>Prochaines opportunités automatiques</CardTitle>
            <CardDescription>
              Les pages et contenus que PraeviSEO pourra traiter en premier si vous laissez tourner l’automatisation.
            </CardDescription>
          </CardHeader>
          <CardContent className="grid gap-4 xl:grid-cols-3">
            {starterPlan.length > 0 ? (
              starterPlan.map((item) => (
                <div key={item.title} className="rounded-2xl border border-border bg-surface-2 px-4 py-4">
                  <div className="text-sm font-semibold text-text">{item.title}</div>
                  <p className="mt-2 text-sm leading-6 text-text-muted">{item.detail}</p>
                  <div className="mt-3 rounded-xl border border-border bg-surface-3 px-3 py-3 text-sm text-text-muted">
                    <span className="font-semibold text-text">Action recommandée :</span> {item.action}
                  </div>
                  <div className="mt-3 text-xs leading-6 text-text-subtle">
                    <span className="font-semibold text-text">Cible :</span>{" "}
                    {item.targetUrl ? (
                      <a href={item.targetUrl} className="text-[hsl(var(--brand))] underline underline-offset-4">
                        {item.targetLabel}
                      </a>
                    ) : (
                      item.targetLabel
                    )}
                  </div>
                  <div className="mt-3 rounded-xl border border-brand/20 bg-brand-muted px-3 py-3 text-sm text-text">
                    <span className="font-semibold">Gain attendu :</span> {item.impact}
                  </div>
                  <div className="mt-4 flex flex-wrap gap-2">
                    {item.ctaHref ? (
                      <Button href={item.ctaHref} size="sm">
                        {item.ctaLabel}
                      </Button>
                    ) : item.ctaAction ? (
                      <form action={item.ctaAction}>
                        <Button type="submit" size="sm">
                          {item.ctaLabel}
                        </Button>
                      </form>
                    ) : null}
                    {item.targetUrl ? (
                      <Button href={item.targetUrl} variant="secondary" size="sm">
                        Ouvrir la cible
                      </Button>
                    ) : null}
                  </div>
                </div>
              ))
            ) : (
              <div className="xl:col-span-3 rounded-2xl border border-border bg-surface-2 px-4 py-4 text-sm leading-6 text-text-muted">
                PraeviSEO préparera d’abord un crawl, un repérage des pages utiles et une première séquence d’actions automatiques
                dès qu’une première opportunité claire sera confirmée sur ce site.
              </div>
            )}
          </CardContent>
        </Card>

        {executionIssues.length > 0 ? (
          <Card>
            <CardHeader>
              <CardTitle>Erreurs à revoir</CardTitle>
              <CardDescription>
                Les derniers points bloquants rencontrés par les automatisations. La santé technique reste disponible séparément.
              </CardDescription>
            </CardHeader>
            <CardContent className="grid gap-4 xl:grid-cols-2">
              {executionIssues.map((issue) => (
                <div key={issue.key} className="rounded-2xl border border-[hsl(var(--destructive)/0.2)] bg-[hsl(var(--destructive)/0.06)] px-4 py-4">
                  <div className="flex items-center justify-between gap-3">
                    <div className="text-sm font-semibold text-text">{issue.title}</div>
                    <Badge variant="danger">{issue.updatedAt ? issue.updatedAt.slice(0, 10) : "À revoir"}</Badge>
                  </div>
                  <p className="mt-2 text-sm leading-6 text-text-muted">{issue.detail}</p>
                </div>
              ))}
            </CardContent>
          </Card>
        ) : null}

      </div>
    </div>
  );
}
