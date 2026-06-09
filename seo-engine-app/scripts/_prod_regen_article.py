#!/usr/bin/env python3
from __future__ import annotations

import re
import sys
from pathlib import Path

import paramiko

ROOT = Path(__file__).resolve().parents[2]
HOST = "217.160.63.27"
APP = "/var/www/seo-engine/seo-engine-app"


def password() -> str:
    text = (ROOT / "_deploy_seo.py").read_text(encoding="utf-8")
    match = re.search(r'password="([^"]+)"', text)
    if not match:
        raise RuntimeError("SSH password not found")
    return match.group(1)


def main() -> int:
    site = sys.argv[1] if len(sys.argv) > 1 else "amiantix"
    keyword = sys.argv[2] if len(sys.argv) > 2 else "Parlons de vos dossiers techniques"

    cmd = (
        f"cd {APP} && php scripts/production-site-profile-validation.php "
        f"--site={site} --keyword={keyword!r}"
    )

    client = paramiko.SSHClient()
    client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    client.connect(HOST, username="root", password=password(), timeout=30)
    _, stdout, stderr = client.exec_command(cmd, timeout=600)
    out = stdout.read().decode("utf-8", errors="replace")
    err = stderr.read().decode("utf-8", errors="replace")
    code = stdout.channel.recv_exit_status()
    client.close()

    if err.strip():
        print(err, file=sys.stderr)

    print(out)
    return code


if __name__ == "__main__":
    raise SystemExit(main())
