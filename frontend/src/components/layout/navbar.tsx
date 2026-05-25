import Link from "next/link";
import { Button } from "@/components/ui/button";
import { SearchIcon } from "lucide-react";

const NAV_LINKS = [
  { label: "Fonctionnalités", href: "#features" },
  { label: "Tarifs", href: "#pricing" },
  { label: "Documentation", href: "#" },
  { label: "Blog", href: "#" },
];

export function Navbar() {
  return (
    <header className="fixed top-0 left-0 right-0 z-50">
      {/* Blur overlay */}
      <div className="absolute inset-0 bg-bg/80 backdrop-blur-xl border-b border-border/50" />

      <div className="relative max-w-7xl mx-auto px-5 h-14 flex items-center justify-between">
        {/* Logo */}
        <Link href="/" className="flex items-center gap-2.5 group">
          <div className="w-7 h-7 rounded-lg bg-brand flex items-center justify-center shadow-sm shadow-brand/30 group-hover:shadow-brand/50 transition-shadow">
            <SearchIcon className="w-3.5 h-3.5 text-white" />
          </div>
          <span className="font-semibold text-text tracking-tight">PraeviSEO</span>
        </Link>

        {/* Nav links */}
        <nav className="hidden md:flex items-center gap-1">
          {NAV_LINKS.map((link) => (
            <Link
              key={link.label}
              href={link.href}
              className="px-3 py-1.5 text-sm text-text-muted hover:text-text rounded-md hover:bg-surface-2 transition-all duration-150"
            >
              {link.label}
            </Link>
          ))}
        </nav>

        {/* CTAs */}
        <div className="flex items-center gap-2">
          <Button href="/login" variant="ghost" size="sm">
            Connexion
          </Button>
          <Button href="/signup" size="sm" className="group">
            <span>Démarrer gratuitement</span>
            <svg
              className="w-3.5 h-3.5 translate-x-0 group-hover:translate-x-0.5 transition-transform"
              fill="none"
              viewBox="0 0 24 24"
              stroke="currentColor"
              strokeWidth={2.5}
            >
              <path strokeLinecap="round" strokeLinejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6" />
            </svg>
          </Button>
        </div>
      </div>
    </header>
  );
}
