import Link from "next/link";
import { Navbar } from "@/components/layout/navbar";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Card, CardContent } from "@/components/ui/card";
import {
  ArrowRight,
  Plug,
  TrendingUp,
  Zap,
  BarChart3,
  CheckCircle2,
  Globe,
  Brain,
  ShieldCheck,
  Star,
  ChevronRight,
} from "lucide-react";

// ─── Feature items ────────────────────────────────────────────────────────────
const FEATURES = [
  {
    icon: Brain,
    title: "Assistant SEO compréhensible",
    description:
      "PraeviSEO traduit déjà Google Search Console en priorités simples : ce qui bouge, ce qui bloque et quoi faire maintenant.",
    color: "text-brand",
    bg: "bg-brand-subtle",
  },
  {
    icon: BarChart3,
    title: "Lecture GSC utile dès le Free",
    description:
      "Sans installation, le Free montre déjà les pages visibles, les recherches utiles et les premières actions à ouvrir.",
    color: "text-[hsl(280_84%_70%)]",
    bg: "bg-[hsl(280_84%_67%)/0.1]",
  },
  {
    icon: Plug,
    title: "Installateur premium",
    description:
      "Une fois activé, PraeviSEO s’installe sur votre serveur et peut agir directement sur le site.",
    color: "text-[hsl(142_71%_55%)]",
    bg: "bg-success-subtle",
  },
  {
    icon: Zap,
    title: "Automatisation complète",
    description:
      "Crawl, réécriture, maillage, images, publication, re-crawl et monitoring continu dans une seule boucle.",
    color: "text-[hsl(38_92%_60%)]",
    bg: "bg-warning-subtle",
  },
];

// ─── Steps ────────────────────────────────────────────────────────────────────
const STEPS = [
  {
    step: "01",
    title: "Connectez votre site",
    description: "Ajoutez votre domaine et ouvrez votre cockpit PraeviSEO.",
  },
  {
    step: "02",
    title: "Reliez Search Console",
    description: "Le Free commence immédiatement à lire vos signaux Google utiles.",
  },
  {
    step: "03",
    title: "Lisez vos priorités",
    description: "PraeviSEO vous explique ce qui progresse, ce qui bloque et quoi traiter d’abord.",
  },
  {
    step: "04",
    title: "Activez l’installateur",
    description: "Le premium s’installe sur le site et exécute les actions à votre place.",
  },
];

// ─── Pricing ──────────────────────────────────────────────────────────────────
const PLANS = [
  {
    name: "Free",
    price: "0",
    description: "Pour comprendre enfin son SEO sans rien installer.",
    features: [
      "Connexion Google Search Console",
      "Cockpit SEO lisible",
      "Pages, recherches, indexation, activité",
      "Priorités expliquées simplement",
      "Aucune installation serveur",
    ],
    cta: "Commencer gratuitement",
    popular: false,
  },
  {
    name: "Installateur",
    price: "89",
    description: "Pour laisser PraeviSEO agir directement sur le site.",
    features: [
      "Installation sur serveur",
      "Crawl automatique",
      "Réécriture, maillage, images",
      "Publication automatique",
      "Monitoring continu",
      "Historique d’exécution",
    ],
    cta: "Découvrir l’installateur",
    popular: true,
  },
  {
    name: "Multi-sites",
    price: "249",
    description: "Pour les agences et portefeuilles de sites.",
    features: [
      "Plusieurs sites suivis",
      "Même automatisation sur chaque site",
      "Pilotage centralisé",
      "Priorités inter-sites",
      "Historique plus large",
      "Accompagnement renforcé",
    ],
    cta: "Parler de votre parc",
    popular: false,
  },
];

// ─── Testimonials ─────────────────────────────────────────────────────────────
const TESTIMONIALS = [
  {
    quote:
      "Le Free nous a enfin permis de comprendre ce que Google voyait déjà. On a arrêté de regarder des chiffres sans savoir quoi faire.",
    author: "Claire D.",
    role: "Dirigeante · PME B2B",
    rating: 5,
  },
  {
    quote:
      "Le premium change tout : PraeviSEO ne se contente plus de conseiller, il relit, améliore et republie nos pages.",
    author: "Thomas L.",
    role: "Responsable produit · SaaS",
    rating: 5,
  },
  {
    quote:
      "On peut maintenant déployer la même logique sur plusieurs sites sans repartir de zéro. C’est enfin un vrai copilote exploitable.",
    author: "Sophie M.",
    role: "Directrice · Agence",
    rating: 5,
  },
];

