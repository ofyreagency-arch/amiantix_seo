import { requireCurrentUser } from "@/lib/auth";
import { Sidebar } from "@/components/layout/sidebar";
import { getSites } from "@/lib/praeviseo-api";

export default async function AppLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  await requireCurrentUser();
  const sites = await getSites();

  return (
    <div className="min-h-screen bg-bg text-text flex">
      <Sidebar sites={sites} />
      <main className="flex-1 min-w-0">{children}</main>
    </div>
  );
}
