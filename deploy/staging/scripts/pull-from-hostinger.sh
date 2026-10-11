#!/usr/bin/env bash
# Run ON the VPS as root. Pulls DB dumps, media, storage and live .env files from Hostinger
# into /opt/spot-staging. Code comes from git (pos/ and accounts/ are checkouts), not from here.
# Safe to re-run: rsync is incremental, so the maintenance-window run only copies the delta.
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
SSH_OPTS=(-p "$HOSTINGER_PORT" -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null)
RSYNC_RSH="sshpass -e ssh -p ${HOSTINGER_PORT} -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null"
REMOTE="${HOSTINGER_USER}@${HOSTINGER_HOST}"

POS_REMOTE="/home/${HOSTINGER_USER}/domains/gamesspoteg.com/public_html/pos.gamesspoteg.com"
ACC_REMOTE="/home/${HOSTINGER_USER}/domains/gamesspoteg.com/public_html/accounts.gamesspoteg.com"

D="$STAGING_ROOT/data"
mkdir -p "$D"/{dumps,live-env,pos_uploads,pos_storage,accounts_storage,accounts_logos} \
  "$D"/accounts_assets/{uploads,ps4,ps5}
chmod 700 "$D/live-env"

echo "==> Dumping MySQL on Hostinger (read-only)"
sshpass -e ssh "${SSH_OPTS[@]}" "$REMOTE" 'bash -s' <<'EOS'
set -euo pipefail
POS=$HOME/domains/gamesspoteg.com/public_html/pos.gamesspoteg.com
ACC=$HOME/domains/gamesspoteg.com/public_html/accounts.gamesspoteg.com
mkdir -p ~/tmp-spot-staging
envval() { grep "^$2=" "$1/.env" | head -1 | cut -d= -f2- | tr -d '\r' | sed -e 's/^"//' -e 's/"$//'; }
dump() {
  local app="$1" out="$2"
  MYSQL_PWD="$(envval "$app" DB_PASSWORD)" mysqldump -h"$(envval "$app" DB_HOST)" -u"$(envval "$app" DB_USERNAME)" \
    --single-transaction --quick --routines --triggers --no-tablespaces "$(envval "$app" DB_DATABASE)" | gzip -c > "$out"
}
dump "$POS" ~/tmp-spot-staging/pos_stg.sql.gz
dump "$ACC" ~/tmp-spot-staging/accounts_stg.sql.gz
ls -lh ~/tmp-spot-staging/
EOS

echo "==> Fetching dumps + live .env files"
# One file per scp: Hostinger's server rejects multiple remote sources in one call
fetch() {
  sshpass -e scp -P "$HOSTINGER_PORT" -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null -q "$REMOTE:$1" "$2"
}
fetch tmp-spot-staging/pos_stg.sql.gz "$D/dumps/pos_stg.sql.gz"
fetch tmp-spot-staging/accounts_stg.sql.gz "$D/dumps/accounts_stg.sql.gz"
fetch "${POS_REMOTE}/.env" "$D/live-env/pos.env"
fetch "${ACC_REMOTE}/.env" "$D/live-env/accounts.env"
chmod 600 "$D"/live-env/*.env
sshpass -e ssh "${SSH_OPTS[@]}" "$REMOTE" 'rm -rf ~/tmp-spot-staging'

pull() { rsync -az --delete -e "$RSYNC_RSH" "$@"; }

echo "==> Rsync POS uploads + storage"
pull "$REMOTE:${POS_REMOTE}/public/uploads/" "$D/pos_uploads/"
pull --exclude logs --exclude framework/cache --exclude framework/views \
  "$REMOTE:${POS_REMOTE}/storage/" "$D/pos_storage/"

echo "==> Rsync Accounts storage + manager uploads + logos"
pull --exclude logs --exclude framework/cache --exclude framework/views \
  "$REMOTE:${ACC_REMOTE}/storage/" "$D/accounts_storage/"
for sub in uploads ps4 ps5; do
  pull "$REMOTE:${ACC_REMOTE}/public/assets/${sub}/" "$D/accounts_assets/${sub}/"
done
pull "$REMOTE:${ACC_REMOTE}/public/logos/" "$D/accounts_logos/"

# Containers run as www-data (uid 33)
chown -R 33:33 "$D"/pos_uploads "$D"/pos_storage "$D"/accounts_storage "$D"/accounts_assets "$D"/accounts_logos

echo "==> Pull complete"
ls -lh "$D/dumps"
du -sh "$D"/pos_uploads "$D"/pos_storage "$D"/accounts_storage "$D"/accounts_assets "$D"/accounts_logos
