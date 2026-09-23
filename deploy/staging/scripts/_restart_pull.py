#!/usr/bin/env python3
"""Restart Hostinger -> VPS pull and print initial log."""
import os
import sys
import time

import paramiko

PASS = os.environ.get("VPS_SSH_PASS", "")
HOSTINGER_PASS = os.environ.get("HOSTINGER_SSH_PASS", "")
if not PASS or not HOSTINGER_PASS:
    print("Set VPS_SSH_PASS and HOSTINGER_SSH_PASS", file=sys.stderr)
    sys.exit(1)

c = paramiko.SSHClient()
c.set_missing_host_key_policy(paramiko.AutoAddPolicy())
c.connect(
    "82.29.178.160",
    username="root",
    password=PASS,
    timeout=60,
    allow_agent=False,
    look_for_keys=False,
)

# Ensure HOSTINGER_SSH_PASS is in the pull script environment on VPS
cmd = f"""
set -euo pipefail
pkill -f pull-from-hostinger || true
pkill -f 'rsync.*pos.gamesspoteg' || true
pkill -f 'rsync.*accounts.gamesspoteg' || true
pkill -f 'scp.*tmp-spot-staging' || true
sleep 1
export HOSTINGER_SSH_PASS={HOSTINGER_PASS!r}
cd /opt/spot-staging
# Prefer existing Hostinger dumps if present to save time
sshpass -e ssh -p 65002 -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null \
  u605441708@93.127.205.20 'ls -lh ~/tmp-spot-staging/ 2>/dev/null || true'
nohup env HOSTINGER_SSH_PASS="$HOSTINGER_SSH_PASS" bash scripts/pull-from-hostinger.sh > /tmp/spot-pull.log 2>&1 &
echo RESTARTED:$!
sleep 3
ps aux | grep -E 'pull-from|sshpass|rsync|scp' | grep -v grep || true
echo ---LOG---
tail -20 /tmp/spot-pull.log || true
"""
_, stdout, stderr = c.exec_command(cmd, timeout=180)
print(stdout.read().decode("utf-8", "replace"))
err = stderr.read().decode("utf-8", "replace")
if err:
    print("STDERR:", err[:3000])
c.close()
