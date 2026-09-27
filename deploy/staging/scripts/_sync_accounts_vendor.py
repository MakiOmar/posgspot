#!/usr/bin/env python3
"""Rsync Accounts public/vendor from Hostinger and rebuild image."""
from __future__ import annotations

import os
import sys
from pathlib import Path

import paramiko

SECRETS = Path(__file__).resolve().parents[1] / ".vps-secrets.env"
sys.stdout.reconfigure(encoding="utf-8", errors="replace")

root_pw = None
for line in SECRETS.read_text(encoding="utf-8").splitlines():
    if line.startswith("VPS_ROOT_PASSWORD="):
        root_pw = line.split("=", 1)[1].strip()

hostinger_pass = os.environ.get("HOSTINGER_SSH_PASS", "")
if not hostinger_pass:
    print("Set HOSTINGER_SSH_PASS", file=sys.stderr)
    sys.exit(1)
if not root_pw:
    print("Missing VPS_ROOT_PASSWORD", file=sys.stderr)
    sys.exit(1)

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

# Write hostinger pass without shell expansion issues
sftp = c.open_sftp()
with sftp.file("/root/.hostinger_pass", "w") as f:
    f.write(hostinger_pass)
sftp.chmod("/root/.hostinger_pass", 0o600)
sftp.close()

cmd = r"""
set -euo pipefail
export SSHPASS=$(cat /root/.hostinger_pass)
RSH='sshpass -e ssh -p 65002 -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null'
REMOTE=u605441708@93.127.205.20:/home/u605441708/domains/gamesspoteg.com/public_html/accounts.gamesspoteg.com/public/vendor/
echo '==> remote size'
sshpass -e ssh -p 65002 -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null \
  u605441708@93.127.205.20 'du -sh domains/gamesspoteg.com/public_html/accounts.gamesspoteg.com/public/vendor; ls domains/gamesspoteg.com/public_html/accounts.gamesspoteg.com/public/vendor | head'
echo '==> rsync'
mkdir -p /opt/spot-staging/accounts/public/vendor
rsync -az --delete -e "$RSH" "$REMOTE" /opt/spot-staging/accounts/public/vendor/
chown -R spotftp:www-data /opt/spot-staging/accounts/public/vendor
du -sh /opt/spot-staging/accounts/public/vendor
test -f /opt/spot-staging/accounts/public/vendor/jquery/jquery.min.js
echo '==> rebuild accounts'
cd /opt/spot-staging
docker compose build accounts
docker compose up -d --no-deps --force-recreate --build accounts accounts-queue
# ensure shared tag
AID=$(docker inspect spot-staging-accounts-1 --format '{{.Image}}')
docker tag "$AID" spot-staging/accounts:latest
docker compose up -d --no-deps --force-recreate accounts-queue
sleep 4
docker compose exec -T accounts test -f /var/www/html/public/vendor/jquery/jquery.min.js
echo '==> HTTP check'
curl -sI https://accounts.thespotmanagment.io/vendor/jquery/jquery.min.js | head -15
curl -sI https://accounts.thespotmanagment.io/vendor/adminlte/dist/css/adminlte.min.css | head -12
rm -f /root/.hostinger_pass
echo VENDOR_OK
"""

_, o, e = c.exec_command(cmd, timeout=1800, get_pty=True)
text = o.read().decode("utf-8", "replace") + e.read().decode("utf-8", "replace")
print(text[-10000:])
code = o.channel.recv_exit_status()
c.close()
sys.exit(code)
