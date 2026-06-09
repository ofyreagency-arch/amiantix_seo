#!/usr/bin/env python3
"""Exécute la validation SiteProfile en production via SSH (usage interne)."""
from __future__ import annotations

import json
import sys
from pathlib import Path

import paramiko

HOST = "217.160.63.27"
USER = "root"
PASSWORD = "HqZfSb0XdSTCy"
APP = "/var/www/seo-engine/seo-engine-app"
LOCAL_APP = Path(__file__).resolve().parents[1]


def run(client: paramiko.SSHClient, cmd: str, timeout: int = 600) -> tuple[int, str, str]:
    _, stdout, stderr = client.exec_command(cmd, timeout=timeout)
    out = stdout.read().decode("utf-8", errors="replace")
    err = stderr.read().decode("utf-8", errors="replace")
    code = stdout.channel.recv_exit_status()
    return code, out, err


def ensure_remote_dir(sftp: paramiko.SFTPClient, remote_dir: str) -> None:
    parts = remote_dir.strip("/").split("/")
    cur = ""
    for part in parts:
        cur = f"{cur}/{part}" if cur else f"/{part}"
        try:
            sftp.stat(cur)
        except FileNotFoundError:
            sftp.mkdir(cur)


def upload_file(sftp: paramiko.SFTPClient, local: Path, remote: str) -> None:
    ensure_remote_dir(sftp, "/".join(remote.split("/")[:-1]))
    sftp.put(str(local), remote)


def upload_tree(sftp: paramiko.SFTPClient, local_dir: Path, remote_dir: str, pattern: str) -> None:
    for path in local_dir.rglob(pattern):
        if not path.is_file():
            continue
        rel = path.relative_to(local_dir).as_posix()
        remote = f"{remote_dir}/{rel}"
        remote_parent = "/".join(remote.split("/")[:-1])
        try:
            sftp.stat(remote_parent)
        except FileNotFoundError:
            parts = remote_parent.split("/")
            cur = ""
            for part in parts:
                if not part:
                    continue
                cur = f"{cur}/{part}" if cur else part
                try:
                    sftp.stat(cur)
                except FileNotFoundError:
                    sftp.mkdir(cur)
        sftp.put(str(path), remote)


def main() -> int:
    sites = sys.argv[1:] if len(sys.argv) > 1 else ["amiantix", "symfony-bridge-lab"]
    client = paramiko.SSHClient()
    client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    client.connect(HOST, username=USER, password=PASSWORD, timeout=20)
    sftp = client.open_sftp()

    try:
        # Sync validation script + SiteProfile code
        upload_file(
            sftp,
            LOCAL_APP / "scripts/production-site-profile-validation.php",
            f"{APP}/scripts/production-site-profile-validation.php",
        )

        upload_dirs = [
            ("app/Understanding", "*.php"),
            ("app/SeoPresets/SiteAware", "*.php"),
            ("app/Exceptions/SiteProfileNotReadyException.php", None),
            ("app/Jobs/RunSiteOnboardingJob.php", None),
        ]

        vendor_gen = LOCAL_APP.parent.parent / "src/Services/Generation/SeoGenerationService.php"
        if vendor_gen.is_file():
            upload_file(
                sftp,
                vendor_gen,
                f"{APP}/vendor/ofyre/seo-engine/src/Services/Generation/SeoGenerationService.php",
            )

        for item in [
            "app/Understanding/SiteProfileBuilder.php",
            "app/Understanding/SiteProfileGate.php",
            "app/Understanding/SiteOnboardingService.php",
            "app/Exceptions/SiteProfileNotReadyException.php",
            "app/Jobs/RunSiteOnboardingJob.php",
            "app/Models/SeoSite.php",
            "app/Runtime/SeoEngineContext.php",
            "app/Jobs/RunObservedSiteCrawlJob.php",
            "app/SeoBridge/Drivers/OpenAiSeoGenerationDriver.php",
            "app/Services/Preset/PresetManager.php",
            "app/Services/Preset/PresetPromptProfile.php",
            "app/RemoteInstallation/RemoteInstallationService.php",
            "app/Http/Controllers/Api/SeoBridgeConnectController.php",
            "config/seo-engine.php",
        ]:
            local = LOCAL_APP / item
            if local.is_file():
                remote = f"{APP}/{item.replace(chr(92), '/')}"
                upload_file(sftp, local, remote)

        site_aware = LOCAL_APP / "app/SeoPresets/SiteAware"
        if site_aware.is_dir():
            for php in site_aware.glob("*.php"):
                upload_file(sftp, php, f"{APP}/app/SeoPresets/SiteAware/{php.name}")

        code, out, err = run(client, f"cd {APP} && php artisan optimize:clear 2>&1", 120)
        print("== optimize:clear ==", out or err)

        code, out, err = run(
            client,
            f"cd {APP} && php -r \"require 'vendor/autoload.php'; $app=require 'bootstrap/app.php'; $app->make(Illuminate\\\\Contracts\\\\Console\\\\Kernel::class)->bootstrap(); echo json_encode(App\\\\Models\\\\SeoSite::query()->get(['site_id','name','url','niche','preset'])->all(), JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);\"",
            60,
        )
        print("== sites ==\n", out or err)

        reports = {}
        keywords = {
            "amiantix": "diagnostic amiante avant travaux en copropriété",
            "symfony-bridge-lab": "publication ressources techniques symfony",
            "symfony_bridge_lab": "publication ressources techniques symfony",
        }

        for site_id in sites:
            kw = keywords.get(site_id, "services professionnels locaux")
            out_file = f"/tmp/site-profile-validation-{site_id}.json"
            cmd = (
                f"cd {APP} && php scripts/production-site-profile-validation.php "
                f"--site={site_id} --keyword=\"{kw}\" --onboard --output={out_file} 2>&1"
            )
            print(f"\n== VALIDATION {site_id} ==\n")
            code, out, err = run(client, cmd, timeout=900)
            sys.stdout.buffer.write(out.encode("utf-8", errors="replace") + b"\n")
            if err:
                print("STDERR:", err)
            code2, json_out, _ = run(client, f"cat {out_file} 2>/dev/null || echo '{{}}'", 30)
            try:
                reports[site_id] = json.loads(json_out)
            except json.JSONDecodeError:
                reports[site_id] = {"raw": json_out, "exit_code": code}

        compare_path = "/tmp/site-profile-validation-compare.json"
        local_compare = LOCAL_APP / "storage/app/site-profile-production-validation.json"
        local_compare.parent.mkdir(parents=True, exist_ok=True)
        local_compare.write_text(json.dumps(reports, ensure_ascii=False, indent=2), encoding="utf-8")
        print(f"\nRapport local: {local_compare}")
        return 0
    finally:
        sftp.close()
        client.close()


if __name__ == "__main__":
    raise SystemExit(main())
