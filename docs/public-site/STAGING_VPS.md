# Staging VPS (Docker Compose)

Isolated full clone of POS, Accounts, and the Qwik storefront on Ubuntu VPS **`82.29.178.160`**, under **`thespotmanagment.io`**. Live Hostinger (`*.gamesspoteg.com`) stays untouched.

Compose skeleton + scripts live in the repo at [`deploy/staging/`](../../deploy/staging/). On the VPS the working tree is **`/opt/spot-staging/`**.

Related: [Storefront deploy](./DEPLOY.md) · [API](./API.md) · [Configuration](../CONFIGURATION.md)

---

## Domain map

| Live (Hostinger — do not change DNS yet) | Staging (VPS) |
| ---------------------------------------- | ------------- |
| `pos.gamesspoteg.com` | `pos.thespotmanagment.io` |
| `accounts.gamesspoteg.com` | `accounts.thespotmanagment.io` |
| `new.gamesspoteg.com` (Qwik) | `thespotmanagment.io` (+ `www` → apex) |

**DNS prerequisite:** A records for `thespotmanagment.io`, `www`, `pos`, `accounts` → `82.29.178.160`. Until that is true, Traefik cannot finish Let's Encrypt; smoke-test with `Host:` headers over HTTP/IP.

---

## Architecture

```
Internet → Traefik (80/443, ACME)
             ├─ Host(pos.thespotmanagment.io)      → pos:8080
             ├─ Host(accounts.thespotmanagment.io) → accounts:8080
             └─ Host(thespotmanagment.io|www)      → storefront:3000

pos / accounts → mysql (pos_stg, accounts_stg) + redis
pos-queue, pos-scheduler, accounts-queue (same images)
```

Persistent host binds (survive image rebuilds):

- `/opt/spot-staging/data/pos_uploads` → POS `public/uploads`
- `/opt/spot-staging/data/pos_storage` → POS `storage`
- `/opt/spot-staging/data/accounts_storage` → Accounts `storage`
- Named volumes: `mysql_data`, `redis_data`, `traefik_letsencrypt`

---

## Services

| Service | Role |
| ------- | ---- |
| `traefik` | TLS edge + HTTP for ACME / Host-header smoke (`traefik:v3.6+`; older tags break on Docker Engine 29+ API) |
| `mysql` | MySQL 8 — DBs `pos_stg`, `accounts_stg`; user `spot_stg` |
| `redis` | Redis 7 |
| `pos` | Laravel POS (serversideup PHP 8.3 FPM+Nginx) |
| `pos-queue` / `pos-scheduler` | `queue:work` / `schedule:work` |
| `accounts` | Laravel Accounts |
| `accounts-queue` | Accounts queue worker |
| `storefront` | Node 20 — `node server/entry.express` |

Compose secrets (not app `.env`): `/opt/spot-staging/.env` — see [`deploy/staging/.env.example`](../../deploy/staging/.env.example).

---

## Day-2 operations

SSH: `root@82.29.178.160` (prefer SSH keys; rotate any password shared in chat).

```bash
cd /opt/spot-staging

# Status / logs
docker compose ps
docker compose logs -f --tail=100 pos accounts storefront traefik

# Rebuild after code or Dockerfile changes
docker compose up -d --build pos accounts storefront

# Restart workers after POS/Accounts image rebuild
docker compose up -d --build pos-queue pos-scheduler accounts-queue
```

### Refresh DB from live (read-only on Hostinger)

```bash
export HOSTINGER_SSH_PASS='…'   # Hostinger SSH password
bash scripts/pull-from-hostinger.sh   # or re-dump only via orchestrator
export MYSQL_ROOT_PASSWORD='…' MYSQL_APP_PASSWORD='…'
# drop/recreate then:
bash scripts/import-db.sh
```

Prefer a controlled dump+import over sharing the live DB. Staging must never write to Hostinger MySQL.

### Remap Laravel `.env` after a fresh pull

```bash
export MYSQL_APP_PASSWORD='…'
bash scripts/configure-staging-env.sh
docker compose exec -u www-data pos php artisan config:clear
docker compose exec -u www-data accounts php artisan config:clear
```

Key staging values:

