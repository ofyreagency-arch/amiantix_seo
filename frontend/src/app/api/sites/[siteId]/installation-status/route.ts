import { NextResponse } from "next/server";
import { getRemoteInstallationStatus } from "@/lib/praeviseo-api";

interface InstallationStatusRouteProps {
  params: Promise<{ siteId: string }>;
}

export async function GET(_request: Request, { params }: InstallationStatusRouteProps) {
  const { siteId } = await params;
  const site = await getRemoteInstallationStatus(siteId);

  if (!site) {
    return NextResponse.json({ site: null, installation: null }, { status: 200 });
  }

  return NextResponse.json({ site, installation: site.installation });
}
