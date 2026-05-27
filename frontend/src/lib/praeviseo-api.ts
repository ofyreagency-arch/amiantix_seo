import "server-only";
import { getSessionToken } from "@/lib/auth";

export type PraeviseoSite = {
  id: number;
  site_id: string;
  name: string;
  url: string;
  niche: string;
  locale: string;
  preset: string;
  is_active: boolean;
  webhook_url: string | null;
  publication_mode: string;
  publication_mode_label: string;
  publication_connect_code: string | null;
  publication_bridge_status: string;
  publication_path_prefix: string | null;
  gsc_property_url: string | null;
  gsc_connection_mode: string | null;
  gsc_connection_status: string;
  gsc_account_email: string | null;
  gsc_last_sync_at: string | null;
  gsc_data_as_of: string | null;
  installation: {
    status: string;
    current_step: string | null;
    progress: number;
    hosting_provider: string | null;
    access_method: string | null;
    requested_at: string | null;
    started_at: string | null;
    completed_at: string | null;
    failed_at: string | null;
    error_message: string | null;
    detected_framework: string | null;
    detected_php_version: string | null;
    detected_composer: string | null;
    logs: Array<{
      at: string;
      level: string;
      step: string;
      message: string;
    }>;
  };
  created_at: string;
  summary: {
    pages_total: number;
    pages_published: number;
    pages_live: number;
    pending_suggestions: number;
    observed_pages: number;
    observed_weak_pages: number;
    observed_orphan_pages: number;
    observed_pillar_candidates: number;
    observed_avg_authority: number;
    observed_avg_orphan: number;
    observed_site_health_score: number;
    observed_snapshot_date: string | null;
    observed_avg_seo_score: number;
    observed_avg_quality_score: number;
    observed_avg_topical_score: number;
    observed_crawl_issues: number;
    observed_pillar_pages: Array<{
      label: string;
      slug: string;
      url: string;
      path: string;
      authority_score: number;
      orphan_score: number;
      overlap_score: number;
      pillar_likelihood: number;
      internal_inlinks: number;
      internal_outlinks: number;
      latest_word_count: number;
      indexability_state: string;
      cluster_label: string | null;
      last_seen_at: string | null;
    }>;
    observed_link_gap_pages: Array<{
      label: string;
      slug: string;
      url: string;
      path: string;
      authority_score: number;
      orphan_score: number;
      overlap_score: number;
      pillar_likelihood: number;
      internal_inlinks: number;
      internal_outlinks: number;
      latest_word_count: number;
      indexability_state: string;
      cluster_label: string | null;
      last_seen_at: string | null;
    }>;
    observed_orphan_alerts: Array<{
      label: string;
      slug: string;
      url: string;
      path: string;
      authority_score: number;
      orphan_score: number;
      overlap_score: number;
      pillar_likelihood: number;
      internal_inlinks: number;
      internal_outlinks: number;
      latest_word_count: number;
      indexability_state: string;
      cluster_label: string | null;
      last_seen_at: string | null;
    }>;
    observed_weak_page_details: Array<{
      label: string;
      slug: string;
      url: string;
      path: string;
      authority_score: number;
      orphan_score: number;
      overlap_score: number;
      pillar_likelihood: number;
      internal_inlinks: number;
      internal_outlinks: number;
      latest_word_count: number;
      indexability_state: string;
      cluster_label: string | null;
      last_seen_at: string | null;
    }>;
    gsc_impressions: number;
    gsc_clicks: number;
    gsc_ctr: number;
    gsc_indexed_pages: number;
    gsc_indexation_synced: boolean;
    gsc_indexation_scope: string;
    gsc_indexation_scope_label: string;
    gsc_indexation_scope_hint: string;
    gsc_previous_impressions: number;
    gsc_previous_clicks: number;
    gsc_previous_ctr: number;
    gsc_delta_impressions: number;
    gsc_delta_clicks: number;
    gsc_delta_ctr_points: number;
    gsc_non_indexed_pages: number;
    top_rising_pages: Array<{
      label: string;
      slug: string;
      url: string;
      impressions: number;
      previous_impressions: number;
      delta_impressions: number;
      delta_percent: number;
      clicks: number;
      ctr: number;
      position: number;
    }>;
    top_falling_pages: Array<{
      label: string;
      slug: string;
      url: string;
      impressions: number;
      previous_impressions: number;
      delta_impressions: number;
      delta_percent: number;
      clicks: number;
      ctr: number;
      position: number;
    }>;
    top_queries: Array<{
      query: string;
      impressions: number;
      clicks: number;
      ctr: number;
      position: number;
    }>;
    top_rising_queries: Array<{
      query: string;
      impressions: number;
      previous_impressions: number;
      delta_impressions: number;
      delta_percent: number;
      clicks: number;
      ctr: number;
      position: number;
    }>;
    top_falling_queries: Array<{
      query: string;
      impressions: number;
      previous_impressions: number;
      delta_impressions: number;
      delta_percent: number;
      clicks: number;
      ctr: number;
      position: number;
    }>;
    new_queries: Array<{
      query: string;
      impressions: number;
      previous_impressions: number;
      delta_impressions: number;
      delta_percent: number;
      clicks: number;
      ctr: number;
      position: number;
    }>;
    indexation_alerts: Array<{
      label: string;
      slug: string;
      url: string;
      state: string;
      detail: string;
    }>;
  };
  readiness: {
    bridge_connected: boolean;
    gsc_connected: boolean;
    has_published_pages: boolean;
    has_live_pages: boolean;
  };
  next_action: {
    kind: string;
    label: string;
    detail: string;
    priority: "high" | "medium" | "low";
  };
};

export type PraeviseoDashboard = {
  sites: PraeviseoSite[];
  totals: {
    impressions: number;
    clicks: number;
    averageCtr: number;
    observedPages: number;
    indexedPages: number;
    indexedPagesSynced: boolean;
  };
};

export type PraeviseoOptimization = {
  id: number;
  status: string;
  source: string;
  created_at: string | null;
  summary: string;
  impact_expected: string;
  page: {
    id: number | null;
    title: string;
    slug: string;
    site_id: string;
  };
};

export type PraeviseoGscOpportunity = {
  site_id: string;
  site_name: string;
  site_url: string;
  type: string;
  label: string;
  slug: string;
  page_id: number | null;
  query: string | null;
  reason: string;
  action: string;
  priority_level: "high" | "medium" | "watch";
  priority_label: string;
  priority_score: number;
  action_state: "ready" | "pending" | "cooldown";
  action_state_label: string;
  pending_suggestion: boolean;
  metrics: Record<string, number | string>;
};

export type PraeviseoObservedRecommendation = {
  id: number;
  site_id: string;
  type: string;
  priority: number;
  estimated_impact: string;
  difficulty: string;
  cluster: string | null;
  title: string;
  reasoning: string;
  suggested_action: string | null;
  status: string;
  generated_at: string | null;
};

export type PraeviseoOptimizations = {
  stats: {
    pending: number;
    applied: number;
    rejected: number;
    total: number;
  };
  gsc_opportunities: {
    summary: {
      low_ctr: number;
      near_top_10: number;
      emerging_queries: number;
      sustained_drop: number;
      total: number;
      ready: number;
      high_priority: number;
    };
    items: PraeviseoGscOpportunity[];
  };
  recommendations: {
    summary: {
      total: number;
      high_priority: number;
      refresh: number;
      internal_links: number;
      clusters: number;
    };
    items: PraeviseoObservedRecommendation[];
  };
  items: PraeviseoOptimization[];
};

export type PraeviseoPublication = {
  id: number;
  site_id: string;
  title: string;
  slug: string;
  status: string;
  published_at: string | null;
  published_live: boolean;
  published_live_at: string | null;
  live_url: string | null;
  seo_score: number | null;
  indexability_score: number | null;
  topical_score: number | null;
  quality_score: number | null;
  cluster: string | null;
  gsc_metrics: {
    impressions: number;
    clicks: number;
    ctr: number;
    position: number | null;
  };
  observed_content: {
    authority_score: number;
    orphan_score: number;
    overlap_score: number;
    pillar_likelihood: number;
    cluster_label: string | null;
    indexability_state: string;
    internal_inlinks: number;
    internal_outlinks: number;
    snapshot_word_count: number;
    snapshot_observed_at: string | null;
    snapshot_title: string | null;
    internal_link_suggestions_count: number;
    cannibalization_count: number;
    query_match_count: number;
    overlap_count: number;
    top_internal_link_target: string | null;
    top_cannibalization_target: string | null;
    top_query_match: string | null;
  } | null;
  latest_suggestion: {
    id: number;
    status: string;
    source: string;
    summary: string;
    impact_expected: string;
    created_at: string | null;
  } | null;
};

