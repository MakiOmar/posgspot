#!/usr/bin/env python3
"""Check VPS pull / compose status."""
import os
import sys

import paramiko

HOST = os.environ.get("VPS_HOST", "82.29.178.160")
PASS = os.environ.get("VPS_SSH_PASS", "")
if not PASS:
    print("Set VPS_SSH_PASS", file=sys.stderr)
    sys.exit(1)

c = paramiko.SSHClient()
c.set_missing_host_key_policy(paramiko.AutoAddPolicy())
c.connect(HOST, username="root", password=PASS, timeout=30)


def run(cmd: str) -> None:
    _, o, e = c.exec_command(cmd, timeout=120)
    out = o.read().decode(errors="replace")
    err = e.read().decode(errors="replace")
    if out:
        print(out, end="" if out.endswith("\n") else "\n")
    if err:
        print(err, end="" if err.endswith("\n") else "\n")


print("=== processes ===")
run("ps aux | grep -E 'pull-from|rsync|scp|sshpass|mysqldump' | grep -v grep || echo none")
print("=== dumps ===")
run("ls -lah /opt/spot-staging/data/dumps/ 2>/dev/null || echo missing")
print("=== trees ===")
run(
    "du -sh /opt/spot-staging/pos /opt/spot-staging/accounts /opt/spot-staging/storefront "
    "/opt/spot-staging/data/pos_uploads /opt/spot-staging/data/pos_storage "
    "/opt/spot-staging/data/accounts_storage 2>/dev/null || true"
)
print("=== pull.log tail ===")
run("tail -50 /opt/spot-staging/pull.log 2>/dev/null || echo no-pull-log")
print("=== docker ===")
run("cd /opt/spot-staging && docker compose ps 2>/dev/null || docker ps --format 'table {{.Names}}\t{{.Status}}'")
c.close()
