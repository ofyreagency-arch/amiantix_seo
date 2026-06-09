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


def load_json_from_output(out: str, marker: str | None = None) -> dict:
    if marker:
        marker_pos = out.rfind(marker)
        if marker_pos < 0:
            raise ValueError(f"marker not found: {marker}")
        start = out.rfind("{", 0, marker_pos)
    else:
        start = out.find("{")

    if start < 0:
        raise ValueError("no JSON object in output")

    decoder = json.JSONDecoder()
    data, _ = decoder.raw_decode(out[start:])
    return data


def main() -> int:
    generate = "--generate" in sys.argv
    skip_onboard = "--skip-onboard" in sys.argv
    scripts_dir = Path(__file__).resolve().parent
    profile_out = OUT_DIR / "amiantix-site-profile-inspection.json"

    if skip_onboard and profile_out.is_file():
        data = json.loads(profile_out.read_text(encoding="utf-8"))
        print("Using cached profile:", profile_out, file=sys.stderr)
    else:
        client = paramiko.SSHClient()
        client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
        client.connect(HOST, username="root", password=password(), timeout=30)

        code, out, err = upload_and_run(
            client,
            scripts_dir / "onboard-and-inspect-profile.php",
            "onboard-and-inspect-profile.php",
            f"cd {APP} && php scripts/onboard-and-inspect-profile.php --site=amiantix --onboard",
            timeout=1800,
        )

        if err.strip():
            print(err, file=sys.stderr)

        try:
            data = load_json_from_output(out)
        except ValueError:
            print(out)
            return code or 1

        profile_out.write_text(json.dumps(data, ensure_ascii=False, indent=2), encoding="utf-8")
        client.close()

        summary = {
            "site_profile_status": data.get("site_profile_status"),
            "editorial_topics_count": data.get("editorial_topics_count"),
            "editorial_topics_top15": data.get("editorial_topics_top15") or (data.get("editorial_topics") or [])[:15],
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
        return 0

    client = paramiko.SSHClient()
    client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    client.connect(HOST, username="root", password=password(), timeout=30)

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

    try:
        gen_data = load_json_from_output(out, '"generated_at"')
    except ValueError:
        print(out, file=sys.stderr)
        return code or 1

    gen_out.write_text(json.dumps(gen_data, ensure_ascii=False, indent=2), encoding="utf-8")
    print(json.dumps(gen_data, ensure_ascii=False, indent=2))

    return code


if __name__ == "__main__":
    raise SystemExit(main())