export type PraeviseoPublications = {
  stats: {
    engine_published: number;
    live_published: number;
    with_live_url: number;
  };
  items: PraeviseoPublication[];
};

export type PraeviseoSettings = {
  user: {
    id: number;
    name: string;
    email: string;
  };
  sites: Array<{
    site_id: string;
    name: string;
    url: string;
    publication_mode: string;
    publication_mode_label: string;
    publication_path_prefix: string | null;
    publication_bridge_status: string;
    gsc_connection_status: string;
    gsc_property_url: string | null;
    gsc_account_email: string | null;
  }>;
};

export type CreateSiteInput = {
  site_id: string;
  name: string;
  url: string;
  niche: string;
  locale: string;
  preset: "generic" | "amiantix";
  publication_mode: "laravel_bridge" | "symfony_bridge" | "wordpress_bridge";
  publication_path_prefix?: string;
};

export type ClaimSiteInput = {
  connect_code?: string;
  site_id?: string;
};

export type SiteGscConnectionInput = {
  site_id: string;
  gsc_property_url: string;
};

export type RemoteInstallationInput = {
  site_id: string;
  hosting_provider: string;
  access_method: "ssh" | "sftp" | "api";
  ssh_host?: string;
  ssh_port?: string;
  ssh_username?: string;
  ssh_project_path?: string;
  ssh_secret?: string;
  ssh_sudo_command?: string;
  sftp_host?: string;
  sftp_port?: string;
  sftp_username?: string;
  sftp_password?: string;
  sftp_project_path?: string;
  framework_hint?: string;
  api_platform?: string;
  api_token?: string;
  api_project_id?: string;
  api_account_name?: string;
  api_notes?: string;
};

type SitesResponse = { sites: unknown[] };
type SiteResponse = { site: unknown };
type InstallationStatusResponse = { site: unknown; installation: unknown };

const backendBaseUrl = (
  process.env.PRAEVISEO_API_URL ??
  process.env.NEXT_PUBLIC_API_URL ??
  ""
).replace(/\/$/, "");

const mockSites: PraeviseoSite[] = [
  {
    id: 1,
    site_id: "amiantix",
    name: "Amiantix",
    url: "https://amiantix.com",
    niche: "amiante",
    locale: "fr",
    preset: "amiantix",
    is_active: true,
    webhook_url: "https://amiantix.com/api/praeviseo/bridge/publish",
    publication_mode: "symfony_bridge",
    publication_mode_label: "Bridge Symfony",
    publication_connect_code: "UIG7-HI9B-95LC",
    publication_bridge_status: "connected",
    publication_path_prefix: "ressources",
    gsc_property_url: "sc-domain:amiantix.com",
    gsc_connection_mode: "service_account",
    gsc_connection_status: "connected",
    gsc_account_email: "service-account@project.iam.gserviceaccount.com",
    gsc_last_sync_at: new Date().toISOString(),
    gsc_data_as_of: new Date().toISOString().slice(0, 10),
    installation: {
      status: "connected",
      current_step: "completed",
      progress: 100,
      hosting_provider: "other",
      access_method: "ssh",
      requested_at: new Date().toISOString(),
      started_at: new Date().toISOString(),
      completed_at: new Date().toISOString(),
      failed_at: null,
      error_message: null,
      detected_framework: "symfony",
      detected_php_version: "8.3",
      detected_composer: "Composer 2",
      logs: [],
    },
    created_at: new Date().toISOString(),
    summary: {
      pages_total: 8,
      pages_published: 5,
      pages_live: 3,
      pending_suggestions: 2,
      observed_pages: 19,
      observed_weak_pages: 6,
      observed_orphan_pages: 3,
      observed_pillar_candidates: 2,
      observed_avg_authority: 38,
      observed_avg_orphan: 29,
      observed_site_health_score: 74,
      observed_snapshot_date: new Date().toISOString().slice(0, 10),
      observed_avg_seo_score: 71,
      observed_avg_quality_score: 68,
      observed_avg_topical_score: 73,
      observed_crawl_issues: 3,
      observed_pillar_pages: [
        {
          label: "Faq",
          slug: "faq",
          url: "https://amiantix.com/faq",
          path: "/faq",
          authority_score: 64,
          orphan_score: 18,
          overlap_score: 12,
          pillar_likelihood: 81,
          internal_inlinks: 3,
          internal_outlinks: 8,
          latest_word_count: 1240,
          indexability_state: "indexable",
          cluster_label: "diagnostic amiante",
          last_seen_at: new Date().toISOString(),
        },
      ],
      observed_link_gap_pages: [
        {
          label: "Diagnostic amiante copropriete",
          slug: "diagnostic-amiante-copropriete",
          url: "https://amiantix.com/diagnostic-amiante-copropriete",
          path: "/diagnostic-amiante-copropriete",
          authority_score: 58,
          orphan_score: 22,
          overlap_score: 18,
          pillar_likelihood: 72,
          internal_inlinks: 1,
          internal_outlinks: 5,
          latest_word_count: 980,
          indexability_state: "indexable",
          cluster_label: "diagnostic amiante",
          last_seen_at: new Date().toISOString(),
        },
      ],
      observed_orphan_alerts: [
        {
          label: "Mentions légales",
          slug: "mentions-legales",
          url: "https://amiantix.com/mentions-legales",
          path: "/mentions-legales",
          authority_score: 8,
          orphan_score: 91,
          overlap_score: 4,
          pillar_likelihood: 12,
          internal_inlinks: 0,
          internal_outlinks: 1,
          latest_word_count: 110,
          indexability_state: "unknown",
          cluster_label: null,
          last_seen_at: new Date().toISOString(),
        },
      ],
      observed_weak_page_details: [
        {
          label: "Qui sommes nous",
          slug: "qui-sommes-nous",
          url: "https://amiantix.com/qui-sommes-nous",
          path: "/qui-sommes-nous",
          authority_score: 16,
          orphan_score: 64,
          overlap_score: 22,
          pillar_likelihood: 35,
          internal_inlinks: 1,
          internal_outlinks: 2,
          latest_word_count: 180,
          indexability_state: "unknown",
          cluster_label: null,
          last_seen_at: new Date().toISOString(),
        },
      ],
      gsc_impressions: 20,
      gsc_clicks: 9,
      gsc_ctr: 0.45,
      gsc_indexed_pages: 14,
      gsc_indexation_synced: true,
      gsc_indexation_scope: "inspected_urls",
      gsc_indexation_scope_label: "URLs inspectées via Google",
      gsc_indexation_scope_hint:
        "PraeviSEO compte ici les URLs qu’il suit et inspecte dans Google Search Console. Le rapport Pages complet de Google peut afficher davantage d’URLs.",
      gsc_previous_impressions: 14,
      gsc_previous_clicks: 6,
      gsc_previous_ctr: 0.42,
      gsc_delta_impressions: 6,
      gsc_delta_clicks: 3,
      gsc_delta_ctr_points: 3,
      gsc_non_indexed_pages: 4,
      top_rising_pages: [
        {
          label: "Faq",
          slug: "faq",
          url: "https://amiantix.com/faq",
          impressions: 47,
          previous_impressions: 29,
          delta_impressions: 18,
          delta_percent: 62.1,
          clicks: 10,
          ctr: 21.3,
          position: 8.4,
        },
      ],
      top_falling_pages: [
        {
          label: "Amiantix.com",
          slug: "",
          url: "https://amiantix.com/",
          impressions: 23,
          previous_impressions: 41,
          delta_impressions: -18,
          delta_percent: -43.9,
          clicks: 0,
          ctr: 0,
          position: 11.8,
        },
      ],
      top_queries: [
        {
          query: "faq amiante",
          impressions: 18,
          clicks: 5,
          ctr: 27.8,
          position: 8.7,
        },
      ],
      top_rising_queries: [
        {
          query: "faq amiante",
          impressions: 18,
          previous_impressions: 11,
          delta_impressions: 7,
          delta_percent: 63.6,
          clicks: 5,
          ctr: 27.8,
          position: 8.7,
        },
      ],
      top_falling_queries: [
        {
          query: "amiantix",
          impressions: 9,
          previous_impressions: 16,
          delta_impressions: -7,
          delta_percent: -43.8,
          clicks: 1,
          ctr: 11.1,
          position: 11.2,
        },
      ],
      new_queries: [
        {
          query: "combien coute un diagnostic amiante",
          impressions: 6,
          previous_impressions: 0,
          delta_impressions: 6,
          delta_percent: 100,
          clicks: 1,
          ctr: 16.7,
          position: 13.4,
        },
      ],
      indexation_alerts: [
        {
          label: "Qui Sommes Nous",
          slug: "qui-sommes-nous",
          url: "https://amiantix.com/qui-sommes-nous",
          state: "Détectée, actuellement non indexée",
          detail: "Google connaît la page mais ne l’a pas encore indexée.",
        },
      ],
    },
    readiness: {
      bridge_connected: true,
      gsc_connected: true,
      has_published_pages: true,
      has_live_pages: true,
    },
    next_action: {
      kind: "monitor",
      label: "Laisser tourner le monitoring",
      detail: "Le site est branché. PraeviSEO surveille maintenant les signaux et rouvrira des actions si besoin.",
      priority: "low",
    },
  },
  {
    id: 2,
    site_id: "zamio",
    name: "Zamio",
    url: "https://zamio.fr",
    niche: "immobilier",
    locale: "fr",
    preset: "generic",
    is_active: true,
    webhook_url: null,
    publication_mode: "laravel_bridge",
    publication_mode_label: "Bridge Laravel",
    publication_connect_code: "PRV-8X92-LKQ1",
    publication_bridge_status: "pending",
    publication_path_prefix: "guides",
    gsc_property_url: null,
    gsc_connection_mode: null,
    gsc_connection_status: "not_connected",
    gsc_account_email: null,
    gsc_last_sync_at: null,
    gsc_data_as_of: null,
    installation: {
      status: "not_started",
      current_step: null,
      progress: 0,
      hosting_provider: null,
      access_method: null,
      requested_at: null,
      started_at: null,
      completed_at: null,
      failed_at: null,
      error_message: null,
      detected_framework: null,
      detected_php_version: null,
      detected_composer: null,
      logs: [],
    },
    created_at: new Date().toISOString(),
    summary: {
      pages_total: 0,
      pages_published: 0,
      pages_live: 0,
      pending_suggestions: 0,
      observed_pages: 0,
      observed_weak_pages: 0,
      observed_orphan_pages: 0,
      observed_pillar_candidates: 0,
      observed_avg_authority: 0,
      observed_avg_orphan: 0,
      observed_site_health_score: 0,
      observed_snapshot_date: null,
      observed_avg_seo_score: 0,
      observed_avg_quality_score: 0,
      observed_avg_topical_score: 0,
      observed_crawl_issues: 0,
      observed_pillar_pages: [],
      observed_link_gap_pages: [],
      observed_orphan_alerts: [],
      observed_weak_page_details: [],
      gsc_impressions: 0,
      gsc_clicks: 0,
      gsc_ctr: 0,
      gsc_indexed_pages: 0,
      gsc_indexation_synced: false,
      gsc_indexation_scope: "inspected_urls",
      gsc_indexation_scope_label: "URLs inspectées via Google",
      gsc_indexation_scope_hint:
        "PraeviSEO compte ici les URLs qu’il suit et inspecte dans Google Search Console. Le rapport Pages complet de Google peut afficher davantage d’URLs.",
      gsc_previous_impressions: 0,
      gsc_previous_clicks: 0,
      gsc_previous_ctr: 0,
      gsc_delta_impressions: 0,
      gsc_delta_clicks: 0,
      gsc_delta_ctr_points: 0,
      gsc_non_indexed_pages: 0,
      top_rising_pages: [],
      top_falling_pages: [],
      top_queries: [],
      top_rising_queries: [],
      top_falling_queries: [],
      new_queries: [],
      indexation_alerts: [],
    },
    readiness: {
      bridge_connected: false,
      gsc_connected: false,
      has_published_pages: false,
      has_live_pages: false,
    },
    next_action: {
      kind: "connect_bridge",
      label: "Activer l automatisation premium",
      detail: "Le free fonctionne déjà avec GSC. Activez ensuite la couche premium pour publier, synchroniser et exécuter des actions sur le site.",
      priority: "high",
    },
  },
];

