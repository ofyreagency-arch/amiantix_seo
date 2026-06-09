"use server";

import { revalidatePath } from "next/cache";
import { redirect } from "next/navigation";
import {
  getSiteAutomationPath,
  requestPremiumCrawl,
  requestPremiumGeneration,
  requestPremiumLinking,
  requestPremiumRewrite,
} from "@/lib/praeviseo-api";

export type CopilotApplyWorkflow = "rewrite" | "generate" | "linking";

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

function buildCopilotFeedbackUrl(
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

export async function applyCopilotAction(formData: FormData): Promise<void> {
  const siteId = String(formData.get("site_id") ?? "").trim();
  const workflow = String(formData.get("apply_workflow") ?? "").trim() as CopilotApplyWorkflow;
  const subject = String(formData.get("subject") ?? "").trim();
  const slug = String(formData.get("slug") ?? "").trim();
  const query = String(formData.get("query") ?? "").trim();
  const fallbackHref = String(formData.get("apply_href") ?? "/dashboard").trim() || "/dashboard";
  const returnTo = String(formData.get("return_to") ?? "/dashboard").trim() || "/dashboard";

  if (!siteId || !workflow) {
    redirect(
      buildCopilotFeedbackUrl(
        returnTo,
        "error",
        "Action impossible",
        "PraeviSEO n’a pas pu identifier l’action à lancer."
      )
    );
  }

  const subjectLabel = subject !== "" ? `« ${subject} »` : "cette action";

  try {
    if (workflow === "rewrite") {
      await requestPremiumRewrite(siteId, slug !== "" ? { slug } : undefined);
    } else if (workflow === "generate") {
      const keyword = query !== "" ? query : subject;
      await requestPremiumGeneration(siteId, keyword !== "" ? { keyword } : undefined);
    } else if (workflow === "linking") {
      await requestPremiumLinking(siteId, slug !== "" ? { slug } : undefined);
    } else {
      redirect(fallbackHref);
    }

    revalidatePath("/dashboard");
    revalidatePath("/optimizations");
    revalidatePath(getSiteAutomationPath(siteId));
    revalidatePath("/publications");

    redirect(
      buildCopilotFeedbackUrl(
        returnTo,
        "success",
        "Action lancée",
        workflow === "generate"
          ? `PraeviSEO prépare le contenu pour ${subjectLabel}. Le résultat apparaîtra dans le studio éditorial.`
          : workflow === "linking"
            ? `PraeviSEO applique le maillage interne sur ${subjectLabel}.`
            : `PraeviSEO a appliqué l’amélioration sur ${subjectLabel}. Vérifiez le résultat dans le studio ou sur le site.`
      )
    );
  } catch (error) {
    if (isNextRedirectError(error)) {
      throw error;
    }

    const message = errorMessage(error, "PraeviSEO n’a pas pu lancer l’action pour le moment.");
    const shouldTryCrawl =
      workflow === "rewrite" &&
      slug !== "" &&
      (message.toLowerCase().includes("introuvable") || message.toLowerCase().includes("not found"));

    if (shouldTryCrawl) {
      try {
        await requestPremiumCrawl(siteId);
        revalidatePath("/dashboard");
        revalidatePath("/optimizations");
        revalidatePath(getSiteAutomationPath(siteId));

        redirect(
          buildCopilotFeedbackUrl(
            returnTo,
            "warning",
            "Page en préparation",
            `PraeviSEO relit d’abord votre site pour préparer « ${slug} ». Réessayez l’action dans quelques minutes.`
          )
        );
      } catch (crawlError) {
        if (isNextRedirectError(crawlError)) {
          throw crawlError;
        }
      }
    }

    redirect(
      buildCopilotFeedbackUrl(
        returnTo,
        "error",
        "Action bloquée",
        message
      )
    );
  }
}
