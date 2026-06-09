#!/usr/bin/env python3
import json
import sys
from pathlib import Path

import paramiko

HOST = "217.160.63.27"
USER = "root"
PASSWORD = "HqZfSb0XdSTCy"
APP = "/var/www/seo-engine/seo-engine-app"
LOCAL_APP = Path(__file__).resolve().parents[1]


def upload_file(sftp, local, remote):
    parts = remote.strip("/").split("/")
    cur = ""
    for part in parts[:-1]:
        cur = f"{cur}/{part}" if cur else f"/{part}"
        try:
            sftp.stat(cur)
        except FileNotFoundError:
            sftp.mkdir(cur)
    sftp.put(str(local), remote)


client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
client.connect(HOST, username=USER, password=PASSWORD, timeout=20)
sftp = client.open_sftp()
upload_file(sftp, LOCAL_APP / "scripts/production-site-profile-validation.php", f"{APP}/scripts/production-site-profile-validation.php")
vendor_gen = LOCAL_APP.parent.parent / "src/Services/Generation/SeoGenerationService.php"
if vendor_gen.is_file():
    upload_file(sftp, vendor_gen, f"{APP}/vendor/ofyre/seo-engine/src/Services/Generation/SeoGenerationService.php")
upload_file(sftp, LOCAL_APP / "app/SeoPresets/SiteAware/SiteAwareImagePromptProvider.php", f"{APP}/app/SeoPresets/SiteAware/SiteAwareImagePromptProvider.php")
sftp.close()

kw = "publication ressources techniques symfony"
out = "/tmp/site-profile-validation-symfony-bridge-lab.json"

cmd = (
    f"cd {APP} && php scripts/production-site-profile-validation.php "
    f"--site=symfony-bridge-lab --keyword='{kw}' --output={out}"
)
_, stdout, stderr = client.exec_command(cmd, timeout=900)
data = (stdout.read() + stderr.read()).decode("utf-8", errors="replace")
Path(LOCAL_APP / "storage/app/symfony-validation-raw.txt").write_text(data, encoding="utf-8")
_, stdout, _ = client.exec_command(f"cat {out}", timeout=60)
report = json.loads(stdout.read().decode("utf-8", errors="replace") or "{}")
Path(LOCAL_APP / "storage/app/prod-symfony-bridge-lab.json").write_text(
    json.dumps(report, ensure_ascii=False, indent=2), encoding="utf-8"
)
print("status", report.get("site_profile_status"), "gen_error", report.get("generation_error"))
client.close()