const mockOptimizations: PraeviseoOptimizations = {
  stats: { pending: 2, applied: 3, rejected: 1, total: 6 },
  gsc_opportunities: {
    summary: {
      low_ctr: 1,
      near_top_10: 2,
      emerging_queries: 1,
      sustained_drop: 1,
      total: 5,
      ready: 3,
      high_priority: 2,
    },
    items: [
      {
        site_id: "amiantix",
        site_name: "Amiantix",
        site_url: "https://amiantix.com",
        type: "near_top_10",
        label: "Diagnostic amiante en copropriete",
        slug: "diagnostic-amiante-copropriete",
        page_id: 11,
        query: null,
        reason: "La page est proche de la zone qui compte et peut gagner vite avec un refresh ciblé.",
        action: "rafraichir la page",
        priority_level: "high",
        priority_label: "Priorite haute",
        priority_score: 670,
        action_state: "ready",
        action_state_label: "Actionnable maintenant",
        pending_suggestion: false,
        metrics: {
          impressions: 148,
          ctr: 2.1,
          position: 11.2,
        },
      },
      {
        site_id: "amiantix",
        site_name: "Amiantix",
        site_url: "https://amiantix.com",
        type: "low_ctr",
        label: "Qui sommes nous",
        slug: "qui-sommes-nous",
        page_id: 12,
        query: null,
        reason: "La page est visible dans Google mais trop peu de personnes cliquent.",
        action: "relancer le CTR",
        priority_level: "medium",
        priority_label: "Gain rapide",
        priority_score: 505,
        action_state: "ready",
        action_state_label: "Actionnable maintenant",
        pending_suggestion: false,
        metrics: {
          impressions: 122,
          ctr: 1.4,
          position: 7.8,
        },
      },
      {
        site_id: "amiantix",
        site_name: "Amiantix",
        site_url: "https://amiantix.com",
        type: "emerging_query",
        label: "Guide repérage avant travaux",
        slug: "guide-reperage-avant-travaux",
        page_id: 13,
        query: "repérage amiante avant travaux",
        reason: "Une requête émergente mérite une réponse plus explicite dans la page.",
        action: "creer une section utile",
        priority_level: "watch",
        priority_label: "A surveiller",
        priority_score: 360,
        action_state: "pending",
        action_state_label: "Suggestion deja en attente",
        pending_suggestion: true,
        metrics: {
          impressions: 28,
          ctr: 3.8,
          position: 12.4,
        },
      },
    ],
  },
  recommendations: {
    summary: {
      total: 3,
      high_priority: 1,
      refresh: 1,
      internal_links: 1,
      clusters: 1,
    },
    items: [
      {
        id: 301,
        site_id: "amiantix",
        type: "refresh_page",
        priority: 20,
        estimated_impact: "high",
        difficulty: "medium",
        cluster: "diagnostic amiante",
        title: "Refresh the FAQ cluster page",
        reasoning: "The page already ranks but still lacks enough depth to convert the current visibility.",
        suggested_action: "Expand the answer structure and strengthen supporting evidence.",
        status: "pending",
        generated_at: new Date().toISOString(),
      },
      {
        id: 302,
        site_id: "amiantix",
        type: "add_internal_links",
        priority: 35,
        estimated_impact: "medium",
        difficulty: "low",
        cluster: "diagnostic amiante",
        title: "Reinforce the main diagnostic page with stronger internal links",
        reasoning: "The cluster already has relevant support pages that can push more authority to the main target.",
        suggested_action: "Add contextual links from stronger support pages to the target page.",
        status: "pending",
        generated_at: new Date().toISOString(),
      },
      {
        id: 303,
        site_id: "amiantix",
        type: "create_page",
        priority: 45,
        estimated_impact: "medium",
        difficulty: "medium",
        cluster: "reglementation",
        title: "Expand cluster: réglementation",
        reasoning: "Google already hints at an uncovered angle that deserves a dedicated supporting page.",
        suggested_action: "Create a supporting page to strengthen the existing cluster.",
        status: "pending",
        generated_at: new Date().toISOString(),
      },
    ],
  },
  items: [
    {
      id: 1,
      status: "pending",
      source: "gsc_opportunity",
      created_at: new Date().toISOString(),
      summary: "Relancer le CTR d une page proche du top 10 avec une réécriture plus nette du title et du H1.",
      impact_expected: "Faire remonter le CTR sur une requête déjà visible sans republier toute la stratégie.",
      page: {
        id: 11,
        title: "Diagnostic amiante en copropriete",
        slug: "diagnostic-amiante-copropriete",
        site_id: "amiantix",
      },
    },
    {
      id: 2,
      status: "applied",
      source: "indexation_backlog",
      created_at: new Date().toISOString(),
      summary: "Ajouter du maillage interne et enrichir une page détectée mais encore peu soutenue.",
      impact_expected: "Renforcer la découverte Google avant prochaine vague d impressions.",
      page: {
        id: 12,
        title: "Qui sommes nous",
        slug: "qui-sommes-nous",
        site_id: "amiantix",
      },
    },
  ],
};

