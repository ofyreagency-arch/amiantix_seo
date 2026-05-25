import * as React from "react";
import { cn } from "@/lib/utils";

interface ProgressProps extends React.HTMLAttributes<HTMLDivElement> {
  value?: number;
  max?: number;
  variant?: "default" | "success" | "warning" | "danger";
  size?: "sm" | "default" | "lg";
  showLabel?: boolean;
}

function Progress({
  value = 0,
  max = 100,
  variant = "default",
  size = "default",
  showLabel = false,
  className,
  ...props
}: ProgressProps) {
  const pct = Math.min(Math.max((value / max) * 100, 0), 100);

  return (
    <div className={cn("w-full", className)} {...props}>
      <div
        className={cn(
          "overflow-hidden rounded-full bg-surface-2",
          size === "sm" && "h-1",
          size === "default" && "h-1.5",
          size === "lg" && "h-2.5"
        )}
        role="progressbar"
        aria-valuenow={value}
        aria-valuemax={max}
        aria-valuemin={0}
      >
        <div
          className={cn(
            "h-full rounded-full transition-all duration-500 ease-out",
            variant === "default" && "bg-brand",
            variant === "success" && "bg-success",
            variant === "warning" && "bg-warning",
            variant === "danger" && "bg-danger"
          )}
          style={{ width: `${pct}%` }}
        />
      </div>
      {showLabel && (
        <p className="mt-1 text-xs text-text-subtle text-right">{Math.round(pct)}%</p>
      )}
    </div>
  );
}

export { Progress };
