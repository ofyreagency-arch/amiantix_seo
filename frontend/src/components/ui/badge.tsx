import * as React from "react";
import { cva, type VariantProps } from "class-variance-authority";
import { cn } from "@/lib/utils";

const badgeVariants = cva(
  "inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset",
  {
    variants: {
      variant: {
        default:
          "bg-brand-muted text-brand ring-brand/20",
        "brand-subtle":
          "bg-brand-muted text-brand ring-brand/20",
        secondary:
          "bg-surface-2 text-text-muted ring-border",
        success:
          "bg-success-subtle text-[hsl(142_71%_60%)] ring-[hsl(142_71%_45%)/0.25]",
        warning:
          "bg-warning-subtle text-[hsl(38_92%_65%)] ring-[hsl(38_92%_50%)/0.25]",
        danger:
          "bg-danger-subtle text-[hsl(0_72%_65%)] ring-[hsl(0_72%_51%)/0.25]",
        outline:
          "bg-transparent text-text-muted ring-border",
      },
      dot: {
        true: "",
        false: "",
      },
    },
    defaultVariants: {
      variant: "default",
      dot: false,
    },
  }
);

export interface BadgeProps
  extends React.HTMLAttributes<HTMLSpanElement>,
    VariantProps<typeof badgeVariants> {
  dot?: boolean;
}

function Badge({ className, variant, dot, children, ...props }: BadgeProps) {
  return (
    <span className={cn(badgeVariants({ variant }), className)} {...props}>
      {dot && (
        <span
          className={cn(
            "inline-block w-1.5 h-1.5 rounded-full",
            variant === "success" && "bg-[hsl(142_71%_55%)]",
            variant === "warning" && "bg-[hsl(38_92%_60%)]",
            variant === "danger" && "bg-[hsl(0_72%_60%)]",
            variant === "default" && "bg-brand animate-pulse-dot",
            variant === "brand-subtle" && "bg-brand animate-pulse-dot",
            (!variant || variant === "secondary" || variant === "outline") && "bg-text-subtle"
          )}
        />
      )}
      {children}
    </span>
  );
}

export { Badge, badgeVariants };
