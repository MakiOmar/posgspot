#!/usr/bin/env python3
"""Finish Accounts custom login theme sync (views + fonts) and rebuild."""
from __future__ import annotations

import os
import sys
from pathlib import Path

import paramiko

SECRETS = Path(__file__).resolve().parents[1] / ".vps-secrets.env"
sys.stdout.reconfigure(encoding="utf-8", errors="replace")
root_pw = next(
    l.split("=", 1)[1].strip()
    for l in SECRETS.read_text(encoding="utf-8").splitlines()
    if l.startswith("VPS_ROOT_PASSWORD=")
)
hpass = os.environ["HOSTINGER_SSH_PASS"]

c = paramiko.SSHClient()
c.set_missing_host_key_policy(paramiko.AutoAddPolicy())
c.connect(
    "82.29.178.160",
    username="root",
    password=root_pw,
    timeout=60,
    allow_agent=False,
    look_for_keys=False,
)
sftp = c.open_sftp()
with sftp.file("/root/.hostinger_pass", "w") as f:
    f.write(hpass)
sftp.chmod("/root/.hostinger_pass", 0o600)
sftp.close()

cmd = r"""
set -euo pipefail
export SSHPASS=$(cat /root/.hostinger_pass)
RSH='sshpass -e ssh -p 65002 -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null'
BASE=u605441708@93.127.205.20:/home/u605441708/domains/gamesspoteg.com/public_html/accounts.gamesspoteg.com

mkdir -p /opt/spot-staging/accounts/resources/views/vendor
rsync -az --delete -e "$RSH" "$BASE/resources/views/vendor/" /opt/spot-staging/accounts/resources/views/vendor/

# Custom fonts referenced by login blade
mkdir -p /opt/spot-staging/accounts/public/assets/fonts
rsync -az -e "$RSH" "$BASE/public/assets/fonts/" /opt/spot-staging/accounts/public/assets/fonts/ || true

# Background images used by login (scan blade)
grep -oE "/assets/[^\"') ]+" /opt/spot-staging/accounts/resources/views/vendor/adminlte/auth/login.blade.php | sort -u || true

# Sync full assets/img if backgrounds live there
rsync -az -e "$RSH" "$BASE/public/assets/img/" /opt/spot-staging/accounts/public/assets/img/ || true

chown -R spotftp:www-data /opt/spot-staging/accounts/resources/views/vendor /opt/spot-staging/accounts/public/assets
wc -l /opt/spot-staging/accounts/resources/views/vendor/adminlte/auth/login.blade.php
ls "/opt/spot-staging/accounts/public/assets/fonts/arista" 2>/dev/null | head || ls /opt/spot-staging/accounts/public/assets/fonts | head

cd /opt/spot-staging
docker compose build --no-cache accounts 2>&1 | tail -40
docker compose up -d --no-deps --force-recreate accounts accounts-queue
sleep 6
docker compose exec -T -u www-data accounts php artisan view:clear
docker compose exec -T accounts test -f /var/www/html/resources/views/vendor/adminlte/auth/login.blade.php
docker compose exec -T accounts wc -l /var/www/html/resources/views/vendor/adminlte/auth/login.blade.php

curl -s https://accounts.thespotmanagment.io/manager/login | grep -oE 'Russo One|Orbitron|PHONE|ACCESS CODE|Arista|login-dark|background-image' | sort | uniq -c
rm -f /root/.hostinger_pass
echo THEME_OK
"""
_, o, e = c.exec_command(cmd, timeout=1800, get_pty=True)
print((o.read() + e.read()).decode("utf-8", "replace")[-10000:])
sys.exit(o.channel.recv_exit_status())
