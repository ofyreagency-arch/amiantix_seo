"use server";

import { revalidatePath } from "next/cache";
import { redirect } from "next/navigation";
import {
  getSiteAutomationPath,
  getSiteConnectPath,
  requestRemoteInstallationPrecheck,
  type InstallationReadinessReport,
  requestPremiumCrawl,
  requestPremiumGeneration,
  requestPremiumImages,
  requestPremiumLinking,
  requestPremiumPublication,
  requestPremiumRewrite,
  requestRemoteInstallation,
} from "@/lib/praeviseo-api";

export type RemoteInstallActionState = {
  status: "idle" | "success" | "error";
  phase: "idle" | "diagnostic" | "install";
  message: string;
  values: Record<string, string>;
  report: InstallationReadinessReport | null;
};

const FIELDS = [
  "hosting_provider",
  "access_method",
  "ssh_host",
  "ssh_port",
  "ssh_username",
  "ssh_project_path",
  "ssh_secret",
  "ssh_sudo_command",
  "sftp_host",
  "sftp_port",
  "sftp_username",
  "sftp_password",
  "sftp_project_path",
  "framework_hint",
  "api_platform",
  "api_token",
  "api_project_id",
  "api_account_name",
  "api_notes",
] as const;

function readValues(formData: FormData): Record<string, string> {
  return FIELDS.reduce<Record<string, string>>((carry, field) => {
    carry[field] = String(formData.get(field) ?? "").trim();

    return carry;
  }, {});
}

function serializeActionError(error: unknown): Record<string, unknown> {
  if (error instanceof Error) {
    const details: Record<string, unknown> = {
      name: error.name,
      message: error.message,
      stack: error.stack ?? null,
    };

    const withDigest = error as Error & { digest?: string; cause?: unknown };

    if (typeof withDigest.digest !== "undefined") {
      details.digest = withDigest.digest;
    }

    if (typeof withDigest.cause !== "undefined") {
      details.cause = withDigest.cause;
    }

    return details;
  }

  if (typeof error === "object" && error !== null) {
    return {
      value: error,
    };
  }

  return {
    value: String(error),
  };
}

function buildAutomationFeedbackUrl(
  siteId: string,
  type: "success" | "warning" | "error",
  title: string,
  detail: string
): string {
  const params = new URLSearchParams({
    feedback: type,
    feedback_title: title,
    feedback_detail: detail,
  });

  return `${getSiteAutomationPath(siteId)}?${params.toString()}`;
}

export async function submitRemoteInstallAction(
  siteId: string,
  _previousState: RemoteInstallActionState,
  formData: FormData
): Promise<RemoteInstallActionState> {
  const values = readValues(formData);

  try {
    const payload = {
      site_id: siteId,
      hosting_provider: values.hosting_provider,
      access_method: (values.access_method as "ssh" | "sftp" | "api") || "ssh",
      ssh_host: values.ssh_host,
      ssh_port: values.ssh_port,
      ssh_username: values.ssh_username,
      ssh_project_path: values.ssh_project_path,
      ssh_secret: values.ssh_secret,
      ssh_sudo_command: values.ssh_sudo_command,
      sftp_host: values.sftp_host,
      sftp_port: values.sftp_port,
      sftp_username: values.sftp_username,
      sftp_password: values.sftp_password,
      sftp_project_path: values.sftp_project_path,
      framework_hint: values.framework_hint,
      api_platform: values.api_platform,
      api_token: values.api_token,
      api_project_id: values.api_project_id,
      api_account_name: values.api_account_name,
      api_notes: values.api_notes,
    };
    const intent = String(formData.get("intent") ?? "precheck");

    if (intent === "precheck") {
      const report = await requestRemoteInstallationPrecheck(payload);

      return {
        status: report.status === "ready" ? "success" : "error",
        phase: "diagnostic",
        message:
          report.status === "ready"
            ? "Le diagnostic est terminé. PraeviSEO peut maintenant lancer l installation."
            : report.summary,
        values,
        report,
      };
    }

    const site = await requestRemoteInstallation(payload);

    return {
      status: "success",
      phase: "install",
      message:
        site.installation.requested_at
          ? "Le diagnostic est validé. PraeviSEO lance maintenant l installation et l activation du site."
          : "Le diagnostic est validé. PraeviSEO lance maintenant l installation.",
      values: {
        ...values,
        hosting_provider: site.installation.hosting_provider ?? values.hosting_provider,
        access_method: site.installation.access_method ?? values.access_method,
      },
      report: site.installation.readiness_report,
    };
  } catch (error) {
    return {
      status: "error",
      phase: _previousState.phase,
      message:
        error instanceof Error
          ? error.message
          : "Impossible d enregistrer les accès pour le moment.",
      values,
      report: _previousState.report,
    };
  }
}