const mockPublications: PraeviseoPublications = {
  stats: { engine_published: 5, live_published: 3, with_live_url: 3 },
  items: [
    {
      id: 11,
      site_id: "amiantix",
      title: "Diagnostic amiante en copropriete",
      slug: "diagnostic-amiante-copropriete",
      status: "published",
      published_at: new Date().toISOString(),
      published_live: true,
      published_live_at: new Date().toISOString(),
      live_url: "https://amiantix.com/ressources/diagnostic-amiante-copropriete",
      seo_score: 78,
      indexability_score: 84,
      topical_score: 81,
      quality_score: 77,
      cluster: "diagnostic amiante",
        gsc_metrics: {
          impressions: 47,
          clicks: 10,
          ctr: 21.3,
          position: 8.4,
        },
        observed_content: {
          authority_score: 64,
          orphan_score: 18,
          overlap_score: 41,
          pillar_likelihood: 82,
          cluster_label: "diagnostic amiante",
          indexability_state: "indexable",
          internal_inlinks: 2,
          internal_outlinks: 7,
          snapshot_word_count: 1240,
          snapshot_observed_at: new Date().toISOString(),
          snapshot_title: "Diagnostic amiante en copropriete",
          internal_link_suggestions_count: 2,
          cannibalization_count: 1,
          query_match_count: 2,
          overlap_count: 1,
          top_internal_link_target: "Guide DTA",
          top_cannibalization_target: "Diagnostic amiante",
          top_query_match: "faq amiante",
        },
        latest_suggestion: {
          id: 101,
          status: "pending",
        source: "gsc_opportunity",
        summary: "Un refresh éditorial est recommandé pour prolonger la traction de cet article.",
        impact_expected: "Consolider la visibilité autour de la requête qui progresse.",
        created_at: new Date().toISOString(),
      },
    },
    {
      id: 12,
      site_id: "zamio",
      title: "Guide estimation locale",
      slug: "guide-estimation-locale",
      status: "published",
      published_at: new Date().toISOString(),
      published_live: false,
      published_live_at: null,
      live_url: null,
      seo_score: 72,
      indexability_score: 70,
      topical_score: 74,
      quality_score: 69,
      cluster: "estimation locale",
        gsc_metrics: {
          impressions: 0,
          clicks: 0,
          ctr: 0,
          position: null,
        },
        observed_content: {
          authority_score: 39,
          orphan_score: 62,
          overlap_score: 12,
          pillar_likelihood: 44,
          cluster_label: "estimation locale",
          indexability_state: "indexable",
          internal_inlinks: 1,
          internal_outlinks: 3,
          snapshot_word_count: 640,
          snapshot_observed_at: new Date().toISOString(),
          snapshot_title: "Guide estimation locale",
          internal_link_suggestions_count: 1,
          cannibalization_count: 0,
          query_match_count: 1,
          overlap_count: 0,
          top_internal_link_target: "Prix m2 par quartier",
          top_cannibalization_target: null,
          top_query_match: "estimation locale",
        },
        latest_suggestion: null,
      },
  ],
};

const mockSettings: PraeviseoSettings = {
  user: {
    id: 1,
    name: "PraeviSEO Demo",
    email: "demo@praeviseo.app",
  },
  sites: mockSites.map((site) => ({
    site_id: site.site_id,
    name: site.name,
    url: site.url,
    publication_mode: site.publication_mode,
    publication_mode_label: site.publication_mode_label,
    publication_path_prefix: site.publication_path_prefix,
    publication_bridge_status: site.publication_bridge_status,
    gsc_connection_status: site.gsc_connection_status,
    gsc_property_url: site.gsc_property_url,
    gsc_account_email: site.gsc_account_email,
  })),
};

const emptyOptimizations: PraeviseoOptimizations = {
  stats: { pending: 0, applied: 0, rejected: 0, total: 0 },
  gsc_opportunities: {
    summary: {
      low_ctr: 0,
      near_top_10: 0,
      emerging_queries: 0,
      sustained_drop: 0,
      total: 0,
      ready: 0,
      high_priority: 0,
    },
    items: [],
  },
  recommendations: {
    summary: {
      total: 0,
      high_priority: 0,
      refresh: 0,
      internal_links: 0,
      clusters: 0,
    },
    items: [],
  },
  items: [],
};

const emptyPublications: PraeviseoPublications = {
  stats: { engine_published: 0, live_published: 0, with_live_url: 0 },
  items: [],
};

const emptySettings: PraeviseoSettings = {
  user: {
    id: 0,
    name: "",
    email: "",
  },
  sites: [],
};

function backendConfigured(): boolean {
  return backendBaseUrl !== "";
}

function humanizeBackendMessage(message: string): string {
  const normalized = message.trim();

  const messageMap: Record<string, string> = {
    "validation.regex":
      "L identifiant doit contenir uniquement des lettres minuscules, chiffres, tirets ou underscores.",
    "validation.required": "Merci de remplir tous les champs obligatoires.",
    "validation.url": "L URL publique doit etre valide, par exemple https://monsite.com.",
    "validation.unique": "Cet identifiant est deja utilise par un autre site.",
    "Code de connexion invalide.": "Code de connexion invalide.",
    "Le code de connexion ne correspond pas au site demandé.":
      "Le code de connexion ne correspond pas au site demandé.",
    "Site introuvable.": "Aucun site correspondant n a ete trouve.",
    "Ce site existe deja dans PraeviSEO. Utilisez le code de connexion pour le rattacher.":
      "Ce site appartient deja a un autre espace client. Utilisez le code de connexion pour le rattacher.",
    "Le site existe deja mais l URL ne correspond pas.":
      "Le site existe deja mais l URL saisie ne correspond pas.",
  };

  if (messageMap[normalized]) {
    return messageMap[normalized];
  }

  if (normalized.toLowerCase().includes("site id has already been taken")) {
    return "Cet identifiant est deja utilise par un autre site.";
  }

  return normalized;
}

