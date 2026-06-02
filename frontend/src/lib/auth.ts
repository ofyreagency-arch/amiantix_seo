import "server-only";

import { cookies } from "next/headers";
import { redirect } from "next/navigation";

export type FrontendUser = {
  id: number;
  name: string;
  email: string;
};

type AuthPayload = {
  token: string;
  user: FrontendUser;
};

const SESSION_COOKIE = "praeviseo_session";

const backendBaseUrl = (
  process.env.PRAEVISEO_API_URL ??
  process.env.NEXT_PUBLIC_API_URL ??
  ""
).replace(/\/$/, "");

function backendConfigured(): boolean {
  return backendBaseUrl !== "";
}

async function authFetch<T>(path: string, init?: RequestInit, token?: string): Promise<T> {
  if (!backendConfigured()) {
    throw new Error("PraeviSEO backend API not configured.");
  }

  const response = await fetch(`${backendBaseUrl}/api/client/auth${path}`, {
    ...init,
    cache: "no-store",
    headers: {
      Accept: "application/json",
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
      ...(init?.headers ?? {}),
    },
  });

  if (!response.ok) {
    const body = (await response.json().catch(() => null)) as { message?: string } | null;
    throw new Error(body?.message ?? "Authentication request failed.");
  }

  return (await response.json()) as T;
}

async function persistSession(token: string): Promise<void> {
  const cookieStore = await cookies();

  cookieStore.set(SESSION_COOKIE, token, {
    httpOnly: true,
    sameSite: "lax",
    secure: process.env.NODE_ENV === "production",
    path: "/",
    maxAge: 60 * 60 * 24 * 30,
  });
}

export async function clearSession(): Promise<void> {
  const cookieStore = await cookies();
  cookieStore.delete(SESSION_COOKIE);
}

export async function getSessionToken(): Promise<string | null> {
  const cookieStore = await cookies();
  const token = cookieStore.get(SESSION_COOKIE)?.value ?? null;

  console.info("[praeviseo][auth] getSessionToken", {
    has_cookie: token !== null,
    cookie_name: SESSION_COOKIE,
    token_length: token ? token.length : 0,
  });

  return token;
}

export async function loginWithPassword(email: string, password: string): Promise<FrontendUser> {
  const payload = await authFetch<AuthPayload>("/login", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
    },
    body: JSON.stringify({ email, password }),
  });

  await persistSession(payload.token);

  return payload.user;
}

export async function registerWithPassword(
  name: string,
  email: string,
  password: string
): Promise<FrontendUser> {
  const payload = await authFetch<AuthPayload>("/register", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
    },
    body: JSON.stringify({ name, email, password }),
  });

  await persistSession(payload.token);

  return payload.user;
}

export async function getCurrentUser(): Promise<FrontendUser | null> {
  if (!backendConfigured()) {
    return null;
  }

  const token = await getSessionToken();

  if (!token) {
    return null;
  }

  try {
    const payload = await authFetch<{ user: FrontendUser }>("/me", undefined, token);

    return payload.user;
  } catch {
    // During server rendering Next.js forbids mutating cookies.
    // We treat an invalid session as logged out here and let explicit
    // auth actions such as logout clear the cookie in a safe context.
    return null;
  }
}

export async function requireCurrentUser(): Promise<FrontendUser> {
  const user = await getCurrentUser();

  if (!user) {
    redirect("/login");
  }

  return user;
}

export async function logoutCurrentUser(): Promise<void> {
  const token = await getSessionToken();

  if (token) {
    await authFetch<{ message: string }>("/logout", { method: "POST" }, token).catch(() => null);
  }

  await clearSession();
}

export async function isAuthenticated(): Promise<boolean> {
  return (await getCurrentUser()) !== null;
}
