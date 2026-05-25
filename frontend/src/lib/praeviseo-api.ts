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
    pending_suggestions: number;
    observed_pages: number;
    search_console_metrics: number;
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
      pending_suggestions: 2,
      observed_pages: 19,
      search_console_metrics: 56,
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
      pending_suggestions: 0,
      observed_pages: 0,
      search_console_metrics: 0,
    },
  },
];

function backendConfigured(): boolean {
  return backendBaseUrl !== "";
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
      pending_suggestions: Number(summary.pending_suggestions ?? 0),
      observed_pages: Number(summary.observed_pages ?? 0),
      search_console_metrics: Number(summary.search_console_metrics ?? 0),
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
    throw new Error(`PraeviSEO admin API error ${response.status} on ${path}`);
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
      return mockSites;
    }

    const payload = await appFetch<SitesResponse>("/api/client/sites", undefined, token);

    return payload.sites.map(normaliseSite);
  } catch {
    return mockSites;
  }
}

export async function getSite(siteId: string): Promise<PraeviseoSite | null> {
  if (!backendConfigured()) {
    return mockSites.find((site) => site.site_id === siteId) ?? null;
  }

  try {
    const token = await getSessionToken();

    if (!token) {
      return mockSites.find((site) => site.site_id === siteId) ?? null;
    }

    const payload = await appFetch<SiteResponse>(`/api/client/sites/${siteId}`, undefined, token);

    return normaliseSite(payload.site);
  } catch {
    return mockSites.find((site) => site.site_id === siteId) ?? null;
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
        pending_suggestions: 0,
        observed_pages: 0,
        search_console_metrics: 0,
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
