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


def upload(local_name: str) -> None:
    local = Path(__file__).resolve().parent / local_name
    client = paramiko.SSHClient()
    client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    client.connect(HOST, username="root", password=password(), timeout=30)
    with client.open_sftp() as sftp:
        with sftp.file(f"{APP}/scripts/{local_name}", "w") as handle:
            handle.write(local.read_text(encoding="utf-8"))
    client.close()


def main() -> int:
    if len(sys.argv) > 1 and sys.argv[1] == "--upload":
        upload(sys.argv[2])
        cmd = f"cd {APP}; php scripts/{sys.argv[2]}"
        if len(sys.argv) > 3:
            cmd += " " + " ".join(sys.argv[3:])
    else:
        cmd = " ".join(sys.argv[1:]) if len(sys.argv) > 1 else f"cd {APP}; php scripts/regenerate-and-validate-article.php --site=amiantix --keyword='diagnostic amiante avant travaux' 2>&1"
    client = paramiko.SSHClient()
    client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    client.connect(HOST, username="root", password=password(), timeout=30)
    _, stdout, stderr = client.exec_command(cmd, timeout=1800)
    out = stdout.read().decode("utf-8", errors="replace")
    err = stderr.read().decode("utf-8", errors="replace")
    code = stdout.channel.recv_exit_status()
    print(out)
    if err.strip():
        print(err, file=sys.stderr)
    client.close()
    return code


if __name__ == "__main__":
    raise SystemExit(main())
