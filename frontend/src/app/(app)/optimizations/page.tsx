import { Topbar } from "@/components/layout/topbar";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";

export default function OptimizationsPage() {
  return (
    <div className="min-h-screen">
      <Topbar
        title="Optimisations"
        subtitle="Vue client des suggestions, réécritures et prochaines actions moteur."
      />
      <div className="p-6">
        <Card>
          <CardHeader>
            <CardTitle>Espace optimisations client</CardTitle>
            <CardDescription>
              Cette page servira à exposer les suggestions et validations côté client, séparées du copilote admin.
            </CardDescription>
          </CardHeader>
          <CardContent className="text-sm text-text-muted leading-7">
            Le shell client est maintenant en place. La prochaine étape ici sera de brancher les suggestions réelles et les statuts de validation sur les endpoints backend dédiés.
          </CardContent>
        </Card>
      </div>
    </div>
  );
}
