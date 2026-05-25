"use server";

import { redirect } from "next/navigation";
import { createSite } from "@/lib/praeviseo-api";

export async function createSiteAction(formData: FormData) {
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
}
