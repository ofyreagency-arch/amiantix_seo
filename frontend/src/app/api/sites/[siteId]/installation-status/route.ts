import { NextResponse } from "next/server";
import { getRemoteInstallationStatus } from "@/lib/praeviseo-api";

interface InstallationStatusRouteProps {
  params: Promise<{ siteId: string }>;
}

export async function GET(_request: Request, { params }: InstallationStatusRouteProps) {
  const { siteId } = await params;
  const site = await getRemoteInstallationStatus(siteId);

  if (!site) {
    return NextResponse.json({ message: "Site introuvable." }, { status: 404 });
  }

  return NextResponse.json({ site, installation: site.installation });
}
