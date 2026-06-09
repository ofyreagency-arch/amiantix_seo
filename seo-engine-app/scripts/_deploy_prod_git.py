#!/usr/bin/env python3
"""One-shot production deploy via git pull. Reads SSH creds from repo _deploy_seo.py."""
from __future__ import annotations

import re
import sys
from pathlib import Path

import paramiko

ROOT = Path(__file__).resolve().parents[2]
DEPLOY_REF = ROOT / "_deploy_seo.py"
HOST = "217.160.63.27"
SEO_APP = "/var/www/seo-engine/seo-engine-app"


def password() -> str:
    text = DEPLOY_REF.read_text(encoding="utf-8")
    match = re.search(r'password="([^"]+)"', text)
    if not match:
        raise RuntimeError("SSH password not found in _deploy_seo.py")
    return match.group(1)


def run(command: str, timeout: int = 600) -> tuple[int, str, str]:
    client = paramiko.SSHClient()
    client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    client.connect(HOST, username="root", password=password(), timeout=30)
    stdin, stdout, stderr = client.exec_command(command, timeout=timeout)
    out = stdout.read().decode("utf-8", errors="replace")
    err = stderr.read().decode("utf-8", errors="replace")
    code = stdout.channel.recv_exit_status()
    client.close()
    return code, out, err


def main() -> int:
    onboard = "--onboard" in sys.argv
    steps = f"""
set -e
cd /var/www/seo-engine
git fetch origin
git checkout main
git reset --hard origin/main
git clean -fd
git log -1 --oneline
cd seo-engine-app
export COMPOSER_ALLOW_SUPERUSER=1
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan queue:restart
echo DEPLOY_OK
"""
    code, out, err = run(steps, timeout=600)
    print(out)
    if err.strip():
        print(err, file=sys.stderr)
    if code != 0:
        print(f"deploy failed exit={code}", file=sys.stderr)
        return code

    if "--verify-amiantix" in sys.argv:
        verify = (
            f"cd {SEO_APP} && php artisan tinker --execute="
            "\"\\$pages=App\\\\Models\\\\SeoSitePage::where('site_id','amiantix')"
            "->where('path','like','%slug-test%')->get(['path','indexability_state','title']);"
            "echo \\$pages->toJson(JSON_UNESCAPED_UNICODE);\""
        )
        code, out, err = run(verify, timeout=120)
        print(out)
        if err.strip():
            print(err, file=sys.stderr)
        return code

    if onboard:
        onboard_cmd = (
            f"cd {SEO_APP} && php scripts/production-site-profile-validation.php "
            "--site=amiantix --keyword='diagnostic amiante avant travaux' --onboard --skip-generate"
        )
        code, out, err = run(onboard_cmd, timeout=1200)
        print(out)
        if err.strip():
            print(err, file=sys.stderr)
        if code != 0:
            print(f"onboarding failed exit={code}", file=sys.stderr)
            return code

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
