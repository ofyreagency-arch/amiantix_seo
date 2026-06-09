#!/usr/bin/env python3
"""Build and restart PraeviSEO frontend on production VPS."""
from __future__ import annotations

import re
import sys
from pathlib import Path

import paramiko

ROOT = Path(__file__).resolve().parents[2]
HOST = "217.160.63.27"


def password() -> str:
    text = (ROOT / "_deploy_seo.py").read_text(encoding="utf-8")
    match = re.search(r'password="([^"]+)"', text)
    if not match:
        raise RuntimeError("SSH password not found in _deploy_seo.py")
    return match.group(1)


def main() -> int:
    cmd = """
set -e
cd /var/www/seo-engine/frontend
if [ ! -f .env.production ]; then
  cp .env.production.example .env.production
fi
cp /var/www/seo-engine/deploy/supervisor/praeviseo-frontend.conf /etc/supervisor/conf.d/praeviseo-frontend.conf
npm ci
npm run build
bash /var/www/seo-engine/deploy/scripts/restart-praeviseo-frontend.sh
supervisorctl status praeviseo-frontend || true
curl -sI http://127.0.0.1:3000 | head -n 1
echo FRONTEND_DEPLOY_OK
"""
    client = paramiko.SSHClient()
    client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    client.connect(HOST, username="root", password=password(), timeout=30)
    _, stdout, stderr = client.exec_command(cmd, timeout=1800)
    out = stdout.read().decode("utf-8", errors="replace")
    err = stderr.read().decode("utf-8", errors="replace")
    code = stdout.channel.recv_exit_status()
    client.close()
    sys.stdout.buffer.write(out.encode("utf-8", errors="replace"))
    if err.strip():
        sys.stderr.buffer.write(err.encode("utf-8", errors="replace"))
    return code


if __name__ == "__main__":
    raise SystemExit(main())
