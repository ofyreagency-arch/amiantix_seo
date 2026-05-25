import { Topbar } from "@/components/layout/topbar";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";

export default function SettingsPage() {
  return (
    <div className="min-h-screen">
      <Topbar
        title="Paramètres"
        subtitle="Réglages client, équipe, billing et préférences d’automatisation."
      />
      <div className="p-6">
        <Card>
          <CardHeader>
            <CardTitle>Paramètres client</CardTitle>
            <CardDescription>
              Cette zone remplacera progressivement les réglages dispersés dans l’admin interne.
            </CardDescription>
          </CardHeader>
          <CardContent className="text-sm text-text-muted leading-7">
            On y branchera ensuite les préférences d’équipe, le niveau d’automatisation, les connecteurs actifs et la configuration produit visible côté client.
          </CardContent>
        </Card>
      </div>
    </div>
  );
}
