import { Badge } from "@/components/ui/badge";
import { Card, CardHeader, CardTitle, CardDescription } from "@/components/ui/card";

type BadgeTone = "default" | "brand-subtle" | "secondary" | "success" | "warning" | "danger";

export interface CockpitMetricItem {
  label: string;
  value: string | number;
  detail?: string;
  tone?: BadgeTone;
}

interface CockpitMetricGridProps {
  items: CockpitMetricItem[];
  columnsClassName?: string;
}

export function CockpitMetricGrid({
  items,
  columnsClassName = "grid gap-4 md:grid-cols-2 xl:grid-cols-4",
}: CockpitMetricGridProps) {
  return (
    <div className={columnsClassName}>
      {items.map((item) => (
        <Card key={item.label} className="border-border-subtle bg-surface/80">
          <CardHeader className="pb-3">
            <div className="flex items-center justify-between gap-3">
              <CardDescription>{item.label}</CardDescription>
              <Badge variant={item.tone ?? "secondary"}>{String(item.value)}</Badge>
            </div>
            {item.detail ? (
              <CardTitle className="text-sm leading-6 text-text-muted font-medium">{item.detail}</CardTitle>
            ) : (
              <CardTitle className="text-3xl">{item.value}</CardTitle>
            )}
          </CardHeader>
        </Card>
      ))}
    </div>
  );
}
