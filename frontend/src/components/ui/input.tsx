import * as React from "react";
import { cn } from "@/lib/utils";

export interface InputProps
  extends Omit<React.InputHTMLAttributes<HTMLInputElement>, "prefix"> {
  prefix?: React.ReactNode;
  suffix?: React.ReactNode;
  error?: string;
  label?: string;
  hint?: string;
}

const Input = React.forwardRef<HTMLInputElement, InputProps>(
  ({ className, prefix, suffix, error, label, hint, id, ...props }, ref) => {
    const inputId = id ?? React.useId();

    return (
      <div className="w-full space-y-1.5">
        {label && (
          <label
            htmlFor={inputId}
            className="block text-sm font-medium text-text-muted"
          >
            {label}
          </label>
        )}
        <div className="relative flex items-center">
          {prefix && (
            <span className="absolute left-3 text-text-subtle pointer-events-none">
              {prefix}
            </span>
          )}
          <input
            ref={ref}
            id={inputId}
            className={cn(
              "flex h-10 w-full rounded-lg bg-surface-2 border text-sm text-text",
              "placeholder:text-text-subtle",
              "transition-all duration-200",
              "focus:outline-none focus:ring-2 focus:ring-brand focus:ring-offset-0 focus:border-brand/50",
              "disabled:cursor-not-allowed disabled:opacity-50",
              error
                ? "border-danger focus:ring-danger"
                : "border-border hover:border-[hsl(240_4%_22%)]",
              prefix ? "pl-9" : "px-3",
              suffix ? "pr-9" : "px-3",
              className
            )}
            {...props}
          />
          {suffix && (
            <span className="absolute right-3 text-text-subtle pointer-events-none">
              {suffix}
            </span>
          )}
        </div>
        {error && <p className="text-xs text-danger">{error}</p>}
        {hint && !error && <p className="text-xs text-text-subtle">{hint}</p>}
      </div>
    );
  }
);
Input.displayName = "Input";

export { Input };
