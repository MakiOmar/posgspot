#!/usr/bin/env python3
"""Post-clone: fetch .env, remap, import DBs, upload storefront, compose up."""
from __future__ import annotations

import os
import subprocess
import sys
import tarfile
import tempfile
from pathlib import Path

try:
    import paramiko
    from scp import SCPClient
except ImportError:
    subprocess.check_call([sys.executable, "-m", "pip", "install", "paramiko", "scp", "-q"])
    import paramiko
    from scp import SCPClient

# scripts/ -> staging/ -> deploy/ -> repo/
ROOT = Path(__file__).resolve().parents[1]  # deploy/staging
REPO = Path(__file__).resolve().parents[3]
STOREFRONT_SRC = REPO / "storefront-qwik"

VPS_HOST = os.environ.get("VPS_HOST", "82.29.178.160")
VPS_PASS = os.environ["VPS_SSH_PASS"]
HOSTINGER_PASS = os.environ["HOSTINGER_SSH_PASS"]
SECRETS = ROOT / ".vps-secrets.env"

POS_REMOTE = "/home/u605441708/domains/gamesspoteg.com/public_html/pos.gamesspoteg.com"
ACC_REMOTE = "/home/u605441708/domains/gamesspoteg.com/public_html/accounts.gamesspoteg.com"


def load_secrets() -> dict[str, str]:
    out: dict[str, str] = {}
    for line in SECRETS.read_text(encoding="utf-8").splitlines():
        line = line.strip()
        if not line or line.startswith("#") or "=" not in line:
            continue
        k, v = line.split("=", 1)
        out[k.strip()] = v.strip()
    return out


def connect() -> paramiko.SSHClient:
    c = paramiko.SSHClient()
    c.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    c.connect(
        VPS_HOST,
        username="root",
        password=VPS_PASS,
        timeout=60,
        allow_agent=False,
        look_for_keys=False,
    )
    return c


def run(c: paramiko.SSHClient, cmd: str, timeout: int = 3600) -> tuple[int, str]:
    print(f"\n$ {cmd[:220]}{'...' if len(cmd) > 220 else ''}", flush=True)
    _, stdout, stderr = c.exec_command(cmd, timeout=timeout, get_pty=True)
    out = stdout.read().decode("utf-8", "replace")
    err = stderr.read().decode("utf-8", "replace")
    code = stdout.channel.recv_exit_status()
    text = (out + ("\n" + err if err.strip() else "")).strip()
    if text:
        safe = text[-6000:].encode("utf-8", "replace").decode("utf-8", "replace")
        try:
            print(safe, flush=True)
        except UnicodeEncodeError:
            sys.stdout.buffer.write(safe.encode("utf-8", "replace") + b"\n")
            sys.stdout.buffer.flush()
    return code, text


