import type { Config } from "tailwindcss";

const config: Config = {
  darkMode: ["class"],
  content: [
    "./src/pages/**/*.{js,ts,jsx,tsx,mdx}",
    "./src/components/**/*.{js,ts,jsx,tsx,mdx}",
    "./src/app/**/*.{js,ts,jsx,tsx,mdx}",
  ],
  theme: {
    extend: {
      colors: {
        // Base surfaces
        bg: "hsl(var(--bg))",
        surface: "hsl(var(--surface))",
        "surface-2": "hsl(var(--surface-2))",
        border: "hsl(var(--border))",
        "border-subtle": "hsl(var(--border-subtle))",
        // Text
        text: "hsl(var(--text))",
        "text-muted": "hsl(var(--text-muted))",
        "text-subtle": "hsl(var(--text-subtle))",
        // Brand
        brand: "hsl(var(--brand))",
        "brand-hover": "hsl(var(--brand-hover))",
        "brand-subtle": "hsl(var(--brand-subtle))",
        "brand-muted": "hsl(var(--brand-muted))",
        // Semantic
        success: "hsl(var(--success))",
        "success-subtle": "hsl(var(--success-subtle))",
        warning: "hsl(var(--warning))",
        "warning-subtle": "hsl(var(--warning-subtle))",
        danger: "hsl(var(--danger))",
        "danger-subtle": "hsl(var(--danger-subtle))",
      },
      fontFamily: {
        sans: ["var(--font-inter)", "system-ui", "sans-serif"],
        mono: ["var(--font-mono)", "Menlo", "Monaco", "monospace"],
      },
      borderRadius: {
        lg: "var(--radius)",
        md: "calc(var(--radius) - 2px)",
        sm: "calc(var(--radius) - 4px)",
      },
      animation: {
        "fade-in": "fadeIn 0.4s ease forwards",
        "fade-up": "fadeUp 0.5s ease forwards",
        "slide-in-right": "slideInRight 0.3s ease forwards",
        "pulse-dot": "pulseDot 2s ease-in-out infinite",
        "spin-slow": "spin 4s linear infinite",
        shimmer: "shimmer 2s linear infinite",
      },
      keyframes: {
        fadeIn: {
          from: { opacity: "0" },
          to: { opacity: "1" },
        },
        fadeUp: {
          from: { opacity: "0", transform: "translateY(16px)" },
          to: { opacity: "1", transform: "translateY(0)" },
        },
        slideInRight: {
          from: { opacity: "0", transform: "translateX(16px)" },
          to: { opacity: "1", transform: "translateX(0)" },
        },
        pulseDot: {
          "0%, 100%": { opacity: "1", transform: "scale(1)" },
          "50%": { opacity: "0.5", transform: "scale(0.85)" },
        },
        shimmer: {
          "0%": { backgroundPosition: "-200% 0" },
          "100%": { backgroundPosition: "200% 0" },
        },
      },
      backgroundImage: {
        "grid-pattern":
          "linear-gradient(hsl(var(--brand) / 0.04) 1px, transparent 1px), linear-gradient(90deg, hsl(var(--brand) / 0.04) 1px, transparent 1px)",
        "hero-glow":
          "radial-gradient(ellipse 80% 50% at 50% -10%, hsl(var(--brand) / 0.25), transparent)",
        "brand-gradient":
          "linear-gradient(135deg, hsl(var(--brand)), hsl(260 84% 72%))",
        shimmer:
          "linear-gradient(90deg, transparent 0%, hsl(var(--surface-2) / 0.8) 50%, transparent 100%)",
      },
    },
  },
  plugins: [],
};

export default config;
