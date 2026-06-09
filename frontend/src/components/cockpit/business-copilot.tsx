import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import type { PraeviseoBusinessCopilot, PraeviseoBusinessCopilotAction } from "@/lib/praeviseo-api";
import { ArrowRight, Sparkles, Zap } from "lucide-react";

function effortBadgeVariant(level: PraeviseoBusinessCopilotAction["effort_level"]) {
  return level === "easy" ? "success" : level === "important" ? "danger" : "warning";
}

function BusinessCopilotActionCard({
  action,
  featured = false,
}: {
  action: PraeviseoBusinessCopilotAction;
  featured?: boolean;
}) {
  return (
    <div
      className={
        featured
          ? "rounded-2xl border border-brand/30 bg-surface px-5 py-5 shadow-sm"
          : "rounded-2xl border border-border bg-surface-2 px-4 py-4"
      }
    >
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div className="min-w-0 flex-1">
          <div className="flex flex-wrap items-center gap-2">
            <p className="text-base font-semibold text-text">{action.card_title}</p>
            {featured ? <Badge variant="brand-subtle">Priorité du jour</Badge> : null}
          </div>
          <p className="mt-1 text-sm font-medium text-text">{action.subject}</p>
          {action.site_name ? <p className="mt-1 text-xs text-text-subtle">{action.site_name}</p> : null}
        </div>
        <Badge variant={effortBadgeVariant(action.effort_level)}>{action.gain_display}</Badge>
      </div>

      <div className="mt-4 grid gap-3 md:grid-cols-3">
        <div className="rounded-xl border border-border/70 bg-surface px-3 py-3">
          <p className="text-[11px] font-semibold uppercase tracking-[0.16em] text-text-subtle">Ce qui bloque</p>
          <p className="mt-2 text-sm leading-6 text-text">{action.problem_plain}</p>
        </div>
        <div className="rounded-xl border border-border/70 bg-surface px-3 py-3">
          <p className="text-[11px] font-semibold uppercase tracking-[0.16em] text-text-subtle">Pourquoi agir</p>
          <p className="mt-2 text-sm leading-6 text-text-muted">{action.why_plain}</p>
        </div>
        <div className="rounded-xl border border-border/70 bg-surface px-3 py-3">
          <p className="text-[11px] font-semibold uppercase tracking-[0.16em] text-text-subtle">Ce qu’il faut faire</p>
          <p className="mt-2 text-sm leading-6 text-text">{action.action_label}</p>
        </div>
      </div>

      <div className="mt-4 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
        <div className="space-y-1">
          <p className="text-sm text-text-muted">{action.action_detail}</p>
          <p className="text-sm font-medium text-text">{action.effort_display}</p>
          <div className="flex flex-wrap gap-2 text-xs text-text-subtle">
            {action.current_position !== null ? (
              <span className="rounded-full border border-border px-2.5 py-1">Position actuelle : {action.current_position}</span>
            ) : null}
            {action.estimated_volume !== null ? (
              <span className="rounded-full border border-border px-2.5 py-1">
                Volume estimé : {new Intl.NumberFormat("fr-FR").format(action.estimated_volume)} recherches/mois
              </span>
            ) : null}
          </div>
        </div>
        <Button href={action.apply_href} variant={featured ? "default" : "secondary"} className="shrink-0">
          {action.apply_ready ? "Appliquer automatiquement" : "Voir comment l’appliquer"}
          <ArrowRight className="h-4 w-4" />
        </Button>
      </div>
    </div>
  );
}

export function BusinessCopilotPriority({
  copilot,
  compact = false,
}: {
  copilot: PraeviseoBusinessCopilot;
  compact?: boolean;
}) {
  const actions = copilot.daily_priority ?? [];
  const top = copilot.top_action;
  const rest = actions.filter((item) => item.rank > 1);

  if (!top && actions.length === 0) {
    return (
      <Card className="border-border-subtle bg-surface/80">
        <CardHeader>
          <CardTitle>Priorité du jour</CardTitle>
          <CardDescription>
            PraeviSEO surveille votre visibilité. Dès qu’une action rentable apparaît, elle sera classée ici.
          </CardDescription>
        </CardHeader>
      </Card>
    );
  }

  if (compact) {
    return (
      <Card className="border-brand/20 bg-brand-muted/30">
        <CardHeader className="pb-3">
          <div className="flex items-center gap-2">
            <Sparkles className="h-4 w-4 text-brand" />
            <CardTitle>Priorité du jour</CardTitle>
          </div>
          <CardDescription>{copilot.subheadline}</CardDescription>
        </CardHeader>
        <CardContent className="space-y-3">
          {actions.slice(0, 3).map((action) => (
            <div key={action.source_id} className="flex items-center justify-between gap-3 rounded-xl border border-border bg-surface px-4 py-3">
              <div className="min-w-0">
                <p className="text-sm font-semibold text-text">
                  {action.card_title} — {action.subject}
                </p>
                <p className="mt-1 text-xs text-text-muted">{action.gain_display}</p>
              </div>
              <Button href={action.apply_href} size="sm" variant="secondary">
                Voir
              </Button>
            </div>
          ))}
        </CardContent>
      </Card>
    );
  }

  return (
    <div className="space-y-4">
      <Card className="border-brand/25 bg-brand-muted/35">
        <CardHeader>
          <div className="flex items-center gap-2">
            <Zap className="h-5 w-5 text-brand" />
            <div>
              <CardTitle>{copilot.headline}</CardTitle>
              <CardDescription className="mt-1">{copilot.subheadline}</CardDescription>
            </div>
          </div>
        </CardHeader>
        {top ? (
          <CardContent>
            <BusinessCopilotActionCard action={top} featured />
          </CardContent>
        ) : null}
      </Card>

      {rest.length > 0 ? (
        <Card className="border-border-subtle bg-surface/80">
          <CardHeader>
            <CardTitle>Actions les plus rentables ensuite</CardTitle>
            <CardDescription>Classées par gain potentiel, pas par jargon SEO.</CardDescription>
          </CardHeader>
          <CardContent className="space-y-3">
            {rest.map((action) => (
              <BusinessCopilotActionCard key={action.source_id} action={action} />
            ))}
          </CardContent>
        </Card>
      ) : null}
    </div>
  );
}
