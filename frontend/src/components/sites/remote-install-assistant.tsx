"use client";

import { useActionState, useEffect, useMemo, useRef, useState } from "react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Progress } from "@/components/ui/progress";
import type { RemoteInstallActionState } from "@/app/(app)/sites/[siteId]/connect/actions";
import type { InstallationReadinessReport, PraeviseoSite } from "@/lib/praeviseo-api";
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
  siteId: string;
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

const ACTIVATION_RESULTS = [
  "Le site peut être recrawlé automatiquement après chaque action importante.",
  "Les réécritures SEO et les enrichissements utiles peuvent être poussés sans intervention manuelle lourde.",
  "Le maillage interne et les futures publications peuvent être suivis depuis PraeviSEO.",
  "Le monitoring continu garde l’historique des actions et des prochaines optimisations à ouvrir.",
] as const;

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

function stepLabel(step: string | null): string {
  return (
    {
      pending: "Installation en préparation",
      connecting_to_server: "Connexion au serveur",
      detecting_environment: "Détection de l’environnement",
      package_installed: "Package PraeviSEO installé",
      installing_praeviseo: "Installation de PraeviSEO",
      laravel_connected: "Configuration Laravel",
      symfony_connected: "Configuration Symfony",
      configuring_site: "Configuration du site",
      laravel_activated: "Activation Laravel",
      symfony_activated: "Activation Symfony",
      activating_monitoring: "Activation du monitoring",
      completed: "PraeviSEO actif",
      failed: "Installation échouée",
    }[step ?? ""] ?? "Préparation de l’installation"
  );
}

function statusVariant(status: string): "default" | "success" | "warning" | "danger" {
  if (status === "completed" || status === "connected") {
    return "success";
  }

  if (status === "failed") {
    return "danger";
  }

  if (isInstallationInProgress(status)) {
    return "warning";
  }

  return "default";
}

