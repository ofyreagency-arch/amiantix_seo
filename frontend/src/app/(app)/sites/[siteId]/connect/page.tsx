import { notFound } from "next/navigation";
import { Topbar } from "@/components/layout/topbar";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { getInstallerUrl, getSite } from "@/lib/praeviseo-api";
import { Download, Monitor, ShieldCheck } from "lucide-react";

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

export default async function SiteConnectPage({ params }: SiteConnectPageProps) {
  const { siteId } = await params;
  const site = await getSite(siteId);

  if (!site) {
    notFound();
  }

  return (
    <div className="min-h-screen">
      <Topbar
        title={`Connecter ${site.name}`}
        subtitle="Le client télécharge l’installateur officiel, lance le script, puis le bridge fait le reste."
      />

      <div className="p-6 space-y-6">
        <div className="rounded-3xl border border-brand/20 bg-brand-muted px-6 py-6">
          <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
              <Badge variant="brand-subtle" className="mb-3">
                {site.publication_mode_label}
              </Badge>
              <h1 className="text-2xl font-bold tracking-tight text-text">Télécharger l’installateur officiel</h1>
              <p className="mt-2 text-sm text-text-muted max-w-2xl leading-7">
                Toute la complexité technique reste cachée derrière le bridge. Le client télécharge simplement
                l’installateur officiel et suit les étapes à l’écran.
              </p>
            </div>
            <div className="rounded-2xl border border-border bg-surface px-5 py-4 min-w-[280px]">
              <div className="text-[11px] uppercase tracking-[0.18em] text-text-subtle font-semibold">Référence technique</div>
              <div className="mt-2 text-2xl font-black text-text tracking-wide">{site.publication_connect_code ?? "—"}</div>
              <div className="mt-2 text-xs text-text-subtle">
                À utiliser seulement si l’installateur le demande pendant la connexion.
              </div>
            </div>
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
                  Télécharger l’installateur
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
              text: "Le client peut ouvrir le fichier et vérifier qu’il détecte juste PHP, Composer, le framework et la connexion PraeviSEO.",
            },
            {
              icon: Download,
              title: "Bridge officiel",
              text: "Le script installe les vrais packages Packagist Laravel / Symfony, sans copie manuelle de fichiers.",
            },
            {
              icon: Monitor,
              title: "Monitoring activé",
              text: "Une fois le bridge connecté, PraeviSEO peut suivre la vraie page publique, pas seulement le runtime interne.",
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
