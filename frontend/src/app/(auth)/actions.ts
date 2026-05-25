"use server";

import { redirect } from "next/navigation";
import { loginWithPassword, logoutCurrentUser, registerWithPassword } from "@/lib/auth";

export type AuthActionState = {
  error?: string;
};

export async function loginAction(
  _previousState: AuthActionState,
  formData: FormData
): Promise<AuthActionState> {
  const email = String(formData.get("email") ?? "").trim();
  const password = String(formData.get("password") ?? "");

  if (!email || !password) {
    return { error: "Renseignez votre email et votre mot de passe." };
  }

  try {
    await loginWithPassword(email, password);
  } catch (error) {
    return {
      error: error instanceof Error ? error.message : "Connexion impossible.",
    };
  }

  redirect("/dashboard");
}

export async function signupAction(
  _previousState: AuthActionState,
  formData: FormData
): Promise<AuthActionState> {
  const name = String(formData.get("name") ?? "").trim();
  const email = String(formData.get("email") ?? "").trim();
  const password = String(formData.get("password") ?? "");

  if (!name || !email || !password) {
    return { error: "Tous les champs sont obligatoires." };
  }

  try {
    await registerWithPassword(name, email, password);
  } catch (error) {
    return {
      error: error instanceof Error ? error.message : "Inscription impossible.",
    };
  }

  redirect("/onboarding");
}

export async function logoutAction(): Promise<void> {
  await logoutCurrentUser();
  redirect("/");
}
