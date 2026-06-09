"use client";

import { applyCopilotAction } from "@/app/(app)/actions/copilot-actions";
import { Button } from "@/components/ui/button";
import type { PraeviseoBusinessCopilotAction } from "@/lib/praeviseo-api";
import { ArrowRight } from "lucide-react";
import { useFormStatus } from "react-dom";

function SubmitButton({
  ready,
  featured,
}: {
  ready: boolean;
  featured: boolean;
}) {
  const { pending } = useFormStatus();

  return (
    <Button
      type="submit"
      variant={featured ? "primary" : "secondary"}
      className="shrink-0"
      loading={pending}
      disabled={pending}
    >
      {ready ? "Appliquer automatiquement" : "Ouvrir dans le studio"}
      <ArrowRight className="h-4 w-4" />
    </Button>
  );
}

export function BusinessCopilotApplyButton({
  action,
  featured = false,
  returnTo,
}: {
  action: PraeviseoBusinessCopilotAction;
  featured?: boolean;
  returnTo: string;
}) {
  if (!action.apply_ready) {
    return (
      <Button href={action.apply_href} variant={featured ? "primary" : "secondary"} className="shrink-0">
        Ouvrir dans le studio
        <ArrowRight className="h-4 w-4" />
      </Button>
    );
  }

  return (
    <form action={applyCopilotAction} className="shrink-0">
      <input type="hidden" name="site_id" value={action.site_id} />
      <input type="hidden" name="apply_workflow" value={action.apply_workflow} />
      <input type="hidden" name="subject" value={action.subject} />
      <input type="hidden" name="slug" value={action.slug} />
      <input type="hidden" name="query" value={action.query ?? ""} />
      <input type="hidden" name="apply_href" value={action.apply_href} />
      <input type="hidden" name="return_to" value={returnTo} />
      <SubmitButton ready={action.apply_ready} featured={featured} />
    </form>
  );
}
