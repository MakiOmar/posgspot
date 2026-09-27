#!/usr/bin/env python3
import os
import sys

import paramiko


def vps_pw():
    path = os.path.join(os.path.dirname(__file__), "..", ".vps-secrets.env")
    with open(path, encoding="utf-8") as f:
        for line in f:
            if line.startswith("VPS_ROOT_PASSWORD="):
                return line.split("=", 1)[1].strip().strip('"').strip("'")
    raise SystemExit("missing")


def main():
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
    cmd = r"""
docker compose -f /opt/spot-staging/docker-compose.yml exec -T accounts \
  php artisan route:list --path=login 2>/dev/null | head -40
echo ---
for url in \
  https://accounts.thespotmanagment.io/manager/login \
  https://accounts.gamesspoteg.com/manager/login \
  https://accounts.thespotmanagment.io/admin/login \
  https://accounts.gamesspoteg.com/admin/login
do
  code=$(curl -sL -o /tmp/p.html -w '%{http_code}' "$url")
  has=$(grep -c 'Ensure long sidebar' /tmp/p.html || echo 0)
  echo "$url => $code has_fix=$has"
done
"""
    _, o, e = c.exec_command(cmd, timeout=120)
    sys.stdout.write(o.read().decode("utf-8", "replace"))
    err = e.read().decode("utf-8", "replace")
    if err.strip():
        sys.stderr.write(err[:1500])
    c.close()


if __name__ == "__main__":
    main()
