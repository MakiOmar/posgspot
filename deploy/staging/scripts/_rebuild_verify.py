#!/usr/bin/env python3
"""Rebuild POS/Accounts/storefront with fixed Dockerfiles; verify vendor; smoke."""
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


def run(cmd: str, timeout: int = 3600) -> tuple[int, str]:
    print(f"\n>>> {cmd[:200]}", flush=True)
    _, o, e = c.exec_command(cmd, timeout=timeout, get_pty=True)
    text = o.read().decode("utf-8", "replace") + e.read().decode("utf-8", "replace")
    code = o.channel.recv_exit_status()
    print(text[-10000:], flush=True)
    print(f"[exit {code}]", flush=True)
    return code, text


with SCPClient(c.get_transport()) as scp:
    for rel in ("pos/Dockerfile", "accounts/Dockerfile", "storefront/Dockerfile", "docker-compose.yml"):
        scp.put(str(ROOT / rel), f"/opt/spot-staging/{rel}")
        print("uploaded", rel, flush=True)

# Build one at a time so failures are visible
for svc in ("pos", "accounts", "storefront"):
    code, out = run(
        f"cd /opt/spot-staging && docker compose build --no-cache --progress=plain {svc} 2>&1 | tail -100",
        timeout=2400,
    )
    if code != 0 or ("ERROR" in out and "failed to solve" in out.lower()):
        # detect composer failure in tail
        if "ERROR" in out or code != 0:
            print(f"BUILD FAILED: {svc}", flush=True)
            sys.exit(1)

code, _ = run(
    """
set -euo pipefail
cd /opt/spot-staging
docker compose up -d --force-recreate pos accounts storefront pos-queue pos-scheduler accounts-queue
sleep 8
docker compose ps
docker compose exec -T pos test -f /var/www/html/vendor/autoload.php
docker compose exec -T accounts test -f /var/www/html/vendor/autoload.php
docker compose exec -T -u www-data pos php artisan package:discover --ansi || true
docker compose exec -T -u www-data pos php artisan storage:link || true
docker compose exec -T -u www-data pos php artisan config:clear
docker compose exec -T -u www-data accounts php artisan package:discover --ansi || true
docker compose exec -T -u www-data accounts php artisan storage:link || true
docker compose exec -T -u www-data accounts php artisan config:clear || true
echo VENDOR_OK
""",
    timeout=300,
)
if code != 0:
    run("cd /opt/spot-staging && docker compose logs --tail=40 pos-queue storefront 2>&1")
    sys.exit(code)

code, _ = run(
    """
set -euo pipefail
echo '=== Traefik POS ==='
curl -sI -H 'Host: pos.thespotmanagment.io' http://127.0.0.1/ | head -15
echo '=== settings ==='
curl -s -H 'Host: pos.thespotmanagment.io' -H 'Accept: application/json' \
  'http://127.0.0.1/api/storefront/v1/settings' | head -c 500
echo
echo '=== Accounts ==='
curl -sI -H 'Host: accounts.thespotmanagment.io' http://127.0.0.1/ | head -12
echo '=== Shop ==='
curl -sI -H 'Host: thespotmanagment.io' http://127.0.0.1/ | head -12
curl -s -H 'Host: thespotmanagment.io' http://127.0.0.1/ | head -c 350
echo
docker compose -f /opt/spot-staging/docker-compose.yml ps
echo FINAL_SMOKE_OK
""",
    timeout=120,
)
c.close()
sys.exit(0 if code == 0 else code)
