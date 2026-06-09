#!/usr/bin/env python3
import paramiko
import sys

HOST = "217.160.63.27"
USER = "root"
PASSWORD = "HqZfSb0XdSTCy"
APP = "/var/www/seo-engine/seo-engine-app"
REMOTE_PHP = "/tmp/_praeviseo_bridge_audit.php"

PHP = """<?php
require '/var/www/seo-engine/seo-engine-app/vendor/autoload.php';
$app = require '/var/www/seo-engine/seo-engine-app/bootstrap/app.php';
$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();

$s = App\\Models\\SeoSite::where('site_id', 'amiantix')->first();
$pub = data_get($s->settings_json, 'publication', []);

$installations = App\\Models\\RemoteInstallation::query()
    ->where('site_id', 'amiantix')
    ->orderByDesc('id')
    ->get(['id','status','current_step','progress','hosting_provider','connection_type','detected_framework','created_at','updated_at','logs_json'])
    ->map(fn ($i) => [
        'id' => $i->id,
        'status' => $i->status,
        'current_step' => $i->current_step,
        'progress' => $i->progress,
        'hosting_provider' => $i->hosting_provider,
        'connection_type' => $i->connection_type,
        'detected_framework' => $i->detected_framework,
        'created_at' => $i->created_at?->toIso8601String(),
        'updated_at' => $i->updated_at?->toIso8601String(),
        'last_log' => collect($i->logs_json ?? [])->last(),
    ])->values()->all();

echo json_encode([
    'site' => [
        'site_id' => $s->site_id,
        'webhook_url_column' => $s->webhook_url,
        'created_at' => $s->created_at?->toIso8601String(),
        'updated_at' => $s->updated_at?->toIso8601String(),
    ],
    'publication' => $pub,
    'remote_installations' => $installations,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
"""

LOG_CMDS = [
    (
        "NGINX_BRIDGE_CONNECT",
        "grep -h 'POST.*api/bridge/connect' /var/log/nginx/access.log /var/log/nginx/access.log.* 2>/dev/null | tail -20 || echo NO_MATCH",
    ),
    (
        "NGINX_INSTALLATION",
        "grep -h 'api/client/sites/amiantix/installation' /var/log/nginx/access.log /var/log/nginx/access.log.* 2>/dev/null | tail -15 || echo NO_MATCH",
    ),
    (
        "LARAVEL_BRIDGE",
        f"grep -hE 'bridge/connect|bridge_status|shared_secret|symfony_connected|publication\\.success|last_push_status' {APP}/storage/logs/laravel*.log 2>/dev/null | tail -60 || echo NO_MATCH",
    ),
    (
        "LARAVEL_INSTALL",
        f"grep -hE 'Remote Symfony|remote installation|installation_requested|RunRemoteInstallation' {APP}/storage/logs/laravel*.log 2>/dev/null | tail -40 || echo NO_MATCH",
    ),
]


def main() -> int:
    client = paramiko.SSHClient()
    client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    client.connect(HOST, username=USER, password=PASSWORD, timeout=20)

    sftp = client.open_sftp()
    with sftp.file(REMOTE_PHP, "w") as f:
        f.write(PHP)
    sftp.close()

    print("===== DB_SNAPSHOT =====")
    _, stdout, stderr = client.exec_command(f"php {REMOTE_PHP}", timeout=60)
    print(stdout.read().decode("utf-8", errors="replace"))
    err = stderr.read().decode("utf-8", errors="replace")
    if err.strip():
        print("STDERR:", err)

    for label, cmd in LOG_CMDS:
        print(f"===== {label} =====")
        _, stdout, stderr = client.exec_command(cmd, timeout=60)
        out = stdout.read().decode("utf-8", errors="replace")
        if out.strip():
            print(out)
        print()

    client.exec_command(f"rm -f {REMOTE_PHP}", timeout=10)
    client.close()
    return 0


if __name__ == "__main__":
    sys.exit(main())
