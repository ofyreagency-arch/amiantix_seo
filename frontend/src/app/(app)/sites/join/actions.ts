"use server";

import { redirect } from "next/navigation";
import { isRedirectError } from "next/dist/client/components/redirect-error";
import { claimSite } from "@/lib/praeviseo-api";

function failureRedirect(formData: FormData, message: string) {
  const params = new URLSearchParams();

  for (const field of ["site_id", "name", "url", "connect_code"]) {
    const value = String(formData.get(field) ?? "").trim();

    if (value !== "") {
      params.set(field, value);
    }
  }

  params.set("error", message);

  redirect(`/sites/join?${params.toString()}`);
}

export async function claimSiteAction(formData: FormData) {
  try {
    const site = await claimSite({
      site_id: String(formData.get("site_id") ?? "").trim() || undefined,
      connect_code: String(formData.get("connect_code") ?? "").trim(),
    });

    redirect(`/sites/${site.site_id}/connect?notice=claimed`);
  } catch (error) {
    if (isRedirectError(error)) {
      throw error;
    }

    failureRedirect(
      formData,
      error instanceof Error ? error.message : "Impossible de rattacher ce site pour le moment."
    );
  }
}
