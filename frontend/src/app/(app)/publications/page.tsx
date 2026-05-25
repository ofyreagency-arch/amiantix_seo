import { Topbar } from "@/components/layout/topbar";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";

export default function PublicationsPage() {
  return (
    <div className="min-h-screen">
      <Topbar
        title="Publications"
        subtitle="Suivi client des publications runtime, bridge et site public réel."
      />
      <div className="p-6">
        <Card>
          <CardHeader>
            <CardTitle>Espace publications client</CardTitle>
            <CardDescription>
              Le client doit suivre ici ce qui a été publié, où, et si le monitoring public a bien repris la main.
            </CardDescription>
          </CardHeader>
          <CardContent className="text-sm text-text-muted leading-7">
            Le front produit est désormais séparé de l’admin. Cette page sera la vue client des pushes live et des contrôles post-publication.
          </CardContent>
        </Card>
      </div>
    </div>
  );
}
