import { Topbar } from "@/components/layout/topbar";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";

export default function HelpPage() {
  return (
    <div className="min-h-screen">
      <Topbar
        title="Aide"
        subtitle="Documentation client, support et réponses simples sans jargon technique."
      />
      <div className="p-6">
        <Card>
          <CardHeader>
            <CardTitle>Aide client</CardTitle>
            <CardDescription>
              Ici, on mettra la documentation visible côté client : connecter un site, relier GSC, publier et comprendre les actions PraeviSEO.
            </CardDescription>
          </CardHeader>
          <CardContent className="text-sm text-text-muted leading-7">
            L’objectif est que le client n’ait jamais besoin d’ouvrir le copilote admin pour comprendre quoi faire ensuite.
          </CardContent>
        </Card>
      </div>
    </div>
  );
}
