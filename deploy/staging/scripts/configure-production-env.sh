#!/usr/bin/env bash
# Build pos/.env and accounts/.env for production on the VPS from the live Hostinger .env files
# (data/live-env/*.env, fetched by pull-from-hostinger.sh). Keeps live APP_KEY, mail, payment and
# integration secrets; only rewrites hosts, DB/Redis wiring and proxy trust. Never prints values.
set -euo pipefail
ROOT="${STAGING_ROOT:-/opt/spot-staging}"
cd "$ROOT"
set -a; source ./.env; set +a
: "${MYSQL_APP_PASSWORD:?Set MYSQL_APP_PASSWORD in $ROOT/.env}"
SHOP="${SHOP_DOMAIN:-gamesspoteg.com}"
POS="${POS_DOMAIN:-pos.gamesspoteg.com}"
ACC="${ACCOUNTS_DOMAIN:-accounts.gamesspoteg.com}"

for f in data/live-env/pos.env data/live-env/accounts.env; do
  [[ -f "$f" ]] || { echo "Missing $f — run pull-from-hostinger.sh first" >&2; exit 1; }
done

# Docker private ranges + Cloudflare edge ranges (fetched fresh so the list never goes stale)
CF_IPS="$(curl -fsS https://www.cloudflare.com/ips-v4; echo; curl -fsS https://www.cloudflare.com/ips-v6)"
TRUSTED="10.0.0.0/8,172.16.0.0/12,192.168.0.0/16,$(echo "$CF_IPS" | grep -v '^$' | paste -sd, -)"

set_kv() {
  local file="$1" key="$2" val="$3"
  if grep -q "^${key}=" "$file"; then
    # Escape sed replacement metacharacters in the value
    local esc; esc="$(printf '%s' "$val" | sed -e 's/[\/&|]/\\&/g')"
    sed -i "s|^${key}=.*|${key}=${esc}|" "$file"
  else
    printf '%s=%s\n' "$key" "$val" >> "$file"
  fi
}

wire_common() {
  local f="$1" db="$2" cache_db="$3" url="$4"
  # The shop moved from new.<domain> to the apex
  sed -i "s#new\.${SHOP//./\\.}#${SHOP}#g" "$f"
  set_kv "$f" APP_URL "$url"
  set_kv "$f" ASSET_URL "$url"
  set_kv "$f" APP_ENV production
  set_kv "$f" APP_DEBUG false
  set_kv "$f" DB_CONNECTION mysql
  set_kv "$f" DB_HOST mysql
  set_kv "$f" DB_PORT 3306
  set_kv "$f" DB_DATABASE "$db"
  set_kv "$f" DB_USERNAME spot_stg
  set_kv "$f" DB_PASSWORD "$MYSQL_APP_PASSWORD"
  set_kv "$f" REDIS_HOST redis
  set_kv "$f" REDIS_PORT 6379
  set_kv "$f" REDIS_PASSWORD null
  set_kv "$f" REDIS_CLIENT phpredis
  set_kv "$f" REDIS_CACHE_DB "$cache_db"
  set_kv "$f" CACHE_DRIVER redis
  set_kv "$f" CACHE_STORE redis
  set_kv "$f" QUEUE_CONNECTION database
  set_kv "$f" SESSION_SECURE_COOKIE true
  set_kv "$f" TRUSTED_PROXIES "$TRUSTED"
}

install -m 640 data/live-env/pos.env pos/.env
install -m 640 data/live-env/accounts.env accounts/.env
# Live files may lack a trailing newline; appended keys would otherwise glue onto the last line
for f in pos/.env accounts/.env; do [ -n "$(tail -c1 "$f")" ] && echo >> "$f"; done

wire_common pos/.env pos_stg 0 "https://${POS}"
set_kv pos/.env STOREFRONT_URL "https://${SHOP}"
set_kv pos/.env CORS_ALLOWED_ORIGINS "https://${SHOP},https://www.${SHOP}"
set_kv pos/.env ACCOUNTS_BASE_URL "https://${ACC}"

wire_common accounts/.env accounts_stg 1 "https://${ACC}"
# POS caches in Redis DB 0; keep Accounts' default connection (cache locks) out of it so a POS cache:clear can't drop them
set_kv accounts/.env REDIS_DB 2
# The image's MariaDB mysqldump rejects MySQL's self-signed cert; traffic stays on spot_internal
set_kv accounts/.env DB_DUMP_SKIP_SSL true
# WordPress is retired; nothing to notify
set_kv accounts/.env WORDPRESS_WEBHOOK_URL ""

# Containers read .env as www-data; the spotftp FTP user owns it so operators can edit via FTPS
if id spotftp >/dev/null 2>&1; then chown spotftp pos/.env accounts/.env; fi
chgrp 33 pos/.env accounts/.env
echo "Production env written: pos/.env, accounts/.env (TRUSTED_PROXIES has $(echo "$TRUSTED" | tr ',' '\n' | wc -l) ranges)"
