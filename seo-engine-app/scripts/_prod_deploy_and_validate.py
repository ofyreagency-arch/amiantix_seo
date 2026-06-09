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
OUT_DIR = Path(__file__).resolve().parents[1] / "storage/app"


def password() -> str:
    text = (ROOT / "_deploy_seo.py").read_text(encoding="utf-8")
    match = re.search(r'password="([^"]+)"', text)
    if not match:
        raise RuntimeError("SSH password not found")
    return match.group(1)


def run(client: paramiko.SSHClient, cmd: str, timeout: int = 1800) -> tuple[int, str, str]:
    _, stdout, stderr = client.exec_command(cmd, timeout=timeout)
    out = stdout.read().decode("utf-8", errors="replace")
    err = stderr.read().decode("utf-8", errors="replace")
    code = stdout.channel.recv_exit_status()
    return code, out, err


def upload(client: paramiko.SSHClient, local: Path, remote_name: str) -> None:
    remote = f"{APP}/scripts/{remote_name}"
    with client.open_sftp() as sftp:
        with sftp.file(remote, "w") as handle:
            handle.write(local.read_text(encoding="utf-8"))


def load_json_from_output(out: str, marker: str) -> dict:
    marker_pos = out.rfind(marker)
    if marker_pos < 0:
        raise ValueError(f"marker not found: {marker}")
    start = out.rfind("{", 0, marker_pos)
    if start < 0:
        raise ValueError("no JSON object")
    decoder = json.JSONDecoder()
    data, _ = decoder.raw_decode(out[start:])
    return data


def main() -> int:
    mode = "amiantix"
    if "--multi-niche" in sys.argv:
        mode = "multi-niche"
    if "--deploy-only" in sys.argv:
        mode = "deploy-only"

    scripts_dir = Path(__file__).resolve().parent
    client = paramiko.SSHClient()
    client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    client.connect(HOST, username="root", password=password(), timeout=30)

    deploy_cmd = """
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
    code, out, err = run(client, deploy_cmd, timeout=900)
    print(out)
    if err.strip():
        print(err, file=sys.stderr)
    if code != 0:
        return code

    if mode == "deploy-only":
        client.close()
        return 0

    for script_name in [
        "regenerate-and-validate-article.php",
        "multi-niche-generation-validation.php",
        "PublishedContentValidationService.php",
    ]:
        local = scripts_dir / script_name
        if local.is_file():
            upload(client, local, script_name)

    # Upload new app classes via git is preferred; local upload fallback for scripts only.

    if mode == "amiantix":
        upload(client, scripts_dir / "regenerate-and-validate-article.php", "regenerate-and-validate-article.php")
        cmd = (
            f"cd {APP} && php scripts/regenerate-and-validate-article.php "
            "--site=amiantix --keyword='diagnostic amiante avant travaux'"
        )
        code, out, err = run(client, cmd, timeout=1800)
        if err.strip():
            print(err, file=sys.stderr)
        data = load_json_from_output(out, '"generated_at"')
        out_path = OUT_DIR / "amiantix-regenerated-article.json"
        out_path.write_text(json.dumps(data, ensure_ascii=False, indent=2), encoding="utf-8")
        print(json.dumps(data, ensure_ascii=False, indent=2))
        client.close()
        return code if data.get("published_validation", {}).get("ok") else 3

    upload(client, scripts_dir / "multi-niche-generation-validation.php", "multi-niche-generation-validation.php")
    cmd = f"cd {APP} && php scripts/multi-niche-generation-validation.php --lab-site=niche-lab"
    code, out, err = run(client, cmd, timeout=7200)
    if err.strip():
        print(err, file=sys.stderr)
    data = load_json_from_output(out, '"validated_at"')
    out_path = OUT_DIR / "multi-niche-validation.json"
    out_path.write_text(json.dumps(data, ensure_ascii=False, indent=2), encoding="utf-8")
    print(json.dumps(data, ensure_ascii=False, indent=2))
    client.close()
    return code if data.get("multi_niche_ok") else 3


if __name__ == "__main__":
    raise SystemExit(main())
