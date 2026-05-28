import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";

interface AssistantGuideProps {
  title: string;
  description: string;
  whatText: string;
  whyText: string;
  nextText: string;
  impactText?: string;
}

function AssistantStep({
  label,
  text,
}: {
  label: string;
  text: string;
}) {
  return (
    <div className="rounded-xl border border-border bg-surface-2 px-4 py-4">
      <p className="text-[11px] font-semibold uppercase tracking-[0.18em] text-text-subtle">{label}</p>
      <p className="mt-2 text-sm leading-6 text-text">{text}</p>
    </div>
  );
}

export function CockpitAssistantGuide({
  title,
  description,
  whatText,
  whyText,
  nextText,
  impactText,
}: AssistantGuideProps) {
  return (
    <Card className="border-brand/20 bg-brand-muted/40">
      <CardHeader>
        <CardTitle>{title}</CardTitle>
        <CardDescription>{description}</CardDescription>
      </CardHeader>
      <CardContent className="grid gap-4 xl:grid-cols-2">
        <AssistantStep label="Ce que PraeviSEO a compris" text={whatText} />
        <AssistantStep label="Pourquoi c’est important" text={whyText} />
        <AssistantStep label="Ce que faire maintenant" text={nextText} />
        <AssistantStep
          label="Ce que cela peut apporter"
          text={impactText ?? "PraeviSEO continuera ensuite à mesurer ce qui progresse et ce qui mérite encore votre attention."}
        />
      </CardContent>
    </Card>
  );
}
