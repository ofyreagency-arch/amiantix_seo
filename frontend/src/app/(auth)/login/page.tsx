"use client";

import Link from "next/link";
import { useState } from "react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { SearchIcon, ArrowRight } from "lucide-react";
import { useRouter } from "next/navigation";

export default function LoginPage() {
  const router = useRouter();
  const [form, setForm] = useState({ email: "", password: "" });
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState("");

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);
    setError("");
    // Mock auth — redirect to dashboard
    await new Promise((r) => setTimeout(r, 800));
    router.push("/dashboard");
  };

  return (
    <div className="min-h-screen bg-bg flex items-center justify-center px-4">
      {/* Background glow */}
      <div className="absolute inset-0 bg-hero-glow opacity-50 pointer-events-none" />

      <div className="relative w-full max-w-[380px]">
        {/* Logo */}
        <Link href="/" className="flex items-center justify-center gap-2.5 mb-8">
          <div className="w-8 h-8 rounded-xl bg-brand flex items-center justify-center shadow-sm shadow-brand/30">
            <SearchIcon className="w-4 h-4 text-white" />
          </div>
          <span className="font-semibold text-lg text-text tracking-tight">PraeviSEO</span>
        </Link>

        {/* Card */}
        <div className="rounded-2xl border border-border bg-surface p-8 shadow-xl shadow-bg">
          <div className="mb-6">
            <h1 className="text-xl font-bold text-text mb-1.5">Bon retour 👋</h1>
            <p className="text-sm text-text-muted">
              Connectez-vous pour accéder à votre dashboard SEO.
            </p>
          </div>

          <form onSubmit={handleSubmit} className="space-y-4">
            <Input
              label="Email"
              type="email"
              placeholder="vous@exemple.fr"
              value={form.email}
              onChange={(e) => setForm((f) => ({ ...f, email: e.target.value }))}
              required
              autoComplete="email"
            />
            <Input
              label="Mot de passe"
              type="password"
              placeholder="••••••••"
              value={form.password}
              onChange={(e) => setForm((f) => ({ ...f, password: e.target.value }))}
              required
              autoComplete="current-password"
            />

            {error && (
              <p className="text-sm text-danger bg-danger-subtle rounded-lg px-3 py-2">
                {error}
              </p>
            )}

            <div className="flex items-center justify-between text-sm">
              <label className="flex items-center gap-2 text-text-muted cursor-pointer">
                <input type="checkbox" className="rounded border-border bg-surface-2 accent-brand" />
                Se souvenir de moi
              </label>
              <Link
                href="/forgot-password"
                className="text-brand hover:text-brand-hover transition-colors"
              >
                Mot de passe oublié ?
              </Link>
            </div>

            <Button type="submit" className="w-full group" loading={loading}>
              Se connecter
              <ArrowRight className="w-4 h-4 group-hover:translate-x-0.5 transition-transform" />
            </Button>
          </form>

          {/* Divider */}
          <div className="relative my-6">
            <div className="absolute inset-0 flex items-center">
              <div className="w-full border-t border-border" />
            </div>
            <div className="relative flex justify-center">
              <span className="px-3 bg-surface text-xs text-text-subtle">ou</span>
            </div>
          </div>

          {/* Google OAuth (placeholder) */}
          <Button
            variant="secondary"
            className="w-full gap-2"
            onClick={() => router.push("/dashboard")}
          >
            <svg className="w-4 h-4" viewBox="0 0 24 24" fill="none">
              <path
                d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"
                fill="#4285F4"
              />
              <path
                d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"
                fill="#34A853"
              />
              <path
                d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z"
                fill="#FBBC05"
              />
              <path
                d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"
                fill="#EA4335"
              />
            </svg>
            Continuer avec Google
          </Button>
        </div>

        <p className="mt-6 text-center text-sm text-text-subtle">
          Pas encore de compte ?{" "}
          <Link href="/signup" className="text-brand hover:text-brand-hover transition-colors font-medium">
            Créer un compte gratuit
          </Link>
        </p>
      </div>
    </div>
  );
}
