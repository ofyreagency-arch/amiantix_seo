import type { Metadata } from "next";
import { Inter } from "next/font/google";
import "./globals.css";

const inter = Inter({
  subsets: ["latin"],
  variable: "--font-inter",
  display: "swap",
});

export const metadata: Metadata = {
  title: {
    default: "PraeviSEO — SEO IA en pilote automatique",
    template: "%s · PraeviSEO",
  },
  description:
    "Connectez votre site, et laissez PraeviSEO analyser, optimiser et publier vos contenus automatiquement pour dominer Google.",
  keywords: ["SEO", "IA", "automatisation", "Google Search Console", "référencement"],
  authors: [{ name: "PraeviSEO" }],
  creator: "PraeviSEO",
  metadataBase: new URL("https://praeviseo.com"),
  openGraph: {
    type: "website",
    locale: "fr_FR",
    url: "https://praeviseo.com",
    title: "PraeviSEO — SEO IA en pilote automatique",
    description: "Connectez votre site et laissez l'IA optimiser votre SEO automatiquement.",
    siteName: "PraeviSEO",
  },
  twitter: {
    card: "summary_large_image",
    title: "PraeviSEO",
    description: "SEO IA en pilote automatique",
  },
  icons: {
    icon: "/favicon.svg",
  },
};

export default function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  return (
    <html lang="fr" className="dark">
      <body className={`${inter.variable} antialiased`}>{children}</body>
    </html>
  );
}
