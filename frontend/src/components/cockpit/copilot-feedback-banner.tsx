import { Badge } from "@/components/ui/badge";
import { Card, CardContent } from "@/components/ui/card";
import { CheckCircle2, AlertTriangle, XCircle } from "lucide-react";

export function CopilotFeedbackBanner({
  feedback,
  title,
  detail,
}: {
  feedback: string | null;
  title: string | null;
  detail: string | null;
}) {
  if (!feedback || !title) {
    return null;
  }

  const tone =
    feedback === "success" ? "success" : feedback === "warning" ? "warning" : "danger";
  const Icon = feedback === "success" ? CheckCircle2 : feedback === "warning" ? AlertTriangle : XCircle;

  return (
    <Card className="border-border-subtle bg-surface/90">
      <CardContent className="flex items-start gap-3 py-4">
        <Icon
          className={
            tone === "success"
              ? "mt-0.5 h-5 w-5 text-success"
              : tone === "warning"
                ? "mt-0.5 h-5 w-5 text-warning"
                : "mt-0.5 h-5 w-5 text-danger"
          }
        />
        <div className="min-w-0 flex-1">
          <div className="flex flex-wrap items-center gap-2">
            <p className="text-sm font-semibold text-text">{title}</p>
            <Badge variant={tone === "success" ? "success" : tone === "warning" ? "warning" : "danger"}>
              {feedback === "success" ? "Action lancée" : feedback === "warning" ? "En préparation" : "Bloqué"}
            </Badge>
          </div>
          {detail ? <p className="mt-1 text-sm leading-6 text-text-muted">{detail}</p> : null}
        </div>
      </CardContent>
    </Card>
  );
}
