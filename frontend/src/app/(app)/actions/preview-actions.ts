"use server";

import { revalidatePath } from "next/cache";
import { redirect } from "next/navigation";
import { requestConfirmPreviewPublish } from "@/lib/praeviseo-api";

function isNextRedirectError(error: unknown): boolean {
  return (
    typeof error === "object" &&
    error !== null &&
    "digest" in error &&
    typeof (error as { digest?: string }).digest === "string" &&
    (error as { digest: string }).digest.startsWith("NEXT_REDIRECT")
  );
}

function errorMessage(error: unknown, fallback: string): string {
  return error instanceof Error && error.message.trim() !== "" ? error.message : fallback;
}

function buildFeedbackUrl(
  returnTo: string,
  type: "success" | "warning" | "error",
  title: string,
  detail: string
): string {
  const params = new URLSearchParams({
    copilot_feedback: type,
    copilot_title: title,
    copilot_detail: detail,
  });

  const separator = returnTo.includes("?") ? "&" : "?";

  return `${returnTo}${separator}${params.toString()}`;
}

export async function confirmPreviewPublish(formData: FormData): Promise<void> {
  const siteId = String(formData.get("site_id") ?? "").trim();
  const slug = String(formData.get("slug") ?? "").trim();
  const query = String(formData.get("query") ?? "").trim();
  const returnTo = String(formData.get("return_to") ?? "/optimizations").trim() || "/optimizations";

  if (!siteId || !slug) {
    redirect(
      buildFeedbackUrl(
        returnTo,
        "error",
        "Publication impossible",
        "PraeviSEO n’a pas pu identifier la page à publier."
      )
    );
  }

  try {
    const result = await requestConfirmPreviewPublish(siteId, slug, query || null);
    const liveUrl = typeof result.publication.live_url === "string" ? result.publication.live_url : "";

    revalidatePath("/optimizations");
    revalidatePath("/preview");
    revalidatePath("/pages");
    revalidatePath("/publications");

    redirect(
      buildFeedbackUrl(
        returnTo,
        "success",
        "Publication confirmée",
        liveUrl !== ""
          ? `PraeviSEO a publié les enrichissements validés sur ${liveUrl}.`
          : `PraeviSEO a publié les enrichissements validés sur « ${slug} ».`
      )
    );
  } catch (error) {
    if (isNextRedirectError(error)) {
      throw error;
    }

    redirect(
      buildFeedbackUrl(
        returnTo,
        "error",
        "Publication bloquée",
        errorMessage(error, "PraeviSEO n’a pas pu publier cette page sur son URL native pour le moment.")
      )
    );
  }
}
