"use client";

import { useMemo, useState } from "react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { CheckCircle2, Cloud, HardDrive, KeyRound, LockKeyhole, ServerCog, WandSparkles } from "lucide-react";

type HostingOption = {
  id: string;
  label: string;
  hint: string;
  category: "vps" | "shared" | "platform";
};

type AccessOption = {
  id: string;
  label: string;
  hint: string;
};

const HOSTING_OPTIONS: HostingOption[] = [
  { id: "vps_linux", label: "VPS Linux", hint: "Ubuntu, Debian, serveur dédié ou cloud", category: "vps" },
  { id: "ovh", label: "OVH", hint: "Hébergement mutualisé ou cloud OVH", category: "shared" },
  { id: "ionos", label: "IONOS", hint: "Mutualisé, VPS ou serveur managé", category: "shared" },
  { id: "hostinger", label: "Hostinger", hint: "Hébergement WordPress, mutualisé ou VPS", category: "shared" },
  { id: "oswitch", label: "o2switch", hint: "Mutualisé cPanel et hébergement web", category: "shared" },
  { id: "vercel", label: "Vercel", hint: "Déploiement web moderne et headless", category: "platform" },
  { id: "other", label: "Autre hébergeur", hint: "PraeviSEO s’adapte à votre environnement", category: "shared" },
];

const ACCESS_OPTIONS: AccessOption[] = [
  { id: "ssh", label: "Accès SSH", hint: "Recommandé pour Laravel, Symfony et VPS Linux" },
  { id: "sftp", label: "Accès SFTP / FTP", hint: "Pour hébergements mutualisés et accès fichiers" },
  { id: "api", label: "API hébergeur", hint: "Quand l’hébergeur propose une connexion native" },
];

function categoryLabel(category: HostingOption["category"]) {
  return (
    {
      vps: "Serveur",
      shared: "Hébergement",
      platform: "Plateforme",
    }[category] ?? "Hébergement"
  );
}

