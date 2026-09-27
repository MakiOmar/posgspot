#!/usr/bin/env python3
"""Fix Accounts HTTPS URL generation + ASSET_URL; rebuild image."""
from __future__ import annotations

import sys
from pathlib import Path

import paramiko

SECRETS = Path(__file__).resolve().parents[1] / ".vps-secrets.env"
sys.stdout.reconfigure(encoding="utf-8", errors="replace")
root_pw = next(
    l.split("=", 1)[1].strip()
    for l in SECRETS.read_text(encoding="utf-8").splitlines()
    if l.startswith("VPS_ROOT_PASSWORD=")
)

c = paramiko.SSHClient()
c.set_missing_host_key_policy(paramiko.AutoAddPolicy())
c.connect(
    "82.29.178.160",
    username="root",
    password=root_pw,
    timeout=60,
    allow_agent=False,
    look_for_keys=False,
)

cmd = r"""
set -euo pipefail

# 1) Env: correct ASSET_URL + secure cookies
ACC=/opt/spot-staging/accounts/.env
POS=/opt/spot-staging/pos/.env
rewrite() {
  local f="$1" k="$2" v="$3"
  if grep -q "^${k}=" "$f"; then sed -i "s|^${k}=.*|${k}=${v}|" "$f"
  else echo "${k}=${v}" >> "$f"; fi
}
rewrite "$ACC" ASSET_URL "https://accounts.thespotmanagment.io"
rewrite "$ACC" APP_URL "https://accounts.thespotmanagment.io"
rewrite "$ACC" SESSION_SECURE_COOKIE "true"
# POS assets if set
if grep -q '^ASSET_URL=' "$POS"; then
  rewrite "$POS" ASSET_URL "https://pos.thespotmanagment.io"
fi
rewrite "$POS" SESSION_SECURE_COOKIE "true"

# 2) Force HTTPS for staging too (not only production)
ASP=/opt/spot-staging/accounts/app/Providers/AppServiceProvider.php
sed -i "s/if ( \$this->app->environment('production') )/if ( ! \$this->app->environment('local') )/" "$ASP"
grep -n forceScheme "$ASP"

# 3) Trust Traefik as proxy
TP=/opt/spot-staging/accounts/app/Http/Middleware/TrustProxies.php
python3 - <<'PY'
from pathlib import Path
p = Path('/opt/spot-staging/accounts/app/Http/Middleware/TrustProxies.php')
t = p.read_text()
if 'protected $proxies;' in t and "protected $proxies = '*'" not in t:
    t = t.replace('protected $proxies;', "protected $proxies = '*';")
    p.write_text(t)
    print('TrustProxies proxies=*')
else:
    print('TrustProxies already set or unexpected')
print(p.read_text())
PY

# Same for POS if present
if [ -f /opt/spot-staging/pos/app/Providers/AppServiceProvider.php ]; then
  sed -i "s/if ( \$this->app->environment('production') )/if ( ! \$this->app->environment('local') )/" \
    /opt/spot-staging/pos/app/Providers/AppServiceProvider.php || true
fi
if [ -f /opt/spot-staging/pos/app/Http/Middleware/TrustProxies.php ]; then
  python3 - <<'PY'
from pathlib import Path
p = Path('/opt/spot-staging/pos/app/Http/Middleware/TrustProxies.php')
if p.exists():
    t = p.read_text()
    if 'protected $proxies;' in t and "protected \$proxies = '*'" not in t and "protected $proxies = '*'" not in t:
        t = t.replace('protected $proxies;', "protected $proxies = '*';")
        p.write_text(t)
        print('POS TrustProxies proxies=*')
PY
fi

# 4) Rebuild accounts (+ pos if patched) so code changes are in the image
cd /opt/spot-staging
docker compose build accounts 2>&1 | tail -30
docker compose up -d --no-deps --force-recreate accounts accounts-queue
# POS env-only change: recreate to pick SESSION_SECURE / ASSET
docker compose up -d --no-deps --force-recreate pos
sleep 6
docker compose exec -T -u www-data accounts php artisan config:clear
docker compose exec -T -u www-data pos php artisan config:clear || true

echo '--- HTML check ---'
curl -s https://accounts.thespotmanagment.io/manager/login | grep -oE 'https?://[^"'\'' >]+' | sort -u | head -40
"""

_, o, e = c.exec_command(cmd, timeout=1200, get_pty=True)
print((o.read() + e.read()).decode("utf-8", "replace")[-8000:])
c.close()
