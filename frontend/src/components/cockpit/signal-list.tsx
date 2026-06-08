import type { ReactNode } from "react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from "@/components/ui/card";

type BadgeTone = "default" | "brand-subtle" | "secondary" | "success" | "warning" | "danger";

interface CockpitSignalListCardProps {
  id?: string;
  title: string;
  description: string;
  empty: boolean;
  emptyMessage: string;
  children: ReactNode;
  className?: string;
}

export function CockpitSignalListCard({
  id,
  title,
  description,
  empty,
  emptyMessage,
  children,
  className,
}: CockpitSignalListCardProps) {
  return (
    <Card id={id} className={className}>
      <CardHeader>
        <CardTitle>{title}</CardTitle>
        <CardDescription>{description}</CardDescription>
      </CardHeader>
      <CardContent className="space-y-3">
        {empty ? (
          <div className="rounded-xl border border-border bg-surface-2 px-4 py-4 text-sm text-text-muted">
            {emptyMessage}
          </div>
        ) : (
          children
        )}
      </CardContent>
    </Card>
  );
}

interface CockpitSignalItemProps {
  title: string;
  subtitle?: string;
  badge?: string;
  badgeTone?: BadgeTone;
  description: string;
  result?: string;
  chips?: string[];
  actions?: Array<{
    label: string;
    href: string;
    variant?: "primary" | "secondary";
  }>;
}

export function CockpitSignalItem({
  title,
  subtitle,
  badge,
  badgeTone = "secondary",
  description,
  result,
  chips = [],
  actions = [],
}: CockpitSignalItemProps) {
  return (
    <div className="rounded-xl border border-border px-4 py-3">
      <div className="flex items-center justify-between gap-3">
        <div>
          <p className="text-sm font-semibold text-text">{title}</p>
          {subtitle ? <p className="text-xs text-text-subtle">{subtitle}</p> : null}
        </div>
        {badge ? <Badge variant={badgeTone}>{badge}</Badge> : null}
      </div>
      <p className="mt-2 text-sm text-text-muted">{description}</p>
      {result ? <p className="mt-2 text-sm text-text">{result}</p> : null}
      {chips.length > 0 ? (
        <div className="mt-3 flex flex-wrap gap-2 text-xs text-text-subtle">
          {chips.map((chip) => (
            <span key={chip} className="rounded-full border border-border px-2.5 py-1">
              {chip}
            </span>
          ))}
        </div>
      ) : null}
      {actions.length > 0 ? (
        <div className="mt-4 flex flex-wrap gap-2">
          {actions.map((action) => (
            <Button key={`${title}-${action.label}-${action.href}`} href={action.href} size="sm" variant={action.variant ?? "secondary"}>
              {action.label}
            </Button>
          ))}
        </div>
      ) : null}
    </div>
  );
}
