#!/usr/bin/env python3
"""Rotate VPS root password; wipe Hostinger pass file on VPS."""
from __future__ import annotations

import os
import secrets
import string
import sys
from pathlib import Path

import paramiko

ROOT = Path(__file__).resolve().parents[1]
SECRETS = ROOT / ".vps-secrets.env"
OLD = os.environ["VPS_SSH_PASS"]

alphabet = string.ascii_letters + string.digits
new_pass = "".join(secrets.choice(alphabet) for _ in range(28))

c = paramiko.SSHClient()
c.set_missing_host_key_policy(paramiko.AutoAddPolicy())
c.connect(
    "82.29.178.160",
    username="root",
    password=OLD,
    timeout=60,
    allow_agent=False,
    look_for_keys=False,
)

# chpasswd via stdin
chan = c.get_transport().open_session()
chan.exec_command("chpasswd")
chan.sendall(f"root:{new_pass}\n".encode())
chan.shutdown_write()
exit_status = chan.recv_exit_status()
out = chan.recv(4096).decode("utf-8", "replace") + chan.recv_stderr(4096).decode(
    "utf-8", "replace"
)
if exit_status != 0:
    print("chpasswd failed:", out, file=sys.stderr)
    sys.exit(1)

_, o, e = c.exec_command(
    "rm -f /root/.hostinger_pass /tmp/spot-pull.log; "
    "chmod 600 /opt/spot-staging/.env /opt/spot-staging/pos/.env "
    "/opt/spot-staging/accounts/.env 2>/dev/null; echo rotated-ok"
)
print(o.read().decode())
c.close()

# Update local secrets file
lines = []
if SECRETS.exists():
    for line in SECRETS.read_text(encoding="utf-8").splitlines():
        if line.startswith("VPS_ROOT_PASSWORD=") or line.startswith("VPS_SSH_PASS="):
            continue
        lines.append(line)
lines.append(f"VPS_ROOT_PASSWORD={new_pass}")
# keep mysql secrets
SECRETS.write_text("\n".join(lines).rstrip() + "\n", encoding="utf-8")
print("Wrote new VPS_ROOT_PASSWORD to deploy/staging/.vps-secrets.env (gitignored)")
print("ROTATE_OK")
