#!/usr/bin/env python3
"""Robust Hostinger -> VPS clone orchestrator (runs remotely via paramiko)."""
from __future__ import annotations

import os
import sys
import time

import paramiko

VPS_HOST = os.environ.get("VPS_HOST", "82.29.178.160")
VPS_PASS = os.environ["VPS_SSH_PASS"]
HOSTINGER_PASS = os.environ.get("HOSTINGER_SSH_PASS", "")
if not HOSTINGER_PASS:
    print("Set HOSTINGER_SSH_PASS", file=sys.stderr)
    sys.exit(1)
HOSTINGER_HOST = "93.127.205.20"
HOSTINGER_PORT = 65002
HOSTINGER_USER = "u605441708"
STAGING = "/opt/spot-staging"

POS_REMOTE = f"/home/{HOSTINGER_USER}/domains/gamesspoteg.com/public_html/pos.gamesspoteg.com"
ACC_REMOTE = f"/home/{HOSTINGER_USER}/domains/gamesspoteg.com/public_html/accounts.gamesspoteg.com"
SHOP_REMOTE = f"/home/{HOSTINGER_USER}/domains/new.gamesspoteg.com/nodejs"


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
        banner_timeout=60,
    )
    return c


def run(c: paramiko.SSHClient, cmd: str, timeout: int = 600) -> tuple[int, str, str]:
    print(f"$ {cmd[:200]}{'...' if len(cmd) > 200 else ''}", flush=True)
    _, stdout, stderr = c.exec_command(cmd, timeout=timeout, get_pty=True)
    out = stdout.read().decode("utf-8", "replace")
    err = stderr.read().decode("utf-8", "replace")
    code = stdout.channel.recv_exit_status()
    if out.strip():
        print(out[-4000:], flush=True)
    if err.strip():
        print("ERR:", err[-2000:], flush=True)
    return code, out, err