def main() -> int:
    secrets = load_secrets()
    mysql_root = secrets["MYSQL_ROOT_PASSWORD"]
    mysql_app = secrets["MYSQL_APP_PASSWORD"]

    c = connect()

    # Ensure compose .env on VPS
    run(
        c,
        f"printf '%s\\n' 'MYSQL_ROOT_PASSWORD={mysql_root}' 'MYSQL_APP_PASSWORD={mysql_app}' "
        f"> /opt/spot-staging/.env && chmod 600 /opt/spot-staging/.env && echo compose-env-ok",
        timeout=30,
    )

    # Fetch Laravel .env from Hostinger if missing
    run(
        c,
        f"""
set -euo pipefail
export SSHPASS=$(cat /root/.hostinger_pass 2>/dev/null || echo '{HOSTINGER_PASS}')
printf '%s' "$SSHPASS" > /root/.hostinger_pass
chmod 600 /root/.hostinger_pass
SCP='sshpass -e scp -P 65002 -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null'
if [ ! -f /opt/spot-staging/pos/.env ]; then
  $SCP u605441708@93.127.205.20:{POS_REMOTE}/.env /opt/spot-staging/pos/.env
fi
if [ ! -f /opt/spot-staging/accounts/.env ]; then
  $SCP u605441708@93.127.205.20:{ACC_REMOTE}/.env /opt/spot-staging/accounts/.env
fi
ls -la /opt/spot-staging/pos/.env /opt/spot-staging/accounts/.env
""",
        timeout=120,
    )

    # Upload updated configure script
    with SCPClient(c.get_transport()) as scp:
        scp.put(
            str(ROOT / "scripts" / "configure-staging-env.sh"),
            "/opt/spot-staging/scripts/configure-staging-env.sh",
        )
        scp.put(str(ROOT / "scripts" / "import-db.sh"), "/opt/spot-staging/scripts/import-db.sh")

    code, _ = run(
        c,
        f"chmod +x /opt/spot-staging/scripts/*.sh && "
        f"MYSQL_APP_PASSWORD='{mysql_app}' bash /opt/spot-staging/scripts/configure-staging-env.sh",
        timeout=60,
    )
    if code != 0:
        return code

    # Verify remapped keys
    run(
        c,
        "grep -E '^(APP_URL|DB_HOST|DB_DATABASE|CORS_ALLOWED|ACCOUNTS_BASE|STOREFRONT|MAIL_MAILER)=' "
        "/opt/spot-staging/pos/.env /opt/spot-staging/accounts/.env | head -40",
        timeout=30,
    )

    # Import DBs (skip if tables already present)
    code, out = run(
        c,
        f"""
set -euo pipefail
cd /opt/spot-staging
export MYSQL_ROOT_PASSWORD='{mysql_root}'
export MYSQL_APP_PASSWORD='{mysql_app}'
# wait healthy
for i in $(seq 1 30); do
  docker compose exec -T mysql mysqladmin ping -h127.0.0.1 -uroot -p"$MYSQL_ROOT_PASSWORD" --silent && break
  sleep 2
done
COUNT=$(docker compose exec -T mysql mysql -N -uroot -p"$MYSQL_ROOT_PASSWORD" -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='pos_stg'" 2>/dev/null | tr -d '\\r' || echo 0)
if [ "${{COUNT:-0}}" -gt 10 ]; then
  echo "pos_stg already has $COUNT tables — skip import"
else
  bash scripts/import-db.sh
fi
""",
        timeout=3600,
    )
    if code != 0:
        print("import failed", file=sys.stderr)
        return code

    # Build storefront tarball from local staging dist
    need = ["package.json", "dist", "server"]
    for n in need:
        if not (STOREFRONT_SRC / n).exists():
            print(f"missing {STOREFRONT_SRC / n}", file=sys.stderr)
            return 1
    lock = STOREFRONT_SRC / "package-lock.json"
    if not lock.exists():
        print("missing package-lock.json", file=sys.stderr)
        return 1

    with tempfile.NamedTemporaryFile(suffix=".tar.gz", delete=False) as tmp:
        tar_path = Path(tmp.name)
    print(f"Packing storefront -> {tar_path}", flush=True)
    with tarfile.open(tar_path, "w:gz") as tar:
        for name in ("package.json", "package-lock.json", "dist", "server"):
            tar.add(STOREFRONT_SRC / name, arcname=name)
        # Keep Dockerfile from VPS / upload ours
        tar.add(ROOT / "storefront" / "Dockerfile", arcname="Dockerfile")
        tar.add(ROOT / "storefront" / ".dockerignore", arcname=".dockerignore")

    print("Uploading storefront archive...", flush=True)
    with SCPClient(c.get_transport()) as scp:
        scp.put(str(tar_path), "/tmp/storefront-stg.tgz")
    tar_path.unlink(missing_ok=True)

    run(
        c,
        """
set -euo pipefail
mkdir -p /opt/spot-staging/storefront
# preserve nothing — replace with staging build
rm -rf /opt/spot-staging/storefront/dist /opt/spot-staging/storefront/server
tar -xzf /tmp/storefront-stg.tgz -C /opt/spot-staging/storefront
# ensure Dockerfiles for pos/accounts still present
test -f /opt/spot-staging/pos/Dockerfile
test -f /opt/spot-staging/accounts/Dockerfile
test -f /opt/spot-staging/storefront/Dockerfile
ls -la /opt/spot-staging/storefront | head
du -sh /opt/spot-staging/storefront
rm -f /tmp/storefront-stg.tgz
""",
        timeout=120,
    )

    # Fix storage permissions for bind mounts
    run(
        c,
        """
set -euo pipefail
mkdir -p /opt/spot-staging/data/pos_storage/framework/{cache,sessions,views} \
  /opt/spot-staging/data/pos_storage/logs \
  /opt/spot-staging/data/accounts_storage/framework/{cache,sessions,views} \
  /opt/spot-staging/data/accounts_storage/logs
chown -R 33:33 /opt/spot-staging/data/pos_storage /opt/spot-staging/data/pos_uploads \
  /opt/spot-staging/data/accounts_storage || true
chmod -R ug+rwX /opt/spot-staging/data/pos_storage /opt/spot-staging/data/pos_uploads \
  /opt/spot-staging/data/accounts_storage || true
echo perms-ok
""",
        timeout=120,
    )

    # Build and start app services
    code, _ = run(
        c,
        """
set -euo pipefail
cd /opt/spot-staging
docker compose up -d --build pos accounts storefront pos-queue pos-scheduler accounts-queue 2>&1
docker compose ps
""",
        timeout=1800,
    )
    if code != 0:
        print("compose up failed", file=sys.stderr)
        run(c, "cd /opt/spot-staging && docker compose logs --tail=80 pos accounts storefront 2>&1 | tail -200", timeout=60)
        return code

    # Post-start artisan fixes
    run(
        c,
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

    c.close()
    print("\n=== POST-CLONE COMPLETE ===", flush=True)
    return 0


if __name__ == "__main__":
    sys.exit(main())
