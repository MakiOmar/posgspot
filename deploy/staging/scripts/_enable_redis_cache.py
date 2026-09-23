#!/usr/bin/env python3
"""Point POS + Accounts cache at Redis; raise storefront read throttle for load tests."""
from __future__ import annotations

import os
import sys

import paramiko

sys.stdout.reconfigure(encoding="utf-8", errors="replace")
pw = None
for line in open(
    os.path.join(os.path.dirname(__file__), "..", ".vps-secrets.env"), encoding="utf-8"
):
    if line.startswith("VPS_ROOT_PASSWORD="):
        pw = line.split("=", 1)[1].strip()
if not pw:
    pw = os.environ.get("VPS_SSH_PASS") or os.environ.get("VPS_ROOT_PASSWORD")
if not pw:
    print("Need VPS_ROOT_PASSWORD in .vps-secrets.env", file=sys.stderr)
    sys.exit(1)

c = paramiko.SSHClient()
c.set_missing_host_key_policy(paramiko.AutoAddPolicy())
c.connect(
    "82.29.178.160",
    username="root",
    password=pw,
    timeout=60,
    allow_agent=False,
    look_for_keys=False,
)


def run(cmd: str, timeout: int = 120) -> str:
    print(f">>> {cmd[:160]}", flush=True)
    _, o, e = c.exec_command(cmd, timeout=timeout, get_pty=True)
    text = o.read().decode("utf-8", "replace") + e.read().decode("utf-8", "replace")
    print(text[-5000:], flush=True)
    return text


# Rewrite cache + redis keys (idempotent sed)
run(
    r"""
set -euo pipefail
rewrite() {
  local f="$1" k="$2" v="$3"
  if grep -q "^${k}=" "$f"; then sed -i "s|^${k}=.*|${k}=${v}|" "$f"
  else echo "${k}=${v}" >> "$f"; fi
}
POS=/opt/spot-staging/pos/.env
ACC=/opt/spot-staging/accounts/.env
for f in "$POS" "$ACC"; do
  rewrite "$f" REDIS_HOST redis
  rewrite "$f" REDIS_PORT 6379
  rewrite "$f" REDIS_CLIENT phpredis
  rewrite "$f" CACHE_DRIVER redis
  rewrite "$f" CACHE_STORE redis
done
rewrite "$POS" REDIS_CACHE_DB 0
rewrite "$ACC" REDIS_CACHE_DB 1
# Real capacity: raise storefront per-IP read throttle for staging load tests
rewrite "$POS" STOREFRONT_RATE_LIMIT_READ 6000
rewrite "$POS" STOREFRONT_RATE_LIMIT 600
grep -E '^(CACHE_|REDIS_|STOREFRONT_RATE)' "$POS" "$ACC"
"""
)

run(
    """
set -euo pipefail
cd /opt/spot-staging
docker compose up -d --force-recreate --no-build pos accounts pos-queue pos-scheduler accounts-queue
sleep 8
docker compose exec -T -u www-data pos php artisan config:clear
docker compose exec -T -u www-data accounts php artisan config:clear
# Verify Redis cache works
docker compose exec -T -u www-data pos php artisan tinker --execute="Cache::put('stg_probe', 'ok', 60); echo Cache::get('stg_probe').PHP_EOL; echo config('cache.default').PHP_EOL;"
docker compose exec -T -u www-data accounts php artisan tinker --execute="Cache::put('stg_probe_acc', 'ok', 60); echo Cache::get('stg_probe_acc').PHP_EOL; echo config('cache.default').PHP_EOL;"
docker compose exec -T redis redis-cli DBSIZE
docker compose exec -T redis redis-cli -n 0 KEYS '*stg_probe*'
docker compose exec -T redis redis-cli -n 1 KEYS '*stg_probe*'
echo REDIS_CACHE_OK
"""
)
c.close()
