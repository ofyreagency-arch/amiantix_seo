"use client";

import { confirmPreviewPublish } from "@/app/(app)/actions/preview-actions";
import { Button } from "@/components/ui/button";
import type { PraeviseoActionPreview } from "@/lib/praeviseo-api";
import { ArrowRight } from "lucide-react";
import { useFormStatus } from "react-dom";

function SubmitButton({ label }: { label: string }) {
  const { pending } = useFormStatus();

  return (
    <Button type="submit" variant="primary" className="shrink-0" loading={pending} disabled={pending}>
      {label}
      <ArrowRight className="h-4 w-4" />
    </Button>
  );
}

export function ConfirmPreviewPublishButton({
  preview,
  returnTo,
}: {
  preview: PraeviseoActionPreview;
  returnTo: string;
}) {
  if (!preview.can_confirm_publish) {
    const title = preview.requires_manual_validation
      ? "Validation obligatoire avant publication"
      : "Publication native non disponible";

    return (
      <div className="rounded-xl border border-border bg-surface-2 px-4 py-3 text-sm text-text-muted max-w-xl">
        <p className="font-medium text-text">{title}</p>
        <p className="mt-2 leading-6">
          {preview.confirm_publish_blocked_reason
            ?? "PraeviSEO affiche ici le plan exact à appliquer. La publication directe sur l’URL native de votre site n’est pas encore activée."}
        </p>
        {preview.confirm_publish_detail ? (
          <p className="mt-2 leading-6 text-text-subtle">{preview.confirm_publish_detail}</p>
        ) : null}
      </div>
    );
  }

  const label = preview.confirm_publish_button_label || "Confirmer et publier";

  return (
    <div className="flex shrink-0 flex-col items-end gap-2">
      <form action={confirmPreviewPublish} className="shrink-0">
        <input type="hidden" name="site_id" value={preview.site_id} />
        <input type="hidden" name="slug" value={preview.slug} />
        <input type="hidden" name="query" value={preview.query ?? ""} />
        <input type="hidden" name="return_to" value={returnTo} />
        <SubmitButton label={label} />
      </form>
      {preview.confirm_publish_detail ? (
        <p className="max-w-sm text-right text-xs leading-5 text-text-subtle">{preview.confirm_publish_detail}</p>
      ) : null}
    </div>
  );
}
