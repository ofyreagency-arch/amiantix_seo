import { notFound } from "next/navigation";
import { Topbar } from "@/components/layout/topbar";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { RemoteInstallAssistant } from "@/components/sites/remote-install-assistant";
import {
  formatPraeviseoStatus,
  formatSitePlatform,
  getPublications,
  getInstallerUrl,
  getPraeviseoInstallDetail,
  getPraeviseoInstallLabel,
  getSite,
  isInstallationInProgress,
} from "@/lib/praeviseo-api";
import {
  launchPremiumCrawlAction,
  launchPremiumLinkingAction,
  launchPremiumPublicationAction,
  launchPremiumRewriteAction,
  submitRemoteInstallAction,
} from "./actions";
import { Bot, Download, FileSearch, ImagePlus, Link2, Monitor, Rocket, ShieldCheck, Sparkles } from "lucide-react";

interface SiteConnectPageProps {
  params: Promise<{ siteId: string }>;
}

const INSTALLERS = [
  {
    label: "Windows",
    filename: "praeviseo-install.ps1" as const,
    launch: ".\\praeviseo-install.ps1",
    description: "Téléchargez le script PowerShell officiel puis double-cliquez dessus.",
  },
  {
    label: "Linux / Mac",
    filename: "praeviseo-install.sh" as const,
    launch: "bash praeviseo-install.sh",
    description: "Téléchargez le script shell officiel puis lancez-le sur le serveur ou la machine cible.",
  },
];

const PREMIUM_MODULES = [
  {
    title: "Installation et connexion",
    description: "PraeviSEO s’installe sur le serveur, détecte votre environnement et active la connexion sécurisée au site.",
    items: ["Installation sur votre serveur", "Connexion sécurisée", "Mises à jour", "Multi-sites"],
  },
  {
    title: "Exécution SEO",
    description: "Une fois actif, PraeviSEO peut lancer et rejouer les optimisations directement sur le site client.",
    items: [
      "Crawl automatique",
      "Génération de pages SEO",
      "Réécriture SEO",
      "Maillage interne automatique",
      "Images SEO",
      "Publication automatique",
      "Re-crawl",
    ],
  },
  {
    title: "Supervision continue",
    description: "Le pack payant garde l’historique des actions, suit les retours Google et surveille les régressions.",
    items: ["Monitoring continu", "Historique complet", "Suivi des actions", "Contrôle des régressions"],
  },
] as const;

const EXECUTION_STEPS = [
  {
    icon: FileSearch,
    title: "1. Crawl du site",
    detail: "PraeviSEO relit le site, repère les pages existantes et valide où il peut agir sans risque.",
  },
  {
    icon: Sparkles,
    title: "2. Préparation des actions",
    detail: "Le moteur prépare les pages à enrichir, les réécritures utiles et les liens internes à ouvrir.",
  },
  {
    icon: Bot,
    title: "3. Exécution automatique",
    detail: "PraeviSEO publie, réécrit, relie les pages et relance les vérifications sans manipulation manuelle.",
  },
  {
    icon: Monitor,
    title: "4. Monitoring continu",
    detail: "Après l’exécution, PraeviSEO suit le résultat, détecte les progrès et prépare la prochaine action utile.",
  },
] as const;

