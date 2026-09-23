#!/usr/bin/env python3
"""Upload deploy/staging skeleton to VPS and bootstrap /opt/spot-staging."""
import os
import secrets
import string
import tarfile
import tempfile
from pathlib import Path

import paramiko

ROOT = Path(__file__).resolve().parents[1]  # deploy/staging
VPS_HOST = "82.29.178.160"
VPS_USER = "root"
VPS_PASS = os.environ["VPS_SSH_PASS"]


def rand_pass(n=32):
    alphabet = string.ascii_letters + string.digits
    return "".join(secrets.choice(alphabet) for _ in range(n))


def main():
    mysql_root = rand_pass()
    mysql_app = rand_pass()

    with tempfile.TemporaryDirectory() as td:
        td_path = Path(td)
        archive = td_path / "staging.tgz"
        with tarfile.open(archive, "w:gz") as tar:
            for p in ROOT.rglob("*"):
                if p.is_file():
                    # skip any local secrets
                    if p.name == ".env" or p.suffix == ".sql" or "sql.gz" in p.name:
                        continue
                    if any(part in {"data", "node_modules", "vendor"} for part in p.parts):
                        continue
                    arcname = p.relative_to(ROOT)
                    tar.add(p, arcname=str(arcname))

        c = paramiko.SSHClient()
        c.set_missing_host_key_policy(paramiko.AutoAddPolicy())
        c.connect(
            VPS_HOST,
            username=VPS_USER,
            password=VPS_PASS,
            timeout=60,
            allow_agent=False,
            look_for_keys=False,
        )
        sftp = c.open_sftp()
        sftp.put(str(archive), "/tmp/spot-staging.tgz")
        sftp.close()

        env_body = f"MYSQL_ROOT_PASSWORD={mysql_root}\nMYSQL_APP_PASSWORD={mysql_app}\n"
        # save passwords locally for subsequent scripts (gitignored path)
        secrets_file = ROOT / ".vps-secrets.env"
        secrets_file.write_text(env_body, encoding="utf-8")
        print("Wrote", secrets_file)

        cmds = f"""
set -euo pipefail
mkdir -p /opt/spot-staging
tar -xzf /tmp/spot-staging.tgz -C /opt/spot-staging
cat > /opt/spot-staging/.env <<'EOF'
{env_body}EOF
chmod 600 /opt/spot-staging/.env
chmod +x /opt/spot-staging/scripts/*.sh
touch /opt/spot-staging/traefik/acme.json
chmod 600 /opt/spot-staging/traefik/acme.json
ufw allow 22/tcp || true
ufw allow 80/tcp || true
ufw allow 443/tcp || true
ufw --force enable || true
cd /opt/spot-staging
docker compose pull traefik mysql redis
docker compose up -d traefik mysql redis
docker compose ps
"""
        stdin, stdout, stderr = c.exec_command(cmds, timeout=300)
        print(stdout.read().decode("utf-8", "replace"))
        err = stderr.read().decode("utf-8", "replace")
        if err.strip():
            print("STDERR", err[-3000:])
        c.close()
        print("Bootstrap done")


if __name__ == "__main__":
    main()