function normaliseSite(raw: unknown): PraeviseoSite {
  const site = raw as Record<string, unknown>;
  const summary = (site.summary ?? {}) as Record<string, unknown>;

  return {
    id: Number(site.id ?? 0),
    site_id: String(site.site_id ?? ""),
    name: String(site.name ?? ""),
    url: String(site.url ?? ""),
    niche: String(site.niche ?? "general"),
    locale: String(site.locale ?? "fr"),
    preset: String(site.preset ?? "generic"),
    is_active: Boolean(site.is_active ?? true),
    webhook_url: site.webhook_url ? String(site.webhook_url) : null,
    publication_mode: String(site.publication_mode ?? "runtime"),
    publication_mode_label: String(site.publication_mode_label ?? "Runtime interne"),
    publication_connect_code: site.publication_connect_code ? String(site.publication_connect_code) : null,
    publication_bridge_status: String(site.publication_bridge_status ?? "pending"),
    publication_path_prefix: site.publication_path_prefix ? String(site.publication_path_prefix) : null,
    gsc_property_url: site.gsc_property_url ? String(site.gsc_property_url) : null,
    gsc_connection_mode: site.gsc_connection_mode ? String(site.gsc_connection_mode) : null,
    gsc_connection_status: String(site.gsc_connection_status ?? "not_connected"),
    gsc_account_email: site.gsc_account_email ? String(site.gsc_account_email) : null,
    gsc_last_sync_at: site.gsc_last_sync_at ? String(site.gsc_last_sync_at) : null,
    gsc_data_as_of: site.gsc_data_as_of ? String(site.gsc_data_as_of) : null,
    installation: {
      status: String((site.installation as Record<string, unknown> | undefined)?.status ?? "not_started"),
      current_step: (site.installation as Record<string, unknown> | undefined)?.current_step
        ? String((site.installation as Record<string, unknown> | undefined)?.current_step)
        : null,
      progress: Number((site.installation as Record<string, unknown> | undefined)?.progress ?? 0),
      hosting_provider: (site.installation as Record<string, unknown> | undefined)?.hosting_provider
        ? String((site.installation as Record<string, unknown> | undefined)?.hosting_provider)
        : null,
      access_method: (site.installation as Record<string, unknown> | undefined)?.access_method
        ? String((site.installation as Record<string, unknown> | undefined)?.access_method)
        : null,
      requested_at: (site.installation as Record<string, unknown> | undefined)?.requested_at
        ? String((site.installation as Record<string, unknown> | undefined)?.requested_at)
        : null,
      started_at: (site.installation as Record<string, unknown> | undefined)?.started_at
        ? String((site.installation as Record<string, unknown> | undefined)?.started_at)
        : null,
      completed_at: (site.installation as Record<string, unknown> | undefined)?.completed_at
        ? String((site.installation as Record<string, unknown> | undefined)?.completed_at)
        : null,
      failed_at: (site.installation as Record<string, unknown> | undefined)?.failed_at
        ? String((site.installation as Record<string, unknown> | undefined)?.failed_at)
        : null,
      error_message: (site.installation as Record<string, unknown> | undefined)?.error_message
        ? String((site.installation as Record<string, unknown> | undefined)?.error_message)
        : null,
      detected_framework: (site.installation as Record<string, unknown> | undefined)?.detected_framework
        ? String((site.installation as Record<string, unknown> | undefined)?.detected_framework)
        : null,
      detected_php_version: (site.installation as Record<string, unknown> | undefined)?.detected_php_version
        ? String((site.installation as Record<string, unknown> | undefined)?.detected_php_version)
        : null,
      detected_composer: (site.installation as Record<string, unknown> | undefined)?.detected_composer
        ? String((site.installation as Record<string, unknown> | undefined)?.detected_composer)
        : null,
      logs: Array.isArray((site.installation as Record<string, unknown> | undefined)?.logs)
        ? (((site.installation as Record<string, unknown> | undefined)?.logs as unknown[]) ?? []).map((entry) => ({
            at: String((entry as Record<string, unknown>)?.at ?? ""),
            level: String((entry as Record<string, unknown>)?.level ?? "info"),
            step: String((entry as Record<string, unknown>)?.step ?? ""),
            message: String((entry as Record<string, unknown>)?.message ?? ""),
          }))
        : [],
    },
    created_at: String(site.created_at ?? new Date().toISOString()),
    summary: {
      pages_total: Number(summary.pages_total ?? 0),
      pages_published: Number(summary.pages_published ?? 0),
      pages_live: Number(summary.pages_live ?? 0),
      pending_suggestions: Number(summary.pending_suggestions ?? 0),
      observed_pages: Number(summary.observed_pages ?? 0),
      observed_weak_pages: Number(summary.observed_weak_pages ?? 0),
      observed_orphan_pages: Number(summary.observed_orphan_pages ?? 0),
      observed_pillar_candidates: Number(summary.observed_pillar_candidates ?? 0),
      observed_avg_authority: Number(summary.observed_avg_authority ?? 0),
      observed_avg_orphan: Number(summary.observed_avg_orphan ?? 0),
      observed_site_health_score: Number(summary.observed_site_health_score ?? 0),
      observed_snapshot_date: summary.observed_snapshot_date ? String(summary.observed_snapshot_date) : null,
      observed_avg_seo_score: Number(summary.observed_avg_seo_score ?? 0),
      observed_avg_quality_score: Number(summary.observed_avg_quality_score ?? 0),
      observed_avg_topical_score: Number(summary.observed_avg_topical_score ?? 0),
      observed_crawl_issues: Number(summary.observed_crawl_issues ?? 0),
      observed_pillar_pages: Array.isArray(summary.observed_pillar_pages)
        ? summary.observed_pillar_pages.map((entry) => ({
            label: String((entry as Record<string, unknown>).label ?? ""),
            slug: String((entry as Record<string, unknown>).slug ?? ""),
            url: String((entry as Record<string, unknown>).url ?? ""),
            path: String((entry as Record<string, unknown>).path ?? "/"),
            authority_score: Number((entry as Record<string, unknown>).authority_score ?? 0),
            orphan_score: Number((entry as Record<string, unknown>).orphan_score ?? 0),
            overlap_score: Number((entry as Record<string, unknown>).overlap_score ?? 0),
            pillar_likelihood: Number((entry as Record<string, unknown>).pillar_likelihood ?? 0),
            internal_inlinks: Number((entry as Record<string, unknown>).internal_inlinks ?? 0),
            internal_outlinks: Number((entry as Record<string, unknown>).internal_outlinks ?? 0),
            latest_word_count: Number((entry as Record<string, unknown>).latest_word_count ?? 0),
            indexability_state: String((entry as Record<string, unknown>).indexability_state ?? "unknown"),
            cluster_label: (entry as Record<string, unknown>).cluster_label ? String((entry as Record<string, unknown>).cluster_label) : null,
            last_seen_at: (entry as Record<string, unknown>).last_seen_at ? String((entry as Record<string, unknown>).last_seen_at) : null,
          }))
        : [],
      observed_link_gap_pages: Array.isArray(summary.observed_link_gap_pages)
        ? summary.observed_link_gap_pages.map((entry) => ({
            label: String((entry as Record<string, unknown>).label ?? ""),
            slug: String((entry as Record<string, unknown>).slug ?? ""),
            url: String((entry as Record<string, unknown>).url ?? ""),
            path: String((entry as Record<string, unknown>).path ?? "/"),
            authority_score: Number((entry as Record<string, unknown>).authority_score ?? 0),
            orphan_score: Number((entry as Record<string, unknown>).orphan_score ?? 0),
            overlap_score: Number((entry as Record<string, unknown>).overlap_score ?? 0),
            pillar_likelihood: Number((entry as Record<string, unknown>).pillar_likelihood ?? 0),
            internal_inlinks: Number((entry as Record<string, unknown>).internal_inlinks ?? 0),
            internal_outlinks: Number((entry as Record<string, unknown>).internal_outlinks ?? 0),
            latest_word_count: Number((entry as Record<string, unknown>).latest_word_count ?? 0),
            indexability_state: String((entry as Record<string, unknown>).indexability_state ?? "unknown"),
            cluster_label: (entry as Record<string, unknown>).cluster_label ? String((entry as Record<string, unknown>).cluster_label) : null,
            last_seen_at: (entry as Record<string, unknown>).last_seen_at ? String((entry as Record<string, unknown>).last_seen_at) : null,
          }))
        : [],
      observed_orphan_alerts: Array.isArray(summary.observed_orphan_alerts)
        ? summary.observed_orphan_alerts.map((entry) => ({
            label: String((entry as Record<string, unknown>).label ?? ""),
            slug: String((entry as Record<string, unknown>).slug ?? ""),
            url: String((entry as Record<string, unknown>).url ?? ""),
            path: String((entry as Record<string, unknown>).path ?? "/"),
            authority_score: Number((entry as Record<string, unknown>).authority_score ?? 0),
            orphan_score: Number((entry as Record<string, unknown>).orphan_score ?? 0),
            overlap_score: Number((entry as Record<string, unknown>).overlap_score ?? 0),
            pillar_likelihood: Number((entry as Record<string, unknown>).pillar_likelihood ?? 0),
            internal_inlinks: Number((entry as Record<string, unknown>).internal_inlinks ?? 0),
            internal_outlinks: Number((entry as Record<string, unknown>).internal_outlinks ?? 0),
            latest_word_count: Number((entry as Record<string, unknown>).latest_word_count ?? 0),
            indexability_state: String((entry as Record<string, unknown>).indexability_state ?? "unknown"),
            cluster_label: (entry as Record<string, unknown>).cluster_label ? String((entry as Record<string, unknown>).cluster_label) : null,
            last_seen_at: (entry as Record<string, unknown>).last_seen_at ? String((entry as Record<string, unknown>).last_seen_at) : null,
          }))
        : [],
      observed_weak_page_details: Array.isArray(summary.observed_weak_page_details)
        ? summary.observed_weak_page_details.map((entry) => ({
            label: String((entry as Record<string, unknown>).label ?? ""),
            slug: String((entry as Record<string, unknown>).slug ?? ""),
            url: String((entry as Record<string, unknown>).url ?? ""),
            path: String((entry as Record<string, unknown>).path ?? "/"),
            authority_score: Number((entry as Record<string, unknown>).authority_score ?? 0),
            orphan_score: Number((entry as Record<string, unknown>).orphan_score ?? 0),
            overlap_score: Number((entry as Record<string, unknown>).overlap_score ?? 0),
            pillar_likelihood: Number((entry as Record<string, unknown>).pillar_likelihood ?? 0),
            internal_inlinks: Number((entry as Record<string, unknown>).internal_inlinks ?? 0),
            internal_outlinks: Number((entry as Record<string, unknown>).internal_outlinks ?? 0),
            latest_word_count: Number((entry as Record<string, unknown>).latest_word_count ?? 0),
            indexability_state: String((entry as Record<string, unknown>).indexability_state ?? "unknown"),
            cluster_label: (entry as Record<string, unknown>).cluster_label ? String((entry as Record<string, unknown>).cluster_label) : null,
            last_seen_at: (entry as Record<string, unknown>).last_seen_at ? String((entry as Record<string, unknown>).last_seen_at) : null,
          }))
        : [],
      gsc_impressions: Number(summary.gsc_impressions ?? 0),
      gsc_clicks: Number(summary.gsc_clicks ?? 0),
      gsc_ctr: Number(summary.gsc_ctr ?? 0),
      gsc_indexed_pages: Number(summary.gsc_indexed_pages ?? 0),
      gsc_indexation_synced: Boolean(summary.gsc_indexation_synced ?? false),
      gsc_indexation_scope: String(summary.gsc_indexation_scope ?? "inspected_urls"),
      gsc_indexation_scope_label: String(summary.gsc_indexation_scope_label ?? "URLs inspectées via Google"),
      gsc_indexation_scope_hint: String(
        summary.gsc_indexation_scope_hint ??
          "PraeviSEO compte ici les URLs qu’il suit et inspecte dans Google Search Console. Le rapport Pages complet de Google peut afficher davantage d’URLs."
      ),
      gsc_previous_impressions: Number(summary.gsc_previous_impressions ?? 0),
      gsc_previous_clicks: Number(summary.gsc_previous_clicks ?? 0),
      gsc_previous_ctr: Number(summary.gsc_previous_ctr ?? 0),
      gsc_delta_impressions: Number(summary.gsc_delta_impressions ?? 0),
      gsc_delta_clicks: Number(summary.gsc_delta_clicks ?? 0),
      gsc_delta_ctr_points: Number(summary.gsc_delta_ctr_points ?? 0),
      gsc_non_indexed_pages: Number(summary.gsc_non_indexed_pages ?? 0),
      top_rising_pages: Array.isArray(summary.top_rising_pages)
        ? summary.top_rising_pages.map((entry) => ({
            label: String((entry as Record<string, unknown>).label ?? ""),
            slug: String((entry as Record<string, unknown>).slug ?? ""),
            url: String((entry as Record<string, unknown>).url ?? ""),
            impressions: Number((entry as Record<string, unknown>).impressions ?? 0),
            previous_impressions: Number((entry as Record<string, unknown>).previous_impressions ?? 0),
            delta_impressions: Number((entry as Record<string, unknown>).delta_impressions ?? 0),
            delta_percent: Number((entry as Record<string, unknown>).delta_percent ?? 0),
            clicks: Number((entry as Record<string, unknown>).clicks ?? 0),
            ctr: Number((entry as Record<string, unknown>).ctr ?? 0),
            position: Number((entry as Record<string, unknown>).position ?? 0),
          }))
        : [],
      top_falling_pages: Array.isArray(summary.top_falling_pages)
        ? summary.top_falling_pages.map((entry) => ({
            label: String((entry as Record<string, unknown>).label ?? ""),
            slug: String((entry as Record<string, unknown>).slug ?? ""),
            url: String((entry as Record<string, unknown>).url ?? ""),
            impressions: Number((entry as Record<string, unknown>).impressions ?? 0),
            previous_impressions: Number((entry as Record<string, unknown>).previous_impressions ?? 0),
            delta_impressions: Number((entry as Record<string, unknown>).delta_impressions ?? 0),
            delta_percent: Number((entry as Record<string, unknown>).delta_percent ?? 0),
            clicks: Number((entry as Record<string, unknown>).clicks ?? 0),
            ctr: Number((entry as Record<string, unknown>).ctr ?? 0),
            position: Number((entry as Record<string, unknown>).position ?? 0),
          }))
        : [],
      top_queries: Array.isArray(summary.top_queries)
        ? summary.top_queries.map((entry) => ({
            query: String((entry as Record<string, unknown>).query ?? ""),
            impressions: Number((entry as Record<string, unknown>).impressions ?? 0),
            clicks: Number((entry as Record<string, unknown>).clicks ?? 0),
            ctr: Number((entry as Record<string, unknown>).ctr ?? 0),
            position: Number((entry as Record<string, unknown>).position ?? 0),
          }))
        : [],
      top_rising_queries: Array.isArray(summary.top_rising_queries)
        ? summary.top_rising_queries.map((entry) => ({
            query: String((entry as Record<string, unknown>).query ?? ""),
            impressions: Number((entry as Record<string, unknown>).impressions ?? 0),
            previous_impressions: Number((entry as Record<string, unknown>).previous_impressions ?? 0),
            delta_impressions: Number((entry as Record<string, unknown>).delta_impressions ?? 0),
            delta_percent: Number((entry as Record<string, unknown>).delta_percent ?? 0),
            clicks: Number((entry as Record<string, unknown>).clicks ?? 0),
            ctr: Number((entry as Record<string, unknown>).ctr ?? 0),
            position: Number((entry as Record<string, unknown>).position ?? 0),
          }))
        : [],
      top_falling_queries: Array.isArray(summary.top_falling_queries)
        ? summary.top_falling_queries.map((entry) => ({
            query: String((entry as Record<string, unknown>).query ?? ""),
            impressions: Number((entry as Record<string, unknown>).impressions ?? 0),
            previous_impressions: Number((entry as Record<string, unknown>).previous_impressions ?? 0),
            delta_impressions: Number((entry as Record<string, unknown>).delta_impressions ?? 0),
            delta_percent: Number((entry as Record<string, unknown>).delta_percent ?? 0),
            clicks: Number((entry as Record<string, unknown>).clicks ?? 0),
            ctr: Number((entry as Record<string, unknown>).ctr ?? 0),
            position: Number((entry as Record<string, unknown>).position ?? 0),
          }))
        : [],
      new_queries: Array.isArray(summary.new_queries)
        ? summary.new_queries.map((entry) => ({
            query: String((entry as Record<string, unknown>).query ?? ""),
            impressions: Number((entry as Record<string, unknown>).impressions ?? 0),
            previous_impressions: Number((entry as Record<string, unknown>).previous_impressions ?? 0),
            delta_impressions: Number((entry as Record<string, unknown>).delta_impressions ?? 0),
            delta_percent: Number((entry as Record<string, unknown>).delta_percent ?? 0),
            clicks: Number((entry as Record<string, unknown>).clicks ?? 0),
            ctr: Number((entry as Record<string, unknown>).ctr ?? 0),
            position: Number((entry as Record<string, unknown>).position ?? 0),
          }))
        : [],
      indexation_alerts: Array.isArray(summary.indexation_alerts)
        ? summary.indexation_alerts.map((entry) => ({
            label: String((entry as Record<string, unknown>).label ?? ""),
            slug: String((entry as Record<string, unknown>).slug ?? ""),
            url: String((entry as Record<string, unknown>).url ?? ""),
            state: String((entry as Record<string, unknown>).state ?? ""),
            detail: String((entry as Record<string, unknown>).detail ?? ""),
          }))
        : [],
    },
    readiness: {
      bridge_connected: Boolean((site.readiness as Record<string, unknown> | undefined)?.bridge_connected ?? false),
      gsc_connected: Boolean((site.readiness as Record<string, unknown> | undefined)?.gsc_connected ?? false),
      has_published_pages: Boolean((site.readiness as Record<string, unknown> | undefined)?.has_published_pages ?? false),
      has_live_pages: Boolean((site.readiness as Record<string, unknown> | undefined)?.has_live_pages ?? false),
    },
    next_action: {
      kind: String((site.next_action as Record<string, unknown> | undefined)?.kind ?? "monitor"),
      label: String((site.next_action as Record<string, unknown> | undefined)?.label ?? "Laisser tourner le monitoring"),
      detail: String((site.next_action as Record<string, unknown> | undefined)?.detail ?? "Le site est branché."),
      priority: String((site.next_action as Record<string, unknown> | undefined)?.priority ?? "low") as "high" | "medium" | "low",
    },
  };
}

