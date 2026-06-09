#!/usr/bin/env python3
import paramiko

HOST = "217.160.63.27"
USER = "root"
PASSWORD = "HqZfSb0XdSTCy"
APP = "/var/www/seo-engine/seo-engine-app"

PHP = f"""<?php
require '{APP}/vendor/autoload.php';
$app = require '{APP}/bootstrap/app.php';
$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
$sites = App\\Models\\SeoSite::query()->get();
echo json_encode($sites->map(fn ($s) => [
    'site_id' => $s->site_id,
    'name' => $s->name,
    'url' => $s->url,
    'niche' => $s->niche,
    'preset' => $s->preset,
    'profile_status' => data_get($s->settings_json, 'site_profile.status'),
])->all(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
"""

client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
client.connect(HOST, username=USER, password=PASSWORD, timeout=20)
sftp = client.open_sftp()
with sftp.file("/tmp/_list_sites.php", "w") as f:
    f.write(PHP)
sftp.close()
_, stdout, stderr = client.exec_command("php /tmp/_list_sites.php", timeout=60)
print(stdout.read().decode("utf-8", errors="replace"))
err = stderr.read().decode("utf-8", errors="replace")
if err.strip():
    print("STDERR:", err)
client.close()
