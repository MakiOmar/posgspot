#!/usr/bin/env bash
# Run ON the VPS as root. Pulls code+DB dumps from Hostinger into /opt/spot-staging.
set -euo pipefail

HOSTINGER_HOST="${HOSTINGER_HOST:-93.127.205.20}"
HOSTINGER_PORT="${HOSTINGER_PORT:-65002}"
HOSTINGER_USER="${HOSTINGER_USER:-u605441708}"
STAGING_ROOT="${STAGING_ROOT:-/opt/spot-staging}"

if [[ -z "${HOSTINGER_SSH_PASS:-}" ]]; then
  echo "Set HOSTINGER_SSH_PASS" >&2
  exit 1
fi

export DEBIAN_FRONTEND=noninteractive
command -v sshpass >/dev/null || apt-get install -y sshpass
command -v rsync >/dev/null || apt-get install -y rsync

export SSHPASS="$HOSTINGER_SSH_PASS"
RSYNC_RSH="sshpass -e ssh -p ${HOSTINGER_PORT} -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null"

POS_REMOTE="/home/${HOSTINGER_USER}/domains/gamesspoteg.com/public_html/pos.gamesspoteg.com"
ACC_REMOTE="/home/${HOSTINGER_USER}/domains/gamesspoteg.com/public_html/accounts.gamesspoteg.com"
SHOP_REMOTE="/home/${HOSTINGER_USER}/domains/new.gamesspoteg.com/nodejs"

mkdir -p "$STAGING_ROOT"/{pos,accounts,storefront,data/{dumps,pos_uploads,pos_storage,accounts_storage}}

echo "==> Dumping MySQL on Hostinger (read-only)"
sshpass -e ssh -p "$HOSTINGER_PORT" -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null \
  "${HOSTINGER_USER}@${HOSTINGER_HOST}" 'bash -s' <<'EOS'
set -euo pipefail
POS=/home/u605441708/domains/gamesspoteg.com/public_html/pos.gamesspoteg.com
ACC=/home/u605441708/domains/gamesspoteg.com/public_html/accounts.gamesspoteg.com
mkdir -p ~/tmp-spot-staging
POSDB=$(grep ^DB_DATABASE= "$POS/.env" | cut -d= -f2- | tr -d '\r')
POSUSER=$(grep ^DB_USERNAME= "$POS/.env" | cut -d= -f2- | tr -d '\r')
POSPASS=$(grep ^DB_PASSWORD= "$POS/.env" | cut -d= -f2- | tr -d '\r')
POSHOST=$(grep ^DB_HOST= "$POS/.env" | cut -d= -f2- | tr -d '\r')
ACCDB=$(grep ^DB_DATABASE= "$ACC/.env" | cut -d= -f2- | tr -d '\r')
ACCUSER=$(grep ^DB_USERNAME= "$ACC/.env" | cut -d= -f2- | tr -d '\r')
ACCPASS=$(grep ^DB_PASSWORD= "$ACC/.env" | cut -d= -f2- | tr -d '\r')
ACCHOST=$(grep ^DB_HOST= "$ACC/.env" | cut -d= -f2- | tr -d '\r')
mysqldump -h"$POSHOST" -u"$POSUSER" -p"$POSPASS" --single-transaction --quick --routines --triggers "$POSDB" | gzip -c > ~/tmp-spot-staging/pos_stg.sql.gz
mysqldump -h"$ACCHOST" -u"$ACCUSER" -p"$ACCPASS" --single-transaction --quick --routines --triggers "$ACCDB" | gzip -c > ~/tmp-spot-staging/accounts_stg.sql.gz
ls -lh ~/tmp-spot-staging/
EOS

echo "==> Fetching dumps"
sshpass -e scp -P "$HOSTINGER_PORT" -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null \
  "${HOSTINGER_USER}@${HOSTINGER_HOST}:tmp-spot-staging/pos_stg.sql.gz" \
  "${HOSTINGER_USER}@${HOSTINGER_HOST}:tmp-spot-staging/accounts_stg.sql.gz" \
  "$STAGING_ROOT/data/dumps/"

echo "==> Rsync POS code"
rsync -az --delete \
  --exclude vendor --exclude node_modules --exclude .git \
  --exclude public/uploads --exclude storage \
  --exclude Dockerfile --exclude .dockerignore --exclude .env \
  -e "$RSYNC_RSH" \
  "${HOSTINGER_USER}@${HOSTINGER_HOST}:${POS_REMOTE}/" "$STAGING_ROOT/pos/"

echo "==> Rsync POS uploads + storage"
rsync -az -e "$RSYNC_RSH" \
  "${HOSTINGER_USER}@${HOSTINGER_HOST}:${POS_REMOTE}/public/uploads/" "$STAGING_ROOT/data/pos_uploads/"
rsync -az -e "$RSYNC_RSH" \
  --exclude logs \
  "${HOSTINGER_USER}@${HOSTINGER_HOST}:${POS_REMOTE}/storage/" "$STAGING_ROOT/data/pos_storage/"

echo "==> Rsync Accounts code"
rsync -az --delete \
  --exclude vendor --exclude node_modules --exclude .git --exclude storage \
  --exclude Dockerfile --exclude .dockerignore --exclude .env \
  -e "$RSYNC_RSH" \
  "${HOSTINGER_USER}@${HOSTINGER_HOST}:${ACC_REMOTE}/" "$STAGING_ROOT/accounts/"

rsync -az -e "$RSYNC_RSH" \
  --exclude logs \
  "${HOSTINGER_USER}@${HOSTINGER_HOST}:${ACC_REMOTE}/storage/" "$STAGING_ROOT/data/accounts_storage/"

echo "==> Rsync shop dist+server"
rsync -az --delete \
  --exclude node_modules --exclude .git --exclude src --exclude adapters \
  --exclude console.log --exclude stderr.log --exclude tmp \
  --exclude Dockerfile --exclude .dockerignore \
  -e "$RSYNC_RSH" \
  "${HOSTINGER_USER}@${HOSTINGER_HOST}:${SHOP_REMOTE}/" "$STAGING_ROOT/storefront/"

echo "==> Pull complete"
ls -lh "$STAGING_ROOT/data/dumps"
du -sh "$STAGING_ROOT/pos" "$STAGING_ROOT/accounts" "$STAGING_ROOT/storefront" \
  "$STAGING_ROOT/data/pos_uploads" "$STAGING_ROOT/data/pos_storage" "$STAGING_ROOT/data/accounts_storage"
