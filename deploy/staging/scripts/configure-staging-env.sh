#!/usr/bin/env bash
# Rewrite cloned Laravel .env files for staging hosts/DB. Run on VPS after pull.
set -euo pipefail
ROOT="${STAGING_ROOT:-/opt/spot-staging}"
DB_PASS="${MYSQL_APP_PASSWORD:?Set MYSQL_APP_PASSWORD}"

rewrite_kv() {
  local file="$1" key="$2" val="$3"
  if grep -q "^${key}=" "$file"; then
    sed -i "s|^${key}=.*|${key}=${val}|" "$file"
  else
    echo "${key}=${val}" >> "$file"
  fi
}

POS_ENV="$ROOT/pos/.env"
ACC_ENV="$ROOT/accounts/.env"

if [[ ! -f "$POS_ENV" ]]; then
  echo "Missing $POS_ENV — run pull-from-hostinger.sh first" >&2
  exit 1
fi
if [[ ! -f "$ACC_ENV" ]]; then
  echo "Missing $ACC_ENV — run pull-from-hostinger.sh first" >&2
  exit 1
fi

cp -a "$POS_ENV" "$POS_ENV.bak"
cp -a "$ACC_ENV" "$ACC_ENV.bak"

# POS
rewrite_kv "$POS_ENV" APP_URL "https://pos.thespotmanagment.io"
rewrite_kv "$POS_ENV" APP_ENV "staging"
rewrite_kv "$POS_ENV" APP_DEBUG "true"
rewrite_kv "$POS_ENV" STOREFRONT_URL "https://thespotmanagment.io"
rewrite_kv "$POS_ENV" CORS_ALLOWED_ORIGINS "https://thespotmanagment.io,https://www.thespotmanagment.io"
rewrite_kv "$POS_ENV" ACCOUNTS_BASE_URL "https://accounts.thespotmanagment.io"
rewrite_kv "$POS_ENV" DB_HOST "mysql"
rewrite_kv "$POS_ENV" DB_PORT "3306"
rewrite_kv "$POS_ENV" DB_DATABASE "pos_stg"
rewrite_kv "$POS_ENV" DB_USERNAME "spot_stg"
rewrite_kv "$POS_ENV" DB_PASSWORD "$DB_PASS"
rewrite_kv "$POS_ENV" REDIS_HOST "redis"
rewrite_kv "$POS_ENV" REDIS_PORT "6379"
rewrite_kv "$POS_ENV" REDIS_CLIENT "phpredis"
rewrite_kv "$POS_ENV" REDIS_CACHE_DB "0"
rewrite_kv "$POS_ENV" CACHE_DRIVER "redis"
rewrite_kv "$POS_ENV" CACHE_STORE "redis"
rewrite_kv "$POS_ENV" QUEUE_CONNECTION "database"
rewrite_kv "$POS_ENV" SESSION_DRIVER "file"
# Sandbox / disable live side effects
rewrite_kv "$POS_ENV" MAIL_MAILER "log"
rewrite_kv "$POS_ENV" MAIL_HOST "localhost"
# Common payment gateways — force non-live where keys exist
for k in PAYMOB_MODE PAYMENT_MODE GATEWAY_MODE FATOORAH_MODE; do
  if grep -q "^${k}=" "$POS_ENV" 2>/dev/null; then
    rewrite_kv "$POS_ENV" "$k" "sandbox"
  fi
done
for k in STRIPE_SECRET PAYMOB_API_KEY PAYMOB_SECRET_KEY; do
  if grep -q "^${k}=" "$POS_ENV" 2>/dev/null; then
    rewrite_kv "$POS_ENV" "$k" "STAGING_DISABLED"
  fi
done

# Accounts
rewrite_kv "$ACC_ENV" APP_URL "https://accounts.thespotmanagment.io"
rewrite_kv "$ACC_ENV" APP_ENV "staging"
rewrite_kv "$ACC_ENV" APP_DEBUG "true"
rewrite_kv "$ACC_ENV" DB_HOST "mysql"
rewrite_kv "$ACC_ENV" DB_PORT "3306"
rewrite_kv "$ACC_ENV" DB_DATABASE "accounts_stg"
rewrite_kv "$ACC_ENV" DB_USERNAME "spot_stg"
rewrite_kv "$ACC_ENV" DB_PASSWORD "$DB_PASS"
rewrite_kv "$ACC_ENV" REDIS_HOST "redis"
rewrite_kv "$ACC_ENV" REDIS_PORT "6379"
rewrite_kv "$ACC_ENV" REDIS_CLIENT "phpredis"
rewrite_kv "$ACC_ENV" REDIS_CACHE_DB "1"
rewrite_kv "$ACC_ENV" CACHE_DRIVER "redis"
rewrite_kv "$ACC_ENV" CACHE_STORE "redis"
rewrite_kv "$ACC_ENV" QUEUE_CONNECTION "database"
rewrite_kv "$ACC_ENV" MAIL_MAILER "log"

echo "Staging env remapped. Backups: *.env.bak"
