#!/usr/bin/env python3
"""Apply POS capacity steps on staging VPS: FPM env, scale pos+queue, warm cache, verify."""
from __future__ import annotations

import os
import re
import sys
import time

import paramiko

COMPOSE_POS_ENV = """
    # PHP-FPM capacity (serversideup/php). Default max_children=20 saturates under k6 stress.
    # On 4-core staging: ~40 children; scale replicas with: docker compose up -d --scale pos=2
    environment:
      PHP_FPM_PM_CONTROL: dynamic
      PHP_FPM_PM_MAX_CHILDREN: "40"
      PHP_FPM_PM_START_SERVERS: "8"
      PHP_FPM_PM_MIN_SPARE_SERVERS: "4"
      PHP_FPM_PM_MAX_SPARE_SERVERS: "12"
      PHP_MEMORY_LIMIT: 256M
      PHP_OPCACHE_ENABLE: "1"
"""


def vps_pw() -> str:
    path = os.path.join(os.path.dirname(__file__), "..", ".vps-secrets.env")
    with open(path, encoding="utf-8") as f:
        for line in f:
            if line.startswith("VPS_ROOT_PASSWORD="):
                return line.split("=", 1)[1].strip().strip('"').strip("'")
    raise SystemExit("VPS_ROOT_PASSWORD missing")


def main() -> None:
    c = paramiko.SSHClient()
    c.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    c.connect(
        "82.29.178.160",
        username="root",
        password=vps_pw(),
        timeout=30,
        allow_agent=False,
        look_for_keys=False,
    )
    sftp = c.open_sftp()

    # Patch compose on VPS
    with sftp.file("/opt/spot-staging/docker-compose.yml", "r") as f:
        compose = f.read().decode("utf-8")

    if "PHP_FPM_PM_MAX_CHILDREN" in compose:
        print("compose already has PHP_FPM env")
    else:
        # Insert environment block after env_file for pos: only (first occurrence under pos)
        pattern = (
            r"(  pos:\n"
            r"    image: spot-staging/pos:latest\n"
            r"    build:\n"
            r"      context: \./pos\n"
            r"      dockerfile: Dockerfile\n"
            r"    restart: unless-stopped\n"
            r"    env_file:\n"
            r"      - \./pos/\.env\n)"
        )
        m = re.search(pattern, compose)
        if not m:
            raise SystemExit("Could not locate pos service header in compose")
        compose = compose[: m.end()] + COMPOSE_POS_ENV + compose[m.end() :]
        with sftp.file("/opt/spot-staging/docker-compose.yml", "w") as f:
            f.write(compose)
        print("patched docker-compose.yml with PHP_FPM env")

    # Ensure rate limits + redis in pos/.env
    with sftp.file("/opt/spot-staging/pos/.env", "r") as f:
        env = f.read().decode("utf-8", "replace")

    def upsert(text: str, key: str, value: str) -> str:
        line = f"{key}={value}"
        if re.search(rf"^{re.escape(key)}=", text, re.M):
            return re.sub(rf"^{re.escape(key)}=.*$", line, text, count=1, flags=re.M)
        return text.rstrip() + "\n" + line + "\n"

    env2 = env
    for k, v in [
        ("CACHE_DRIVER", "redis"),
        ("CACHE_STORE", "redis"),
        ("REDIS_HOST", "redis"),
        ("STOREFRONT_RATE_LIMIT_READ", "6000"),
        ("STOREFRONT_RATE_LIMIT", "600"),
    ]:
        env2 = upsert(env2, k, v)
    if env2 != env:
        with sftp.file("/opt/spot-staging/pos/.env", "w") as f:
            f.write(env2)
        print("updated pos/.env cache/rate limits")
    else:
        print("pos/.env already has redis + raised rate limits")

    sftp.close()

    remote = r"""
set -euo pipefail
cd /opt/spot-staging

echo '== recreate pos with FPM env + scale 2 =='
docker compose up -d --no-deps --scale pos=2 pos

echo '== scale queue to 2 =='
docker compose up -d --no-deps --scale pos-queue=2 pos-queue

echo '== wait healthy =='
for i in 1 2 3 4 5 6 7 8 9 10 11 12; do
  ok=$(docker compose ps pos --format '{{.Status}}' | grep -c healthy || true)
  echo "healthy_count=$ok"
  if [ "$ok" -ge 2 ]; then break; fi
  sleep 5
done

echo '== ps =='
docker compose ps --format 'table {{.Name}}\t{{.Service}}\t{{.Status}}' | grep -E 'pos|NAME' || docker compose ps

echo '== FPM config in first pos =='
CID=$(docker compose ps -q pos | head -1)
docker exec "$CID" sh -c 'grep -E "^(pm |pm\.)" /usr/local/etc/php-fpm.d/zz-docker-php.conf /usr/local/etc/php-fpm.d/*.conf 2>/dev/null | head -40'
docker exec "$CID" sh -c 'printenv | grep -E "^PHP_FPM_|^PHP_MEMORY_|^PHP_OPCACHE_" | sort'

echo '== clear + warm cache =='
docker compose exec -T -u www-data pos php artisan config:clear || true
docker compose exec -T -u www-data pos php artisan cache:clear || true
curl -sS -o /dev/null -w 'settings:%{http_code}\n' https://pos.thespotmanagment.io/api/storefront/v1/settings
curl -sS -o /dev/null -w 'homepage:%{http_code}\n' https://pos.thespotmanagment.io/api/storefront/v1/homepage
curl -sS -o /dev/null -w 'categories:%{http_code}\n' https://pos.thespotmanagment.io/api/storefront/v1/categories
curl -sS -o /dev/null -w 'products:%{http_code}\n' 'https://pos.thespotmanagment.io/api/storefront/v1/products?per_page=24'

echo '== docker stats =='
docker stats --no-stream --format 'table {{.Name}}\t{{.CPUPerc}}\t{{.MemUsage}}' | head -20

echo DONE
"""
    _, o, e = c.exec_command(remote, timeout=300)
    # stream-ish wait
    sys.stdout.write(o.read().decode("utf-8", "replace"))
    err = e.read().decode("utf-8", "replace")
    code = o.channel.recv_exit_status()
    if err.strip():
        sys.stderr.write(err[:4000])
    c.close()
    if code != 0:
        raise SystemExit(code)
    print("apply ok")


if __name__ == "__main__":
    main()
