"use client";

import { useActionState, useEffect, useMemo, useRef, useState } from "react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import type { RemoteInstallActionState } from "@/app/(app)/sites/[siteId]/connect/actions";
import type { PraeviseoSite } from "@/lib/praeviseo-api";
import { CheckCircle2, Cloud, HardDrive, KeyRound, LockKeyhole, ServerCog, WandSparkles } from "lucide-react";

type HostingOption = {
  id: string;
  label: string;
  hint: string;
  category: "vps" | "shared" | "platform";
};

type AccessOption = {
  id: "ssh" | "sftp" | "api";
  label: string;
  hint: string;
};

type RemoteInstallAssistantProps = {
  site: Pick<PraeviseoSite, "installation" | "publication_bridge_status">;
  submitAction: (
    state: RemoteInstallActionState,
    formData: FormData
  ) => Promise<RemoteInstallActionState>;
  initialState: RemoteInstallActionState;
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

function isInstallationInProgress(status: string): boolean {
  return ["requested", "pending", "connecting", "detecting_environment", "installing", "configuring", "activating"].includes(
    status
  );
}

export function RemoteInstallAssistant({
  site,
  submitAction,
  initialState,
}: RemoteInstallAssistantProps) {
  const [state, formAction, isPending] = useActionState(submitAction, initialState);
  const accessStepRef = useRef<HTMLDivElement | null>(null);
  const [hostingId, setHostingId] = useState<string>(site.installation.hosting_provider ?? "vps_linux");
  const [accessId, setAccessId] = useState<AccessOption["id"]>(
    (site.installation.access_method as AccessOption["id"] | null) ?? "ssh"
  );
  const [showAccessStep, setShowAccessStep] = useState<boolean>(isInstallationInProgress(site.installation.status));

  const selectedHosting = useMemo(
    () => HOSTING_OPTIONS.find((option) => option.id === hostingId) ?? HOSTING_OPTIONS[0],
    [hostingId]
  );

  const selectedAccess = useMemo(
    () => ACCESS_OPTIONS.find((option) => option.id === accessId) ?? ACCESS_OPTIONS[0],
    [accessId]
  );

  const installationRequested =
    isInstallationInProgress(site.installation.status) ||
    site.publication_bridge_status === "requested" ||
    state.status === "success";

  const valueFor = (field: string) => state.values[field] ?? "";

  useEffect(() => {
    if (state.values.hosting_provider) {
      setHostingId(state.values.hosting_provider);
    }

    if (state.values.access_method && ["ssh", "sftp", "api"].includes(state.values.access_method)) {
      setAccessId(state.values.access_method as AccessOption["id"]);
    }

    if (state.status !== "idle") {
      setShowAccessStep(true);

      requestAnimationFrame(() => {
        accessStepRef.current?.scrollIntoView({ behavior: "smooth", block: "start" });
      });
    }
  }, [state]);

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
              {installationRequested ? (
                <div className="rounded-2xl border border-success/30 bg-success/10 px-4 py-4 text-sm text-text">
                  <div className="font-semibold">Installation distante déjà en préparation</div>
                  <p className="mt-2 leading-6 text-text-muted">
                    {state.message ||
                      "Vos accès sont bien enregistrés. PraeviSEO prépare maintenant automatiquement l’activation distante du site."}
                  </p>
                </div>
              ) : null}

              {state.status === "error" ? (
                <div className="rounded-2xl border border-danger/30 bg-danger/10 px-4 py-4 text-sm text-danger">
                  {state.message}
                </div>
              ) : null}

              <form action={formAction} className="space-y-4">
                <input type="hidden" name="hosting_provider" value={selectedHosting.id} />
                <input type="hidden" name="access_method" value={selectedAccess.id} />

                <div className="rounded-2xl border border-border bg-surface-2 px-4 py-4">
                  <div className="text-sm font-semibold text-text">{selectedHosting.label}</div>
                  <p className="mt-2 text-sm text-text-muted leading-6">
                    Mode d’accès sélectionné : {selectedAccess.label}
                  </p>
                </div>

                {selectedAccess.id === "ssh" ? (
                  <div className="grid gap-4 md:grid-cols-2">
                    <Input name="ssh_host" label="Hôte SSH" placeholder="ssh.votre-hebergeur.com" defaultValue={valueFor("ssh_host")} />
                    <Input name="ssh_port" label="Port" placeholder="22" defaultValue={valueFor("ssh_port")} />
                    <Input name="ssh_username" label="Utilisateur" placeholder="deploy ou root" defaultValue={valueFor("ssh_username")} />
                    <Input
                      name="ssh_project_path"
                      label="Chemin du projet"
                      placeholder="/var/www/mon-site"
                      defaultValue={valueFor("ssh_project_path")}
                    />
                    <Input
                      name="ssh_secret"
                      type="password"
                      label="Mot de passe ou clé privée"
                      placeholder="Clé privée ou accès SSH"
                      defaultValue={valueFor("ssh_secret")}
                    />
                    <Input
                      name="ssh_sudo_command"
                      label="Commande sudo (optionnel)"
                      placeholder="sudo -S"
                      defaultValue={valueFor("ssh_sudo_command")}
                    />
                  </div>
                ) : null}

                {selectedAccess.id === "sftp" ? (
                  <div className="grid gap-4 md:grid-cols-2">
                    <Input name="sftp_host" label="Hôte SFTP / FTP" placeholder="ftp.votre-hebergeur.com" defaultValue={valueFor("sftp_host")} />
                    <Input name="sftp_port" label="Port" placeholder="21 ou 22" defaultValue={valueFor("sftp_port")} />
                    <Input name="sftp_username" label="Utilisateur" placeholder="Identifiant FTP" defaultValue={valueFor("sftp_username")} />
                    <Input
                      name="sftp_password"
                      type="password"
                      label="Mot de passe"
                      placeholder="Mot de passe FTP"
                      defaultValue={valueFor("sftp_password")}
                    />
                    <Input
                      name="sftp_project_path"
                      label="Dossier du site"
                      placeholder="/www ou /htdocs/mon-site"
                      defaultValue={valueFor("sftp_project_path")}
                    />
                    <Input
                      name="framework_hint"
                      label="Framework connu (optionnel)"
                      placeholder="Laravel, Symfony, WordPress..."
                      defaultValue={valueFor("framework_hint")}
                    />
                  </div>
                ) : null}

                {selectedAccess.id === "api" ? (
                  <div className="grid gap-4 md:grid-cols-2">
                    <Input
                      name="api_platform"
                      label="Nom de la plateforme"
                      placeholder="Vercel, Plesk, cPanel..."
                      defaultValue={valueFor("api_platform")}
                    />
                    <Input name="api_token" type="password" label="Jeton API" placeholder="Token d’accès API" defaultValue={valueFor("api_token")} />
                    <Input
                      name="api_project_id"
                      label="Projet / Site ID"
                      placeholder="Identifiant du projet"
                      defaultValue={valueFor("api_project_id")}
                    />
                    <Input
                      name="api_account_name"
                      label="Équipe / Compte"
                      placeholder="Nom du compte ou de l’équipe"
                      defaultValue={valueFor("api_account_name")}
                    />
                    <div className="md:col-span-2 space-y-1.5">
                      <label className="block text-sm font-medium text-text-muted">Notes d’accès</label>
                      <textarea
                        name="api_notes"
                        rows={4}
                        defaultValue={valueFor("api_notes")}
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
                  <Button size="lg" type="submit" loading={isPending}>
                    {installationRequested ? "Mettre à jour les accès" : "Préparer l’installation distante"}
                  </Button>
                  <Button type="button" variant="secondary" onClick={() => setShowAccessStep(false)}>
                    Fermer cette étape
                  </Button>
                </div>
              </form>
            </CardContent>
          </Card>
        ) : null}
      </div>
    </div>
  );
}
