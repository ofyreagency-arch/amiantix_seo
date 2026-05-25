"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { cn } from "@/lib/utils";
import {
  LayoutDashboard,
  Globe,
  Sparkles,
  Send,
  Settings,
  HelpCircle,
  SearchIcon,
  ChevronDown,
  Plus,
} from "lucide-react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";

const NAV_ITEMS = [
  {
    section: "Principal",
    items: [
      { label: "Vue d'ensemble", href: "/dashboard", icon: LayoutDashboard },
      { label: "Mes sites", href: "/sites", icon: Globe },
      {
        label: "Optimisations",
        href: "/optimizations",
        icon: Sparkles,
        badge: "8",
      },
      { label: "Publications", href: "/publications", icon: Send },
    ],
  },
  {
    section: "Compte",
    items: [
      { label: "Paramètres", href: "/settings", icon: Settings },
      { label: "Aide", href: "/help", icon: HelpCircle },
    ],
  },
];

type SidebarSite = {
  site_id: string;
  name: string;
  is_active: boolean;
  publication_bridge_status: string;
};

interface SidebarProps {
  sites?: SidebarSite[];
}

export function Sidebar({ sites = [] }: SidebarProps) {
  const pathname = usePathname();
  const activeSite =
    sites.find((site) => pathname.startsWith(`/sites/${site.site_id}`)) ?? sites[0] ?? null;

  return (
    <aside className="flex flex-col w-60 min-h-screen bg-surface border-r border-border shrink-0">
      {/* Logo */}
      <div className="px-4 h-14 flex items-center border-b border-border">
        <Link href="/" className="flex items-center gap-2.5 group">
          <div className="w-7 h-7 rounded-lg bg-brand flex items-center justify-center shadow-sm shadow-brand/30">
            <SearchIcon className="w-3.5 h-3.5 text-white" />
          </div>
          <span className="font-semibold text-text tracking-tight">PraeviSEO</span>
        </Link>
      </div>

      {/* Site selector */}
      <div className="px-3 py-3 border-b border-border">
        <button className="w-full flex items-center gap-2 px-2.5 py-2 rounded-lg hover:bg-surface-2 transition-colors group">
          <div className="w-6 h-6 rounded-md bg-brand-subtle flex items-center justify-center shrink-0">
            <Globe className="w-3.5 h-3.5 text-brand" />
          </div>
          <div className="flex-1 min-w-0 text-left">
            <p className="text-xs font-medium text-text truncate">
              {activeSite?.name ?? "Aucun site"}
            </p>
            <p className="text-[10px] text-text-subtle truncate">
              {activeSite
                ? activeSite.publication_bridge_status === "connected"
                  ? "Site connecté"
                  : "Connexion en attente"
                : "Ajoutez votre premier site"}
            </p>
          </div>
          <ChevronDown className="w-3.5 h-3.5 text-text-subtle shrink-0 group-hover:text-text-muted transition-colors" />
        </button>
      </div>

      {/* Navigation */}
      <nav className="flex-1 px-3 py-3 space-y-5 overflow-y-auto">
        {NAV_ITEMS.map((section) => (
          <div key={section.section}>
            <p className="px-2 mb-1 text-[10px] font-semibold uppercase tracking-widest text-text-subtle">
              {section.section}
            </p>
            <ul className="space-y-0.5">
              {section.items.map((item) => {
                const Icon = item.icon;
                const isActive =
                  item.href === "/dashboard"
                    ? pathname === item.href
                    : pathname.startsWith(item.href);

                return (
                  <li key={item.label}>
                    <Link
                      href={item.href}
                      className={cn(
                        "flex items-center gap-2.5 px-2.5 py-2 rounded-lg text-sm transition-all duration-150",
                        isActive
                          ? "bg-brand-subtle text-brand font-medium"
                          : "text-text-muted hover:text-text hover:bg-surface-2"
                      )}
                    >
                      <Icon
                        className={cn(
                          "w-4 h-4 shrink-0",
                          isActive ? "text-brand" : "text-text-subtle"
                        )}
                      />
                      <span className="flex-1 truncate">{item.label}</span>
                      {item.badge && (
                        <Badge variant="default" className="text-[10px] px-1.5 py-0 h-4">
                          {item.badge}
                        </Badge>
                      )}
                    </Link>
                  </li>
                );
              })}
            </ul>
          </div>
        ))}
      </nav>

      {/* Add site CTA */}
      <div className="px-3 py-3 border-t border-border">
        <Button href="/sites/new" variant="secondary" size="sm" className="w-full">
          <Plus className="w-3.5 h-3.5" />
          Ajouter un site
        </Button>
      </div>

      {/* User profile */}
      <div className="px-3 pb-3">
        <div className="flex items-center gap-2.5 px-2.5 py-2 rounded-lg hover:bg-surface-2 transition-colors cursor-pointer">
          <div className="w-7 h-7 rounded-full bg-brand-subtle flex items-center justify-center shrink-0">
            <span className="text-xs font-semibold text-brand">A</span>
          </div>
          <div className="flex-1 min-w-0">
            <p className="text-xs font-medium text-text truncate">Espace client</p>
            <p className="text-[10px] text-text-subtle truncate">
              {sites.length} site{sites.length > 1 ? "s" : ""}
            </p>
          </div>
        </div>
      </div>
    </aside>
  );
}