export default async function SiteConnectPage({ params }: SiteConnectPageProps) {
  const { siteId } = await params;
  const site = await getSite(siteId);
  const publications = await getPublications();

  if (!site) {
    notFound();
  }

  const sitePublications = publications.items.filter((item) => item.site_id === site.site_id);
  const leadRisingPage = site.summary.top_rising_pages[0] ?? null;
  const leadRefresh = sitePublications.find((item) => item.latest_suggestion || item.observed_content) ?? null;
  const leadIndexationAlert = site.summary.indexation_alerts[0] ?? null;
  const livePublishedCount = sitePublications.filter((item) => item.published_live).length;
  const monitoredContentCount = sitePublications.filter((item) => item.observed_content).length;
  const executionCenter = [
    {
      title: "Crawl automatique",
      status:
        site.crawl?.status === "running"
          ? "En cours"
          : site.crawl?.status === "pending"
            ? "Planifié"
            : site.crawl?.status === "completed"
              ? "Terminé"
              : monitoredContentCount > 0
                ? "Déjà prêt"
                : "À ouvrir",
      detail:
        site.crawl?.status === "running"
          ? `PraeviSEO relit actuellement le site. ${site.crawl.crawled_url_count} page(s) ont déjà été parcourue(s) sur ${site.crawl.max_pages} maximum.`
          : site.crawl?.status === "pending"
            ? "Le prochain crawl premium a été demandé. PraeviSEO va relire le site pour préparer les prochaines actions automatiques."
            : site.crawl?.status === "completed"
              ? `Le dernier crawl premium a relu ${site.crawl.crawled_url_count} page(s) et remonté ${site.crawl.issues_count} point(s) à surveiller.`
              : monitoredContentCount > 0
                ? `${monitoredContentCount} page(s) sont déjà relues par PraeviSEO. Le pack payant pourra relancer automatiquement ce crawl.`
                : "Le premier crawl premium relira le site pour préparer les prochaines actions automatiques.",
    },
    {
      title: "Réécriture SEO",
      status: leadRefresh ? "Déjà ciblée" : "À préparer",
      detail: leadRefresh
        ? "PraeviSEO a déjà repéré un contenu à retravailler. L’automatisation pourra reprendre cette amélioration sans attente manuelle."
        : "La couche payante préparera les premières réécritures dès qu’un contenu utile sera détecté.",
    },
    {
      title: "Maillage interne",
      status: site.summary.observed_link_gap_pages.length > 0 ? "Déjà repéré" : "À ouvrir",
      detail:
        site.summary.observed_link_gap_pages.length > 0
          ? "Le site contient déjà des pages à mieux relier. Le pack payant pourra ouvrir ces liens automatiquement."
          : "Le maillage interne sera préparé dès que PraeviSEO aura assez de pages à relier proprement.",
    },
    {
      title: "Publication automatique",
      status: livePublishedCount > 0 ? "Déjà active" : site.publication_bridge_status === "connected" ? "Prête à démarrer" : "En attente d’activation",
      detail:
        livePublishedCount > 0
          ? `${livePublishedCount} contenu(s) sont déjà visibles. La couche payante pourra republier et mettre à jour ce qui doit bouger.`
          : site.publication_bridge_status === "connected"
            ? "Le site est prêt à recevoir les premières publications et mises à jour automatiques."
            : "La publication démarrera juste après l’activation complète de la connexion premium.",
    },
    {
      title: "Monitoring continu",
      status: site.summary.observed_site_health_score > 0 ? "Déjà en lecture" : "À lancer",
      detail:
        site.summary.observed_site_health_score > 0
          ? "PraeviSEO suit déjà la santé du site. Le pack payant ajoutera l’historique des actions et les relances automatiques."
          : "Le monitoring premium suivra les actions exécutées, les retours Google et les prochaines priorités utiles.",
    },
  ] as const;
  const executionReadiness = [
    {
      title: "Connexion au site",
      status: site.readiness.bridge_connected ? "Active" : "À terminer",
      detail: site.readiness.bridge_connected
        ? "PraeviSEO est déjà relié au site pour exécuter des actions avancées."
        : "La connexion premium doit encore être finalisée avant les vraies actions automatiques.",
    },
    {
      title: "Lecture Google",
      status: site.readiness.gsc_connected ? "Active" : "À connecter",
      detail: site.readiness.gsc_connected
        ? "Google Search Console alimente déjà les signaux qui guideront l’exécution automatique."
        : "PraeviSEO aura besoin de Search Console pour prioriser les actions les plus rentables.",
    },
    {
      title: "Publication live",
      status: site.publication_target.engine_actionable ? "Prête" : "À terminer",
      detail: site.publication_target.detail,
    },
    {
      title: "Prochaine action",
      status: "Prête",
      detail: site.next_action.detail,
    },
  ] as const;
  const executionHistory = [
    ...(site.crawl?.requested_at
      ? [
          {
            at: site.crawl.requested_at,
            label: "Crawl premium demandé",
            detail: "PraeviSEO a enregistré une nouvelle lecture complète du site pour préparer les prochaines actions.",
            tone: "secondary" as const,
          },
        ]
      : []),
    ...(site.crawl?.started_at
      ? [
          {
            at: site.crawl.started_at,
            label: "Crawl premium lancé",
            detail: `PraeviSEO relit le site avec un plafond de ${site.crawl.max_pages} page(s).`,
            tone: "default" as const,
          },
        ]
      : []),
    ...(site.crawl?.completed_at
      ? [
          {
            at: site.crawl.completed_at,
            label: "Crawl premium terminé",
            detail: `${site.crawl.crawled_url_count} page(s) relue(s), ${site.crawl.issues_count} point(s) à surveiller remonté(s).`,
            tone: "default" as const,
          },
        ]
      : []),
    ...(site.crawl?.error
      ? [
          {
            at: site.crawl.completed_at ?? site.crawl.requested_at ?? new Date().toISOString(),
            label: "Crawl premium interrompu",
            detail: site.crawl.error,
            tone: "danger" as const,
          },
        ]
      : []),
    ...(site.installation.requested_at
      ? [
          {
            at: site.installation.requested_at,
            label: "Demande d’activation enregistrée",
            detail: "Les accès premium ont été transmis à PraeviSEO pour préparer l’installation distante.",
            tone: "secondary" as const,
          },
        ]
      : []),
    ...(site.installation.started_at
      ? [
          {
            at: site.installation.started_at,
            label: "Installation commencée",
            detail: "PraeviSEO a commencé à se connecter au site et à détecter l’environnement.",
            tone: "default" as const,
          },
        ]
      : []),
    ...site.installation.logs.slice(-6).reverse().map((log) => ({
      at: log.at,
      label: log.message,
      detail: log.step ? `Étape : ${log.step}.` : "PraeviSEO continue l’activation premium sur le site.",
      tone:
        log.level === "error"
          ? ("danger" as const)
          : log.level === "success"
          ? ("default" as const)
          : ("secondary" as const),
    })),
    ...(site.installation.completed_at
      ? [
          {
            at: site.installation.completed_at,
            label: "Automatisation activée",
            detail: "PraeviSEO est prêt à exécuter ses actions automatiques et à suivre leur résultat.",
            tone: "default" as const,
          },
        ]
      : []),
    ...(site.installation.failed_at
      ? [
          {
            at: site.installation.failed_at,
            label: "Installation interrompue",
            detail: site.installation.error_message ?? "PraeviSEO n’a pas pu terminer l’installation pour le moment.",
            tone: "danger" as const,
          },
        ]
      : []),
  ];
  const starterPlan = [
    leadRisingPage
      ? {
          title: "Page déjà proche d’un gain visible",
          detail: `${leadRisingPage.label} commence déjà à gagner du terrain dans Google et peut être renforcée automatiquement.`,
          impact: "Une page déjà visible peut progresser plus vite si PraeviSEO la relit, l’enrichit puis relance sa publication.",
        }
      : null,
    leadRefresh
      ? {
          title: "Contenu à enrichir ensuite",
          detail:
            leadRefresh.latest_suggestion?.summary ??
            "PraeviSEO pourra reprendre ce contenu, l’enrichir puis le republier si vous activez l’automatisation.",
          impact:
            leadRefresh.latest_suggestion?.impact_expected ??
            "Un contenu plus clair, plus solide et plus facile à faire progresser dans Google.",
        }
      : null,
    leadIndexationAlert
      ? {
          title: "Page à sécuriser",
          detail: `${leadIndexationAlert.label} reste encore fragile dans la lecture Google actuelle.`,
          impact: "L’automatisation pourra corriger, republier puis relancer une vérification propre derrière.",
        }
      : null,
  ].filter(Boolean) as Array<{ title: string; detail: string; impact: string }>;

  const installationLabel = getPraeviseoInstallLabel(site);
  const installationDetail = getPraeviseoInstallDetail(site);
  const installationPending = isInstallationInProgress(site.installation.status) || site.publication_bridge_status === "requested";

  return (
    <div className="min-h-screen">
      <Topbar
        title={`Automatisation premium · ${site.name}`}
        subtitle="Le mode free vous explique déjà votre SEO. Cette page active la couche qui agit ensuite directement sur votre site."
      />

      <div className="p-6 space-y-6">
        <div className="rounded-3xl border border-brand/20 bg-brand-muted px-6 py-6">
          <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
              <Badge variant="brand-subtle" className="mb-3">
                {formatSitePlatform(site.publication_mode)}
              </Badge>
              <h1 className="text-2xl font-bold tracking-tight text-text">Activer l'automatisation premium sur votre site</h1>
              <p className="mt-2 text-sm text-text-muted max-w-2xl leading-7">
                {installationDetail}
              </p>
              <p className="mt-3 text-sm text-text-muted max-w-2xl leading-7">
                Une fois cette couche active, PraeviSEO peut installer, relire, réécrire, relier, publier et surveiller votre site
                en continu, sans vous faire gérer la partie technique.
              </p>
            </div>
            <div className="rounded-2xl border border-border bg-surface px-5 py-4 min-w-[280px]">
              <div className="text-sm font-semibold text-text">{installationLabel}</div>
              <div className="mt-2 text-sm text-text-muted leading-6">
                {installationPending
                  ? "Vos accès ont bien été enregistrés. L'activation premium peut maintenant être préparée pour ce site."
                  : "Le free reste disponible sans installation. Cette étape ajoute seulement l'exécution et l'automatisation avancée sur le site."}
              </div>
              <div className="mt-3">
                <Badge variant={site.publication_bridge_status === "connected" ? "default" : "secondary"}>
                  {formatPraeviseoStatus(site.publication_bridge_status)}
                </Badge>
              </div>
            </div>
          </div>
        </div>

        <div className="grid gap-6 xl:grid-cols-3">
          {PREMIUM_MODULES.map((module) => (
            <Card key={module.title}>
              <CardHeader>
                <CardTitle>{module.title}</CardTitle>
                <CardDescription>{module.description}</CardDescription>
              </CardHeader>
              <CardContent className="space-y-3 text-sm text-text-muted">
                {module.items.map((item) => (
                  <div key={item} className="flex items-start gap-2">
                    <ShieldCheck className="mt-0.5 h-4 w-4 shrink-0 text-[hsl(var(--success))]" />
                    <span>{item}</span>
                  </div>
                ))}
              </CardContent>
            </Card>
          ))}
        </div>

        <RemoteInstallAssistant
          siteId={site.site_id}
          site={site}
          submitAction={submitRemoteInstallAction.bind(null, site.site_id)}
          initialState={{ status: "idle", message: "", values: {} }}
        />

        <Card>
          <CardHeader>
            <CardTitle>Ce que PraeviSEO fera juste après l’activation</CardTitle>
            <CardDescription>
              Le pack payant ne se contente pas de vous montrer des idées. Il enchaîne les vraies étapes d’exécution.
            </CardDescription>
          </CardHeader>
          <CardContent className="grid gap-4 xl:grid-cols-4">
            {EXECUTION_STEPS.map((step) => {
              const Icon = step.icon;

              return (
                <div key={step.title} className="rounded-2xl border border-border bg-surface-2 px-4 py-4">
                  <div className="flex h-11 w-11 items-center justify-center rounded-2xl bg-brand-subtle">
                    <Icon className="h-5 w-5 text-[hsl(var(--brand))]" />
                  </div>
                  <div className="mt-4 text-sm font-semibold text-text">{step.title}</div>
                  <p className="mt-2 text-sm leading-6 text-text-muted">{step.detail}</p>
                </div>
              );
            })}
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Votre plan de démarrage premium</CardTitle>
            <CardDescription>
              Voici les premières actions que PraeviSEO pourra prendre en charge automatiquement sur ce site une fois l’installation active.
            </CardDescription>
          </CardHeader>
          <CardContent className="grid gap-4 xl:grid-cols-3">
            {starterPlan.length > 0 ? (
              starterPlan.map((item) => (
                <div key={item.title} className="rounded-2xl border border-border bg-surface-2 px-4 py-4">
                  <div className="text-sm font-semibold text-text">{item.title}</div>
                  <p className="mt-2 text-sm leading-6 text-text-muted">{item.detail}</p>
                  <div className="mt-3 rounded-xl border border-brand/20 bg-brand-muted px-3 py-3 text-sm text-text">
                    <span className="font-semibold">Ce que cela peut apporter :</span> {item.impact}
                  </div>
                </div>
              ))
            ) : (
              <div className="xl:col-span-3 rounded-2xl border border-border bg-surface-2 px-4 py-4 text-sm leading-6 text-text-muted">
                PraeviSEO préparera d’abord un crawl, un repérage des pages utiles et une première séquence d’actions automatiques
                dès que l’installation premium sera active sur ce site.
              </div>
            )}
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
              <div>
                <CardTitle>Centre d’exécution premium</CardTitle>
                <CardDescription>
                  Voici les briques qui tourneront pour ce site dès que l’installation sera complètement active.
                </CardDescription>
              </div>
              <div className="flex flex-wrap gap-2">
                <form action={launchPremiumCrawlAction.bind(null, site.site_id)}>
                  <Button type="submit" variant="secondary">
                    Lancer un crawl premium
                  </Button>
                </form>
                <form action={launchPremiumRewriteAction.bind(null, site.site_id)}>
                  <Button type="submit" variant="secondary">
                    Préparer une réécriture
                  </Button>
                </form>
                <form action={launchPremiumLinkingAction.bind(null, site.site_id)}>
                  <Button type="submit" variant="secondary">
                    Renforcer le maillage
                  </Button>
                </form>
                <form action={launchPremiumPublicationAction.bind(null, site.site_id)}>
                  <Button type="submit" variant="secondary">
                    Publier la meilleure page
                  </Button>
                </form>
              </div>
            </div>
          </CardHeader>
          <CardContent className="grid gap-4 xl:grid-cols-5">
            {executionCenter.map((item) => (
              <div key={item.title} className="rounded-2xl border border-border bg-surface-2 px-4 py-4">
                <div className="flex items-center justify-between gap-3">
                  <div className="text-sm font-semibold text-text">{item.title}</div>
                  <Badge variant="secondary">{item.status}</Badge>
                </div>
                <p className="mt-3 text-sm leading-6 text-text-muted">{item.detail}</p>
              </div>
            ))}
          </CardContent>
        </Card>

        <div className="grid gap-6 xl:grid-cols-[0.95fr_1.05fr]">
          <Card>
            <CardHeader>
              <CardTitle>Où en est l’automatisation</CardTitle>
              <CardDescription>
                Cette lecture vous montre ce qui est déjà prêt et ce qui reste à ouvrir avant que PraeviSEO agisse en continu.
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-3">
              {executionReadiness.map((item) => (
                <div key={item.title} className="rounded-2xl border border-border bg-surface-2 px-4 py-4">
                  <div className="flex items-center justify-between gap-3">
                    <div className="text-sm font-semibold text-text">{item.title}</div>
                    <Badge variant="secondary">{item.status}</Badge>
                  </div>
                  <p className="mt-2 text-sm leading-6 text-text-muted">{item.detail}</p>
                  {item.title === "Publication live" && site.publication_target.target ? (
                    <div className="mt-3 rounded-xl border border-border bg-surface px-3 py-3 text-sm text-text">
                      <span className="font-semibold">Point de publication :</span> {site.publication_target.target}
                    </div>
                  ) : null}
                </div>
              ))}
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Historique d’exécution</CardTitle>
              <CardDescription>
                Vous pouvez suivre ici ce que PraeviSEO a déjà lancé, confirmé ou relancé sur le site.
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-3">
              {executionHistory.length > 0 ? (
                executionHistory.map((entry, index) => (
                  <div key={`${entry.at}-${index}`} className="rounded-2xl border border-border bg-surface-2 px-4 py-4">
                    <div className="flex items-center justify-between gap-3">
                      <div className="text-sm font-semibold text-text">{entry.label}</div>
                      <Badge variant={entry.tone}>{entry.at.slice(0, 10)}</Badge>
                    </div>
                    <p className="mt-2 text-sm leading-6 text-text-muted">{entry.detail}</p>
                  </div>
                ))
              ) : (
                <div className="rounded-2xl border border-border bg-surface-2 px-4 py-4 text-sm leading-6 text-text-muted">
                  L’historique d’exécution apparaîtra ici dès que PraeviSEO aura lancé les premières étapes premium sur le site.
                </div>
              )}
            </CardContent>
          </Card>
        </div>

        <div className="grid gap-6 xl:grid-cols-3">
          {[
            {
              icon: Rocket,
              title: "Ce que vous gagnez",
              text: "Un site qui n’attend plus une action manuelle pour être relu, enrichi, relié puis publié.",
            },
            {
              icon: Link2,
              title: "Ce qui devient automatique",
              text: "Maillage interne, réécritures utiles, republication, re-crawl et suivi continu peuvent s’enchaîner proprement.",
            },
            {
              icon: ImagePlus,
              title: "Ce qui s’ajoute ensuite",
              text: "Images SEO, exécutions plus poussées et supervision multi-sites viennent s’appuyer sur cette même couche installée.",
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

        <div className="rounded-2xl border border-border bg-surface px-6 py-5">
          <div className="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
            <div>
              <div className="text-base font-semibold text-text">Alternative avancée</div>
              <p className="mt-1 text-sm text-text-muted leading-6">
                Si vous préférez déclencher l’activation manuellement, vous pouvez toujours utiliser les scripts officiels ci-dessous.
              </p>
            </div>
            <Badge variant="secondary">Méthode actuelle</Badge>
          </div>
        </div>

        <div className="grid gap-6 lg:grid-cols-2">
          {INSTALLERS.map((installer) => (
            <Card key={installer.filename} className="overflow-hidden">
              <CardHeader>
                <div className="flex items-center justify-between gap-3">
                  <div>
                    <CardTitle>{installer.label}</CardTitle>
                    <CardDescription className="mt-2">{installer.description}</CardDescription>
                  </div>
                  <Badge variant="secondary">{installer.filename}</Badge>
                </div>
              </CardHeader>
              <CardContent className="space-y-4">
                <Button href={getInstallerUrl(installer.filename)} className="w-full" external>
                  <Download className="w-4 h-4" />
                  Télécharger le script premium
                </Button>
                <div className="rounded-2xl border border-border bg-surface-2 px-4 py-4">
                  <div className="text-[11px] uppercase tracking-[0.18em] text-text-subtle font-semibold">Lancement</div>
                  <code className="mt-2 block text-sm font-semibold text-text">{installer.launch}</code>
                </div>
              </CardContent>
            </Card>
          ))}
        </div>

        <div className="grid gap-6 xl:grid-cols-3">
          {[
            {
              icon: ShieldCheck,
              title: "Script lisible",
              text: "Le client peut ouvrir le fichier et vérifier qu'il detecte simplement l'environnement et prepare la connexion premium.",
            },
            {
              icon: Download,
              title: "Activation premium",
              text: "Le script active la couche premium proprement sur votre site, sans manipulation manuelle compliquée.",
            },
            {
              icon: Monitor,
              title: "Monitoring activé",
              text: "Une fois la couche premium active, la plateforme peut suivre la vraie page publique et exécuter des actions avancées.",
            },
          ].map((item) => {
            const Icon = item.icon;

            return (
              <Card key={item.title}>
                <CardContent className="pt-5">
                  <div className="w-11 h-11 rounded-2xl bg-brand-subtle flex items-center justify-center">
                    <Icon className="w-5 h-5 text-[hsl(var(--brand))]" />
                  </div>
                  <div className="mt-4 text-base font-semibold text-text">{item.title}</div>
                  <p className="mt-2 text-sm text-text-muted leading-6">{item.text}</p>
                </CardContent>
              </Card>
            );
          })}
        </div>
      </div>
    </div>
  );
}
