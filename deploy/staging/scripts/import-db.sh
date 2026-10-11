#!/usr/bin/env bash
# Replace pos_stg / accounts_stg with the gzipped dumps in data/dumps. Run after mysql is healthy.
# Drops both databases first so no stale tables survive a re-import.
set -euo pipefail
ROOT="${STAGING_ROOT:-/opt/spot-staging}"
cd "$ROOT"
set -a; source ./.env; set +a
: "${MYSQL_ROOT_PASSWORD:?}" "${MYSQL_APP_PASSWORD:?}"

sql() { docker compose exec -T -e MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql mysql -uroot "$@"; }

sql -e "DROP DATABASE IF EXISTS pos_stg; DROP DATABASE IF EXISTS accounts_stg;
  CREATE DATABASE pos_stg CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
  CREATE DATABASE accounts_stg CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
  CREATE USER IF NOT EXISTS 'spot_stg'@'%' IDENTIFIED BY '${MYSQL_APP_PASSWORD}';
  ALTER USER 'spot_stg'@'%' IDENTIFIED BY '${MYSQL_APP_PASSWORD}';
  GRANT ALL PRIVILEGES ON pos_stg.* TO 'spot_stg'@'%';
  GRANT ALL PRIVILEGES ON accounts_stg.* TO 'spot_stg'@'%';
  FLUSH PRIVILEGES;" </dev/null

echo "==> Importing POS dump"
gzip -dc data/dumps/pos_stg.sql.gz | sql pos_stg
echo "==> Importing Accounts dump"
gzip -dc data/dumps/accounts_stg.sql.gz | sql accounts_stg
echo "==> Import done"
