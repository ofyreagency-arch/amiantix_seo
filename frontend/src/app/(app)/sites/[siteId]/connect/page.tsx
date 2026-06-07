import { Download, ShieldCheck, Stethoscope, TerminalSquare, Wrench } from "lucide-react";
import { Topbar } from "@/components/layout/topbar";
import { SiteAccessState } from "@/components/sites/site-access-state";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { RemoteInstallAssistant } from "@/components/sites/remote-install-assistant";
import {
  formatPraeviseoStatus,
  formatSitePlatform,
  getInstallerUrl,
  getPraeviseoInstallDetail,
  getPraeviseoInstallLabel,
  getSite,
  isInstallationInProgress,
} from "@/lib/praeviseo-api";
import { submitRemoteInstallAction } from "./actions";

interface SiteConnectPageProps {
  params: Promise<{ siteId: string }>;
}

const INSTALLERS = [
  {
    label: "Windows",
    filename: "praeviseo-install.ps1" as const,
    launch: ".\\praeviseo-install.ps1",
    description: "Téléchargez le script PowerShell officiel puis lancez-le sur le serveur ou le poste cible.",
  },
  {
    label: "Linux / Mac",
    filename: "praeviseo-install.sh" as const,
    launch: "bash praeviseo-install.sh",
    description: "Téléchargez le script shell officiel puis lancez-le sur la machine qui héberge le site.",
  },
] as const;

const ACTIVATION_STEPS = [
  {
    title: "1. Connexion du site",
    detail: "PraeviSEO récupère uniquement les accès utiles pour parler au serveur ou à l’environnement du site.",
  },
  {
    title: "2. Diagnostic de préparation",
    detail: "Le Doctor vérifie le framework, PHP, Composer, la configuration du bridge et ce qui manque réellement.",
  },
  {
    title: "3. Installation et activation",
    detail: "Seulement si le diagnostic est propre, PraeviSEO installe le bridge, connecte le site et active la couche premium.",
  },
  {
    title: "4. Passage au copilote SEO",
    detail: "Une fois activé, le site se pilote depuis le cockpit SEO et les automatisations, pas depuis cet écran technique.",
  },
] as const;

type SystemTruthRow = {
  feature: string;
  state: "OK" | "KO" | "Partiel" | "À vérifier";
  test: string;
  result: string;
};

function truthTone(state: SystemTruthRow["state"]): "default" | "secondary" | "warning" | "danger" {
  switch (state) {
    case "OK":
      return "default";
    case "Partiel":
    case "À vérifier":
      return "warning";
    default:
      return "danger";
  }
}

