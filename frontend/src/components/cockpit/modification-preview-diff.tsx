import { Badge } from "@/components/ui/badge";
import type { PraeviseoActionPreview } from "@/lib/praeviseo-api";
import { formatDate } from "@/lib/utils";
import { ArrowRight, Minus, Plus } from "lucide-react";

function ListBlock({
  title,
  items,
  empty,
  tone = "default",
}: {
  title: string;
  items: string[];
  empty: string;
  tone?: "default" | "added";
}) {
  return (
    <div>
      <p className="text-xs font-semibold uppercase tracking-[0.14em] text-text-subtle">{title}</p>
      {items.length > 0 ? (
        <ul className="mt-2 space-y-2">
          {items.map((item) => (
            <li
              key={item}
              className={`rounded-lg border px-3 py-2 text-sm leading-6 ${
                tone === "added" ? "border-brand/25 bg-brand-muted/30 text-text" : "border-border bg-surface text-text-muted"
              }`}
            >
              {tone === "added" ? <Plus className="mr-1 inline h-3.5 w-3.5 text-brand" /> : <Minus className="mr-1 inline h-3.5 w-3.5 text-text-subtle" />}
              {item}
            </li>
          ))}
        </ul>
      ) : (
        <p className="mt-2 text-sm text-text-subtle">{empty}</p>
      )}
    </div>
  );
}

export function ModificationPreviewDiff({ preview }: { preview: PraeviseoActionPreview }) {
  const { current, proposed, diff } = preview;

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap gap-2">
        {diff.sections_added_count > 0 ? (
          <Badge variant="brand-subtle">{diff.sections_added_count} section(s) à ajouter</Badge>
        ) : null}
        {diff.faq_added_count > 0 ? (
          <Badge variant="brand-subtle">{diff.faq_added_count} question(s) FAQ</Badge>
        ) : null}
        {diff.headings_added_count > 0 ? (
          <Badge variant="secondary">{diff.headings_added_count} nouveau(x) titre(s) H2</Badge>
        ) : null}
        {diff.title_will_change ? <Badge variant="warning">Titre proposé</Badge> : null}
      </div>

      <div className="grid gap-4 xl:grid-cols-2">
        <div className="rounded-2xl border border-border bg-surface px-5 py-5">
          <div className="flex items-center justify-between gap-3">
            <p className="text-sm font-semibold text-text">Aujourd’hui sur votre site</p>
            <Badge variant="secondary">État observé</Badge>
          </div>
          <div className="mt-4 space-y-4">
            <div>
              <p className="text-xs text-text-subtle">Titre observé</p>
              <p className="mt-1 text-sm font-medium text-text">{current.title || "Non détecté"}</p>
            </div>
            {current.meta_description ? (
              <div>
                <p className="text-xs text-text-subtle">Meta description</p>
                <p className="mt-1 text-sm leading-6 text-text-muted">{current.meta_description}</p>
              </div>
            ) : null}
            <ListBlock
              title="Titres H2 déjà présents"
              items={current.h2_headings}
              empty="PraeviSEO n’a pas encore relevé de H2 structurants sur cette page."
            />
            {current.content_excerpt ? (
              <div>
                <p className="text-xs text-text-subtle">Extrait du contenu crawlé</p>
                <p className="mt-1 text-sm leading-6 text-text-muted">{current.content_excerpt}…</p>
              </div>
            ) : null}
            <div className="flex flex-wrap gap-3 text-xs text-text-subtle">
              {current.word_count > 0 ? <span>{current.word_count} mots observés</span> : null}
              {current.observed_at ? <span>Lu le {formatDate(current.observed_at)}</span> : null}
            </div>
          </div>
        </div>

        <div className="rounded-2xl border border-brand/25 bg-brand-muted/15 px-5 py-5">
          <div className="flex items-center justify-between gap-3">
            <p className="text-sm font-semibold text-text">Après modification proposée</p>
            <Badge variant="brand-subtle">Plan PraeviSEO</Badge>
          </div>
          <div className="mt-4 space-y-4">
            <div>
              <p className="text-xs text-text-subtle">Titre après modification</p>
              <p className="mt-1 text-sm font-medium text-text">{proposed.title || current.title || "Inchangé"}</p>
            </div>
            {proposed.content_summary ? (
              <div>
                <p className="text-xs text-text-subtle">Résumé de l’enrichissement</p>
                <p className="mt-1 text-sm leading-6 text-text">{proposed.content_summary}</p>
              </div>
            ) : null}
            <ListBlock
              title="Sections à ajouter"
              items={proposed.sections_to_add}
              empty="Aucune section nouvelle proposée pour le moment."
              tone="added"
            />
            <ListBlock
              title="FAQ à ajouter"
              items={proposed.faq_to_add}
              empty="Aucune question FAQ proposée pour le moment."
              tone="added"
            />
            {diff.headings_added.length > 0 ? (
              <div>
                <p className="text-xs font-semibold uppercase tracking-[0.14em] text-text-subtle">Nouveaux H2 visibles après enrichissement</p>
                <ul className="mt-2 space-y-2">
                  {diff.headings_added.map((heading) => (
                    <li key={heading} className="flex items-center gap-2 rounded-lg border border-brand/20 bg-surface px-3 py-2 text-sm text-text">
                      <ArrowRight className="h-3.5 w-3.5 text-brand" />
                      {heading}
                    </li>
                  ))}
                </ul>
              </div>
            ) : null}
          </div>
        </div>
      </div>
    </div>
  );
}