export function RemoteInstallAssistant({
  siteId,
  site,
  submitAction,
  initialState,
}: RemoteInstallAssistantProps) {
  const [state, formAction, isPending] = useActionState(submitAction, initialState);
  const accessStepRef = useRef<HTMLDivElement | null>(null);
  const [liveInstallation, setLiveInstallation] = useState(site.installation);
  const [hostingId, setHostingId] = useState<string>(site.installation.hosting_provider ?? "vps_linux");
  const [accessId, setAccessId] = useState<AccessOption["id"]>(
    (site.installation.access_method as AccessOption["id"] | null) ?? "ssh"
  );
  const [showAccessStep, setShowAccessStep] = useState<boolean>(isInstallationInProgress(site.installation.status));
  const [latestReport, setLatestReport] = useState<InstallationReadinessReport | null>(site.installation.readiness_report);

  const selectedHosting = useMemo(
    () => HOSTING_OPTIONS.find((option) => option.id === hostingId) ?? HOSTING_OPTIONS[0],
    [hostingId]
  );

  const selectedAccess = useMemo(
    () => ACCESS_OPTIONS.find((option) => option.id === accessId) ?? ACCESS_OPTIONS[0],
    [accessId]
  );

  const installationRequested =
    ["connecting", "detecting_environment", "installing", "configuring", "activating", "completed"].includes(liveInstallation.status) ||
    (state.phase === "install" && state.status === "success");
  const installationFailed = liveInstallation.status === "failed";
  const accessesSaved =
    liveInstallation.status === "pending" ||
    state.phase !== "idle" ||
    installationRequested ||
    installationFailed;
  const progressInstallation = installationFailed
    ? {
        ...liveInstallation,
        status: "not_started",
        current_step: null,
        progress: 0,
      }
    : liveInstallation;
  const report = state.report ?? latestReport;
  const reportReady = report?.status === "ready";
  const hasBlockers = (report?.blockers?.length ?? 0) > 0;
  const diagnosticCompleted = state.phase === "diagnostic" && report !== null;

  const valueFor = (field: string) => state.values[field] ?? "";

  useEffect(() => {
    setLiveInstallation(site.installation);
    setLatestReport(site.installation.readiness_report);
  }, [site.installation]);

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

    if (state.report) {
      setLatestReport(state.report);
    }
  }, [state]);

  useEffect(() => {
    if (!isInstallationInProgress(liveInstallation.status)) {
      return;
    }

    let cancelled = false;

    const poll = async () => {
      try {
        const response = await fetch(`/api/sites/${siteId}/installation-status`, {
          cache: "no-store",
        });

        if (!response.ok) {
          return;
        }

        const payload = (await response.json()) as {
          installation?: PraeviseoSite["installation"];
        };

        if (!cancelled && payload.installation) {
          setLiveInstallation(payload.installation);
        }
      } catch {
        // Silent retry on next tick.
      }
    };

    void poll();
    const intervalId = window.setInterval(() => {
      void poll();
    }, 2000);

    return () => {
      cancelled = true;
      window.clearInterval(intervalId);
    };
  }, [liveInstallation.status, siteId]);

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
            <CardTitle>3. PraeviSEO commence par diagnostiquer</CardTitle>
            <CardDescription>
              Une fois l’accès fourni, PraeviSEO analyse d’abord votre environnement, explique ce qui manque puis seulement après lance l’installation.
            </CardDescription>
          </CardHeader>
          <CardContent className="space-y-3 text-sm text-text-muted">
            {[
              "Détection automatique de Laravel, Symfony ou WordPress",
              "Rapport clair sur les points validés, les warnings et les bloquants",
              "Installation et activation uniquement quand le diagnostic est prêt",
            ].map((item) => (
              <div key={item} className="flex items-start gap-2">
                <CheckCircle2 className="w-4 h-4 text-[hsl(var(--success))] shrink-0 mt-0.5" />
                <span>{item}</span>
              </div>
            ))}
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>4. Ce qui devient actif ensuite</CardTitle>
            <CardDescription>
              Cette installation ouvre la partie payante qui agit ensuite directement sur le site client.
            </CardDescription>
          </CardHeader>
          <CardContent className="space-y-3 text-sm text-text-muted">
            {ACTIVATION_RESULTS.map((item) => (
              <div key={item} className="flex items-start gap-2">
                <HardDrive className="mt-0.5 h-4 w-4 shrink-0 text-[hsl(var(--brand))]" />
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
              Le client ne manipule ni terminal, ni Composer, ni fichiers serveur. PraeviSEO analyse, explique, corrige ce qu il peut, puis active le site.
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
                Assistant d installation
              </div>
              <p className="mt-2 text-sm leading-6 text-text-muted">
                Le moteur et le bridge restent identiques. Ce parcours premium simplifie surtout la connexion, le diagnostic et l activation.
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

        {installationRequested && !installationFailed ? (
          <Card>
            <CardHeader>
              <CardTitle>Installation et activation</CardTitle>
              <CardDescription>
                Le diagnostic est terminé. PraeviSEO suit maintenant l installation et l activation du site en temps réel.
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="flex items-center justify-between gap-3">
                <Badge variant={statusVariant(progressInstallation.status)}>
                  {stepLabel(progressInstallation.current_step)}
                </Badge>
                <span className="text-sm text-text-muted">{progressInstallation.progress}%</span>
              </div>
              <Progress
                value={progressInstallation.progress}
                variant={statusVariant(progressInstallation.status)}
                size="lg"
              />
              <div className="rounded-2xl border border-border bg-surface-2 px-4 py-4 text-sm text-text-muted leading-6">
                {progressInstallation.logs.at(-1)?.message ||
                    "PraeviSEO installe maintenant la couche premium et prépare l activation du site."}
              </div>
              {progressInstallation.logs.length > 0 ? (
                <div className="space-y-2">
                  {progressInstallation.logs.slice(-5).reverse().map((log) => (
                    <div key={`${log.at}-${log.step}`} className="rounded-xl border border-border bg-surface-2 px-3 py-3">
                      <div className="text-xs font-semibold uppercase tracking-[0.14em] text-text-subtle">
                        {stepLabel(log.step)}
                      </div>
                      <div className="mt-1 text-sm text-text">{log.message}</div>
                    </div>
                  ))}
                </div>
              ) : null}
            </CardContent>
          </Card>
        ) : null}

        {report ? (
          <Card>
            <CardHeader>
              <CardTitle>PraeviSEO Installation Doctor</CardTitle>
              <CardDescription>
                Diagnostic → rapport → correction → installation → activation. PraeviSEO commence toujours par vous expliquer ce qui manque.
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="rounded-2xl border border-border bg-surface-2 px-4 py-4">
                <div className="flex items-center justify-between gap-3">
                  <div>
                    <div className="text-sm font-semibold text-text">Diagnostic d installation</div>
                    <p className="mt-1 text-sm leading-6 text-text-muted">{report.summary}</p>
                  </div>
                  <Badge variant={reportReady ? "success" : "warning"}>{report.score}%</Badge>
                </div>
              </div>

              {diagnosticCompleted ? (
                <div className="rounded-2xl border border-success/30 bg-success/10 px-4 py-4 text-sm text-text">
                  <div className="font-semibold">Diagnostic terminé</div>
                  <p className="mt-2 leading-6 text-text-muted">
                    PraeviSEO a terminé l analyse de votre environnement. L installation réelle peut maintenant démarrer si tous les bloquants sont levés.
                  </p>
                </div>
              ) : null}

              <div className="grid gap-4 xl:grid-cols-2">
                <div className="rounded-2xl border border-border bg-surface-2 px-4 py-4">
                  <div className="text-xs font-semibold uppercase tracking-[0.16em] text-text-subtle">Validé</div>
                  <div className="mt-3 space-y-3">
                    {report.validated.length > 0 ? report.validated.map((item) => (
                      <div key={item.key}>
                        <div className="text-sm font-semibold text-text">{item.label}</div>
                        <p className="mt-1 text-sm leading-6 text-text-muted">{item.detail}</p>
                      </div>
                    )) : <p className="text-sm leading-6 text-text-muted">Aucun point validé pour le moment.</p>}
                  </div>
                </div>

                <div className="rounded-2xl border border-border bg-surface-2 px-4 py-4">
                  <div className="text-xs font-semibold uppercase tracking-[0.16em] text-text-subtle">Bloquants</div>
                  <div className="mt-3 space-y-3">
                    {report.blockers.length > 0 ? report.blockers.map((item) => (
                      <div key={item.key}>
                        <div className="flex items-center gap-2">
                          <div className="text-sm font-semibold text-text">{item.label}</div>
                          {item.autofixable ? <Badge variant="secondary">Corrigible auto</Badge> : null}
                        </div>
                        <p className="mt-1 text-sm leading-6 text-text-muted">{item.detail}</p>
                      </div>
                    )) : <p className="text-sm leading-6 text-text-muted">Aucun blocage détecté. PraeviSEO peut installer.</p>}
                  </div>
                </div>
              </div>

              {(report.warnings.length > 0 || report.autofixable.length > 0 || report.manual_actions.length > 0) ? (
                <div className="grid gap-4 xl:grid-cols-3">
                  <div className="rounded-2xl border border-border bg-surface-2 px-4 py-4">
                    <div className="text-xs font-semibold uppercase tracking-[0.16em] text-text-subtle">Warnings</div>
                    <div className="mt-3 space-y-3">
                      {report.warnings.length > 0 ? report.warnings.map((item) => (
                        <div key={item.key}>
                          <div className="text-sm font-semibold text-text">{item.label}</div>
                          <p className="mt-1 text-sm leading-6 text-text-muted">{item.detail}</p>
                        </div>
                      )) : <p className="text-sm leading-6 text-text-muted">Aucun warning détecté.</p>}
                    </div>
                  </div>
                  <div className="rounded-2xl border border-border bg-surface-2 px-4 py-4">
                    <div className="text-xs font-semibold uppercase tracking-[0.16em] text-text-subtle">Corrections auto prévues</div>
                    <div className="mt-3 space-y-3">
                      {report.autofixable.length > 0 ? report.autofixable.map((item) => (
                        <div key={item.key}>
                          <div className="text-sm font-semibold text-text">{item.label}</div>
                          <p className="mt-1 text-sm leading-6 text-text-muted">{item.detail}</p>
                        </div>
                      )) : <p className="text-sm leading-6 text-text-muted">Aucune correction automatique prévue pour ce diagnostic.</p>}
                    </div>
                  </div>
                  <div className="rounded-2xl border border-border bg-surface-2 px-4 py-4">
                    <div className="text-xs font-semibold uppercase tracking-[0.16em] text-text-subtle">Actions humaines</div>
                    <div className="mt-3 space-y-3">
                      {report.manual_actions.length > 0 ? report.manual_actions.map((item) => (
                        <div key={item.key}>
                          <div className="text-sm font-semibold text-text">{item.label}</div>
                          <p className="mt-1 text-sm leading-6 text-text-muted">{item.detail}</p>
                        </div>
                      )) : <p className="text-sm leading-6 text-text-muted">Aucune action humaine supplémentaire n est demandée.</p>}
                    </div>
                  </div>
                </div>
              ) : null}
            </CardContent>
          </Card>
        ) : null}

        <Card>
          <CardHeader>
            <CardTitle>Ce que le client doit ressentir</CardTitle>
          </CardHeader>
          <CardContent className="space-y-3 text-sm text-text-muted">
            {[
              "Je choisis où mon site est hébergé",
              "Je donne un accès sécurisé si nécessaire",
              "PraeviSEO m explique d abord ce qui manque",
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
              {state.phase === "install" && installationRequested && !installationFailed ? (
                <div className="rounded-2xl border border-success/30 bg-success/10 px-4 py-4 text-sm text-text">
                  <div className="font-semibold">Installation et activation en cours</div>
                  <p className="mt-2 leading-6 text-text-muted">
                    {state.message ||
                      liveInstallation.logs.at(-1)?.message ||
                      "PraeviSEO installe maintenant la couche premium puis activera le site dès que l environnement sera prêt."}
                  </p>
                </div>
              ) : null}

              {accessesSaved && !installationRequested && !installationFailed ? (
                <div className="rounded-2xl border border-success/30 bg-success/10 px-4 py-4 text-sm text-text">
                  <div className="font-semibold">{report ? "Diagnostic disponible" : "Accès enregistrés"}</div>
                  <p className="mt-2 leading-6 text-text-muted">
                    {report
                      ? "PraeviSEO a terminé l analyse de préparation. Lisez le rapport ci-dessous avant de lancer l installation."
                      : "PraeviSEO a bien enregistré vos accès. La prochaine étape est le diagnostic de préparation, pas encore l installation."}
                  </p>
                </div>
              ) : null}

              {installationFailed ? (
                <div className="rounded-2xl border border-danger/30 bg-danger/10 px-4 py-4 text-sm text-danger">
                  <div className="font-semibold text-text">Dernière tentative interrompue</div>
                  <p className="mt-2 leading-6">
                    {liveInstallation.error_message ||
                      liveInstallation.logs.at(-1)?.message ||
                      "PraeviSEO a bien enregistré vos accès, mais la tentative précédente n a pas abouti. Corrigez les champs ci-dessous puis relancez d abord le diagnostic."}
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
                  Cette étape commence par le diagnostic de votre environnement. PraeviSEO n essaiera pas d installer quoi que ce soit tant que les bloquants ne sont pas levés.
                </div>

                <div className="flex flex-wrap gap-3">
                  <Button size="lg" type="submit" name="intent" value="precheck" loading={isPending}>
                    Lancer le diagnostic d’installation
                  </Button>
                  <Button size="lg" type="submit" name="intent" value="install" loading={isPending} disabled={!reportReady || hasBlockers}>
                    Installer PraeviSEO
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
