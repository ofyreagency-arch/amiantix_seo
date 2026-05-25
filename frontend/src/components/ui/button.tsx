import * as React from "react";
import { cva, type VariantProps } from "class-variance-authority";
import Link from "next/link";
import { cn } from "@/lib/utils";

const buttonVariants = cva(
  [
    "inline-flex items-center justify-center gap-2 rounded-lg font-medium",
    "transition-all duration-200 ease-out",
    "focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2 focus-visible:ring-offset-bg",
    "disabled:pointer-events-none disabled:opacity-40",
    "select-none whitespace-nowrap",
  ].join(" "),
  {
    variants: {
      variant: {
        primary:
          "bg-brand text-white hover:bg-brand-hover active:scale-[0.98] shadow-sm shadow-brand/20 hover:shadow-brand/30",
        secondary:
          "bg-surface-2 text-text border border-border hover:bg-[hsl(240_5%_14%)] hover:border-[hsl(240_4%_20%)] active:scale-[0.98]",
        ghost:
          "text-text-muted hover:text-text hover:bg-surface-2 active:scale-[0.98]",
        outline:
          "border border-border text-text hover:bg-surface-2 active:scale-[0.98]",
        destructive:
          "bg-danger text-white hover:bg-[hsl(0_72%_44%)] active:scale-[0.98]",
        link: "text-brand underline-offset-4 hover:underline p-0 h-auto",
        "brand-subtle":
          "bg-brand-muted text-brand border border-brand/20 hover:bg-brand-subtle active:scale-[0.98]",
      },
      size: {
        xs: "h-7 px-2.5 text-xs",
        sm: "h-8 px-3 text-sm",
        default: "h-10 px-4 text-sm",
        lg: "h-11 px-6 text-base",
        xl: "h-13 px-8 text-lg",
        icon: "h-9 w-9",
        "icon-sm": "h-7 w-7",
      },
    },
    defaultVariants: {
      variant: "primary",
      size: "default",
    },
  }
);

export interface ButtonProps
  extends React.ButtonHTMLAttributes<HTMLButtonElement>,
    VariantProps<typeof buttonVariants> {
  href?: string;
  external?: boolean;
  loading?: boolean;
}

const Button = React.forwardRef<HTMLButtonElement, ButtonProps>(
  ({ className, variant, size, href, external, loading, children, disabled, ...props }, ref) => {
    const classes = cn(buttonVariants({ variant, size }), className);
    const isDisabled = disabled || loading;

    if (href) {
      return (
        <Link
          href={href}
          className={classes}
          {...(external ? { target: "_blank", rel: "noopener noreferrer" } : {})}
        >
          {loading && <SpinnerIcon />}
          {children}
        </Link>
      );
    }

    return (
      <button ref={ref} className={classes} disabled={isDisabled} {...props}>
        {loading && <SpinnerIcon />}
        {children}
      </button>
    );
  }
);
Button.displayName = "Button";

function SpinnerIcon() {
  return (
    <svg
      className="h-4 w-4 animate-spin"
      xmlns="http://www.w3.org/2000/svg"
      fill="none"
      viewBox="0 0 24 24"
    >
      <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
      <path
        className="opacity-75"
        fill="currentColor"
        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"
      />
    </svg>
  );
}

export { Button, buttonVariants };
