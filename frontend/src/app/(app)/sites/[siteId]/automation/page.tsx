import { Topbar } from "@/components/layout/topbar";
import { SiteAccessState } from "@/components/sites/site-access-state";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import {
  getPublications,
  getSettings,
  getSite,
} from "@/lib/praeviseo-api";
import { formatDate } from "@/lib/utils";
import {
  launchPremiumCrawlAction,
  launchPremiumGenerationToStudioAction,
  launchPremiumImageToStudioAction,
  launchPremiumLinkingAction,
  launchPremiumPublicationToStudioAction,
  launchPremiumRewriteToStudioAction,
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

type GenerationAudit = {
  status: "eligible" | "rejected" | "cooldown" | "no_data";
  queries_analyzed_count: number;
  eligible_queries_count: number;
  rejected_queries_count: number;
  limit_reason: string | null;
  minimum_query_impressions: number;
  maximum_query_position: number;
  min_hours_between_articles: number;
  max_articles_per_28_days: number;
  best_query: {
    query: string;
    impressions: number;
    previous_impressions: number;
    position: number;
    score: number;
    eligible: boolean;
    rejection_reason: string | null;
  } | null;
  rejection_breakdown: Record<string, number>;
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

function describeGenerationReason(reason: string | null | undefined): string {
  switch (reason) {
    case "volume_trop_faible":
      return "Le volume est encore trop faible par rapport au seuil minimum.";
    case "position_inconnue":
      return "La position Google est absente ou encore inutilisable.";
    case "position_trop_lointaine":
      return "La requête est encore trop loin dans Google pour ouvrir un article propre.";
    case "deja_couverte":
      return "Le sujet est déjà trop proche d’un contenu existant.";
    case "site_profile":
      return "Aucune requête Google assez nette pour l’instant, mais le profil métier propose déjà un sujet éditorial.";
    default:
      return "Aucun sujet assez net n’est encore retenu par le moteur.";
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
  const liveVerifiedCount = sitePublications.filter((item) => item.live_verified).length;
  const publishedButUnverifiedCount = sitePublications.filter(
    (item) => item.published_live && !item.live_verified
  ).length;
  const monitoredContentCount = sitePublications.filter((item) => item.observed_content).length;
  const publicationReady = site.publication_target.engine_actionable;
  const bridgeOperational = bridgeConnected || publicationReady;
  const hasPublishedPages = site.readiness.has_published_pages || sitePublications.length > 0;
  const hasVerifiedLivePages = liveVerifiedCount > 0;
  const hasUnverifiedPublishedPages = publishedButUnverifiedCount > 0;
  const latestPublishedContent =
    sitePublications.find((item) => item.live_verified) ??
    sitePublications.find((item) => item.published_live) ??
    null;
  const currentCrawl = site.crawl;
  const lastSuccessfulCrawl = site.last_successful_crawl;
  const loopStatus =
    site.action_statuses.monitoring.state === "failed"
      ? "À revoir"
      : bridgeConnected && (gscConnected || hasPublishedPages || Boolean(lastSuccessfulCrawl))
        ? "Active"
        : "En attente";
  const crawlReport = site.crawl_report;
  const generationAudit = ((site.summary as { generation_audit?: GenerationAudit }).generation_audit ?? null);
  const generationAuditReason =
    generationAudit?.limit_reason ??
    generationAudit?.best_query?.rejection_reason ??
    null;
  const generationAuditSummary = generationAudit
    ? `Requêtes analysées : ${generationAudit.queries_analyzed_count}. Seuil minimum : ${generationAudit.minimum_query_impressions} impressions et position <= ${generationAudit.maximum_query_position}.`
    : null;
  const displayCrawl =
    currentCrawl &&
    lastSuccessfulCrawl &&
    currentCrawl.id === lastSuccessfulCrawl.id &&
    currentCrawl.status !== "completed" &&
    lastSuccessfulCrawl.status === "completed"
      ? lastSuccessfulCrawl
      : currentCrawl;
  const crawlReportIssues = crawlReport?.issues ?? [];
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
      status: hasVerifiedLivePages
        ? "Live vérifié"
        : hasUnverifiedPublishedPages
          ? "À vérifier"
        : publicationReady
          ? "Prête"
          : bridgeOperational
            ? "Bridge connecté"
            : idleActionLabel(site.action_statuses.publication.state, site.action_statuses.publication.label, "À préparer"),
      detail:
        (hasVerifiedLivePages
          ? `${liveVerifiedCount} contenu(s) live sont réellement confirmés et peuvent être repris automatiquement.`
          : hasUnverifiedPublishedPages
            ? `${publishedButUnverifiedCount} contenu(s) ont une URL live enregistrée, mais PraeviSEO n’a pas encore confirmé qu’elle répond correctement.`
          : site.publication_target.live_gap_detail
            ? site.publication_target.live_gap_detail
          : publicationReady
            ? site.publication_target.detail || "Le bridge est prêt. PraeviSEO peut pousser le premier contenu utile dès qu’il est prêt."
            : bridgeOperational
              ? "Le bridge répond déjà. Il reste à pousser un premier contenu visible pour démarrer la boucle live."
              : site.action_statuses.publication.detail ||
                site.publication_target.detail ||
                "La publication démarrera juste après l’activation complète de la connexion premium."),
      updatedAt: site.action_statuses.publication.updated_at,
      nextPass: ACTION_NEXT_PASSES.publication,
      result: hasVerifiedLivePages
        ? `Le site a déjà ${liveVerifiedCount} contenu(s) réellement visibles en live.`
        : hasUnverifiedPublishedPages
          ? `${publishedButUnverifiedCount} contenu(s) ont été poussés, mais restent à vérifier avant d’être considérés comme vraiment visibles.`
        : publicationReady
          ? "Le site peut déjà recevoir une première publication live."
          : describeResult(site.action_statuses.publication.state, site.action_statuses.publication.detail, site.action_statuses.publication.error),
      impact:
        hasVerifiedLivePages
          ? `${liveVerifiedCount} contenu(s) sont déjà visibles et peuvent maintenant être suivis en conditions réelles.`
          : hasUnverifiedPublishedPages
            ? "Évite de présenter comme visible un contenu dont l’URL live n’est pas encore confirmée."
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
  const latestFailures = [
    site.action_statuses.crawl.error
      ? {
          action: "Crawl",
          detail: site.action_statuses.crawl.error,
          at: site.action_statuses.crawl.updated_at,
        }
      : null,
    site.action_statuses.generation.error
      ? {
          action: "Génération",
          detail: site.action_statuses.generation.error,
          at: site.action_statuses.generation.updated_at,
        }
      : null,
    site.action_statuses.rewrite.error
      ? {
          action: "Réécriture",
          detail: site.action_statuses.rewrite.error,
          at: site.action_statuses.rewrite.updated_at,
        }
      : null,
    site.action_statuses.linking.error
      ? {
          action: "Maillage",
          detail: site.action_statuses.linking.error,
          at: site.action_statuses.linking.updated_at,
        }
      : null,
    site.action_statuses.images.error
      ? {
          action: "Image",
          detail: site.action_statuses.images.error,
          at: site.action_statuses.images.updated_at,
        }
      : null,
    site.action_statuses.publication.error
      ? {
          action: "Publication",
          detail: site.action_statuses.publication.error,
          at: site.action_statuses.publication.updated_at,
        }
      : null,
  ]
    .filter(
      (item): item is { action: string; detail: string; at: string | null } =>
        Boolean(item)
    )
    .sort((left, right) => new Date(right.at ?? 0).getTime() - new Date(left.at ?? 0).getTime());

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
  const generationFromProfile = site.summary.new_queries[0]?.source === "site_profile";
  const runCrawlAction = launchPremiumCrawlAction.bind(null, site.site_id);
  const runGenerationKeywordAction = generationKeyword
    ? launchPremiumGenerationToStudioAction.bind(null, site.site_id, generationKeyword)
    : null;
  const runRewriteAction = (slug?: string | null) => launchPremiumRewriteToStudioAction.bind(null, site.site_id, slug ?? undefined);
  const runLinkingAction = (slug?: string | null) => launchPremiumLinkingAction.bind(null, site.site_id, slug ?? undefined);
  const runImageAction = (slug?: string | null) => launchPremiumImageToStudioAction.bind(null, site.site_id, slug ?? undefined);
  const runPublicationAction = (slug?: string | null) =>
    launchPremiumPublicationToStudioAction.bind(null, site.site_id, slug ?? undefined);
  const queryFocusHref = generationKeyword
    ? `/publications?focus=query&site=${encodeURIComponent(site.site_id)}&query=${encodeURIComponent(generationKeyword)}`
    : "/queries";
  const rewriteFocusHref = refreshPageSlug
    ? `/publications?focus=content&site=${encodeURIComponent(site.site_id)}&slug=${encodeURIComponent(refreshPageSlug)}&action=rewrite`
    : "/pages";
  const linkingFocusHref = linkGapSlug
    ? `/pages?focus=linking&site=${encodeURIComponent(site.site_id)}&target=${encodeURIComponent(linkGapSlug)}`
    : "/pages";
  const imageFocusTarget = risingPageSlug ?? refreshPageSlug;
  const imageFocusHref = imageFocusTarget
    ? `/publications?focus=content&site=${encodeURIComponent(site.site_id)}&slug=${encodeURIComponent(imageFocusTarget)}&action=image`
    : "/pages";
  const publicationFocusTarget = latestPublishedContent?.slug ?? refreshPageSlug;
  const publicationFocusHref = publicationFocusTarget
    ? `/publications?focus=content&site=${encodeURIComponent(site.site_id)}&slug=${encodeURIComponent(publicationFocusTarget)}&action=publish`
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
          ? generationFromProfile
            ? `Le profil métier propose déjà un sujet éditorial : ${site.summary.new_queries[0].query}.`
            : `Une requête montante est déjà visible : ${site.summary.new_queries[0].query}.`
          : "Ouvrir un nouveau contenu dès qu’un sujet assez net mérite une vraie page.",
      recommended: recommendedActionKey === "generation",
      available: generationReady,
      blockedReason: generationReady
        ? null
        : gscConnected
          ? [
              generationAuditSummary,
              generationAudit?.limit_reason
                ? `Blocage actuel : ${generationAudit.limit_reason}.`
                : `Motif principal de rejet : ${describeGenerationReason(generationAuditReason)}`,
              generationAudit?.best_query
                ? `Meilleure requête vue : ${generationAudit.best_query.query} (${generationAudit.best_query.impressions} impressions, position ${generationAudit.best_query.position}).`
                : null,
            ]
              .filter(Boolean)
              .join(" ")
          : "Reliez d’abord Google Search Console pour laisser PraeviSEO détecter les vraies recherches montantes du site.",
      helperHref: queryFocusHref,
      helperLabel: "Voir les requêtes utiles",
      action: generationReady && runGenerationKeywordAction ? runGenerationKeywordAction : runCrawlAction,
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
      stage: publicationLaunchReady ? "now" : bridgeOperational ? "soon" : "prep",
      ctaLabel: publicationLaunchReady
        ? "Publier en live"
        : !bridgeOperational
          ? "Bridge à connecter"
          : "Contenu encore à préparer",
      description: hasVerifiedLivePages
        ? "Pousser un nouveau contenu ou une mise à jour sur le site déjà connecté."
        : hasUnverifiedPublishedPages
          ? "Vérifier d’abord les URLs live déjà poussées avant de présenter le site comme vraiment publié."
        : publicationReady
          ? "Le bridge répond déjà : une première publication live peut partir."
          : bridgeOperational
            ? "Le bridge est actif, mais PraeviSEO attend encore un contenu prêt à pousser."
            : "La publication live restera limitée tant que le bridge n’est pas complètement prêt.",
      recommended: recommendedActionKey === "publication",
      available: publicationLaunchReady,
      blockedReason: publicationLaunchReady
        ? null
        : !bridgeOperational
          ? "Le bridge client n’est pas encore assez branché pour pousser une vraie publication live."
          : !publicationReady
            ? "Le bridge répond, mais PraeviSEO n’a pas encore validé une publication réellement actionnable."
            : "Le bridge est prêt, mais aucun contenu assez propre n’est encore prêt à partir en live.",
      helperHref: !bridgeOperational ? `/sites/${site.site_id}/connect` : publicationFocusHref,
      helperLabel: !bridgeOperational ? "Ouvrir la santé technique" : "Voir les contenus prêts",
      action: runPublicationAction(latestPublishedContent?.slug ?? refreshPageSlug),
    },
  ] as const;
  const actionableNowButtons = actionButtons.filter((item) => item.stage === "now");
  const comingSoonButtons = actionButtons.filter((item) => item.stage === "soon");
  const preparationButtons = actionButtons.filter((item) => item.stage === "prep");
  const readyNowActions = actionableNowButtons.slice(0, 4);
  const blockedActions = [...comingSoonButtons, ...preparationButtons].slice(0, 4);
  const recommendedActionButton =
    recommendedActionKey ? actionButtons.find((item) => item.key === recommendedActionKey) ?? null : null;
  const priorityAction = (() => {
    if (site.summary.indexation_alerts.length > 0) {
      return {
        title: `${site.summary.indexation_alerts.length} page(s) hors index à corriger`,
        detail: "Google voit déjà des pages qui restent hors index. Tant que ce bloc n’est pas traité, le site perd du terrain visible.",
        whyNow: "Impact estimé élevé : une page hors index ne peut pas produire de trafic organique propre.",
        actionLabel: "Ouvrir l’indexation",
        href: "/indexation",
        source: `Source réelle : Search Console, ${site.summary.indexation_alerts.length} alerte(s) page-level encore non indexée(s).`,
      };
    }

    if (site.summary.observed_link_gap_pages.length > 0) {
      return {
        title: `${site.summary.observed_link_gap_pages.length} page(s) sous-maillée(s)`,
        detail: "Le crawl a trouvé des pages qui méritent plus de soutien interne avant de viser une vraie progression SEO.",
        whyNow: "Impact estimé moyen à fort : le maillage aide Google à recrawler, contextualiser puis pousser la bonne page.",
        actionLabel: "Lancer le maillage",
        action: actionButtons.find((item) => item.key === "linking" && item.available)?.action ?? null,
        helperHref: actionButtons.find((item) => item.key === "linking")?.helperHref ?? "/pages",
        helperLabel: actionButtons.find((item) => item.key === "linking")?.helperLabel ?? "Voir les pages sous-maillées",
        source: `Source réelle : crawl observé, ${site.summary.observed_link_gap_pages.length} page(s) avec manque de liens internes utiles.`,
      };
    }

    if (generationAudit && !generationReady) {
      return {
        title: "Créer un article : aucune requête encore retenue",
        detail: describeGenerationReason(generationAuditReason),
        whyNow: generationAuditSummary ?? "Le moteur n’a pas encore trouvé un sujet article assez net.",
        actionLabel: "Voir les requêtes utiles",
        href: "/queries",
        source: generationAudit.best_query
          ? `Meilleure requête vue : ${generationAudit.best_query.query} (${generationAudit.best_query.impressions} impressions, position ${generationAudit.best_query.position}).`
          : "Aucune requête exploitable n’a encore été confirmée sur la dernière fenêtre GSC.",
      };
    }

    return {
      title: site.next_action.label,
      detail: site.next_action.detail,
      whyNow: "C’est actuellement l’action la plus utile remontée par le moteur compte tenu du contexte du site.",
      actionLabel: recommendedAction.label,
      href: recommendedAction.href,
      action: recommendedActionButton?.available ? recommendedActionButton.action : null,
      helperHref: recommendedActionButton?.helperHref ?? null,
      helperLabel: recommendedActionButton?.helperLabel ?? null,
      source: `Source réelle : moteur runtime, next_action.kind = ${site.next_action.kind}.`,
    };
  })();
  const executionHighlights = executionCenter.filter((item) =>
    ["crawl", "publication", "rewrite", "monitoring"].includes(item.key)
  );
  const recentActivity = executionHistory.slice(0, 4);
  const problemActions = [
    generationKeyword || generationAudit
      ? {
          key: "generation",
          title: generationReady ? "Créer un article utile" : "Comprendre pourquoi aucun article ne part",
          detail: generationReady
            ? `Une requête montante est déjà visible : ${generationKeyword}.`
            : generationAudit?.best_query
              ? `Aucune requête n’est encore retenue. La meilleure vue pour l’instant est ${generationAudit.best_query.query}.`
              : "Aucune requête assez nette n’est encore retenue pour ouvrir un article fiable.",
          whyNow: generationReady
            ? "Le moteur a déjà trouvé un sujet exploitable. C’est maintenant qu’il faut ouvrir le brouillon avant que le signal retombe."
            : generationAuditSummary ??
              "Le moteur lit bien les requêtes Google, mais aucune n’a encore passé les seuils utiles ou les règles anti-doublon.",
          primaryHref: queryFocusHref,
          primaryLabel: "Voir les requêtes utiles",
          action: generationReady && runGenerationKeywordAction ? runGenerationKeywordAction : null,
          actionLabel: generationReady ? "Créer l’article ciblé" : null,
          secondaryHref: generationReady ? `/publications?focus=query&site=${encodeURIComponent(site.site_id)}&query=${encodeURIComponent(generationKeyword ?? "")}` : null,
          secondaryLabel: generationReady ? "Ouvrir le studio" : null,
        }
      : null,
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

        <Card>
          <CardHeader>
            <CardTitle>Historique réel des actions</CardTitle>
            <CardDescription>
              Ce qui a vraiment tourné, quand, avec quel résultat, sans lire les logs serveur.
            </CardDescription>
          </CardHeader>
          <CardContent className="grid gap-4 xl:grid-cols-[1.1fr_0.9fr]">
            <div className="space-y-3">
              {executionHistory.map((entry, index) => (
                <div key={`${entry.at}-${entry.kind}-${index}`} className="rounded-2xl border border-border bg-surface-2 px-4 py-4">
                  <div className="flex items-center justify-between gap-3">
                    <div className="text-sm font-semibold text-text">{entry.label}</div>
                    <Badge variant={entry.tone}>{formatDate(entry.at)}</Badge>
                  </div>
                  <p className="mt-2 text-sm leading-6 text-text-muted">{entry.detail}</p>
                  {entry.repeat_count && entry.repeat_count > 1 ? (
                    <p className="mt-2 text-xs text-text-subtle">Vu {entry.repeat_count} fois sur l’historique récent.</p>
                  ) : null}
                </div>
              ))}
            </div>

            <div className="space-y-3">
              <div className="rounded-2xl border border-border bg-surface-2 px-4 py-4">
                <div className="text-sm font-semibold text-text">Dernière erreur réelle</div>
                <div className="mt-3 space-y-3">
                  {latestFailures.length > 0 ? (
                    latestFailures.slice(0, 3).map((item) => (
                      <div key={`${item.action}-${item.at ?? "unknown"}`} className="rounded-xl border border-danger/20 bg-danger/10 px-3 py-3">
                        <div className="flex items-center justify-between gap-3">
                          <div className="text-sm font-medium text-text">{item.action}</div>
                          <Badge variant="danger">{item.at ? formatDate(item.at) : "à l’instant"}</Badge>
                        </div>
                        <p className="mt-2 text-sm leading-6 text-text-muted">{item.detail}</p>
                      </div>
                    ))
                  ) : (
                    <div className="rounded-xl border border-border bg-surface-3 px-3 py-3 text-sm leading-6 text-text-muted">
                      Aucune erreur active remontée sur les dernières actions visibles.
                    </div>
                  )}
                </div>
              </div>

              <div className="rounded-2xl border border-border bg-surface-2 px-4 py-4">
                <div className="text-sm font-semibold text-text">Vérité du moteur</div>
                <p className="mt-2 text-sm leading-6 text-text-muted">
                  Les statuts “Terminé” ou “Actif” affichés plus bas ne valent que pour l’action moteur interne. Si une erreur existe ici ou si le live n’est pas vérifié, le système n’est pas considéré comme sain.
                </p>
              </div>
            </div>
          </CardContent>
        </Card>

        <Card className="border-brand/20 bg-brand-muted">
          <CardHeader>
            <CardTitle>Action prioritaire</CardTitle>
            <CardDescription>
              La seule action à regarder en premier pour savoir quoi faire maintenant.
            </CardDescription>
          </CardHeader>
          <CardContent className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
              <div className="text-base font-semibold text-text">{priorityAction.title}</div>
              <p className="mt-2 max-w-3xl text-sm leading-6 text-text-muted">{priorityAction.detail}</p>
              <div className="mt-3 rounded-xl border border-border bg-surface/70 px-3 py-3 text-sm leading-6 text-text">
                <span className="font-semibold">Pourquoi maintenant :</span> {priorityAction.whyNow}
              </div>
              <p className="mt-3 text-xs leading-6 text-text-subtle">{priorityAction.source}</p>
            </div>
            {priorityAction.href ? (
              <Button href={priorityAction.href}>{priorityAction.actionLabel}</Button>
            ) : priorityAction.action ? (
              <form action={priorityAction.action}>
                <Button type="submit">{priorityAction.actionLabel}</Button>
              </form>
            ) : priorityAction.helperHref ? (
              <Button href={priorityAction.helperHref}>{priorityAction.helperLabel ?? priorityAction.actionLabel}</Button>
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

        <Card>
          <CardHeader>
            <CardTitle>Vue simple du site</CardTitle>
            <CardDescription>
              Les 4 chiffres qui servent vraiment à décider quoi faire ensuite.
            </CardDescription>
          </CardHeader>
          <CardContent className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            {[
              ["Pages suivies", crawlReportProducedData.observed_pages],
              ["Problèmes de crawl", crawlReportProducedData.crawl_issues],
              ["Pages hors index", crawlReportProducedData.non_indexed_pages],
              ["Pages à relier", crawlReportProducedData.link_gap_pages],
            ].map(([label, value]) => (
              <div key={label} className="rounded-2xl border border-border bg-surface-2 px-4 py-4">
                <div className="text-xs uppercase tracking-[0.22em] text-text-subtle">{label}</div>
                <div className="mt-2 text-2xl font-semibold text-text">{value}</div>
              </div>
            ))}
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
              <div>
                <CardTitle>Actions à faire maintenant</CardTitle>
                <CardDescription>
                  Les seules actions utiles tout de suite. Le reste attendra.
                </CardDescription>
              </div>
            </div>
          </CardHeader>
          <CardContent className="space-y-4">
            {readyNowActions.length > 0 ? (
              <div className="grid gap-3 xl:grid-cols-2">
                {readyNowActions.map((item) => (
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
                      <Badge variant={item.recommended ? "default" : "secondary"}>
                        {item.recommended ? "À lancer" : "Disponible"}
                      </Badge>
                    </div>
                    <p className="mt-2 text-sm leading-6 text-text-muted">{item.description}</p>
                    <div className="mt-4 flex flex-wrap gap-2">
                      <form action={item.action} className="grow">
                        <Button type="submit" variant={item.recommended ? "primary" : "secondary"} className="w-full">
                          {item.ctaLabel}
                        </Button>
                      </form>
                      {item.helperHref ? (
                        <Button href={item.helperHref} variant="secondary" className="grow">
                          {item.helperLabel}
                        </Button>
                      ) : null}
                    </div>
                  </div>
                ))}
              </div>
            ) : (
              <div className="rounded-2xl border border-border bg-surface-2 px-4 py-4 text-sm leading-6 text-text-muted">
                Aucune action n’est encore assez prête pour partir tout de suite. Commencez par relancer un crawl propre.
              </div>
            )}

            {blockedActions.length > 0 ? (
              <div className="rounded-2xl border border-border bg-surface-2 px-4 py-4">
                <div className="text-sm font-semibold text-text">Ce qui attend encore</div>
                <div className="mt-3 grid gap-3 xl:grid-cols-2">
                  {blockedActions.map((item) => (
                    <div key={item.key} className="rounded-xl border border-border bg-surface-3 px-3 py-3">
                      <div className="flex items-center justify-between gap-3">
                        <div className="text-sm font-medium text-text">{item.label}</div>
                        <Badge variant="secondary">{item.stage === "soon" ? "Bientôt prêt" : "En préparation"}</Badge>
                      </div>
                      <p className="mt-2 text-sm leading-6 text-text-muted">{item.blockedReason ?? item.description}</p>
                      {item.key === "generation" && generationAudit ? (
                        <div className="mt-3 rounded-xl border border-border bg-surface px-3 py-3 text-xs leading-6 text-text-muted">
                          <div>
                            <span className="font-semibold text-text">Requêtes analysées :</span>{" "}
                            {generationAudit.queries_analyzed_count}
                          </div>
                          <div>
                            <span className="font-semibold text-text">Seuil minimum :</span>{" "}
                            {generationAudit.minimum_query_impressions} impressions et position ≤ {generationAudit.maximum_query_position}
                          </div>
                          <div>
                            <span className="font-semibold text-text">Rejet principal :</span>{" "}
                            {describeGenerationReason(generationAuditReason)}
                          </div>
                          <div>
                            <span className="font-semibold text-text">Meilleure requête vue :</span>{" "}
                            {generationAudit.best_query
                              ? `${generationAudit.best_query.query} (${generationAudit.best_query.impressions} impressions, position ${generationAudit.best_query.position})`
                              : "aucune requête exploitable pour le moment"}
                          </div>
                        </div>
                      ) : null}
                    </div>
                  ))}
                </div>
              </div>
            ) : null}
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Problèmes concrets à traiter</CardTitle>
            <CardDescription>
              Pas de théorie : juste les vrais blocages détectés et quoi faire dessus.
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

        <details className="group rounded-3xl border border-border bg-surface/80">
          <summary className="flex cursor-pointer list-none items-center justify-between gap-3 px-5 py-4">
            <div>
              <p className="text-sm font-semibold text-text">Détails de l’automatisation</p>
              <p className="text-xs text-text-subtle">Activité récente, historique moteur et éléments secondaires du site.</p>
            </div>
            <span className="text-xs text-text-subtle transition group-open:rotate-180">▾</span>
          </summary>

          <div className="grid gap-6 border-t border-border px-5 py-5 xl:grid-cols-[0.95fr_1.05fr]">
            <Card>
              <CardHeader>
                <CardTitle>Activité récente</CardTitle>
                <CardDescription>
                  Les derniers événements vraiment utiles sur ce site.
                </CardDescription>
              </CardHeader>
              <CardContent className="space-y-3">
                {recentActivity.length > 0 ? (
                  recentActivity.map((entry, index) => (
                    <div key={`${entry.at}-${index}`} className="rounded-2xl border border-border bg-surface-2 px-4 py-4">
                      <div className="flex items-center justify-between gap-3">
                        <div className="text-sm font-semibold text-text">{entry.label}</div>
                        <Badge variant={entry.tone}>{entry.at.slice(0, 10)}</Badge>
                      </div>
                      <p className="mt-2 text-sm leading-6 text-text-muted">{entry.detail}</p>
                    </div>
                  ))
                ) : (
                  <div className="rounded-2xl border border-border bg-surface-2 px-4 py-4 text-sm leading-6 text-text-muted">
                    Aucune activité utile n’a encore été enregistrée sur ce site.
                  </div>
                )}
              </CardContent>
            </Card>

            <Card>
              <CardHeader>
                <CardTitle>État du moteur</CardTitle>
                <CardDescription>
                  Ce qui tourne déjà sans t’obliger à lire 10 cartes différentes.
                </CardDescription>
              </CardHeader>
              <CardContent className="space-y-3">
                {executionHighlights.map((item) => (
                  <div key={item.title} className="rounded-2xl border border-border bg-surface-2 px-4 py-4">
                    <div className="flex items-center justify-between gap-3">
                      <div className="text-sm font-semibold text-text">{item.title}</div>
                      <Badge variant="secondary">{item.status}</Badge>
                    </div>
                    <p className="mt-2 text-sm leading-6 text-text-muted">{item.result}</p>
                  </div>
                ))}
              </CardContent>
            </Card>
          </div>
        </details>
      </div>
    </div>
  );
}
