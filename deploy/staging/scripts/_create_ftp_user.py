#!/usr/bin/env python3
"""Create spotftp user + vsftpd with full write access to /opt/spot-staging."""
from __future__ import annotations

import os
import secrets
import string
import sys
from pathlib import Path

import paramiko

SECRETS = Path(__file__).resolve().parents[1] / ".vps-secrets.env"
sys.stdout.reconfigure(encoding="utf-8", errors="replace")

pw = None
for line in SECRETS.read_text(encoding="utf-8").splitlines():
    if line.startswith("VPS_ROOT_PASSWORD="):
        pw = line.split("=", 1)[1].strip()
if not pw:
    print("Missing VPS_ROOT_PASSWORD in .vps-secrets.env", file=sys.stderr)
    sys.exit(1)

alphabet = string.ascii_letters + string.digits
ftp_pass = "".join(secrets.choice(alphabet) for _ in range(24))
ftp_user = "spotftp"

c = paramiko.SSHClient()
c.set_missing_host_key_policy(paramiko.AutoAddPolicy())
c.connect(
    "82.29.178.160",
    username="root",
    password=pw,
    timeout=60,
    allow_agent=False,
    look_for_keys=False,
)


def run(cmd: str, timeout: int = 180) -> tuple[int, str]:
    print(f">>> {cmd[:200]}", flush=True)
    _, o, e = c.exec_command(cmd, timeout=timeout, get_pty=True)
    text = o.read().decode("utf-8", "replace") + e.read().decode("utf-8", "replace")
    code = o.channel.recv_exit_status()
    print(text[-4000:], flush=True)
    return code, text


# Install vsftpd + openssl for FTPS cert
run(
    """
export DEBIAN_FRONTEND=noninteractive
apt-get update -qq
apt-get install -y vsftpd openssl
""",
    timeout=300,
)

# Create user (home = staging root)
run(
    f"""
set -euo pipefail
if ! id -u {ftp_user} >/dev/null 2>&1; then
  useradd -d /opt/spot-staging -s /bin/bash {ftp_user}
fi
echo '{ftp_user}:{ftp_pass}' | chpasswd
# Ensure www-data group exists (UID/GID 33 matches container)
getent group www-data >/dev/null || groupadd -g 33 www-data
usermod -g www-data -aG www-data {ftp_user}
# Ownership: FTP user owns tree; group www-data so PHP containers can still write storage/uploads
chown -R {ftp_user}:www-data /opt/spot-staging
chmod -R ug+rwX /opt/spot-staging
# Keep compose secrets readable only by owner+group (not world)
chmod 640 /opt/spot-staging/.env /opt/spot-staging/pos/.env /opt/spot-staging/accounts/.env 2>/dev/null || true
find /opt/spot-staging -type d -exec chmod g+s {{}} \\;
# Restore Traefik acme perms if present
chmod 600 /opt/spot-staging/traefik/acme.json 2>/dev/null || true
id {ftp_user}
ls -la /opt/spot-staging | head
"""
)

# vsftpd config
vsftpd_conf = r"""
listen=YES
listen_ipv6=NO
anonymous_enable=NO
local_enable=YES
write_enable=YES
local_umask=002
dirmessage_enable=YES
use_localtime=YES
xferlog_enable=YES
connect_from_port_20=YES
chroot_local_user=YES
allow_writeable_chroot=YES
user_sub_token=$USER
local_root=/opt/spot-staging
pasv_enable=YES
pasv_min_port=40000
pasv_max_port=40100
pasv_address=82.29.178.160
pasv_addr_resolve=NO
secure_chroot_dir=/var/run/vsftpd/empty
pam_service_name=vsftpd
userlist_enable=YES
userlist_file=/etc/vsftpd.userlist
userlist_deny=NO
# FTPS (explicit TLS) — clients should prefer this over plain FTP
ssl_enable=YES
rsa_cert_file=/etc/ssl/private/vsftpd.pem
rsa_private_key_file=/etc/ssl/private/vsftpd.pem
force_local_data_ssl=NO
force_local_logins_ssl=NO
ssl_tlsv1=YES
ssl_sslv2=NO
ssl_sslv3=NO
require_ssl_reuse=NO
"""

# Write conf via remote python to avoid quoting hell
run(
    f"""
python3 - <<'PY'
from pathlib import Path
Path('/etc/vsftpd.conf').write_text({vsftpd_conf!r}, encoding='utf-8')
Path('/etc/vsftpd.userlist').write_text('{ftp_user}\\n', encoding='utf-8')
print('vsftpd.conf written')
PY
# Self-signed cert for FTPS (FileZilla: accept once)
if [ ! -f /etc/ssl/private/vsftpd.pem ]; then
  openssl req -x509 -nodes -days 3650 -newkey rsa:2048 \\
    -keyout /etc/ssl/private/vsftpd.pem -out /etc/ssl/private/vsftpd.pem \\
    -subj '/CN=thespotmanagment.io'
  chmod 600 /etc/ssl/private/vsftpd.pem
fi
mkdir -p /var/run/vsftpd/empty
systemctl enable vsftpd
systemctl restart vsftpd
systemctl is-active vsftpd
ss -tlnp | grep -E ':21|:4000' || true
"""
)

# Firewall
run(
    """
if command -v ufw >/dev/null; then
  ufw allow 21/tcp comment 'FTP'
  ufw allow 40000:40100/tcp comment 'FTP passive'
  ufw status | head -30
fi
"""
)

# Persist password locally (gitignored)
lines = []
for line in SECRETS.read_text(encoding="utf-8").splitlines():
    if line.startswith("FTP_USER=") or line.startswith("FTP_PASSWORD="):
        continue
    lines.append(line)
lines.append(f"FTP_USER={ftp_user}")
lines.append(f"FTP_PASSWORD={ftp_pass}")
SECRETS.write_text("\n".join(lines).rstrip() + "\n", encoding="utf-8")

print("\n=== FTP READY ===", flush=True)
print(f"Host: 82.29.178.160", flush=True)
print(f"User: {ftp_user}", flush=True)
print(f"Pass: (saved to deploy/staging/.vps-secrets.env as FTP_PASSWORD)", flush=True)
print(f"Root: /opt/spot-staging (chrooted)", flush=True)
print(f"Protocol: FTP or FTPS explicit on port 21; passive 40000-40100", flush=True)

c.close()
