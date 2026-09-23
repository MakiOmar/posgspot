#!/usr/bin/env bash
# Import gzipped dumps into mysql container. Run after `docker compose up -d mysql` is healthy.
set -euo pipefail
ROOT="${STAGING_ROOT:-/opt/spot-staging}"
cd "$ROOT"

docker compose exec -T mysql mysql -uroot -p"${MYSQL_ROOT_PASSWORD}" -e \
  "CREATE DATABASE IF NOT EXISTS pos_stg; CREATE DATABASE IF NOT EXISTS accounts_stg;
   CREATE USER IF NOT EXISTS 'spot_stg'@'%' IDENTIFIED BY '${MYSQL_APP_PASSWORD}';
   ALTER USER 'spot_stg'@'%' IDENTIFIED BY '${MYSQL_APP_PASSWORD}';
   GRANT ALL PRIVILEGES ON pos_stg.* TO 'spot_stg'@'%';
   GRANT ALL PRIVILEGES ON accounts_stg.* TO 'spot_stg'@'%';
   FLUSH PRIVILEGES;"

echo "==> Importing POS dump"
gzip -dc "$ROOT/data/dumps/pos_stg.sql.gz" | docker compose exec -T mysql \
  mysql -uroot -p"${MYSQL_ROOT_PASSWORD}" pos_stg

echo "==> Importing Accounts dump"
gzip -dc "$ROOT/data/dumps/accounts_stg.sql.gz" | docker compose exec -T mysql \
  mysql -uroot -p"${MYSQL_ROOT_PASSWORD}" accounts_stg

echo "==> Import done"