export function RemoteInstallAssistant() {
  const [hostingId, setHostingId] = useState<string>("vps_linux");
  const [accessId, setAccessId] = useState<string>("ssh");

  const selectedHosting = useMemo(
    () => HOSTING_OPTIONS.find((option) => option.id === hostingId) ?? HOSTING_OPTIONS[0],
    [hostingId]
  );

  const selectedAccess = useMemo(
    () => ACCESS_OPTIONS.find((option) => option.id === accessId) ?? ACCESS_OPTIONS[0],
    [accessId]
  );

  return (
    <div className="grid gap-6 xl:grid-cols-[1.1fr_0.9fr]">
      <div className="space-y-6">
        <Card>
          <CardHeader>
            <CardTitle>1. Choisissez votre hébergement</CardTitle>
            <CardDescription>
              PraeviSEO s’adapte à l’endroit où votre site est hébergé. Le client choisit simplement son environnement.
            </CardDescription>
          </CardHeader>
          <CardContent className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
            {HOSTING_OPTIONS.map((option) => {
              const selected = option.id === selectedHosting.id;

              return (
                <button
                  key={option.id}
                  type="button"
                  onClick={() => setHostingId(option.id)}
                  className={[
                    "rounded-2xl border px-4 py-4 text-left transition-all",
                    selected
                      ? "border-brand bg-brand-muted shadow-sm shadow-brand/10"
                      : "border-border bg-surface-2 hover:border-brand/30 hover:bg-surface",
                  ].join(" ")}
                >
                  <div className="flex items-center justify-between gap-3">
                    <Badge variant={selected ? "default" : "secondary"}>{categoryLabel(option.category)}</Badge>
                    {selected ? <CheckCircle2 className="h-4 w-4 text-[hsl(var(--success))]" /> : null}
                  </div>
                  <div className="mt-3 text-sm font-semibold text-text">{option.label}</div>
                  <p className="mt-2 text-sm leading-6 text-text-muted">{option.hint}</p>
                </button>
              );
            })}
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>2. Choisissez votre mode d’accès</CardTitle>
            <CardDescription>
              PraeviSEO vous demandera ensuite uniquement l’accès sécurisé adapté à votre hébergement.
            </CardDescription>
          </CardHeader>
          <CardContent className="grid gap-3 md:grid-cols-3">
            {ACCESS_OPTIONS.map((option) => {
              const selected = option.id === selectedAccess.id;

              return (
                <button
                  key={option.id}
                  type="button"
                  onClick={() => setAccessId(option.id)}
                  className={[
                    "rounded-2xl border px-4 py-4 text-left transition-all",
                    selected
                      ? "border-brand bg-brand-muted shadow-sm shadow-brand/10"
                      : "border-border bg-surface-2 hover:border-brand/30 hover:bg-surface",
                  ].join(" ")}
                >
                  <div className="flex items-center justify-between gap-3">
                    <div className="w-9 h-9 rounded-xl bg-brand-subtle flex items-center justify-center">
                      {option.id === "ssh" ? (
                        <KeyRound className="h-4 w-4 text-[hsl(var(--brand))]" />
                      ) : option.id === "sftp" ? (
                        <LockKeyhole className="h-4 w-4 text-[hsl(var(--brand))]" />
                      ) : (
                        <Cloud className="h-4 w-4 text-[hsl(var(--brand))]" />
                      )}
                    </div>
                    {selected ? <CheckCircle2 className="h-4 w-4 text-[hsl(var(--success))]" /> : null}
                  </div>
                  <div className="mt-3 text-sm font-semibold text-text">{option.label}</div>
                  <p className="mt-2 text-sm leading-6 text-text-muted">{option.hint}</p>
                </button>
              );
            })}
          </CardContent>
        </Card>

        <Card className="border-brand/20 bg-brand-muted">
          <CardHeader>
            <CardTitle>3. PraeviSEO s’occupe de l’installation</CardTitle>
            <CardDescription>
              Une fois l’accès fourni, PraeviSEO pourra détecter automatiquement votre site et préparer l’activation.
            </CardDescription>
          </CardHeader>
          <CardContent className="space-y-3 text-sm text-text-muted">
            {[
              "Détection automatique de Laravel, Symfony ou WordPress",
              "Installation et configuration de PraeviSEO sur l’hébergement distant",
              "Activation du monitoring SEO, des publications et des optimisations",
            ].map((item) => (
              <div key={item} className="flex items-start gap-2">
                <CheckCircle2 className="w-4 h-4 text-[hsl(var(--success))] shrink-0 mt-0.5" />
                <span>{item}</span>
              </div>
            ))}
          </CardContent>
        </Card>
      </div>

      <div className="space-y-6">
        <Card>
          <CardHeader>
            <CardTitle>Assistant d’installation</CardTitle>
            <CardDescription>
              Le client ne manipule ni terminal, ni Composer, ni fichiers serveur. PraeviSEO se charge de la couche technique.
            </CardDescription>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="rounded-2xl border border-border bg-surface-2 px-4 py-4">
              <div className="text-[11px] uppercase tracking-[0.18em] text-text-subtle font-semibold">Hébergement choisi</div>
              <div className="mt-2 text-sm font-semibold text-text">{selectedHosting.label}</div>
              <p className="mt-2 text-sm leading-6 text-text-muted">{selectedHosting.hint}</p>
            </div>

            <div className="rounded-2xl border border-border bg-surface-2 px-4 py-4">
              <div className="text-[11px] uppercase tracking-[0.18em] text-text-subtle font-semibold">Accès recommandé</div>
              <div className="mt-2 text-sm font-semibold text-text">{selectedAccess.label}</div>
              <p className="mt-2 text-sm leading-6 text-text-muted">{selectedAccess.hint}</p>
            </div>

            <div className="rounded-2xl border border-brand/20 bg-brand-muted px-4 py-4">
              <div className="flex items-center gap-2 text-sm font-semibold text-text">
                <WandSparkles className="w-4 h-4 text-[hsl(var(--brand))]" />
                Installation distante assistée
              </div>
              <p className="mt-2 text-sm leading-6 text-text-muted">
                Cette expérience prépare le vrai flow SaaS d’installation automatique sur hébergement. Le moteur et
                le bridge restent identiques, mais la complexité technique est masquée au client.
              </p>
            </div>

            <Button size="lg" className="w-full">
              Continuer vers l’installation assistée
            </Button>

            <p className="text-xs leading-6 text-text-subtle">
              Si votre hébergeur ne permet pas encore l’installation assistée, vous pourrez toujours utiliser
              l’installateur officiel ci-dessous.
            </p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Ce que le client doit ressentir</CardTitle>
          </CardHeader>
          <CardContent className="space-y-3 text-sm text-text-muted">
            {[
              "Je choisis où mon site est hébergé",
              "Je donne un accès sécurisé si nécessaire",
              "PraeviSEO installe automatiquement le nécessaire",
              "Mon site devient actif sans jargon technique",
            ].map((item) => (
              <div key={item} className="flex items-start gap-2">
                <ServerCog className="w-4 h-4 text-[hsl(var(--brand))] shrink-0 mt-0.5" />
                <span>{item}</span>
              </div>
            ))}
          </CardContent>
        </Card>
      </div>
    </div>
  );
}
