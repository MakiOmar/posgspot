#!/usr/bin/env python3
"""Upload push script to VPS and run it (uses /root/.hostinger_pass on VPS)."""
import os
import sys

import paramiko


def vps_pw():
    path = os.path.join(os.path.dirname(__file__), "..", ".vps-secrets.env")
    with open(path, encoding="utf-8") as f:
        for line in f:
            if line.startswith("VPS_ROOT_PASSWORD="):
                return line.split("=", 1)[1].strip().strip('"').strip("'")
    raise SystemExit("missing VPS_ROOT_PASSWORD")


def main():
    local_sh = os.path.join(os.path.dirname(__file__), "_push_sidebar_fix_live.sh")
    with open(local_sh, encoding="utf-8") as f:
        sh = f.read().replace("\r\n", "\n")

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
    with sftp.file("/tmp/push_sidebar_fix.sh", "w") as f:
        f.write(sh)
    sftp.close()

    _, o, e = c.exec_command(
        "test -s /root/.hostinger_pass || { echo 'missing /root/.hostinger_pass' >&2; exit 2; }; "
        "chmod +x /tmp/push_sidebar_fix.sh && bash /tmp/push_sidebar_fix.sh",
        timeout=180,
    )
    sys.stdout.write(o.read().decode("utf-8", "replace"))
    err = e.read().decode("utf-8", "replace")
    code = o.channel.recv_exit_status()
    if err.strip():
        sys.stderr.write(err)
    c.close()
    raise SystemExit(code)


if __name__ == "__main__":
    main()
