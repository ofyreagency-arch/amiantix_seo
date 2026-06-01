"use server";

import { revalidatePath } from "next/cache";
import { redirect } from "next/navigation";
import {
  getSiteConnectPath,
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
  message: string;
  values: Record<string, string>;
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

export async function submitRemoteInstallAction(
  siteId: string,
  _previousState: RemoteInstallActionState,
  formData: FormData
): Promise<RemoteInstallActionState> {
  const values = readValues(formData);

  try {
    const site = await requestRemoteInstallation({
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
    });

    return {
      status: "success",
      message:
        site.installation.requested_at
          ? "Vos accès ont bien été enregistrés. La couche premium d automatisation est maintenant en préparation."
          : "Vos accès ont bien été enregistrés.",
      values: {
        ...values,
        hosting_provider: site.installation.hosting_provider ?? values.hosting_provider,
        access_method: site.installation.access_method ?? values.access_method,
      },
    };
  } catch (error) {
    return {
      status: "error",
      message:
        error instanceof Error
          ? error.message
          : "Impossible d enregistrer les accès pour le moment.",
      values,
    };
  }
}

export async function launchPremiumCrawlAction(siteId: string): Promise<void> {
  await requestPremiumCrawl(siteId);
  revalidatePath(getSiteConnectPath(siteId));
  redirect(getSiteConnectPath(siteId));
}

export async function launchPremiumGenerationAction(siteId: string): Promise<void> {
  await requestPremiumGeneration(siteId);
  revalidatePath(getSiteConnectPath(siteId));
  redirect(getSiteConnectPath(siteId));
}

export async function launchPremiumRewriteAction(siteId: string): Promise<void> {
  await requestPremiumRewrite(siteId);
  revalidatePath(getSiteConnectPath(siteId));
  redirect(getSiteConnectPath(siteId));
}

export async function launchPremiumLinkingAction(siteId: string): Promise<void> {
  await requestPremiumLinking(siteId);
  revalidatePath(getSiteConnectPath(siteId));
  redirect(getSiteConnectPath(siteId));
}

export async function launchPremiumImageAction(siteId: string): Promise<void> {
  await requestPremiumImages(siteId);
  revalidatePath(getSiteConnectPath(siteId));
  redirect(getSiteConnectPath(siteId));
}

export async function launchPremiumPublicationAction(siteId: string): Promise<void> {
  await requestPremiumPublication(siteId);
  revalidatePath(getSiteConnectPath(siteId));
  redirect(getSiteConnectPath(siteId));
}
