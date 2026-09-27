#!/usr/bin/env python3
import os
import sys

import paramiko


def pw():
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
        password=pw(),
        timeout=30,
        allow_agent=False,
        look_for_keys=False,
    )
    cmd = r"""
hostname
hostname -I | awk '{print $1}'
cd /opt/spot-staging
docker compose ps pos
echo ---
for id in $(docker compose ps -q pos); do
  docker inspect -f '{{.Name}} Hostname={{.Config.Hostname}} Pid={{.State.Pid}} NetworkMode={{.HostConfig.NetworkMode}}' "$id"
done
echo ---
docker info --format 'Swarm={{.Swarm.LocalNodeState}} CPUs={{.NCPU}}'
"""
    _, o, e = c.exec_command(cmd, timeout=60)
    sys.stdout.write(o.read().decode("utf-8", "replace"))
    err = e.read().decode("utf-8", "replace")
    if err.strip():
        sys.stderr.write(err[:1000])
    c.close()


if __name__ == "__main__":
    main()