async function appFetch<T>(path: string, init?: RequestInit, token?: string): Promise<T> {
  const response = await fetch(`${backendBaseUrl}${path}`, {
    ...init,
    cache: "no-store",
    headers: {
      Accept: "application/json",
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
      ...(init?.headers ?? {}),
    },
  });

  if (!response.ok) {
    let message = `PraeviSEO admin API error ${response.status} on ${path}`;

    try {
      const payload = (await response.json()) as {
        message?: string;
        errors?: Record<string, string[]>;
      };

      if (payload.message) {
        message = payload.message;
      }

      const firstFieldErrors = payload.errors
        ? Object.values(payload.errors).flat().filter(Boolean)
        : [];

      if (firstFieldErrors.length > 0) {
        message = firstFieldErrors[0] ?? message;
      }
    } catch {
      const text = await response.text();

      if (text.trim() !== "") {
        message = text;
      }
    }

    throw new Error(humanizeBackendMessage(message));
  }

  return (await response.json()) as T;
}

export async function getSites(): Promise<PraeviseoSite[]> {
  if (!backendConfigured()) {
    return mockSites;
  }

  try {
    const token = await getSessionToken();

    if (!token) {
      return [];
    }

    const payload = await appFetch<SitesResponse>("/api/client/sites", undefined, token);

    return payload.sites.map(normaliseSite);
  } catch {
    return [];
  }
}

