#!/usr/bin/env python3
import json
import paramiko

HOST = "217.160.63.27"
USER = "root"
PASSWORD = "HqZfSb0XdSTCy"
APP = "/var/www/seo-engine/seo-engine-app"

client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
client.connect(HOST, username=USER, password=PASSWORD, timeout=20)

for site in ["amiantix", "symfony-bridge-lab"]:
  for path in [f"/tmp/site-profile-validation-{site}.json"]:
    _, stdout, _ = client.exec_command(f"test -f {path} && cat {path} || echo '{{}}'", timeout=120)
    raw = stdout.read().decode("utf-8", errors="replace")
    try:
      data = json.loads(raw)
    except json.JSONDecodeError:
      data = {"error": "invalid_json", "raw": raw[:500]}
    out = f"c:/Users/donov/Desktop/ofyre-seo-engine-main/seo-engine-app/storage/app/prod-{site}.json"
    with open(out.replace("/", "\\"), "w", encoding="utf-8") as f:
      json.dump(data, f, ensure_ascii=False, indent=2)
    print(site, "status=", data.get("site_profile_status"), "keys=", list(data.keys())[:8])

client.close()