export async function launchPremiumCrawlAction(siteId: string): Promise<void> {
  console.info("[praeviseo][action] launchPremiumCrawlAction:start", {
    site_id: siteId,
  });

  try {
    await requestPremiumCrawl(siteId);
    const redirectTo = buildAutomationFeedbackUrl(
      siteId,
      "success",
      "Crawl lancé",
      "PraeviSEO a bien planifié une nouvelle relecture du site. Le statut du crawl va passer en planifié puis en cours dès que le job démarre."
    );
    console.info("[praeviseo][action] launchPremiumCrawlAction:success", {
      site_id: siteId,
      redirect_to: redirectTo,
    });
    revalidatePath(getSiteConnectPath(siteId));
    revalidatePath(getSiteAutomationPath(siteId));
    redirect(redirectTo);
  } catch (error) {
    console.error("[praeviseo][action] launchPremiumCrawlAction:error", {
      site_id: siteId,
      ...serializeActionError(error),
    });

    throw error;
  }
}

export async function launchPremiumGenerationAction(siteId: string): Promise<void> {
  try {
    await requestPremiumGeneration(siteId);
    revalidatePath(getSiteConnectPath(siteId));
    revalidatePath(getSiteAutomationPath(siteId));
    redirect(
      buildAutomationFeedbackUrl(
        siteId,
        "success",
        "Article lancé",
        "PraeviSEO a bien démarré la préparation d’un nouvel article pour ce site."
      )
    );
  } catch (error) {
    const message = error instanceof Error ? error.message : "PraeviSEO n a pas pu démarrer la génération pour le moment.";

    if (message.includes("attend encore avant d ouvrir un nouveau sujet")) {
      revalidatePath(getSiteConnectPath(siteId));
      revalidatePath(getSiteAutomationPath(siteId));
      redirect(
        buildAutomationFeedbackUrl(
          siteId,
          "warning",
          "Cooldown génération actif",
          "PraeviSEO évite simplement d’ouvrir un nouveau sujet trop vite. La génération redeviendra disponible au prochain passage utile."
        )
      );
    }

    throw error;
  }
}

export async function launchPremiumGenerationForKeywordAction(siteId: string, keyword: string): Promise<void> {
  try {
    await requestPremiumGeneration(siteId, { keyword });
    revalidatePath(getSiteConnectPath(siteId));
    revalidatePath(getSiteAutomationPath(siteId));
    redirect(
      buildAutomationFeedbackUrl(
        siteId,
        "success",
        "Article ciblé lancé",
        `PraeviSEO a bien démarré un nouvel article autour de "${keyword}".`
      )
    );
  } catch (error) {
    throw error;
  }
}

export async function launchPremiumRewriteAction(siteId: string, slug?: string | null): Promise<void> {
  await requestPremiumRewrite(siteId, slug ? { slug } : undefined);
  revalidatePath(getSiteConnectPath(siteId));
  revalidatePath(getSiteAutomationPath(siteId));
  redirect(
    buildAutomationFeedbackUrl(
      siteId,
      "success",
      "Réécriture préparée",
      slug
        ? `PraeviSEO a relancé la réécriture de "${slug}" et a remis ce contenu dans la boucle de travail.`
        : "PraeviSEO a relancé une réécriture utile sur le contenu le plus prioritaire du moment."
    )
  );
}

export async function launchPremiumLinkingAction(siteId: string, slug?: string | null): Promise<void> {
  await requestPremiumLinking(siteId, slug ? { slug } : undefined);
  revalidatePath(getSiteConnectPath(siteId));
  revalidatePath(getSiteAutomationPath(siteId));
  redirect(
    buildAutomationFeedbackUrl(
      siteId,
      "success",
      "Maillage relancé",
      slug
        ? `PraeviSEO a relancé le maillage autour de "${slug}" pour ouvrir des liens internes plus utiles.`
        : "PraeviSEO a relancé le maillage interne sur la meilleure page à soutenir."
    )
  );
}

export async function launchPremiumImageAction(siteId: string, slug?: string | null): Promise<void> {
  await requestPremiumImages(siteId, slug ? { slug } : undefined);
  revalidatePath(getSiteConnectPath(siteId));
  revalidatePath(getSiteAutomationPath(siteId));
  redirect(
    buildAutomationFeedbackUrl(
      siteId,
      "success",
      "Image SEO préparée",
      slug
        ? `PraeviSEO a préparé l’image SEO de "${slug}".`
        : "PraeviSEO a préparé une image SEO sur la page la plus utile du moment."
    )
  );
}

export async function launchPremiumPublicationAction(siteId: string, slug?: string | null): Promise<void> {
  await requestPremiumPublication(siteId, slug ? { slug } : undefined);
  revalidatePath(getSiteConnectPath(siteId));
  revalidatePath(getSiteAutomationPath(siteId));
  redirect(
    buildAutomationFeedbackUrl(
      siteId,
      "success",
      "Publication lancée",
      slug
        ? `PraeviSEO a poussé "${slug}" vers le site client.`
        : "PraeviSEO a poussé le contenu prêt le plus prioritaire vers le site client."
    )
  );
}
