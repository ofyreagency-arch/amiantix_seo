#!/usr/bin/env python3
from __future__ import annotations

import json
import re
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
    script = (Path(__file__).resolve().parent / "verify-unblock-prod.php").read_text(encoding="utf-8")

    client = paramiko.SSHClient()
    client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    client.connect(HOST, username="root", password=password(), timeout=30)
    remote = f"{APP}/scripts/verify-unblock-prod.php"
    with client.open_sftp() as sftp:
        with sftp.file(remote, "w") as handle:
            handle.write(script)
    cmd = f"cd {APP} && php scripts/verify-unblock-prod.php"
    _, stdout, stderr = client.exec_command(cmd, timeout=120)
    out = stdout.read().decode("utf-8", errors="replace").strip()
    err = stderr.read().decode("utf-8", errors="replace").strip()
    client.close()

    if err:
        print(err)
    print(out)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
