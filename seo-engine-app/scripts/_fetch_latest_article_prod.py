#!/usr/bin/env python3
from __future__ import annotations

import json
import re
from pathlib import Path

import paramiko

ROOT = Path(__file__).resolve().parents[2]
HOST = "217.160.63.27"
APP = "/var/www/seo-engine/seo-engine-app"
OUT = Path(__file__).resolve().parents[1] / "storage/app/latest-generated-article.json"


def password() -> str:
    text = (ROOT / "_deploy_seo.py").read_text(encoding="utf-8")
    match = re.search(r'password="([^"]+)"', text)
    if not match:
        raise RuntimeError("SSH password not found")
    return match.group(1)


def main() -> int:
    script = (Path(__file__).resolve().parent / "fetch-latest-article.php").read_text(encoding="utf-8")

    client = paramiko.SSHClient()
    client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    client.connect(HOST, username="root", password=password(), timeout=30)

    remote = f"{APP}/scripts/fetch-latest-article.php"
    with client.open_sftp() as sftp:
        with sftp.file(remote, "w") as handle:
            handle.write(script)

    _, stdout, stderr = client.exec_command(f"cd {APP} && php scripts/fetch-latest-article.php", timeout=120)
    raw = stdout.read().decode("utf-8", errors="replace").strip()
    err = stderr.read().decode("utf-8", errors="replace").strip()
    client.close()

    if err:
        print(err)

    data = json.loads(raw)
    OUT.write_text(json.dumps(data, ensure_ascii=False, indent=2), encoding="utf-8")

    content = data.get("content") or ""
    print(
        json.dumps(
            {
                "site_id": data.get("site_id"),
                "slug": data.get("slug"),
                "generation_source": data.get("generation_source"),
                "word_count": data.get("word_count"),
                "injections": {
                    "checklist": "Checklist operationnelle" in content or "Checklist opérationnelle" in content,
                    "errors_block": "Erreurs frequentes" in content or "Erreurs fréquentes" in content,
                    "routine": "Routine documentaire" in content,
                    "resources": "Ressources et pages utiles" in content,
                    "bridge_phrase": "C est dans ce passage" in content or "C'est dans ce passage" in content,
                },
            },
            ensure_ascii=False,
            indent=2,
        )
    )

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
