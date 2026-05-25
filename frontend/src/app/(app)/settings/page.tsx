import { Topbar } from "@/components/layout/topbar";
import { Badge } from "@/components/ui/badge";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { ProfileSettingsForm } from "@/components/settings/profile-settings-form";
import { formatPraeviseoStatus, formatSitePlatform, getSettings } from "@/lib/praeviseo-api";

export default async function SettingsPage() {
  const settings = await getSettings();

  return (
    <div className="min-h-screen">
      <Topbar
        title="Parametres"
        subtitle="Profil client, état des sites et connexions actives."
      />
      <div className="p-6 space-y-6">
        <div className="grid gap-6 lg:grid-cols-[minmax(0,420px)_minmax(0,1fr)]">
          <Card>
            <CardHeader>
              <CardTitle>Profil</CardTitle>
              <CardDescription>
                Mettez a jour les informations du compte utilise pour piloter vos sites.
              </CardDescription>
            </CardHeader>
            <CardContent>
              <ProfileSettingsForm
                name={settings.user.name}
                email={settings.user.email}
              />
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Etat des sites connectes</CardTitle>
              <CardDescription>
                Vue client de l’installation PraeviSEO, des sections publiques et des connexions Google.
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
              {settings.sites.map((site) => (
                <div key={site.site_id} className="rounded-xl border border-border p-4 space-y-3">
                  <div className="flex flex-wrap items-center gap-2 justify-between">
                    <div>
                      <p className="text-sm font-semibold text-text">{site.name}</p>
                      <p className="text-xs text-text-subtle">{site.url}</p>
                    </div>
                    <div className="flex items-center gap-2">
                      <Badge variant="brand-subtle">{formatSitePlatform(site.publication_mode)}</Badge>
                      <Badge variant={site.publication_bridge_status === "connected" ? "success" : "warning"}>
                        {formatPraeviseoStatus(site.publication_bridge_status)}
                      </Badge>
                    </div>
                  </div>

                  <div className="grid gap-2 md:grid-cols-2 text-xs text-text-subtle">
                    <span>Section publique : {site.publication_path_prefix ?? "non definie"}</span>
                    <span>GSC : {site.gsc_connection_status}</span>
                    <span>Propriete : {site.gsc_property_url ?? "non reliee"}</span>
                    <span>Compte Google : {site.gsc_account_email ?? "non renseigne"}</span>
                  </div>
                </div>
              ))}
            </CardContent>
          </Card>
        </div>
      </div>
    </div>
  );
}
