"use client";

import Link from "next/link";
import { useState } from "react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { SearchIcon, ArrowRight, CheckCircle2 } from "lucide-react";
import { useRouter } from "next/navigation";

const PERKS = [
  "14 jours d'essai gratuit",
  "Aucune carte bancaire",
  "Annulation à tout moment",
];

export default function SignupPage() {
  const router = useRouter();
  const [form, setForm] = useState({
    name: "",
    email: "",
    password: "",
  });
  const [loading, setLoading] = useState(false);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);
    await new Promise((r) => setTimeout(r, 900));
    // After signup → onboarding
    router.push("/onboarding");
  };

  return (
    <div className="min-h-screen bg-bg flex items-center justify-center px-4">
      <div className="absolute inset-0 bg-hero-glow opacity-40 pointer-events-none" />

      <div className="relative w-full max-w-[420px]">
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
            <h1 className="text-xl font-bold text-text mb-1.5">
              Démarrez gratuitement 🚀
            </h1>
            <p className="text-sm text-text-muted">
              Créez votre compte et connectez votre premier site en 2 minutes.
            </p>
          </div>

          {/* Perks */}
          <div className="flex flex-wrap gap-x-4 gap-y-1 mb-6">
            {PERKS.map((p) => (
              <span key={p} className="flex items-center gap-1.5 text-xs text-text-subtle">
                <CheckCircle2 className="w-3.5 h-3.5 text-success" />
                {p}
              </span>
            ))}
          </div>

          <form onSubmit={handleSubmit} className="space-y-4">
            <Input
              label="Nom ou société"
              placeholder="Agence ACME"
              value={form.name}
              onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))}
              required
              autoComplete="organization"
            />
            <Input
              label="Email professionnel"
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
              placeholder="8 caractères minimum"
              value={form.password}
              onChange={(e) => setForm((f) => ({ ...f, password: e.target.value }))}
              required
              minLength={8}
              autoComplete="new-password"
              hint="Au moins 8 caractères"
            />

            <Button type="submit" className="w-full group mt-2" loading={loading}>
              Créer mon compte
              <ArrowRight className="w-4 h-4 group-hover:translate-x-0.5 transition-transform" />
            </Button>
          </form>

          <p className="mt-4 text-center text-xs text-text-subtle">
            En créant un compte, vous acceptez nos{" "}
            <Link href="#" className="text-brand hover:underline">CGU</Link>{" "}
            et notre{" "}
            <Link href="#" className="text-brand hover:underline">
              Politique de confidentialité
            </Link>
            .
          </p>
        </div>

        <p className="mt-6 text-center text-sm text-text-subtle">
          Déjà un compte ?{" "}
          <Link href="/login" className="text-brand hover:text-brand-hover transition-colors font-medium">
            Se connecter
          </Link>
        </p>
      </div>
    </div>
  );
}
