import { Bell, RefreshCw } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";

interface TopbarProps {
  title: string;
  subtitle?: string;
  actions?: React.ReactNode;
  lastSync?: string;
}

export function Topbar({ title, subtitle, actions, lastSync }: TopbarProps) {
  return (
    <div className="h-14 flex items-center justify-between px-6 border-b border-border bg-surface/50 backdrop-blur-sm shrink-0">
      {/* Left: title */}
      <div className="flex items-center gap-3 min-w-0">
        <div className="min-w-0">
          <h1 className="text-sm font-semibold text-text truncate">{title}</h1>
          {subtitle && (
            <p className="text-xs text-text-subtle truncate">{subtitle}</p>
          )}
        </div>
        {lastSync && (
          <span className="hidden sm:flex items-center gap-1.5 text-xs text-text-subtle">
            <RefreshCw className="w-3 h-3" />
            Synchro {lastSync}
          </span>
        )}
      </div>

      {/* Right: actions */}
      <div className="flex items-center gap-2 shrink-0">
        {actions}
        <Button variant="ghost" size="icon" className="relative">
          <Bell className="w-4 h-4" />
          <Badge
            variant="danger"
            className="absolute -top-0.5 -right-0.5 w-4 h-4 p-0 flex items-center justify-center text-[9px] rounded-full"
          >
            3
          </Badge>
        </Button>
      </div>
    </div>
  );
}
