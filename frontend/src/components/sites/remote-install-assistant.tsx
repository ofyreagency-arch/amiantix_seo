"use client";

import { useMemo, useRef, useState } from "react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
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
  const [showAccessStep, setShowAccessStep] = useState<boolean>(false);
  const accessStepRef = useRef<HTMLDivElement | null>(null);

  const selectedHosting = useMemo(
    () => HOSTING_OPTIONS.find((option) => option.id === hostingId) ?? HOSTING_OPTIONS[0],
    [hostingId]
  );

  const selectedAccess = useMemo(
    () => ACCESS_OPTIONS.find((option) => option.id === accessId) ?? ACCESS_OPTIONS[0],
    [accessId]
  );

  const beginInstall = () => {
    setShowAccessStep(true);

    requestAnimationFrame(() => {
      accessStepRef.current?.scrollIntoView({ behavior: "smooth", block: "start" });
    });
  };

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

            <Button size="lg" className="w-full" onClick={beginInstall}>
              Continuer vers la configuration des accès
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

        {showAccessStep ? (
          <Card ref={accessStepRef}>
            <CardHeader>
              <CardTitle>4. Renseignez l’accès à votre hébergement</CardTitle>
              <CardDescription>
                PraeviSEO vous demande uniquement les informations utiles pour préparer l’installation sur votre hébergement.
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="rounded-2xl border border-border bg-surface-2 px-4 py-4">
                <div className="text-sm font-semibold text-text">{selectedHosting.label}</div>
                <p className="mt-2 text-sm text-text-muted leading-6">
                  Mode d’accès sélectionné : {selectedAccess.label}
                </p>
              </div>

              {selectedAccess.id === "ssh" ? (
                <div className="grid gap-4 md:grid-cols-2">
                  <Input label="Hôte SSH" placeholder="ssh.votre-hebergeur.com" />
                  <Input label="Port" placeholder="22" />
                  <Input label="Utilisateur" placeholder="deploy ou root" />
                  <Input label="Chemin du projet" placeholder="/var/www/mon-site" />
                  <Input label="Mot de passe ou clé privée" placeholder="Clé privée ou accès SSH" />
                  <Input label="Commande sudo (optionnel)" placeholder="sudo -S" />
                </div>
              ) : null}

              {selectedAccess.id === "sftp" ? (
                <div className="grid gap-4 md:grid-cols-2">
                  <Input label="Hôte SFTP / FTP" placeholder="ftp.votre-hebergeur.com" />
                  <Input label="Port" placeholder="21 ou 22" />
                  <Input label="Utilisateur" placeholder="Identifiant FTP" />
                  <Input label="Mot de passe" placeholder="Mot de passe FTP" />
                  <Input label="Dossier du site" placeholder="/www ou /htdocs/mon-site" />
                  <Input label="Framework connu (optionnel)" placeholder="Laravel, Symfony, WordPress..." />
                </div>
              ) : null}

              {selectedAccess.id === "api" ? (
                <div className="grid gap-4 md:grid-cols-2">
                  <Input label="Nom de la plateforme" placeholder="Vercel, Plesk, cPanel..." />
                  <Input label="Jeton API" placeholder="Token d’accès API" />
                  <Input label="Projet / Site ID" placeholder="Identifiant du projet" />
                  <Input label="Équipe / Compte" placeholder="Nom du compte ou de l’équipe" />
                  <div className="md:col-span-2 space-y-1.5">
                    <label className="block text-sm font-medium text-text-muted">Notes d’accès</label>
                    <textarea
                      rows={4}
                      placeholder="Ajoutez ici les informations utiles pour que PraeviSEO retrouve le bon projet ou le bon hébergement."
                      className="flex w-full rounded-lg bg-surface-2 border border-border px-3 py-2 text-sm text-text placeholder:text-text-subtle transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-brand focus:border-brand/50"
                    />
                  </div>
                </div>
              ) : null}

              <div className="rounded-2xl border border-brand/20 bg-brand-muted px-4 py-4 text-sm text-text-muted leading-6">
                Cette étape prépare la vraie installation distante assistée. Si vous n’avez pas encore ces accès,
                vous pourrez toujours revenir ici plus tard ou utiliser l’installateur officiel en dessous.
              </div>

              <div className="flex flex-wrap gap-3">
                <Button size="lg">Préparer l’installation distante</Button>
                <Button type="button" variant="secondary" onClick={() => setShowAccessStep(false)}>
                  Fermer cette étape
                </Button>
              </div>
            </CardContent>
          </Card>
        ) : null}
      </div>
    </div>
  );
}
