#!/usr/bin/env python3
"""Enable vsftpd listing of hidden (dot) files."""
from __future__ import annotations

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

c = paramiko.SSHClient()
c.set_missing_host_key_policy(paramiko.AutoAddPolicy())
c.connect(
    "82.29.178.160",
    username="root",
    password=root_pw,
    timeout=30,
    allow_agent=False,
    look_for_keys=False,
)

cmd = r"""
set -euo pipefail
CONF=/etc/vsftpd.conf
if grep -q '^force_dot_files=' "$CONF"; then
  sed -i 's/^force_dot_files=.*/force_dot_files=YES/' "$CONF"
else
  echo 'force_dot_files=YES' >> "$CONF"
fi
sed -i '/^hide_file=/d;/^deny_file=/d' "$CONF"
chmod 640 /opt/spot-staging/.env /opt/spot-staging/pos/.env /opt/spot-staging/accounts/.env 2>/dev/null || true
chown spotftp:www-data /opt/spot-staging/.env /opt/spot-staging/pos/.env /opt/spot-staging/accounts/.env 2>/dev/null || true
systemctl restart vsftpd
systemctl is-active vsftpd
echo '--- conf ---'
grep -E 'force_dot|hide_file|deny_file' "$CONF" || echo '(no hide/deny rules)'
echo '--- listing ---'
su -s /bin/bash spotftp -c 'ls -la /opt/spot-staging' | head -25
"""
_, o, e = c.exec_command(cmd, timeout=60, get_pty=True)
print(o.read().decode("utf-8", "replace"))
print(e.read().decode("utf-8", "replace"))
c.close()
