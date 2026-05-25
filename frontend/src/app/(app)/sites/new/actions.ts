"use server";

import { redirect } from "next/navigation";
import { createSite } from "@/lib/praeviseo-api";

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
    const site = await createSite({
      site_id: String(formData.get("site_id") ?? "").trim(),
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
    failureRedirect(
      formData,
      error instanceof Error ? error.message : "Impossible de creer le site pour le moment."
    );
  }
}
