#!/usr/bin/env python3
"""Diagnose and repair staging containers."""
from __future__ import annotations

import os
import sys

import paramiko

sys.stdout.reconfigure(encoding="utf-8", errors="replace")
c = paramiko.SSHClient()
c.set_missing_host_key_policy(paramiko.AutoAddPolicy())
c.connect(
    "82.29.178.160",
    username="root",
    password=os.environ["VPS_SSH_PASS"],
    timeout=60,
    allow_agent=False,
    look_for_keys=False,
)


def run(cmd: str, timeout: int = 300) -> str:
    print(f"\n>>> {cmd[:160]}", flush=True)
    _, o, e = c.exec_command(cmd, timeout=timeout, get_pty=True)
    text = o.read().decode("utf-8", "replace") + e.read().decode("utf-8", "replace")
    print(text[-7000:], flush=True)
    return text


run("ls -la /opt/spot-staging/pos/composer.json /opt/spot-staging/pos/vendor 2>&1 | head")
run("ls -la /opt/spot-staging/accounts/composer.json /opt/spot-staging/accounts/vendor 2>&1 | head")
run("head -5 /opt/spot-staging/pos/Dockerfile; cat /opt/spot-staging/pos/.dockerignore")
run("cd /opt/spot-staging && docker compose logs --tail=40 storefront 2>&1")
run("cd /opt/spot-staging && docker compose logs --tail=40 pos-queue 2>&1")
run("cd /opt/spot-staging && docker compose logs --tail=30 pos 2>&1")
run("cd /opt/spot-staging && docker compose exec -T pos ls -la /var/www/html | head -30")
run("cd /opt/spot-staging && docker compose exec -T pos ls -la /var/www/html/vendor 2>&1 | head")
run("docker network inspect spot_edge --format '{{range .Containers}}{{.Name}} {{end}}'")
run("cd /opt/spot-staging && docker compose exec -T traefik wget -qO- http://127.0.0.1:8080/api/http/routers 2>&1 | head -c 1500 || true")
# Traefik dashboard disabled — check labels
run("docker inspect spot-staging-pos-1 --format '{{json .Config.Labels}}' | head -c 2000")
c.close()
