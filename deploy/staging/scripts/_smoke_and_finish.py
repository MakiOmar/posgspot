#!/usr/bin/env python3
"""Check compose status and finish artisan + smoke if apps are up."""
from __future__ import annotations

import os
import sys

import paramiko

VPS_PASS = os.environ["VPS_SSH_PASS"]


def main() -> int:
    sys.stdout.reconfigure(encoding="utf-8", errors="replace")
    c = paramiko.SSHClient()
    c.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    c.connect(
        "82.29.178.160",
        username="root",
        password=VPS_PASS,
        timeout=60,
        allow_agent=False,
        look_for_keys=False,
    )

    def run(cmd: str, timeout: int = 600) -> tuple[int, str]:
        print(f"\n=== {cmd[:100]} ===", flush=True)
        _, stdout, stderr = c.exec_command(cmd, timeout=timeout, get_pty=True)
        out = stdout.read().decode("utf-8", "replace")
        err = stderr.read().decode("utf-8", "replace")
        code = stdout.channel.recv_exit_status()
        text = out + (("\n" + err) if err.strip() else "")
        print(text[-8000:], flush=True)
        return code, text

    code, out = run("cd /opt/spot-staging && docker compose ps -a", timeout=60)
    # If apps not running, try build again
    if "spot-staging-pos-1" not in out or "Up" not in out:
        print("\nApps missing — building...", flush=True)
        code, _ = run(
            "cd /opt/spot-staging && docker compose up -d --build "
            "pos accounts storefront pos-queue pos-scheduler accounts-queue",
            timeout=1800,
        )
        if code != 0:
            run(
                "cd /opt/spot-staging && docker compose logs --tail=100 pos accounts storefront",
                timeout=60,
            )
            return code
        run("cd /opt/spot-staging && docker compose ps", timeout=60)

    run(
        """
set -euo pipefail
cd /opt/spot-staging
docker compose exec -T -u www-data pos php artisan storage:link || true
docker compose exec -T -u www-data pos php artisan config:clear || true
docker compose exec -T -u www-data accounts php artisan config:clear || true
echo artisan-ok
""",
        timeout=180,
    )

    # Smoke via Traefik Host headers on localhost inside VPS
    run(
        """
set -euo pipefail
echo '--- POS / ---'
curl -sI -H 'Host: pos.thespotmanagment.io' http://127.0.0.1/ | head -15
echo '--- POS settings ---'
curl -s -H 'Host: pos.thespotmanagment.io' -H 'Accept: application/json' \
  http://127.0.0.1/api/storefront/v1/settings | head -c 500
echo
echo '--- Accounts / ---'
curl -sI -H 'Host: accounts.thespotmanagment.io' http://127.0.0.1/ | head -15
echo '--- Shop / ---'
curl -sI -H 'Host: thespotmanagment.io' http://127.0.0.1/ | head -15
echo '--- Shop body snippet ---'
curl -s -H 'Host: thespotmanagment.io' http://127.0.0.1/ | head -c 400
echo
echo SMOKE_DONE
""",
        timeout=120,
    )

    c.close()
    return 0


if __name__ == "__main__":
    sys.exit(main())
