import Link from "next/link";
import { Badge } from "@/components/ui/badge";
import { cn } from "@/lib/utils";

type CockpitSectionItem = {
  label: string;
  href: string;
  count?: number | string;
  tone?: "default" | "secondary" | "success" | "warning" | "danger";
};

interface CockpitSectionNavProps {
  items: CockpitSectionItem[];
  className?: string;
}

export function CockpitSectionNav({ items, className }: CockpitSectionNavProps) {
  return (
    <div
      className={cn(
        "rounded-2xl border border-border bg-surface/80 p-2 backdrop-blur-sm overflow-x-auto",
        className
      )}
    >
      <div className="flex min-w-max items-center gap-2">
        {items.map((item, index) => (
          <Link
            key={`${item.label}-${item.href}`}
            href={item.href}
            className={cn(
              "inline-flex items-center gap-2 rounded-xl border px-3 py-2 text-sm transition-colors",
              index === 0
                ? "border-brand/20 bg-brand-muted text-text"
                : "border-border bg-surface-2 text-text-muted hover:text-text hover:border-brand/20 hover:bg-brand-muted/60"
            )}
          >
            <span className="whitespace-nowrap font-medium">{item.label}</span>
            {item.count !== undefined ? (
              <Badge variant={item.tone ?? "secondary"} className="h-5 px-2 text-[10px]">
                {item.count}
              </Badge>
            ) : null}
          </Link>
        ))}
      </div>
    </div>
  );
}
