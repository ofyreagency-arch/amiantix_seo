"use server";

import { redirect } from "next/navigation";
import { isRedirectError } from "next/dist/client/components/redirect-error";
import { connectSiteGsc } from "@/lib/praeviseo-api";

function failureRedirect(siteId: string, formData: FormData, message: string) {
  const params = new URLSearchParams();

  for (const field of [
    "gsc_connection_mode",
    "gsc_property_url",
    "gsc_credentials_path",
    "gsc_account_email",
  ]) {
    const value = String(formData.get(field) ?? "").trim();

    if (value !== "") {
      params.set(field, value);
    }
  }

  params.set("error", message);

  redirect(`/sites/${siteId}/search-console?${params.toString()}`);
}

export async function connectGscAction(siteId: string, formData: FormData) {
  try {
    await connectSiteGsc({
      site_id: siteId,
      gsc_connection_mode: String(formData.get("gsc_connection_mode") ?? "service_account").trim() as
        | "service_account"
        | "oauth_google",
      gsc_property_url: String(formData.get("gsc_property_url") ?? "").trim(),
      gsc_credentials_path: String(formData.get("gsc_credentials_path") ?? "").trim(),
      gsc_account_email: String(formData.get("gsc_account_email") ?? "").trim(),
    });

    redirect(`/sites/${siteId}?notice=gsc-connected`);
  } catch (error) {
    if (isRedirectError(error)) {
      throw error;
    }

    failureRedirect(
      siteId,
      formData,
      error instanceof Error ? error.message : "Impossible de connecter Google Search Console pour le moment."
    );
  }
}
