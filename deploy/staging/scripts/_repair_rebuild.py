#!/usr/bin/env python3
"""Upload fixed Docker/Traefik configs and rebuild all app services."""
from __future__ import annotations

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


def run(cmd: str, timeout: int = 3600) -> int:
    print(f"\n>>> {cmd[:200]}", flush=True)
    _, o, e = c.exec_command(cmd, timeout=timeout, get_pty=True)
    text = o.read().decode("utf-8", "replace") + e.read().decode("utf-8", "replace")
    print(text[-8000:], flush=True)
    return o.channel.recv_exit_status()


files = [
    "docker-compose.yml",
    "pos/Dockerfile",
    "accounts/Dockerfile",
    "storefront/Dockerfile",
    "traefik/traefik.yml",
    "traefik/dynamic.yml",
]
with SCPClient(c.get_transport()) as scp:
    for rel in files:
        local = ROOT / rel
        remote = f"/opt/spot-staging/{rel}"
        print(f"upload {rel}", flush=True)
        scp.put(str(local), remote)

# Ensure dynamic.yml exists
run("mkdir -p /opt/spot-staging/traefik && test -f /opt/spot-staging/traefik/dynamic.yml")

code = run(
    """
set -euo pipefail
cd /opt/spot-staging
docker compose up -d traefik
docker compose build --no-cache pos accounts storefront 2>&1
docker compose up -d --force-recreate pos accounts storefront pos-queue pos-scheduler accounts-queue
sleep 5
docker compose ps
""",
    timeout=2400,
)
if code != 0:
    run("cd /opt/spot-staging && docker compose logs --tail=60 pos storefront traefik 2>&1 | tail -200")
    sys.exit(code)

run(
    """
set -euo pipefail
cd /opt/spot-staging
docker compose exec -T pos ls vendor/autoload.php
docker compose exec -T -u www-data pos php artisan storage:link || true
docker compose exec -T -u www-data pos php artisan config:clear
docker compose exec -T -u www-data accounts php artisan config:clear || true
echo '--- direct POS ---'
curl -sI http://127.0.0.1:8080/ 2>/dev/null | head -5 || docker compose exec -T pos curl -sI http://127.0.0.1:8080/ | head -10
echo '--- via Traefik Host ---'
curl -sI -H 'Host: pos.thespotmanagment.io' http://127.0.0.1/ | head -15
curl -s -H 'Host: pos.thespotmanagment.io' -H 'Accept: application/json' \
  http://127.0.0.1/api/storefront/v1/settings | head -c 400
echo
curl -sI -H 'Host: accounts.thespotmanagment.io' http://127.0.0.1/ | head -12
curl -sI -H 'Host: thespotmanagment.io' http://127.0.0.1/ | head -12
curl -s -H 'Host: thespotmanagment.io' http://127.0.0.1/ | head -c 300
echo
echo REPAIR_SMOKE_DONE
""",
    timeout=180,
)
c.close()
