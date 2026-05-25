"use server";

import { redirect } from "next/navigation";
import { isRedirectError } from "next/dist/client/components/redirect-error";
import { createSite, getSite } from "@/lib/praeviseo-api";

function normaliseSiteId(value: string): string {
  return value
    .trim()
    .toLowerCase()
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .replace(/\s+/g, "-")
    .replace(/[^a-z0-9_-]/g, "-")
    .replace(/-+/g, "-")
    .replace(/^[-_]+|[-_]+$/g, "");
}

function failureRedirect(formData: FormData, message: string) {
  const params = new URLSearchParams();

  for (const field of [
    "site_id",
    "name",
    "url",
    "niche",
    "locale",
    "preset",
    "publication_mode",
    "publication_path_prefix",
  ]) {
    const value = String(formData.get(field) ?? "").trim();

    if (value !== "") {
      params.set(field, value);
    }
  }

  params.set("error", message);

  redirect(`/sites/new?${params.toString()}`);
}

export async function createSiteAction(formData: FormData) {
  try {
    const siteId = normaliseSiteId(String(formData.get("site_id") ?? ""));

    const site = await createSite({
      site_id: siteId,
      name: String(formData.get("name") ?? "").trim(),
      url: String(formData.get("url") ?? "").trim(),
      niche: String(formData.get("niche") ?? "general").trim(),
      locale: String(formData.get("locale") ?? "fr").trim(),
      preset: (String(formData.get("preset") ?? "generic").trim() as "generic" | "amiantix"),
      publication_mode: (
        String(formData.get("publication_mode") ?? "symfony_bridge").trim() as
          | "laravel_bridge"
          | "symfony_bridge"
          | "wordpress_bridge"
      ),
      publication_path_prefix: String(formData.get("publication_path_prefix") ?? "ressources").trim(),
    });

    redirect(`/sites/${site.site_id}/connect`);
  } catch (error) {
    if (isRedirectError(error)) {
      throw error;
    }

    const siteId = normaliseSiteId(String(formData.get("site_id") ?? ""));
    formData.set("site_id", siteId);

    if (
      error instanceof Error &&
      error.message.toLowerCase().includes("identifiant est deja utilise")
    ) {
      const existingSite = await getSite(siteId);

      if (existingSite) {
        redirect(`/sites/${existingSite.site_id}/connect?notice=already-exists`);
      }
    }

    failureRedirect(
      formData,
      error instanceof Error ? error.message : "Impossible de creer le site pour le moment."
    );
  }
}
