import { promises as fs } from "node:fs";
import path from "node:path";
import { NextResponse } from "next/server";

const installers = {
  "praeviseo-install.ps1": {
    contentType: "text/plain; charset=utf-8",
  },
  "praeviseo-install.sh": {
    contentType: "text/x-shellscript; charset=utf-8",
  },
} as const;

interface InstallerRouteProps {
  params: Promise<{ filename: string }>;
}

export async function GET(_: Request, { params }: InstallerRouteProps) {
  const { filename } = await params;

  if (!(filename in installers)) {
    return new NextResponse("Not found", { status: 404 });
  }

  const installerPath = path.resolve(process.cwd(), "..", filename);
  const file = await fs.readFile(installerPath);
  const metadata = installers[filename as keyof typeof installers];

  return new NextResponse(file, {
    status: 200,
    headers: {
      "Content-Type": metadata.contentType,
      "Content-Disposition": `attachment; filename="${filename}"`,
      "Cache-Control": "no-store",
    },
  });
}
