#!/usr/bin/env python3
from __future__ import annotations

import json
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
    keyword = sys.argv[2] if len(sys.argv) > 2 else "diagnostic amiante avant travaux"
    script = (Path(__file__).resolve().parent / "debug-generation-length.php").read_text(encoding="utf-8")

    client = paramiko.SSHClient()
    client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    client.connect(HOST, username="root", password=password(), timeout=30)

    remote = f"{APP}/scripts/debug-generation-length.php"
    with client.open_sftp() as sftp:
        with sftp.file(remote, "w") as handle:
            handle.write(script)

    cmd = f"cd {APP} && php scripts/debug-generation-length.php --site={site} --keyword={keyword!r}"
    _, stdout, stderr = client.exec_command(cmd, timeout=600)
    out = stdout.read().decode("utf-8", errors="replace")
    err = stderr.read().decode("utf-8", errors="replace")
    code = stdout.channel.recv_exit_status()
    client.close()

    if err.strip():
        print(err, file=sys.stderr)

    print(out)
    if out.strip():
        data = json.loads(out)
        print(json.dumps({k: data[k] for k in data if k != "content_excerpt"}, ensure_ascii=False, indent=2))

    return code


if __name__ == "__main__":
    raise SystemExit(main())
