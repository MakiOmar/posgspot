#!/usr/bin/env python3
import os
import sys
from pathlib import Path

import paramiko
from scp import SCPClient

ROOT = Path(__file__).resolve().parents[1]
sys.stdout.reconfigure(encoding="utf-8", errors="replace")
c = paramiko.SSHClient()
c.set_missing_host_key_policy(paramiko.AutoAddPolicy())
c.connect(
    "82.29.178.160",
    username="root",
    password=os.environ["VPS_SSH_PASS"],
    timeout=60,
    allow_agent=False,
    look_for_keys=False,
)
with SCPClient(c.get_transport()) as scp:
    scp.put(str(ROOT / "docker-compose.yml"), "/opt/spot-staging/docker-compose.yml")

cmd = r"""
set -euo pipefail
cd /opt/spot-staging
POS_IMG=$(docker inspect spot-staging-pos-1 --format '{{.Image}}')
ACC_IMG=$(docker inspect spot-staging-accounts-1 --format '{{.Image}}')
docker tag "$POS_IMG" spot-staging/pos:latest
docker tag "$ACC_IMG" spot-staging/accounts:latest
docker compose up -d --force-recreate --no-build pos-queue pos-scheduler accounts-queue
sleep 8
docker compose ps
echo '--- queue logs ---'
docker compose logs --tail=20 pos-queue
echo '--- scheduler ---'
docker compose logs --tail=10 pos-scheduler
echo '--- accounts-queue ---'
docker compose logs --tail=10 accounts-queue
echo QUEUES_FIXED
"""
_, o, e = c.exec_command(cmd, timeout=180, get_pty=True)
print((o.read() + e.read()).decode("utf-8", "replace")[-10000:])
c.close()