- POS: `APP_URL`, `STOREFRONT_URL`, `CORS_ALLOWED_ORIGINS`, `ACCOUNTS_BASE_URL`, `DB_HOST=mysql`, `CACHE_DRIVER=redis` / `CACHE_STORE=redis`, `REDIS_HOST=redis`, `MAIL_MAILER=log`
- Accounts: `APP_URL`, `DB_HOST=mysql`, `CACHE_DRIVER=redis`, `REDIS_CACHE_DB=1`, `MAIL_MAILER=log`
- Storefront image: bake `PUBLIC_API_BASE=https://pos.thespotmanagment.io`, `PUBLIC_ACCOUNTS_BASE=https://accounts.thespotmanagment.io`, run with `ORIGIN=https://thespotmanagment.io`

### Load testing (k6)

From a workstation with [k6](https://k6.io/) installed (see [`load-tests/k6/README.md`](../../load-tests/k6/README.md)):

```powershell
# Mixed shop HTML + POS API + Accounts (staging defaults)
k6 run -e SCENARIO=load -e BASE_URL=https://pos.thespotmanagment.io/api/storefront/v1 `
  --summary-export load-tests/k6/results/summary-vps-load.json `
  load-tests/k6/scenarios/vps-mixed.js

k6 run -e SCENARIO=stress -e BASE_URL=https://pos.thespotmanagment.io/api/storefront/v1 `
  --summary-export load-tests/k6/results/summary-vps-stress.json `
  load-tests/k6/scenarios/vps-mixed.js
```

Staging POS uses Redis cache (`CACHE_DRIVER=redis`, `REDIS_HOST=redis`). For capacity runs, `STOREFRONT_RATE_LIMIT_READ` may be raised temporarily (e.g. 6000) so per-IP throttle does not dominate results.

On a workstation (from `storefront-qwik/`):

```bash
# temporarily set staging PUBLIC_* in .env.production, then:
npm run build
# pack dist/ + server/ + package.json + package-lock.json into /opt/spot-staging/storefront/
# restore live .env.production afterwards
cd /opt/spot-staging && docker compose up -d --build storefront
```

---

## Smoke checklist

Until DNS points at the VPS, use the VPS IP and Host headers:

```bash
IP=82.29.178.160
curl -sI -H 'Host: pos.thespotmanagment.io' "http://$IP/" | head
curl -s -H 'Host: pos.thespotmanagment.io' "http://$IP/api/storefront/v1/settings" | head -c 400
curl -sI -H 'Host: accounts.thespotmanagment.io' "http://$IP/" | head
curl -sI -H 'Host: thespotmanagment.io' "http://$IP/" | head
```

After DNS + ACME:

1. HTTPS certs valid on all three hosts.
2. POS admin login on `https://pos.thespotmanagment.io`.
3. Shop EN/AR loads; browser network calls go to staging POS (CORS OK).
4. Accounts reachable; device track / digital proxy from POS still works.
5. `docker compose logs pos-queue` shows a worker heartbeat / test job.
6. Confirm no writes to live Hostinger DBs (staging `DB_HOST=mysql` only).

---

## Security notes

- Never commit `/opt/spot-staging/.env`, app `.env`, SQL dumps, or `acme.json`.
- VPS root password was rotated after bootstrap; value is only in local `deploy/staging/.vps-secrets.env` (gitignored). Prefer SSH keys going forward.
- Rotate the Hostinger SSH password that was used for the one-time clone (out of band on Hostinger).
- Staging uses `MAIL_MAILER=log` and payment keys stubbed where remapped — do not put live gateway secrets in staging.
- Keep `APP_KEY` from the live clone if encrypted columns exist.
- PHP images install `gd` + `git`/`unzip`; `composer install --no-scripts` (package discover runs after containers are up with MySQL).
- POS/Accounts queue + scheduler share `image: spot-staging/pos:latest` / `spot-staging/accounts:latest` with the web containers (do not build separate stale images).

---

## Future cutover (documented only — not this phase)

1. Point `pos.gamesspoteg.com`, `accounts.gamesspoteg.com`, and shop DNS at the VPS (or reverse-proxy).
2. Swap staging payment/mail credentials for live keys; set `APP_ENV=production`, `APP_DEBUG=false`.
3. Rebuild storefront with live `PUBLIC_API_BASE` / `PUBLIC_ACCOUNTS_BASE`.
4. Keep Hostinger as rollback until traffic and queues are confirmed.
5. Do **not** share one database between live and staging during cutover rehearsal.

---

## Repo layout

```
deploy/staging/
  docker-compose.yml
  .env.example
  traefik/traefik.yml
  mysql/init/01-databases.sql
  pos/Dockerfile
  accounts/Dockerfile
  storefront/Dockerfile
  scripts/
    pull-from-hostinger.sh
    configure-staging-env.sh
    import-db.sh
    bootstrap-vps.py          # initial Docker + tree sync
```
