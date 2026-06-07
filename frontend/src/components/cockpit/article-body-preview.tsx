"use client";

import { useMemo, useState } from "react";
import { Button } from "@/components/ui/button";

type ArticleBlock = {
  type: "heading" | "paragraph";
  text: string;
};

type ArticleBodyPreviewProps = {
  blocks: ArticleBlock[];
  wordCount: number;
  previewCount?: number;
};

export function ArticleBodyPreview({
  blocks,
  wordCount,
  previewCount = 3,
}: ArticleBodyPreviewProps) {
  const [expanded, setExpanded] = useState(false);

  const visibleBlocks = useMemo(() => {
    if (expanded) {
      return blocks;
    }

    return blocks.slice(0, previewCount);
  }, [blocks, expanded, previewCount]);

  const hasMore = blocks.length > previewCount;
  const hiddenCount = Math.max(0, blocks.length - visibleBlocks.length);

  return (
    <div className="mt-6 border-t border-border pt-5">
      <div className="flex items-center justify-between gap-3">
        <div className="text-xs uppercase tracking-[0.18em] text-text-subtle">
          Article complet dans le moteur
        </div>
        <div className="text-xs text-text-subtle">{wordCount} mots</div>
      </div>
      <div className="mt-4 space-y-4">
        {visibleBlocks.length > 0 ? (
          visibleBlocks.map((block, index) =>
            block.type === "heading" ? (
              <h3 key={`${block.type}-${index}`} className="text-lg font-semibold text-text">
                {block.text}
              </h3>
            ) : (
              <p key={`${block.type}-${index}`} className="text-[15px] leading-8 text-text-muted">
                {block.text}
              </p>
            )
          )
        ) : (
          <p className="text-[15px] leading-8 text-text-muted">
            Aucun corps complet n’a encore été remonté par le moteur pour ce contenu.
          </p>
        )}
      </div>
      {hasMore ? (
        <div className="mt-4 space-y-3">
          {!expanded ? (
            <p className="text-xs text-text-subtle">
              {hiddenCount} bloc(s) restent masqués pour garder la lecture simple.
            </p>
          ) : null}
          <Button
            type="button"
            variant="secondary"
            size="sm"
            onClick={() => setExpanded((value) => !value)}
          >
            {expanded ? "Voir moins" : "Voir l’article complet"}
          </Button>
        </div>
      ) : null}
    </div>
  );
}
