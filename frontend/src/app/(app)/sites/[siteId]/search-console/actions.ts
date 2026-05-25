"use server";

import { redirect } from "next/navigation";
import { isRedirectError } from "next/dist/client/components/redirect-error";
import { connectSiteGsc } from "@/lib/praeviseo-api";

function failureRedirect(siteId: string, formData: FormData, message: string) {
  const params = new URLSearchParams();

  for (const field of ["step", "gsc_property_url"]) {
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
    const step = String(formData.get("step") ?? "").trim();

    if (step === "select-property" && String(formData.get("gsc_property_url") ?? "").trim() === "") {
      redirect(`/sites/${siteId}/search-console?step=select-property`);
    }

    await connectSiteGsc({
      site_id: siteId,
      gsc_property_url: String(formData.get("gsc_property_url") ?? "").trim(),
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
