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
  created_at: string;
  summary: {
    pages_total: number;
    pages_published: number;
    pages_live: number;
    pending_suggestions: number;
    observed_pages: number;
    search_console_metrics: number;
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
    connectedSites: number;
    publishedPages: number;
    pendingSuggestions: number;
    observedPages: number;
    gscConnectedSites: number;
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

export type PraeviseoOptimizations = {
  stats: {
    pending: number;
    applied: number;
    rejected: number;
    total: number;
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

type SitesResponse = { sites: unknown[] };
type SiteResponse = { site: unknown };

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
    created_at: new Date().toISOString(),
    summary: {
      pages_total: 8,
      pages_published: 5,
      pages_live: 3,
      pending_suggestions: 2,
      observed_pages: 19,
      search_console_metrics: 56,
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
    created_at: new Date().toISOString(),
    summary: {
      pages_total: 0,
      pages_published: 0,
      pages_live: 0,
      pending_suggestions: 0,
      observed_pages: 0,
      search_console_metrics: 0,
    },
    readiness: {
      bridge_connected: false,
      gsc_connected: false,
      has_published_pages: false,
      has_live_pages: false,
    },
    next_action: {
      kind: "connect_bridge",
      label: "Connecter le bridge officiel",
      detail: "Installez le bridge pour activer la vraie publication et le monitoring du site public.",
      priority: "high",
    },
  },
];

const mockOptimizations: PraeviseoOptimizations = {
  stats: { pending: 2, applied: 3, rejected: 1, total: 6 },
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
    created_at: String(site.created_at ?? new Date().toISOString()),
    summary: {
      pages_total: Number(summary.pages_total ?? 0),
      pages_published: Number(summary.pages_published ?? 0),
      pages_live: Number(summary.pages_live ?? 0),
      pending_suggestions: Number(summary.pending_suggestions ?? 0),
      observed_pages: Number(summary.observed_pages ?? 0),
      search_console_metrics: Number(summary.search_console_metrics ?? 0),
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

  return {
    sites,
    totals: {
      connectedSites: sites.filter((site) => site.publication_bridge_status === "connected").length,
      publishedPages: sites.reduce((sum, site) => sum + site.summary.pages_published, 0),
      pendingSuggestions: sites.reduce((sum, site) => sum + site.summary.pending_suggestions, 0),
      observedPages: sites.reduce((sum, site) => sum + site.summary.observed_pages, 0),
      gscConnectedSites: sites.filter((site) => site.gsc_connection_status === "connected").length,
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
      created_at: new Date().toISOString(),
      summary: {
        pages_total: 0,
        pages_published: 0,
        pages_live: 0,
        pending_suggestions: 0,
        observed_pages: 0,
        search_console_metrics: 0,
      },
      readiness: {
        bridge_connected: false,
        gsc_connected: false,
        has_published_pages: false,
        has_live_pages: false,
      },
      next_action: {
        kind: "connect_bridge",
        label: "Connecter le bridge officiel",
        detail: "Installez le bridge pour activer la vraie publication et le monitoring du site public.",
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

export function getInstallerUrl(filename: "praeviseo-install.ps1" | "praeviseo-install.sh"): string {
  return `/installers/${filename}`;
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

export function hasBackendConnection(): boolean {
  return backendConfigured();
}
