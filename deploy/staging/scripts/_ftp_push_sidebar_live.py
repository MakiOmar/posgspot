#!/usr/bin/env python3
"""Push sidebar scroll fix to live Accounts via FTP from the VPS."""
from __future__ import annotations

import ftplib
import io
import os
import sys

ACC = "/domains/gamesspoteg.com/public_html/accounts.gamesspoteg.com"
LOCAL = "/opt/spot-staging/accounts"


def upload(ftp: ftplib.FTP, local_path: str, remote_path: str) -> None:
    with open(local_path, "rb") as f:
        ftp.storbinary(f"STOR {remote_path}", f)
    print("UPLOADED", remote_path)


def mkdir_p(ftp: ftplib.FTP, path: str) -> None:
    parts = path.strip("/").split("/")
    cur = ""
    for p in parts:
        cur += "/" + p
        try:
            ftp.cwd(cur)
        except ftplib.error_perm:
            try:
                ftp.mkd(cur)
            except ftplib.error_perm as e:
                # may already exist in race
                if not str(e).startswith("550"):
                    raise
            ftp.cwd(cur)


def main() -> None:
    pw = open("/root/.hostinger_pass", encoding="utf-8").read().strip()
    ftp = ftplib.FTP()
    ftp.connect("93.127.205.20", 21, timeout=60)
    ftp.login("u605441708", pw)
    ftp.set_pasv(True)

    upload(ftp, f"{LOCAL}/config/adminlte.php", f"{ACC}/config/adminlte.php")
    upload(
        ftp,
        f"{LOCAL}/resources/views/vendor/adminlte/master.blade.php",
        f"{ACC}/resources/views/vendor/adminlte/master.blade.php",
    )
    mkdir_p(ftp, f"{ACC}/public/css")
    upload(
        ftp,
        f"{LOCAL}/public/css/sidebar-scroll-fix.css",
        f"{ACC}/public/css/sidebar-scroll-fix.css",
    )

    # verify remote sizes
    for rel in (
        "config/adminlte.php",
        "resources/views/vendor/adminlte/master.blade.php",
        "public/css/sidebar-scroll-fix.css",
    ):
        size = ftp.size(f"{ACC}/{rel}")
        print("SIZE", rel, size)

    ftp.quit()
    print("FTP_PUSH_OK")


if __name__ == "__main__":
    try:
        main()
    except Exception as e:
        print("ERR", e, file=sys.stderr)
        raise
