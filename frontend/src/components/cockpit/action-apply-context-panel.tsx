import { Badge } from "@/components/ui/badge";
import type { PraeviseoActionApplyContext } from "@/lib/praeviseo-api";
import { cn } from "@/lib/utils";
import { AlertTriangle, CheckCircle2, FileText, Globe, Info } from "lucide-react";

function impactTone(impact: PraeviseoActionApplyContext["live_site_impact"]) {
  switch (impact) {
    case "live_auto":
      return "warning" as const;
    case "studio_then_publish":
    case "draft_only":
      return "secondary" as const;
    case "preview_then_confirm":
    case "advisory_only":
    default:
      return "brand-subtle" as const;
  }
}

function impactIcon(impact: PraeviseoActionApplyContext["live_site_impact"]) {
  switch (impact) {
    case "live_auto":
      return AlertTriangle;
    case "studio_then_publish":
    case "draft_only":
      return FileText;
    default:
      return Info;
  }
}

export function ActionApplyContextPanel({
  context,
  className,
  compact = false,
}: {
  context: PraeviseoActionApplyContext;
  className?: string;
  compact?: boolean;
}) {
  const ImpactIcon = impactIcon(context.live_site_impact);

  return (
    <div
      className={cn(
        "rounded-2xl border border-border bg-surface/80",
        context.will_modify_live_site ? "border-warning/30 bg-warning/5" : "border-brand/15 bg-brand-muted/10",
        className
      )}
    >
      <div className={cn("px-4 py-4", compact ? "space-y-3" : "space-y-4")}>
        <div className="flex flex-wrap items-start justify-between gap-3">
          <div>
            <p className="text-[11px] font-semibold uppercase tracking-[0.16em] text-text-subtle">Page concernée</p>
            <p className="mt-2 text-sm font-semibold text-text">{context.target_label}</p>
            <div className="mt-2 flex flex-wrap gap-2">
              <Badge variant="secondary">{context.page_kind_label}</Badge>
              {context.target_path ? (
                <Badge variant="secondary">{context.target_path}</Badge>
              ) : null}
            </div>
            {context.target_url ? (
              <p className="mt-2 text-xs text-text-subtle break-all">
                <Globe className="mr-1 inline h-3.5 w-3.5" />
                {context.target_url}
              </p>
            ) : null}
          </div>
          <Badge variant={impactTone(context.live_site_impact)}>{context.live_site_impact_label}</Badge>
        </div>

        <div className={cn("grid gap-3", compact ? "grid-cols-1" : "md:grid-cols-2")}>
          <div className="rounded-xl border border-border/70 bg-surface px-3 py-3">
            <p className="text-[11px] font-semibold uppercase tracking-[0.16em] text-text-subtle">Pourquoi cette action</p>
            <p className="mt-2 text-sm leading-6 text-text">{context.why_this_action}</p>
          </div>
          <div className="rounded-xl border border-border/70 bg-surface px-3 py-3">
            <p className="text-[11px] font-semibold uppercase tracking-[0.16em] text-text-subtle">Ce qui changera</p>
            <p className="mt-2 text-sm leading-6 text-text-muted">{context.what_will_change}</p>
          </div>
        </div>

        <div
          className={cn(
            "rounded-xl border px-3 py-3",
            context.will_modify_live_site ? "border-warning/30 bg-warning/10" : "border-border/70 bg-surface"
          )}
        >
          <div className="flex items-start gap-2">
            <ImpactIcon className={cn("mt-0.5 h-4 w-4 shrink-0", context.will_modify_live_site ? "text-warning" : "text-brand")} />
            <div>
              <p className="text-sm font-semibold text-text">
                {context.will_modify_live_site ? "PraeviSEO peut toucher le site live" : "PraeviSEO ne modifie pas encore le site live"}
              </p>
              <p className="mt-1 text-sm leading-6 text-text-muted">{context.live_site_impact_detail}</p>
              {context.button_explanation ? (
                <p className="mt-2 text-xs leading-5 text-text-subtle">
                  <CheckCircle2 className="mr-1 inline h-3.5 w-3.5" />
                  {context.button_explanation}
                </p>
              ) : null}
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
