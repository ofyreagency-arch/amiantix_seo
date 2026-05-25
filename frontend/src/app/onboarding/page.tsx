import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { CheckCircle2, Globe, Plug, SearchCheck } from "lucide-react";

export default function OnboardingPage() {
  return (
    <div className="min-h-screen bg-bg text-text px-4 py-16">
      <div className="max-w-4xl mx-auto space-y-6">
        <div className="text-center max-w-2xl mx-auto">
          <h1 className="text-4xl font-bold tracking-tight">Bienvenue dans PraeviSEO</h1>
          <p className="mt-4 text-text-muted leading-7">
            L’espace client est prêt. La prochaine vraie étape est de connecter votre premier site, puis d’activer le bridge et Search Console.
          </p>
        </div>

        <div className="grid gap-5 md:grid-cols-3">
          {[
            {
              icon: Globe,
              title: "1. Ajouter un site",
              text: "Renseignez le domaine, le framework et la section de publication.",
            },
            {
              icon: Plug,
              title: "2. Installer le bridge",
              text: "Téléchargez l’installateur officiel et laissez le script brancher le site.",
            },
            {
              icon: SearchCheck,
              title: "3. Relier la Search Console",
              text: "Activez ensuite les signaux Google pour nourrir l’autopilot.",
            },
          ].map((item) => {
            const Icon = item.icon;

            return (
              <Card key={item.title}>
                <CardHeader>
                  <div className="w-10 h-10 rounded-2xl bg-brand-subtle flex items-center justify-center">
                    <Icon className="w-4 h-4 text-[hsl(var(--brand))]" />
                  </div>
                  <CardTitle className="pt-4">{item.title}</CardTitle>
                  <CardDescription>{item.text}</CardDescription>
                </CardHeader>
              </Card>
            );
          })}
        </div>

        <Card className="border-brand/20 bg-brand-muted">
          <CardContent className="pt-6 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
              <div className="flex items-center gap-2 text-sm font-semibold text-text">
                <CheckCircle2 className="w-4 h-4 text-[hsl(var(--success))]" />
                Le cockpit client remplace maintenant l’usage de l’admin interne pour l’onboarding.
              </div>
              <p className="mt-2 text-sm text-text-muted">
                Créez votre premier site depuis l’app publique, puis laissez le bridge faire le reste.
              </p>
            </div>
            <Button href="/sites/new">Connecter mon premier site</Button>
          </CardContent>
        </Card>
      </div>
    </div>
  );
}
