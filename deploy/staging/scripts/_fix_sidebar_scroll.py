#!/usr/bin/env python3
"""Fix Accounts AdminLTE sidebar scroll on staging VPS.

Live Hostinger is updated separately via _ftp_push_sidebar_live.py
(from the VPS, using /root/.hostinger_pass).
"""
from __future__ import annotations

import os
import re
import sys

import paramiko

SIDEBAR_CSS = """
/* Ensure long sidebar menus scroll when OverlayScrollbars is absent/broken */
.main-sidebar .sidebar {
  overflow-y: auto !important;
  overflow-x: hidden !important;
  height: calc(100vh - (3.5rem + 1px)) !important;
  max-height: calc(100vh - (3.5rem + 1px)) !important;
  -webkit-overflow-scrolling: touch;
}
.main-sidebar .sidebar .os-viewport,
.main-sidebar .sidebar.os-host {
  overflow-y: auto !important;
}
"""

SIDEBAR_CSS_MARKER = "/* Ensure long sidebar menus scroll"


def vps_pw() -> str:
    path = os.path.join(os.path.dirname(__file__), "..", ".vps-secrets.env")
    with open(path, encoding="utf-8") as f:
        for line in f:
            if line.startswith("VPS_ROOT_PASSWORD="):
                return line.split("=", 1)[1].strip().strip('"').strip("'")
    raise SystemExit("VPS_ROOT_PASSWORD missing")


def ssh_run(c: paramiko.SSHClient, cmd: str, timeout: int = 120) -> str:
    _, o, e = c.exec_command(cmd, timeout=timeout)
    out = o.read().decode("utf-8", "replace")
    err = e.read().decode("utf-8", "replace")
    if err.strip():
        print("STDERR:", err[:2000], file=sys.stderr)
    return out


def patch_adminlte_config(text: str) -> str:
    text2, n = re.subn(
        r"('layout_fixed_sidebar'\s*=>\s*)null",
        r"\1true",
        text,
        count=1,
    )
    if n == 0:
        text2, n = re.subn(
            r"('layout_fixed_sidebar'\s*=>\s*)false",
            r"\1true",
            text,
            count=1,
        )
    if n == 0 and "'layout_fixed_sidebar' => true" not in text2:
        raise RuntimeError("Could not patch layout_fixed_sidebar")
    return text2


def ensure_css_in_master(master: str) -> str:
    if SIDEBAR_CSS_MARKER in master:
        return master
    snippet = (
        "\n    {{-- Sidebar scroll fallback (long menus) --}}\n"
        "    <style>\n"
        + SIDEBAR_CSS
        + "\n    </style>\n"
    )
    if "</head>" not in master:
        raise RuntimeError("master.blade.php missing </head>")
    return master.replace("</head>", snippet + "</head>", 1)


def fix_staging() -> None:
    print("=== STAGING VPS ===")
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
    base = "/opt/spot-staging/accounts"
    out = ssh_run(c, f"cat {base}/config/adminlte.php")
    patched = patch_adminlte_config(out)
    sftp = c.open_sftp()
    with sftp.file(f"{base}/config/adminlte.php", "w") as f:
        f.write(patched)
    with sftp.file(
        f"{base}/resources/views/vendor/adminlte/master.blade.php", "r"
    ) as f:
        master = f.read().decode("utf-8")
    master2 = ensure_css_in_master(master)
    with sftp.file(
        f"{base}/resources/views/vendor/adminlte/master.blade.php", "w"
    ) as f:
        f.write(master2)
    try:
        sftp.mkdir(f"{base}/public/css")
    except IOError:
        pass
    with sftp.file(f"{base}/public/css/sidebar-scroll-fix.css", "w") as f:
        f.write(SIDEBAR_CSS)
    sftp.close()

    out = ssh_run(
        c,
        r"""
set -e
cd /opt/spot-staging
CID=$(docker compose ps -q accounts)
echo CID=$CID
if [ -n "$CID" ]; then
  docker cp /opt/spot-staging/accounts/config/adminlte.php "$CID:/var/www/html/config/adminlte.php"
  docker cp /opt/spot-staging/accounts/resources/views/vendor/adminlte/master.blade.php \
    "$CID:/var/www/html/resources/views/vendor/adminlte/master.blade.php"
  docker exec -u www-data "$CID" mkdir -p /var/www/html/public/css
  docker cp /opt/spot-staging/accounts/public/css/sidebar-scroll-fix.css \
    "$CID:/var/www/html/public/css/sidebar-scroll-fix.css"
  docker exec -u www-data "$CID" php artisan view:clear
  docker exec -u www-data "$CID" php artisan config:clear
  docker exec -u www-data "$CID" php artisan cache:clear || true
fi
grep -n "layout_fixed_sidebar" /opt/spot-staging/accounts/config/adminlte.php | head -3
grep -n "Ensure long sidebar" /opt/spot-staging/accounts/resources/views/vendor/adminlte/master.blade.php | head -3
curl -sI https://accounts.thespotmanagment.io/css/sidebar-scroll-fix.css | head -8
""",
    )
    print(out)
    c.close()
    print("staging done")


if __name__ == "__main__":
    fix_staging()
