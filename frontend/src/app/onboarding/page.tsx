import { requireCurrentUser } from "@/lib/auth";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { ArrowUpRight, CheckCircle2, Globe, SearchCheck, Sparkles } from "lucide-react";

export default async function OnboardingPage() {
  await requireCurrentUser();

  return (
    <div className="min-h-screen bg-bg text-text px-4 py-16">
      <div className="max-w-4xl mx-auto space-y-6">
        <div className="text-center max-w-2xl mx-auto">
          <h1 className="text-4xl font-bold tracking-tight">Bienvenue dans PraeviSEO</h1>
          <p className="mt-4 text-text-muted leading-7">
            L’espace client est prêt. La prochaine vraie étape est d’ajouter votre premier site, de relier
            Google Search Console, puis de laisser PraeviSEO faire remonter vos premières priorités SEO.
          </p>
        </div>

        <div className="grid gap-5 md:grid-cols-3">
          {[
            {
              icon: Globe,
              title: "1. Ajouter un site",
              text: "Renseignez le domaine public et créez votre espace de suivi SEO.",
            },
            {
              icon: SearchCheck,
              title: "2. Connecter Search Console",
              text: "Reliez la propriété Google pour alimenter affichages, visites, pages bien lues par Google et premières tendances utiles.",
            },
            {
              icon: Sparkles,
              title: "3. Lire vos premiers insights",
              text: "PraeviSEO transforme déjà les signaux GSC en priorités, opportunités et recommandations lisibles.",
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
                Le free PraeviSEO est déjà un vrai cockpit SEO, sans installation technique.
              </div>
              <p className="mt-2 text-sm text-text-muted">
                Connectez d’abord votre site et Google Search Console. PraeviSEO commencera déjà à expliquer votre SEO
                et à vous guider sur les prochaines actions utiles.
              </p>
            </div>
            <Button href="/sites/new">
              Connecter mon premier site
              <ArrowUpRight className="w-4 h-4" />
            </Button>
          </CardContent>
        </Card>
      </div>
    </div>
  );
}