export default function LandingPage() {
  return (
    <div className="min-h-screen bg-bg text-text overflow-x-hidden">
      <Navbar />

      {/* ── HERO ──────────────────────────────────────────────────────────────── */}
      <section className="relative pt-32 pb-28 px-5 overflow-hidden">
        {/* Grid background */}
        <div
          className="absolute inset-0 bg-grid-pattern"
          style={{ backgroundSize: "50px 50px" }}
        />
        {/* Radial glow */}
        <div className="absolute inset-0 bg-hero-glow" />
        {/* Bottom fade */}
        <div className="absolute bottom-0 left-0 right-0 h-32 bg-gradient-to-t from-bg to-transparent" />

        <div className="relative max-w-4xl mx-auto text-center">
          {/* Announcement badge */}
          <div className="inline-flex items-center gap-2 rounded-full border border-brand/30 bg-brand-muted px-4 py-1.5 text-sm text-brand mb-8 animate-fade-in">
            <span className="w-1.5 h-1.5 rounded-full bg-brand animate-pulse-dot" />
            Nouveau · Bridge WordPress désormais disponible
            <ChevronRight className="w-3 h-3" />
          </div>

            {/* Headline */}
            <h1 className="text-5xl md:text-[72px] font-bold tracking-tighter leading-[1.05] mb-6 animate-fade-up">
              Comprenez votre SEO.
              <br />
              <span className="text-gradient-brand">Puis laissez PraeviSEO agir.</span>
            </h1>

          {/* Subline */}
            <p className="text-xl text-text-muted max-w-2xl mx-auto leading-relaxed mb-10 animate-fade-up animation-delay-100">
              Le Free traduit déjà Google Search Console en plan d’action compréhensible.
              L’installateur premium ajoute ensuite le crawl, la réécriture, le maillage,
              les images et la publication automatique sur votre site.
            </p>

          {/* CTAs */}
          <div className="flex flex-col sm:flex-row items-center justify-center gap-3 mb-8 animate-fade-up animation-delay-200">
            <Button href="/signup" size="lg" className="group w-full sm:w-auto">
              Connecter mon site
              <ArrowRight className="w-4 h-4 group-hover:translate-x-0.5 transition-transform" />
            </Button>
            <Button href="#demo" variant="secondary" size="lg" className="w-full sm:w-auto">
              Voir la démo
            </Button>
          </div>

          {/* Trust signals */}
          <div className="flex flex-wrap items-center justify-center gap-x-6 gap-y-2 text-sm text-text-subtle animate-fade-up animation-delay-300">
              {[
                "Free sans installation",
                "Google Search Console en premier",
                "Premium activable ensuite",
              ].map((t) => (
              <span key={t} className="flex items-center gap-1.5">
                <CheckCircle2 className="w-3.5 h-3.5 text-success" />
                {t}
              </span>
            ))}
          </div>
        </div>

        {/* Dashboard mockup */}
        <div className="relative max-w-5xl mx-auto mt-20 animate-fade-up animation-delay-400">
          <div className="relative rounded-2xl border border-brand/20 overflow-hidden shadow-2xl shadow-brand/10 glow-brand">
            {/* Window chrome */}
            <div className="flex items-center gap-1.5 px-4 py-3 bg-surface border-b border-border">
              <div className="w-3 h-3 rounded-full bg-danger/60" />
              <div className="w-3 h-3 rounded-full bg-warning/60" />
              <div className="w-3 h-3 rounded-full bg-success/60" />
              <div className="flex-1 mx-4">
                <div className="w-48 h-5 rounded-md bg-surface-2 flex items-center justify-center">
                  <span className="text-[10px] text-text-subtle">app.praeviseo.com/dashboard</span>
                </div>
              </div>
            </div>

            {/* Mock dashboard content */}
            <div className="bg-bg p-6 grid grid-cols-12 gap-4 min-h-[380px]">
              {/* Sidebar mock */}
              <div className="col-span-2 hidden md:block space-y-2">
                <div className="h-7 rounded-lg bg-brand-subtle" />
                {["w-full", "w-4/5", "w-full", "w-3/4"].map((w, i) => (
                  <div key={i} className={`h-7 rounded-lg bg-surface ${w}`} />
                ))}
              </div>

              {/* Main content mock */}
              <div className="col-span-12 md:col-span-10 space-y-4">
                {/* KPI row */}
                <div className="grid grid-cols-4 gap-3">
                  {[
                    { label: "Pages sync.", value: "47", color: "text-text" },
                    { label: "Score SEO", value: "78/100", color: "text-brand" },
                    { label: "Optimisations", value: "8", color: "text-[hsl(280_84%_70%)]" },
                    { label: "Publiées", value: "23", color: "text-success" },
                  ].map((kpi) => (
                    <div key={kpi.label} className="rounded-lg border border-border bg-surface p-3">
                      <p className="text-[10px] text-text-subtle mb-1">{kpi.label}</p>
                      <p className={`text-lg font-bold ${kpi.color}`}>{kpi.value}</p>
                    </div>
                  ))}
                </div>

                {/* Status cards row */}
                <div className="grid grid-cols-4 gap-3">
                  {[
                    { label: "Site connecté", ok: true },
                    { label: "GSC active", ok: true },
                    { label: "Assistant SEO actif", ok: true },
                    { label: "Automatisation prête", ok: true },
                  ].map((s) => (
                    <div
                      key={s.label}
                      className="rounded-lg border border-success/20 bg-success-subtle px-3 py-2 flex items-center gap-2"
                    >
                      <CheckCircle2 className="w-3.5 h-3.5 text-success shrink-0" />
                      <span className="text-[10px] text-text-muted truncate">{s.label}</span>
                    </div>
                  ))}
                </div>

                {/* Chart placeholder */}
                <div className="rounded-lg border border-border bg-surface p-3 h-28 flex items-end gap-1 overflow-hidden">
                  {[40, 55, 48, 62, 58, 72, 68, 80, 75, 88, 84, 95].map((h, i) => (
                    <div
                      key={i}
                      className="flex-1 rounded-t-sm bg-brand/30 hover:bg-brand/50 transition-colors"
                      style={{ height: `${h}%` }}
                    />
                  ))}
                </div>
              </div>
            </div>
          </div>

          {/* Floating badge */}
          <div className="absolute -bottom-4 left-1/2 -translate-x-1/2 flex items-center gap-2 rounded-full border border-success/30 bg-success-subtle px-4 py-1.5 text-xs text-success whitespace-nowrap shadow-lg">
            <span className="w-1.5 h-1.5 rounded-full bg-success animate-pulse-dot" />
            +342% de trafic organique en 6 mois · acme.fr
          </div>
        </div>
      </section>

      {/* ── HOW IT WORKS ──────────────────────────────────────────────────────── */}
      <section className="py-28 px-5" id="how-it-works">
        <div className="max-w-5xl mx-auto">
          <div className="text-center mb-16">
            <Badge variant="secondary" className="mb-4">Comment ça fonctionne</Badge>
            <h2 className="text-3xl md:text-4xl font-bold tracking-tight mb-4">
              Opérationnel en{" "}
              <span className="text-gradient-brand">moins de 5 minutes</span>
            </h2>
            <p className="text-text-muted max-w-xl mx-auto">
              De la connexion à l'optimisation automatique, le flow est conçu pour
              être le plus simple possible.
            </p>
          </div>

          <div className="relative grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            {/* Connector line (desktop) */}
            <div className="hidden lg:block absolute top-10 left-[12.5%] right-[12.5%] h-px bg-gradient-to-r from-transparent via-border to-transparent" />

            {STEPS.map(({ step, title, description }, i) => (
              <div
                key={step}
                className="relative flex flex-col items-center text-center p-6 rounded-2xl border border-border bg-surface hover:border-brand/30 hover:bg-surface-2 transition-all duration-300 group"
              >
                {/* Step number */}
                <div className="w-10 h-10 rounded-xl bg-surface-2 border border-border flex items-center justify-center mb-4 group-hover:border-brand/30 group-hover:bg-brand-subtle transition-all duration-300 z-10">
                  <span className="text-xs font-mono font-bold text-text-subtle group-hover:text-brand transition-colors">
                    {step}
                  </span>
                </div>
                <h3 className="text-sm font-semibold text-text mb-2">{title}</h3>
                <p className="text-xs text-text-muted leading-relaxed">{description}</p>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* ── FEATURES ──────────────────────────────────────────────────────────── */}
      <section className="py-28 px-5 bg-surface/30" id="features">
        <div className="max-w-6xl mx-auto">
          <div className="text-center mb-16">
            <Badge variant="secondary" className="mb-4">Fonctionnalités</Badge>
            <h2 className="text-3xl md:text-4xl font-bold tracking-tight mb-4">
              Deux niveaux clairs,{" "}
              <span className="text-gradient-brand">rien de confus</span>
            </h2>
            <p className="text-text-muted max-w-xl mx-auto">
              D’abord comprendre ce que Google voit déjà. Ensuite activer la couche
              qui agit réellement sur le site.
            </p>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 gap-5">
            {FEATURES.map(({ icon: Icon, title, description, color, bg }) => (
              <Card
                key={title}
                className="group hover:border-border-subtle hover:bg-surface-2 transition-all duration-300 p-6"
              >
                <CardContent className="p-0">
                  <div className={`w-10 h-10 rounded-xl ${bg} flex items-center justify-center mb-4`}>
                    <Icon className={`w-5 h-5 ${color}`} />
                  </div>
                  <h3 className="text-base font-semibold text-text mb-2">{title}</h3>
                  <p className="text-sm text-text-muted leading-relaxed">{description}</p>
                </CardContent>
              </Card>
            ))}
          </div>

          {/* Platforms */}
          <div className="mt-16 text-center">
            <p className="text-xs text-text-subtle uppercase tracking-widest mb-6">
              Compatible avec
            </p>
            <div className="flex items-center justify-center gap-8 flex-wrap">
              {["Laravel", "Symfony", "WordPress", "Vue.js", "Nuxt", "React"].map(
                (platform) => (
                  <div
                    key={platform}
                    className="px-5 py-2 rounded-lg border border-border bg-surface text-sm font-medium text-text-muted hover:text-text hover:border-brand/30 transition-all duration-150"
                  >
                    {platform}
                  </div>
                )
              )}
            </div>
          </div>
        </div>
      </section>

      {/* ── TESTIMONIALS ──────────────────────────────────────────────────────── */}
      <section className="py-28 px-5">
        <div className="max-w-5xl mx-auto">
          <div className="text-center mb-16">
            <h2 className="text-3xl md:text-4xl font-bold tracking-tight mb-4">
              Des résultats concrets
            </h2>
            <p className="text-text-muted max-w-xl mx-auto">
              Rejoignez des centaines d'équipes qui font confiance à PraeviSEO.
            </p>
          </div>
          <div className="grid grid-cols-1 md:grid-cols-3 gap-5">
            {TESTIMONIALS.map(({ quote, author, role, rating }) => (
              <Card key={author} className="p-6 hover:border-border-subtle transition-colors">
                <CardContent className="p-0 space-y-4">
                  <div className="flex gap-0.5">
                    {Array.from({ length: rating }).map((_, i) => (
                      <Star key={i} className="w-4 h-4 fill-warning text-warning" />
                    ))}
                  </div>
                  <p className="text-sm text-text-muted leading-relaxed">"{quote}"</p>
                  <div>
                    <p className="text-sm font-semibold text-text">{author}</p>
                    <p className="text-xs text-text-subtle">{role}</p>
                  </div>
                </CardContent>
              </Card>
            ))}
          </div>
        </div>
      </section>

      {/* ── PRICING ───────────────────────────────────────────────────────────── */}
      <section className="py-28 px-5 bg-surface/30" id="pricing">
        <div className="max-w-5xl mx-auto">
          <div className="text-center mb-16">
            <Badge variant="secondary" className="mb-4">Tarifs</Badge>
            <h2 className="text-3xl md:text-4xl font-bold tracking-tight mb-4">
              Simple, prévisible, sans surprise
            </h2>
            <p className="text-text-muted max-w-xl mx-auto">
              Commencez gratuitement, puis activez seulement le niveau d’automatisation dont vous avez besoin.
            </p>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-3 gap-5">
            {PLANS.map(({ name, price, description, features, cta, popular }) => (
              <div
                key={name}
                className={`relative rounded-xl border p-6 transition-all duration-300 ${
                  popular
                    ? "border-brand/40 bg-brand-muted shadow-lg shadow-brand/10 scale-[1.02]"
                    : "border-border bg-surface hover:border-border-subtle"
                }`}
              >
                {popular && (
                  <div className="absolute -top-3 left-1/2 -translate-x-1/2">
                    <Badge variant="default" className="shadow-sm">
                      Le plus populaire
                    </Badge>
                  </div>
                )}
                <div className="mb-6">
                  <h3 className="text-sm font-semibold text-text mb-1">{name}</h3>
                  <p className="text-xs text-text-subtle mb-4">{description}</p>
                  <div className="flex items-baseline gap-1">
                    <span className="text-4xl font-bold text-text">{price}€</span>
                    <span className="text-sm text-text-subtle">/mois</span>
                  </div>
                </div>

                <ul className="space-y-2.5 mb-6">
                  {features.map((f) => (
                    <li key={f} className="flex items-start gap-2 text-sm text-text-muted">
                      <CheckCircle2 className="w-4 h-4 text-success shrink-0 mt-0.5" />
                      {f}
                    </li>
                  ))}
                </ul>

                <Button
                  href="/signup"
                  variant={popular ? "primary" : "secondary"}
                  className="w-full"
                >
                  {cta}
                </Button>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* ── FINAL CTA ─────────────────────────────────────────────────────────── */}
      <section className="py-28 px-5">
        <div className="max-w-3xl mx-auto text-center">
          <div className="relative rounded-2xl border border-brand/20 bg-brand-muted p-12 overflow-hidden">
            {/* Glow */}
            <div className="absolute inset-0 bg-hero-glow opacity-50" />

            <div className="relative">
              <h2 className="text-3xl md:text-4xl font-bold tracking-tight mb-4">
                Prêt à passer du constat à l’action ?
              </h2>
              <p className="text-text-muted mb-8 max-w-lg mx-auto">
                Ouvrez d’abord votre cockpit Free. Activez ensuite l’installateur si vous
                voulez que PraeviSEO travaille directement sur votre site.
              </p>
              <div className="flex flex-col sm:flex-row items-center justify-center gap-3">
                <Button href="/signup" size="lg" className="group w-full sm:w-auto">
                  Ouvrir mon cockpit Free
                  <ArrowRight className="w-4 h-4 group-hover:translate-x-0.5 transition-transform" />
                </Button>
                <Button href="/login" variant="secondary" size="lg" className="w-full sm:w-auto">
                  J'ai déjà un compte
                </Button>
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* ── FOOTER ────────────────────────────────────────────────────────────── */}
      <footer className="border-t border-border py-12 px-5">
        <div className="max-w-7xl mx-auto">
          <div className="flex flex-col md:flex-row items-start justify-between gap-8">
            {/* Brand */}
            <div className="space-y-3">
              <div className="flex items-center gap-2.5">
                <div className="w-7 h-7 rounded-lg bg-brand flex items-center justify-center">
                  <Globe className="w-3.5 h-3.5 text-white" />
                </div>
                <span className="font-semibold text-text">PraeviSEO</span>
              </div>
              <p className="text-xs text-text-subtle max-w-[200px] leading-relaxed">
                SEO IA en pilote automatique pour les développeurs et agences.
              </p>
            </div>

            {/* Links */}
            {[
              {
                title: "Produit",
                links: ["Fonctionnalités", "Tarifs", "Roadmap", "Changelog"],
              },
              {
                title: "Ressources",
                links: ["Documentation", "API", "Blog", "Statut"],
              },
              {
                title: "Légal",
                links: ["Confidentialité", "CGU", "Cookies", "Contact"],
              },
            ].map(({ title, links }) => (
              <div key={title}>
                <p className="text-xs font-semibold text-text uppercase tracking-widest mb-3">
                  {title}
                </p>
                <ul className="space-y-2">
                  {links.map((l) => (
                    <li key={l}>
                      <Link
                        href="#"
                        className="text-xs text-text-subtle hover:text-text transition-colors"
                      >
                        {l}
                      </Link>
                    </li>
                  ))}
                </ul>
              </div>
            ))}
          </div>

          <div className="mt-12 pt-6 border-t border-border flex flex-col sm:flex-row items-center justify-between gap-4">
            <p className="text-xs text-text-subtle">
              © 2026 PraeviSEO. Tous droits réservés.
            </p>
            <p className="text-xs text-text-subtle">
              Fait avec ❤️ en France
            </p>
          </div>
        </div>
      </footer>
    </div>
  );
}