def main() -> int:
    c = connect()

    # Stop stale pulls
    run(
        c,
        "pkill -f pull-from-hostinger || true; pkill -f 'rsync .*gamesspoteg' || true; "
        "pkill -f 'scp .*tmp-spot-staging' || true; sleep 1; echo cleaned",
        timeout=30,
    )

    # Ensure tools
    run(
        c,
        "export DEBIAN_FRONTEND=noninteractive; "
        "command -v sshpass >/dev/null || apt-get install -y sshpass; "
        "command -v rsync >/dev/null || apt-get install -y rsync; "
        "mkdir -p {s}/{{pos,accounts,storefront,data/{{dumps,pos_uploads,pos_storage,accounts_storage}}}}".format(
            s=STAGING
        ),
        timeout=120,
    )

    # Export password to a root-only file for rsync/sshpass
    # Avoid shell-metachar issues by writing via python on remote
    write_pass = (
        "python3 - <<'PY'\n"
        f"open('/root/.hostinger_pass','w').write({HOSTINGER_PASS!r})\n"
        "import os; os.chmod('/root/.hostinger_pass', 0o600)\n"
        "print('pass-file-ok')\n"
        "PY"
    )
    code, _, _ = run(c, write_pass, timeout=30)
    if code != 0:
        print("failed to write hostinger pass file", file=sys.stderr)
        return 1

    ssh_opts = (
        f"-p {HOSTINGER_PORT} -o StrictHostKeyChecking=no "
        "-o UserKnownHostsFile=/dev/null -o ServerAliveInterval=30 "
        "-o ServerAliveCountMax=6 -o ConnectTimeout=30"
    )
    scp_opts = (
        f"-P {HOSTINGER_PORT} -o StrictHostKeyChecking=no "
        "-o UserKnownHostsFile=/dev/null -o ServerAliveInterval=30 "
        "-o ConnectTimeout=30"
    )
    rsh = f"sshpass -f /root/.hostinger_pass ssh {ssh_opts}"

    # Ensure dumps exist on Hostinger (reuse if present and non-empty)
    dump_cmd = f"""
export SSHPASS=$(cat /root/.hostinger_pass)
sshpass -e ssh {ssh_opts} {HOSTINGER_USER}@{HOSTINGER_HOST} 'bash -s' <<'EOS'
set -euo pipefail
POS={POS_REMOTE}
ACC={ACC_REMOTE}
mkdir -p ~/tmp-spot-staging
need=0
[ -s ~/tmp-spot-staging/pos_stg.sql.gz ] || need=1
[ -s ~/tmp-spot-staging/accounts_stg.sql.gz ] || need=1
if [ "$need" = 1 ]; then
  POSDB=$(grep ^DB_DATABASE= "$POS/.env" | cut -d= -f2- | tr -d '\\r')
  POSUSER=$(grep ^DB_USERNAME= "$POS/.env" | cut -d= -f2- | tr -d '\\r')
  POSPASS=$(grep ^DB_PASSWORD= "$POS/.env" | cut -d= -f2- | tr -d '\\r')
  POSHOST=$(grep ^DB_HOST= "$POS/.env" | cut -d= -f2- | tr -d '\\r')
  ACCDB=$(grep ^DB_DATABASE= "$ACC/.env" | cut -d= -f2- | tr -d '\\r')
  ACCUSER=$(grep ^DB_USERNAME= "$ACC/.env" | cut -d= -f2- | tr -d '\\r')
  ACCPASS=$(grep ^DB_PASSWORD= "$ACC/.env" | cut -d= -f2- | tr -d '\\r')
  ACCHOST=$(grep ^DB_HOST= "$ACC/.env" | cut -d= -f2- | tr -d '\\r')
  [ -s ~/tmp-spot-staging/pos_stg.sql.gz ] || mysqldump -h"$POSHOST" -u"$POSUSER" -p"$POSPASS" --single-transaction --quick --routines --triggers "$POSDB" | gzip -c > ~/tmp-spot-staging/pos_stg.sql.gz
  [ -s ~/tmp-spot-staging/accounts_stg.sql.gz ] || mysqldump -h"$ACCHOST" -u"$ACCUSER" -p"$ACCPASS" --single-transaction --quick --routines --triggers "$ACCDB" | gzip -c > ~/tmp-spot-staging/accounts_stg.sql.gz
fi
ls -lh ~/tmp-spot-staging/
EOS
"""
    code, _, _ = run(c, dump_cmd, timeout=1800)
    if code != 0:
        print("dump step failed", file=sys.stderr)
        return 1

    # Fetch dumps one at a time
    for name in ("pos_stg.sql.gz", "accounts_stg.sql.gz"):
        dest = f"{STAGING}/data/dumps/{name}"
        fetch = f"""
set -euo pipefail
if [ -s {dest} ]; then echo "already have {name}"; ls -lh {dest}; exit 0; fi
export SSHPASS=$(cat /root/.hostinger_pass)
sshpass -e scp {scp_opts} \
  {HOSTINGER_USER}@{HOSTINGER_HOST}:tmp-spot-staging/{name} {dest}.partial
mv {dest}.partial {dest}
ls -lh {dest}
"""
        code, _, _ = run(c, fetch, timeout=900)
        if code != 0:
            print(f"fetch {name} failed", file=sys.stderr)
            return 1

    # Rsync helpers
    def rsync(src: str, dst: str, extra: str = "") -> int:
        cmd = f"""
set -euo pipefail
export SSHPASS=$(cat /root/.hostinger_pass)
rsync -az --info=progress2 {extra} -e '{rsh}' \
  {HOSTINGER_USER}@{HOSTINGER_HOST}:{src} {dst}
echo RSYNC_OK:{dst}
du -sh {dst}
"""
        code, _, _ = run(c, cmd, timeout=7200)
        return code

    steps = [
        (
            "POS code",
            f"{POS_REMOTE}/",
            f"{STAGING}/pos/",
            "--delete --exclude vendor --exclude node_modules --exclude .git "
            "--exclude public/uploads --exclude storage "
            "--exclude Dockerfile --exclude .dockerignore --exclude .env",
        ),
        (
            "POS uploads",
            f"{POS_REMOTE}/public/uploads/",
            f"{STAGING}/data/pos_uploads/",
            "",
        ),
        (
            "POS storage",
            f"{POS_REMOTE}/storage/",
            f"{STAGING}/data/pos_storage/",
            "--exclude logs",
        ),
        (
            "Accounts code",
            f"{ACC_REMOTE}/",
            f"{STAGING}/accounts/",
            "--delete --exclude vendor --exclude node_modules --exclude .git "
            "--exclude storage --exclude Dockerfile --exclude .dockerignore --exclude .env",
        ),
        (
            "Accounts storage",
            f"{ACC_REMOTE}/storage/",
            f"{STAGING}/data/accounts_storage/",
            "--exclude logs",
        ),
    ]

    for label, src, dst, extra in steps:
        print(f"\n=== {label} ===", flush=True)
        if rsync(src, dst, extra) != 0:
            print(f"rsync failed: {label}", file=sys.stderr)
            return 1

    print("\n=== DONE sizes ===", flush=True)
    run(
        c,
        f"ls -lh {STAGING}/data/dumps; du -sh {STAGING}/pos {STAGING}/accounts "
        f"{STAGING}/data/pos_uploads {STAGING}/data/pos_storage {STAGING}/data/accounts_storage",
        timeout=60,
    )
    c.close()
    return 0


if __name__ == "__main__":
    sys.exit(main())
