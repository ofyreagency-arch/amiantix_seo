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


def upload_and_run(client: paramiko.SSHClient, local_script: Path, remote_name: str, cmd: str, timeout: int = 1200) -> tuple[int, str, str]:
    remote = f"{APP}/scripts/{remote_name}"
    with client.open_sftp() as sftp:
        with sftp.file(remote, "w") as handle:
            handle.write(local_script.read_text(encoding="utf-8"))

    _, stdout, stderr = client.exec_command(cmd, timeout=timeout)
    out = stdout.read().decode("utf-8", errors="replace")
    err = stderr.read().decode("utf-8", errors="replace")
    code = stdout.channel.recv_exit_status()
    return code, out, err


def main() -> int:
    generate = "--generate" in sys.argv
    scripts_dir = Path(__file__).resolve().parent

    client = paramiko.SSHClient()
    client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    client.connect(HOST, username="root", password=password(), timeout=30)

    profile_out = OUT_DIR / "amiantix-site-profile-inspection.json"
    code, out, err = upload_and_run(
        client,
        scripts_dir / "onboard-and-inspect-profile.php",
        "onboard-and-inspect-profile.php",
        f"cd {APP} && php scripts/onboard-and-inspect-profile.php --site=amiantix --onboard",
        timeout=1800,
    )

    if err.strip():
        print(err, file=sys.stderr)

    # JSON is last block in output - find first {
    start = out.find("{")
    if start < 0:
        print(out)
        return code or 1

    data = json.loads(out[start:])
    profile_out.write_text(json.dumps(data, ensure_ascii=False, indent=2), encoding="utf-8")

    summary = {
        "site_profile_status": data.get("site_profile_status"),
        "editorial_topics_count": data.get("editorial_topics_count"),
        "editorial_topics_top15": data.get("editorial_topics_top15") or (data.get("editorial_topics") or [])[:15],
        "editorial_topics_count": data.get("editorial_topics_count"),
        "services": data.get("services"),
        "topic_violations": data.get("topic_violations"),
        "topics_clean": data.get("topics_clean"),
        "next_profile_keyword": data.get("next_profile_keyword"),
        "next_candidate_keyword": data.get("next_candidate_keyword"),
    }
    print(json.dumps(summary, ensure_ascii=False, indent=2))

    if not data.get("topics_clean"):
        print("TOPIC_VIOLATIONS_DETECTED", file=sys.stderr)
        return 2

    if not generate:
        print("\nProfile OK. Run again with --generate to create test articles.")
        return code

    topics = list(data.get("editorial_topics") or [])[:3]
    if not topics:
        print("No editorial topics to generate", file=sys.stderr)
        return 2

    topics_arg = "|".join(topics)
    gen_out = OUT_DIR / "amiantix-test-articles.json"
    code, out, err = upload_and_run(
        client,
        scripts_dir / "generate-test-articles.php",
        "generate-test-articles.php",
        f"cd {APP} && php scripts/generate-test-articles.php --site=amiantix --topics={topics_arg!r}",
        timeout=1800,
    )
    client.close()

    if err.strip():
        print(err, file=sys.stderr)

    start = out.rfind("{")
    if start >= 0:
        gen_data = json.loads(out[start:])
        gen_out.write_text(json.dumps(gen_data, ensure_ascii=False, indent=2), encoding="utf-8")
        print(json.dumps(gen_data, ensure_ascii=False, indent=2))

    return code


if __name__ == "__main__":
    raise SystemExit(main())