export async function getSite(siteId: string): Promise<PraeviseoSite | null> {
  if (!backendConfigured()) {
    return mockSites.find((site) => site.site_id === siteId) ?? null;
  }

  try {
    const token = await getSessionToken();

    if (!token) {
      return null;
    }

    const payload = await appFetch<SiteResponse>(`/api/client/sites/${siteId}`, undefined, token);

    return normaliseSite(payload.site);
  } catch {
    return null;
  }
}

export async function getDashboard(): Promise<PraeviseoDashboard> {
  const sites = await getSites();
  const totals = sites.reduce(
    (carry, site) => {
      carry.impressions += site.summary.gsc_impressions;
      carry.clicks += site.summary.gsc_clicks;
      carry.observedPages += site.summary.observed_pages;
      carry.indexedPages += site.summary.gsc_indexed_pages;
      carry.indexedPagesSynced = carry.indexedPagesSynced || site.summary.gsc_indexation_synced;

      return carry;
    },
    {
      impressions: 0,
      clicks: 0,
      observedPages: 0,
      indexedPages: 0,
      indexedPagesSynced: false,
    }
  );

  return {
    sites,
    totals: {
      impressions: totals.impressions,
      clicks: totals.clicks,
      averageCtr: totals.impressions > 0 ? totals.clicks / totals.impressions : 0,
      observedPages: totals.observedPages,
      indexedPages: totals.indexedPages,
      indexedPagesSynced: totals.indexedPagesSynced,
    },
  };
}

export async function getOptimizations(): Promise<PraeviseoOptimizations> {
  if (!backendConfigured()) {
    return mockOptimizations;
  }

  try {
    const token = await getSessionToken();

    if (!token) {
      return emptyOptimizations;
    }

    return await appFetch<PraeviseoOptimizations>("/api/client/optimizations", undefined, token);
  } catch {
    return emptyOptimizations;
  }
}

export async function getPublications(): Promise<PraeviseoPublications> {
  if (!backendConfigured()) {
    return mockPublications;
  }

  try {
    const token = await getSessionToken();

    if (!token) {
      return emptyPublications;
    }

    return await appFetch<PraeviseoPublications>("/api/client/publications", undefined, token);
  } catch {
    return emptyPublications;
  }
}

export async function getSettings(): Promise<PraeviseoSettings> {
  if (!backendConfigured()) {
    return mockSettings;
  }

  try {
    const token = await getSessionToken();

    if (!token) {
      return emptySettings;
    }

    return await appFetch<PraeviseoSettings>("/api/client/settings", undefined, token);
  } catch {
    return emptySettings;
  }
}

export async function updateProfile(input: { name: string; email: string }) {
  if (!backendConfigured()) {
    return {
      user: {
        id: 1,
        name: input.name,
        email: input.email,
      },
    };
  }

  const token = await getSessionToken();

  if (!token) {
    throw new Error("Session client manquante.");
  }

  return await appFetch<{ user: { id: number; name: string; email: string } }>(
    "/api/client/settings/profile",
    {
      method: "PATCH",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify(input),
    },
    token
  );
}

export async function createSite(input: CreateSiteInput): Promise<PraeviseoSite> {
  if (!backendConfigured()) {
    return {
      id: mockSites.length + 1,
      site_id: input.site_id,
      name: input.name,
      url: input.url,
      niche: input.niche,
      locale: input.locale,
      preset: input.preset,
      is_active: true,
      webhook_url: null,
      publication_mode: input.publication_mode,
      publication_mode_label:
        input.publication_mode === "symfony_bridge"
          ? "Bridge Symfony"
          : input.publication_mode === "laravel_bridge"
            ? "Bridge Laravel"
            : "Plugin WordPress",
      publication_connect_code: "PRV-MOCK-DEMO",
      publication_bridge_status: "pending",
      publication_path_prefix: input.publication_path_prefix ?? "ressources",
      gsc_property_url: null,
      gsc_connection_mode: null,
      gsc_connection_status: "not_connected",
      gsc_account_email: null,
      gsc_last_sync_at: null,
      gsc_data_as_of: null,
      installation: {
        status: "not_started",
        current_step: null,
        progress: 0,
        hosting_provider: null,
        access_method: null,
        requested_at: null,
        started_at: null,
        completed_at: null,
        failed_at: null,
        error_message: null,
        detected_framework: null,
        detected_php_version: null,
        detected_composer: null,
        logs: [],
      },
      created_at: new Date().toISOString(),
      summary: {
        pages_total: 0,
        pages_published: 0,
        pages_live: 0,
        pending_suggestions: 0,
        observed_pages: 0,
        observed_weak_pages: 0,
        observed_orphan_pages: 0,
        observed_pillar_candidates: 0,
        observed_avg_authority: 0,
        observed_avg_orphan: 0,
        observed_site_health_score: 0,
        observed_snapshot_date: null,
        observed_avg_seo_score: 0,
        observed_avg_quality_score: 0,
        observed_avg_topical_score: 0,
        observed_crawl_issues: 0,
        observed_pillar_pages: [],
        observed_link_gap_pages: [],
        observed_orphan_alerts: [],
        observed_weak_page_details: [],
        gsc_impressions: 0,
        gsc_clicks: 0,
        gsc_ctr: 0,
        gsc_indexed_pages: 0,
        gsc_indexation_synced: false,
        gsc_indexation_scope: "inspected_urls",
        gsc_indexation_scope_label: "URLs inspectées via Google",
        gsc_indexation_scope_hint:
          "PraeviSEO compte ici les URLs qu’il suit et inspecte dans Google Search Console. Le rapport Pages complet de Google peut afficher davantage d’URLs.",
        gsc_previous_impressions: 0,
        gsc_previous_clicks: 0,
        gsc_previous_ctr: 0,
        gsc_delta_impressions: 0,
        gsc_delta_clicks: 0,
        gsc_delta_ctr_points: 0,
        gsc_non_indexed_pages: 0,
        top_rising_pages: [],
        top_falling_pages: [],
        top_queries: [],
        top_rising_queries: [],
        top_falling_queries: [],
        new_queries: [],
        indexation_alerts: [],
      },
      readiness: {
        bridge_connected: false,
        gsc_connected: false,
        has_published_pages: false,
        has_live_pages: false,
      },
      next_action: {
        kind: "connect_bridge",
        label: "Activer l automatisation premium",
        detail: "Le free fonctionne déjà avec GSC. Activez ensuite la couche premium pour publier, synchroniser et exécuter des actions sur le site.",
        priority: "high",
      },
    };
  }

  const token = await getSessionToken();

  if (!token) {
    throw new Error("Session client manquante.");
  }

  const payload = await appFetch<SiteResponse>("/api/client/sites", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
    },
    body: JSON.stringify(input),
  }, token);

  return normaliseSite(payload.site);
}

