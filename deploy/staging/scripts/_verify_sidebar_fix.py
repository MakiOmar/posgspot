#!/usr/bin/env python3
import ftplib
import io
import subprocess
import sys

import paramiko


def vps_pw():
    import os

    path = os.path.join(os.path.dirname(__file__), "..", ".vps-secrets.env")
    with open(path, encoding="utf-8") as f:
        for line in f:
            if line.startswith("VPS_ROOT_PASSWORD="):
                return line.split("=", 1)[1].strip().strip('"').strip("'")
    raise SystemExit("missing pw")


def main():
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
    cmd = r"""
set -e
for url in \
  https://accounts.thespotmanagment.io/ \
  https://accounts.thespotmanagment.io/login \
  https://accounts.gamesspoteg.com/ \
  https://accounts.gamesspoteg.com/login
do
  code=$(curl -sL -o /tmp/acc_page.html -w '%{http_code}' "$url")
  echo "URL $url => $code"
  grep -c 'Ensure long sidebar' /tmp/acc_page.html || true
  grep -o 'overflow-y: auto !important' /tmp/acc_page.html | head -1 || true
done
docker compose -f /opt/spot-staging/docker-compose.yml exec -T accounts \
  grep -n layout_fixed_sidebar /var/www/html/config/adminlte.php | head -2
docker compose -f /opt/spot-staging/docker-compose.yml exec -T accounts \
  grep -c 'Ensure long sidebar' /var/www/html/resources/views/vendor/adminlte/master.blade.php
python3 - <<'PY'
import ftplib, io
pw=open('/root/.hostinger_pass').read().strip()
ftp=ftplib.FTP(); ftp.connect('93.127.205.20',21,timeout=40); ftp.login('u605441708',pw); ftp.set_pasv(True)
buf=io.BytesIO()
ftp.retrbinary('RETR /domains/gamesspoteg.com/public_html/accounts.gamesspoteg.com/config/adminlte.php', buf.write)
for line in buf.getvalue().decode().splitlines():
  if 'layout_fixed_sidebar' in line:
    print('LIVE_CFG', line.strip())
buf=io.BytesIO()
ftp.retrbinary('RETR /domains/gamesspoteg.com/public_html/accounts.gamesspoteg.com/resources/views/vendor/adminlte/master.blade.php', buf.write)
print('LIVE_MASTER_HAS_FIX', 'Ensure long sidebar' in buf.getvalue().decode())
try:
  print('LIVE_CONFIG_CACHE_SIZE', ftp.size('/domains/gamesspoteg.com/public_html/accounts.gamesspoteg.com/bootstrap/cache/config.php'))
except Exception as e:
  print('LIVE_CONFIG_CACHE', type(e).__name__)
ftp.quit()
PY
"""
    _, o, e = c.exec_command(cmd, timeout=120)
    sys.stdout.write(o.read().decode("utf-8", "replace"))
    err = e.read().decode("utf-8", "replace")
    if err.strip():
        sys.stderr.write(err[:2000])
    c.close()


if __name__ == "__main__":
    main()