export default async function SiteConnectPage({ params }: SiteConnectPageProps) {
  const { siteId } = await params;
  const site = await getSite(siteId);

  if (!site) {
    return <SiteAccessState siteId={siteId} areaLabel="la santé technique" />;
  }

  const installationLabel = getPraeviseoInstallLabel(site);
  const installationDetail = getPraeviseoInstallDetail(site);
  const installationPending = isInstallationInProgress(site.installation.status) || site.installation.status === "completed";
  const automationPath = `/sites/${site.site_id}/automation`;
  const cockpitPath = `/sites/${site.site_id}`;
  const readyForAutomation = site.publication_bridge_status === "connected";
  const publicationOperational = site.publication_target.engine_actionable;
  const publicationStatus = site.action_statuses.publication;
  const generationStatus = site.action_statuses.generation;
  const rewriteStatus = site.action_statuses.rewrite;
  const linkingStatus = site.action_statuses.linking;
  const imageStatus = site.action_statuses.images;
  const crawlStatus = site.action_statuses.crawl;
  const truthRows: SystemTruthRow[] = [
    {
      feature: "GSC",
      state: site.readiness.gsc_connected ? "OK" : "KO",
      test: "Connexion Search Console",
      result: site.readiness.gsc_connected
        ? "Lecture Google connectée."
        : "Aucune lecture Google confirmée.",
    },
    {
      feature: "Crawl",
      state: site.last_successful_crawl ? "OK" : crawlStatus.state === "failed" ? "KO" : "À vérifier",
      test: "Dernier crawl observé",
      result: site.last_successful_crawl
        ? `${site.last_successful_crawl.crawled_url_count} page(s) relues, ${site.last_successful_crawl.issues_count} point(s) remonté(s).`
        : crawlStatus.error || crawlStatus.detail || "Aucune relecture complète confirmée.",
    },
    {
      feature: "Génération article",
      state: generationStatus.state === "completed" ? "Partiel" : generationStatus.state === "failed" ? "KO" : generationStatus.state === "running" ? "À vérifier" : "Partiel",
      test: "Dernière action génération",
      result: generationStatus.error || generationStatus.detail || "Le moteur sait ouvrir un brouillon, mais sa qualité éditoriale reste à confirmer.",
    },
    {
      feature: "Publication",
      state: publicationOperational ? "Partiel" : publicationStatus.state === "failed" ? "KO" : "À vérifier",
      test: "Bridge + cible live",
      result: publicationStatus.error || site.publication_target.detail,
    },
    {
      feature: "Images",
      state: imageStatus.state === "completed" ? "Partiel" : imageStatus.state === "failed" ? "KO" : "À vérifier",
      test: "Dernière action image",
      result: imageStatus.error || imageStatus.detail || "Aucune image validée de bout en bout n’est encore confirmée ici.",
    },
    {
      feature: "Réécriture",
      state: rewriteStatus.state === "completed" ? "Partiel" : rewriteStatus.state === "failed" ? "KO" : "À vérifier",
      test: "Dernière action réécriture",
      result: rewriteStatus.error || rewriteStatus.detail || "Aucune réécriture terminée n’est encore confirmée ici.",
    },
    {
      feature: "Maillage",
      state: linkingStatus.state === "completed" ? "Partiel" : linkingStatus.state === "failed" ? "KO" : "À vérifier",
      test: "Dernière action maillage",
      result: linkingStatus.error || linkingStatus.detail || "Aucun renfort de maillage terminé n’est encore confirmé ici.",
    },
  ];
  const blockingItems = [
    crawlStatus.error ? { label: "Erreur crawl", detail: crawlStatus.error } : null,
    generationStatus.error ? { label: "Erreur génération", detail: generationStatus.error } : null,
    rewriteStatus.error ? { label: "Erreur réécriture", detail: rewriteStatus.error } : null,
    linkingStatus.error ? { label: "Erreur maillage", detail: linkingStatus.error } : null,
    imageStatus.error ? { label: "Erreur image", detail: imageStatus.error } : null,
    publicationStatus.error ? { label: "Erreur publication", detail: publicationStatus.error } : null,
    !publicationOperational ? { label: "Publication live", detail: site.publication_target.detail } : null,
  ].filter((item): item is { label: string; detail: string } => Boolean(item));

  return (
    <div className="min-h-screen">
      <Topbar
        title={`Santé technique · ${site.name}`}
        subtitle="Connexion, Doctor, activation et maintenance technique du site."
        actions={
          <div className="flex items-center gap-2">
            <Button href={cockpitPath} variant="secondary" size="sm">
              Retour au cockpit SEO
            </Button>
            {readyForAutomation ? (
              <Button href={automationPath} size="sm">
                Voir les automatisations
              </Button>
            ) : null}
          </div>
        }
      />

      <div className="p-6 space-y-6">
        <div className="rounded-3xl border border-brand/20 bg-brand-muted px-6 py-6">
          <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div className="max-w-3xl">
              <Badge variant="brand-subtle" className="mb-3">
                {formatSitePlatform(site.publication_mode)}
              </Badge>
              <h1 className="text-2xl font-bold tracking-tight text-text">Connexion et activation technique du site</h1>
              <p className="mt-2 text-sm leading-7 text-text-muted">
                {installationDetail}
              </p>
              <p className="mt-3 text-sm leading-7 text-text-muted">
                Cette page sert uniquement à connecter le site, lancer le Doctor, corriger les points techniques puis activer
                PraeviSEO. Une fois le site prêt, le suivi quotidien se fait depuis le cockpit SEO et la page Automatisations.
              </p>
            </div>

            <div className="min-w-[280px] rounded-2xl border border-border bg-surface px-5 py-4">
              <div className="text-sm font-semibold text-text">{installationLabel}</div>
              <div className="mt-2 text-sm leading-6 text-text-muted">
                {installationPending
                  ? "Le site a une tentative d’activation en cours ou récemment terminée. Le Doctor reste la référence avant toute relance."
                  : "Aucune installation n’est lancée tant que le diagnostic de préparation n’est pas propre."}
              </div>
              <div className="mt-3 flex flex-wrap gap-2">
                <Badge variant={site.publication_bridge_status === "connected" ? "default" : "secondary"}>
                  {formatPraeviseoStatus(site.publication_bridge_status)}
                </Badge>
                <Badge variant="secondary">
                  Doctor {site.installation_doctor.status === "ready" ? "prêt" : site.installation_doctor.status}
                </Badge>
              </div>
            </div>
          </div>
        </div>

        <Card>
          <CardHeader>
            <CardTitle>Vérité du système</CardTitle>
            <CardDescription>
              Ce tableau dit seulement ce qui est réellement confirmé aujourd’hui, sans supposer que le reste fonctionne.
            </CardDescription>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="overflow-hidden rounded-2xl border border-border">
              <table className="w-full text-left">
                <thead className="bg-surface-2 text-xs uppercase tracking-[0.18em] text-text-subtle">
                  <tr>
                    <th className="px-4 py-3 font-medium">Fonction</th>
                    <th className="px-4 py-3 font-medium">État</th>
                    <th className="px-4 py-3 font-medium">Test réel</th>
                    <th className="px-4 py-3 font-medium">Résultat</th>
                  </tr>
                </thead>
                <tbody>
                  {truthRows.map((row) => (
                    <tr key={row.feature} className="border-t border-border align-top">
                      <td className="px-4 py-4 text-sm font-semibold text-text">{row.feature}</td>
                      <td className="px-4 py-4">
                        <Badge variant={truthTone(row.state)}>{row.state}</Badge>
                      </td>
                      <td className="px-4 py-4 text-sm text-text-muted">{row.test}</td>
                      <td className="px-4 py-4 text-sm leading-6 text-text-muted">{row.result}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>

            <div className="rounded-2xl border border-border bg-surface-2 px-4 py-4">
              <div className="text-sm font-semibold text-text">Ce qui casse maintenant</div>
              <div className="mt-3 space-y-3">
                {blockingItems.length > 0 ? (
                  blockingItems.map((item) => (
                    <div key={item.label} className="rounded-xl border border-danger/20 bg-danger/10 px-3 py-3">
                      <div className="text-sm font-medium text-text">{item.label}</div>
                      <p className="mt-1 text-sm leading-6 text-text-muted">{item.detail}</p>
                    </div>
                  ))
                ) : (
                  <p className="text-sm leading-6 text-text-muted">
                    Aucun blocage franc n’est remonté sur cette lecture. Les zones en “Partiel” demandent encore une validation réelle, pas une nouvelle feature.
                  </p>
                )}
              </div>
            </div>
          </CardContent>
        </Card>

        <div className="grid gap-4 xl:grid-cols-4">
          {[
            {
              icon: ShieldCheck,
              title: "Connexion du site",
              text: "Accès serveur, connexion sécurisée et détection du bon projet avant toute action intrusive.",
            },
            {
              icon: Stethoscope,
              title: "Doctor",
              text: "Vérifie les prérequis, explique les blocages et prépare les corrections automatiques quand elles sont sûres.",
            },
            {
              icon: Wrench,
              title: "Activation",
              text: "Installe le bridge, valide la connexion et prépare la couche premium seulement quand tout est prêt.",
            },
            {
              icon: TerminalSquare,
              title: "Maintenance technique",
              text: "Garde cette page pour les accès, les relances, les diagnostics et l’historique d’installation.",
            },
          ].map((item) => {
            const Icon = item.icon;

            return (
              <Card key={item.title}>
                <CardContent className="pt-5">
                  <div className="flex h-11 w-11 items-center justify-center rounded-2xl bg-brand-subtle">
                    <Icon className="h-5 w-5 text-[hsl(var(--brand))]" />
                  </div>
                  <div className="mt-4 text-base font-semibold text-text">{item.title}</div>
                  <p className="mt-2 text-sm leading-6 text-text-muted">{item.text}</p>
                </CardContent>
              </Card>
            );
          })}
        </div>

        <Card>
          <CardHeader>
            <CardTitle>Parcours technique</CardTitle>
            <CardDescription>
              L’objectif ici n’est pas de piloter le SEO quotidien, mais de rendre le site installable et activable sans friction.
            </CardDescription>
          </CardHeader>
          <CardContent className="grid gap-4 xl:grid-cols-4">
            {ACTIVATION_STEPS.map((step) => (
              <div key={step.title} className="rounded-2xl border border-border bg-surface-2 px-4 py-4">
                <div className="text-sm font-semibold text-text">{step.title}</div>
                <p className="mt-2 text-sm leading-6 text-text-muted">{step.detail}</p>
              </div>
            ))}
          </CardContent>
        </Card>

        <RemoteInstallAssistant
          siteId={site.site_id}
          site={site}
          submitAction={submitRemoteInstallAction.bind(null, site.site_id)}
          initialState={{ status: "idle", phase: "idle", message: "", values: {}, report: site.installation_doctor.last_report }}
        />

        <Card>
          <CardHeader>
            <CardTitle>Scripts officiels</CardTitle>
            <CardDescription>
              Si vous préférez lancer l’installation depuis le serveur cible, gardez ici les scripts officiels comme méthode technique secondaire.
            </CardDescription>
          </CardHeader>
          <CardContent className="grid gap-6 lg:grid-cols-2">
            {INSTALLERS.map((installer) => (
              <div key={installer.filename} className="rounded-2xl border border-border bg-surface-2 px-4 py-4">
                <div className="flex items-center justify-between gap-3">
                  <div>
                    <div className="text-sm font-semibold text-text">{installer.label}</div>
                    <p className="mt-1 text-sm leading-6 text-text-muted">{installer.description}</p>
                  </div>
                  <Badge variant="secondary">{installer.filename}</Badge>
                </div>

                <div className="mt-4 flex flex-col gap-3">
                  <Button href={getInstallerUrl(installer.filename)} className="w-full" external>
                    <Download className="w-4 h-4" />
                    Télécharger le script
                  </Button>
                  <div className="rounded-xl border border-border bg-surface px-3 py-3">
                    <div className="text-[11px] font-semibold uppercase tracking-[0.18em] text-text-subtle">Commande</div>
                    <code className="mt-2 block text-sm font-semibold text-text">{installer.launch}</code>
                  </div>
                </div>
              </div>
            ))}
          </CardContent>
        </Card>
      </div>
    </div>
  );
}