export async function claimSite(input: ClaimSiteInput): Promise<PraeviseoSite> {
  if (!backendConfigured()) {
    const site = mockSites.find((entry) => entry.publication_connect_code === input.connect_code);

    if (!site) {
      throw new Error("Code de connexion invalide.");
    }

    return site;
  }

  const token = await getSessionToken();

  if (!token) {
    throw new Error("Session client manquante.");
  }

  const payload = await appFetch<SiteResponse>(
    "/api/client/sites/claim",
    {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify(input),
    },
    token
  );

  return normaliseSite(payload.site);
}

export async function connectSiteGsc(input: SiteGscConnectionInput): Promise<PraeviseoSite> {
  if (!backendConfigured()) {
    const site = mockSites.find((entry) => entry.site_id === input.site_id);

    if (!site) {
      throw new Error("Site introuvable.");
    }

    return {
      ...site,
      gsc_property_url: input.gsc_property_url,
      gsc_connection_mode: "service_account",
      gsc_connection_status: "configured",
      gsc_account_email: null,
      gsc_last_sync_at: null,
      gsc_data_as_of: null,
      readiness: {
        ...site.readiness,
        gsc_connected: false,
      },
    };
  }

  const token = await getSessionToken();

  if (!token) {
    throw new Error("Session client manquante.");
  }

  const payload = await appFetch<SiteResponse>(
    `/api/client/sites/${input.site_id}/gsc`,
    {
      method: "PATCH",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify({
        gsc_property_url: input.gsc_property_url,
      }),
    },
    token
  );

  return normaliseSite(payload.site);
}

export async function requestRemoteInstallation(input: RemoteInstallationInput): Promise<PraeviseoSite> {
  if (!backendConfigured()) {
    const site = mockSites.find((entry) => entry.site_id === input.site_id);

    if (!site) {
      throw new Error("Site introuvable.");
    }

    return {
      ...site,
      publication_bridge_status: "requested",
      installation: {
        status: "pending",
        current_step: "pending",
        progress: 0,
        hosting_provider: input.hosting_provider,
        access_method: input.access_method,
        requested_at: new Date().toISOString(),
        started_at: null,
        completed_at: null,
        failed_at: null,
        error_message: null,
        detected_framework: null,
        detected_php_version: null,
        detected_composer: null,
        logs: [],
      },
      next_action: {
        kind: "installation_requested",
        label: "PraeviSEO prépare votre installation",
        detail: "Vos accès ont bien été enregistrés. PraeviSEO peut maintenant préparer l activation distante du site.",
        priority: "medium",
      },
    };
  }

  const token = await getSessionToken();

  if (!token) {
    throw new Error("Session client manquante.");
  }

  const payload = await appFetch<SiteResponse>(
    `/api/client/sites/${input.site_id}/installation`,
    {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify({
        hosting_provider: input.hosting_provider,
        access_method: input.access_method,
        ssh_host: input.ssh_host,
        ssh_port: input.ssh_port,
        ssh_username: input.ssh_username,
        ssh_project_path: input.ssh_project_path,
        ssh_secret: input.ssh_secret,
        ssh_sudo_command: input.ssh_sudo_command,
        sftp_host: input.sftp_host,
        sftp_port: input.sftp_port,
        sftp_username: input.sftp_username,
        sftp_password: input.sftp_password,
        sftp_project_path: input.sftp_project_path,
        framework_hint: input.framework_hint,
        api_platform: input.api_platform,
        api_token: input.api_token,
        api_project_id: input.api_project_id,
        api_account_name: input.api_account_name,
        api_notes: input.api_notes,
      }),
    },
    token
  );

  return normaliseSite(payload.site);
}

export async function getRemoteInstallationStatus(siteId: string): Promise<PraeviseoSite | null> {
  if (!backendConfigured()) {
    return mockSites.find((site) => site.site_id === siteId) ?? null;
  }

  try {
    const token = await getSessionToken();

    if (!token) {
      return null;
    }

    const payload = await appFetch<InstallationStatusResponse>(
      `/api/client/sites/${siteId}/installation-status`,
      undefined,
      token
    );

    return normaliseSite(payload.site);
  } catch {
    return null;
  }
}

export function getInstallerUrl(
  filename: "praeviseo-install.ps1" | "praeviseo-install.sh",
  siteId?: string
): string {
  const params = new URLSearchParams();

  if (siteId) {
    params.set("site_id", siteId);
  }

  const query = params.toString();

  return query === "" ? `/installers/${filename}` : `/installers/${filename}?${query}`;
}

export function getSiteConnectPath(siteId: string): string {
  return `/sites/${siteId}/connect`;
}

export function getSitePath(siteId: string): string {
  return `/sites/${siteId}`;
}

export function formatRelativeStatus(status: string): string {
  return status.replace(/_/g, " ");
}

export function formatGscStatus(status: string): string {
  return (
    {
      connected: "Connectée",
      connected_empty: "Connectée",
      configured: "Connectée",
      not_connected: "Non reliée",
      error: "Erreur de synchronisation",
    }[status] ?? formatRelativeStatus(status)
  );
}

export function hasBackendConnection(): boolean {
  return backendConfigured();
}

export function formatSitePlatform(mode: string): string {
  return (
    {
      symfony_bridge: "Site Symfony",
      laravel_bridge: "Site Laravel",
      wordpress_bridge: "Site WordPress",
      runtime: "Site web",
      webhook_api: "Site web",
      disabled: "Site web",
    }[mode] ?? "Site web"
  );
}

export function formatPraeviseoStatus(status: string): string {
  if (status === "connected") {
    return "Automatisation active";
  }

  if (status === "requested") {
    return "Automatisation en préparation";
  }

  return "Automatisation non activée";
}

export function getPraeviseoClientStatus(
  site: Pick<PraeviseoSite, "publication_bridge_status" | "readiness">
): string {
  if (site.publication_bridge_status === "connected") {
    return "Automatisation active";
  }

  if (site.publication_bridge_status === "requested") {
    return "Automatisation en préparation";
  }

  return site.readiness.gsc_connected ? "Analyse GSC active" : "Search Console à connecter";
}

export function isInstallationInProgress(status: string): boolean {
  return ["requested", "pending", "connecting", "detecting_environment", "installing", "configuring", "activating"].includes(
    status
  );
}

export function getPraeviseoInstallLabel(site: Pick<PraeviseoSite, "publication_bridge_status">): string {
  if (site.publication_bridge_status === "requested") {
    return "Automatisation premium en préparation";
  }

  return site.publication_bridge_status === "connected"
    ? "Automatisation premium active"
    : "Automatisation premium non activée";
}

export function getPraeviseoInstallDetail(site: Pick<PraeviseoSite, "publication_bridge_status">): string {
  if (site.publication_bridge_status === "requested") {
    return "Vos accès ont bien été enregistrés. La couche premium d automatisation est en préparation.";
  }

  return site.publication_bridge_status === "connected"
    ? "Le site peut maintenant recevoir des actions avancées, des publications supervisées et une automatisation continue."
    : "Le mode free fonctionne déjà avec Google Search Console. L automatisation premium reste optionnelle.";
}

export function getPraeviseoClientDetail(
  site: Pick<PraeviseoSite, "publication_bridge_status" | "readiness">
): string {
  if (site.publication_bridge_status === "connected") {
    return "PraeviSEO analyse vos performances GSC et peut aussi executer des actions avancees via la couche premium.";
  }

  if (site.publication_bridge_status === "requested") {
    return "Vos accès ont bien été enregistrés. L automatisation premium est en préparation, mais l analyse GSC reste déjà active.";
  }

  if (site.readiness.gsc_connected) {
    return "PraeviSEO analyse déjà vos performances, vos requêtes, vos opportunités et votre indexation via Google Search Console.";
  }

  return "Commencez par connecter Google Search Console pour activer le cockpit SEO free.";
}

export function getPraeviseoActivationLabel(
  site: Pick<PraeviseoSite, "publication_bridge_status" | "readiness">
): string {
  if (site.publication_bridge_status === "requested") {
    return "Suivre l’automatisation";
  }

  if (site.publication_bridge_status === "connected") {
    return "Automatisation active";
  }

  return site.readiness.gsc_connected ? "Activer l’automatisation premium" : "Connecter Search Console";
}
