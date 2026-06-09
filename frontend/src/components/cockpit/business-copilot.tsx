import { BusinessCopilotApplyButton } from "@/components/cockpit/business-copilot-apply-button";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import type { PraeviseoBusinessCopilot, PraeviseoBusinessCopilotAction } from "@/lib/praeviseo-api";
import { Sparkles, Zap } from "lucide-react";

function effortBadgeVariant(level: PraeviseoBusinessCopilotAction["effort_level"]) {
  return level === "easy" ? "success" : level === "important" ? "danger" : "warning";
}

function BusinessCopilotActionCard({
  action,
  featured = false,
  returnTo,
}: {
  action: PraeviseoBusinessCopilotAction;
  featured?: boolean;
  returnTo: string;
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

      {action.modification_plan?.sections?.length ||
      action.modification_plan?.topics?.length ||
      action.modification_plan?.faq?.length ? (
        <div className="mt-4 rounded-xl border border-brand/20 bg-brand-muted/20 px-4 py-4">
          <p className="text-[11px] font-semibold uppercase tracking-[0.16em] text-brand">Ce que PraeviSEO va modifier</p>
          {action.modification_plan.content_summary ? (
            <p className="mt-2 text-sm leading-6 text-text">{action.modification_plan.content_summary}</p>
          ) : null}
          <div className="mt-3 grid gap-3 md:grid-cols-3">
            {action.modification_plan.sections.length > 0 ? (
              <div>
                <p className="text-xs font-semibold text-text-muted">Sections à ajouter</p>
                <ul className="mt-2 space-y-1.5 text-sm leading-6 text-text">
                  {action.modification_plan.sections.map((item) => (
                    <li key={item}>• {item}</li>
                  ))}
                </ul>
              </div>
            ) : null}
            {action.modification_plan.topics.length > 0 ? (
              <div>
                <p className="text-xs font-semibold text-text-muted">Sujets à couvrir</p>
                <ul className="mt-2 space-y-1.5 text-sm leading-6 text-text">
                  {action.modification_plan.topics.map((item) => (
                    <li key={item}>• {item}</li>
                  ))}
                </ul>
              </div>
            ) : null}
            {action.modification_plan.faq.length > 0 ? (
              <div>
                <p className="text-xs font-semibold text-text-muted">FAQ à ajouter</p>
                <ul className="mt-2 space-y-1.5 text-sm leading-6 text-text">
                  {action.modification_plan.faq.map((item) => (
                    <li key={item}>• {item}</li>
                  ))}
                </ul>
              </div>
            ) : null}
          </div>
          {action.modification_plan.title_change ? (
            <p className="mt-3 text-sm text-text-muted">
              <span className="font-medium text-text">Titre proposé :</span> {action.modification_plan.title_change}
            </p>
          ) : null}
        </div>
      ) : null}

      <div className="mt-4 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
        <div className="space-y-1">
          <p className="text-sm text-text-muted">{action.action_detail}</p>
          <p className="text-sm font-medium text-text">{action.effort_display}</p>
          {action.gain_basis ? <p className="text-xs text-text-subtle">Gain estimé à partir de : {action.gain_basis}</p> : null}
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
        <BusinessCopilotApplyButton action={action} featured={featured} returnTo={returnTo} />
      </div>
    </div>
  );
}

export function BusinessCopilotPriority({
  copilot,
  compact = false,
  returnTo = "/dashboard",
}: {
  copilot: PraeviseoBusinessCopilot;
  compact?: boolean;
  returnTo?: string;
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
            <BusinessCopilotActionCard action={top} featured returnTo={returnTo} />
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
              <BusinessCopilotActionCard key={action.source_id} action={action} returnTo={returnTo} />
            ))}
          </CardContent>
        </Card>
      ) : null}
    </div>
  );
}
