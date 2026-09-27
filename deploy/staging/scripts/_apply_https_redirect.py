#!/usr/bin/env python3
"""Upload compose + recreate edge-facing services for HTTPS redirect."""
from __future__ import annotations

import sys
from pathlib import Path

import paramiko
from scp import SCPClient

ROOT = Path(__file__).resolve().parents[1]
SECRETS = ROOT / ".vps-secrets.env"
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
    timeout=60,
    allow_agent=False,
    look_for_keys=False,
)
with SCPClient(c.get_transport()) as scp:
    scp.put(str(ROOT / "docker-compose.yml"), "/opt/spot-staging/docker-compose.yml")

_, o, e = c.exec_command(
    """
set -euo pipefail
cd /opt/spot-staging
docker compose up -d --no-build --force-recreate pos accounts storefront
sleep 5
echo '--- HTTP accounts (expect 301/308 to https) ---'
curl -sI http://accounts.thespotmanagment.io/manager/login | head -15
echo '--- HTTPS accounts ---'
curl -sI https://accounts.thespotmanagment.io/manager/login | head -12
""",
    timeout=180,
    get_pty=True,
)
print(o.read().decode("utf-8", "replace")[-5000:])
c.close()
