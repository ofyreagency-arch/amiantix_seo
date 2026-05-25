"use server";

import { revalidatePath } from "next/cache";
import { updateProfile } from "@/lib/praeviseo-api";

export type SettingsActionState = {
  success?: string;
  error?: string;
};

export async function updateProfileAction(
  _previousState: SettingsActionState,
  formData: FormData
): Promise<SettingsActionState> {
  const name = String(formData.get("name") ?? "").trim();
  const email = String(formData.get("email") ?? "").trim();

  if (!name || !email) {
    return {
      error: "Le nom et l email sont obligatoires.",
    };
  }

  try {
    await updateProfile({ name, email });
  } catch (error) {
    return {
      error: error instanceof Error ? error.message : "Mise a jour impossible.",
    };
  }

  revalidatePath("/settings");

  return {
    success: "Profil mis a jour.",
  };
}
