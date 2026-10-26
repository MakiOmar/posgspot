# Production VPS (Docker Compose)

POS, Accounts and the Qwik storefront run on Ubuntu VPS **`82.29.178.160`** behind **Cloudflare**, under **`gamesspoteg.com`**. Hostinger is kept in maintenance mode as a rollback for 1–2 weeks after cutover.

Compose skeleton and scripts live in the repo at [`deploy/staging/`](../../deploy/staging/) (the folder and compose project keep the historical `staging` name). On the VPS the working tree is **`/opt/spot-staging/`**, where `pos/` and `accounts/` are git checkouts of `master`.

Related: [Storefront deploy](./DEPLOY.md) · [API](./API.md) · [Configuration](../CONFIGURATION.md)

---

## Domain map

| Host | Service |
| ---- | ------- |
| `gamesspoteg.com` (+ `www` → apex) | Qwik storefront (`storefront:3000`) |
| `pos.gamesspoteg.com` | POS (`pos:8080`) |
| `accounts.gamesspoteg.com` | Accounts (`accounts:8080`) |

Hosts come from `/opt/spot-staging/.env` (`SHOP_DOMAIN`, `POS_DOMAIN`, `ACCOUNTS_DOMAIN`). WordPress and `thespotmanagment.io` are retired. Other subdomains (`itq`, `smk`, `ulsp`, `staging`, `api`, wildcard, mail) stay on Hostinger.

---

## Cloudflare

- `@`, `www`, `pos`, `accounts` are **proxied** A records to the VPS; SSL/TLS mode **Full (strict)**.
- Traefik gets Let's Encrypt certificates through the **DNS-01** challenge (`dnsChallenge.provider: cloudflare`), using `CF_DNS_API_TOKEN` (Zone DNS Edit + Zone Read on `gamesspoteg.com`) from the compose `.env`. Works while records are proxied; keep the token valid or renewals fail.
- Traefik entrypoints trust `X-Forwarded-*` only from Cloudflare ranges; both apps set `TRUSTED_PROXIES` (Docker ranges + Cloudflare ranges, written by `configure-production-env.sh`) so `$request->ip()` is the visitor.
- Rollback = point the four records back to Hostinger (`93.127.205.20`) and `php artisan up` there. Proxied records switch in seconds.
- After a deploy that changes static assets, purge the Cloudflare cache.

---

## Architecture

```
Visitor → Cloudflare (proxy, TLS) → Traefik (80/443, DNS-01 certs)
             ├─ Host(pos.gamesspoteg.com)      → pos:8080 (×2)
             ├─ Host(accounts.gamesspoteg.com) → accounts:8080
             └─ Host(gamesspoteg.com|www)      → storefront:3000

pos / accounts → mysql (pos_stg, accounts_stg) + redis
pos-queue (×2), pos-scheduler, accounts-queue, accounts-scheduler (same images)
```

Redis: POS caches in DB 0; Accounts caches in DB 1 and uses DB 2 for its default connection (cache locks), set by `configure-production-env.sh`.

Persistent host binds (survive image rebuilds):

- `data/pos_uploads` → POS `public/uploads`; `data/pos_storage` → POS `storage`
- `data/accounts_storage` → Accounts `storage`
- `data/accounts_assets/{uploads,ps4,ps5}` → Accounts `public/assets/...`; `data/accounts_logos` → Accounts `public/logos`
- Named volumes: `mysql_data`, `redis_data`, `traefik_letsencrypt`

Compose secrets: `/opt/spot-staging/.env` (mode 600) — see [`deploy/staging/.env.example`](../../deploy/staging/.env.example).

---

## Day-2 operations

Copy-paste deploy steps per service and for everything at once: [VPS_PULL_AND_REBUILD.md](./VPS_PULL_AND_REBUILD.md).

```bash
cd /opt/spot-staging
docker compose ps
docker compose logs -f --tail=100 pos accounts storefront traefik

# Deploy new code (only after it is committed and pushed — never edit code on the VPS)
git -C pos pull && git -C accounts pull
cp pos/deploy/staging/docker-compose.yml . && cp -r pos/deploy/staging/traefik pos/deploy/staging/storefront .   # when infra changed
cp pos/deploy/staging/pos/{Dockerfile,.dockerignore} pos/ && cp pos/deploy/staging/accounts/{Dockerfile,.dockerignore} accounts/   # compose builds from the checkouts
docker compose up -d --build pos accounts storefront
docker compose up -d --force-recreate --scale pos=2 --scale pos-queue=2 pos-queue pos-scheduler accounts-queue accounts-scheduler
docker compose exec -u www-data pos php artisan migrate --force
docker compose exec -u www-data accounts php artisan migrate --force
```

Laravel caches: the `pos` and `accounts` web containers run `php artisan optimize --except=views` at boot (serversideup `AUTORUN_*` in the Dockerfiles; migrations and `storage:link` stay off). Config, routes and events are cached per container, so:

- `.env` edits need a container recreate (`docker compose up -d --force-recreate pos accounts`), as before.
- Do not run `optimize:clear` after a deploy — it drops the caches until the next restart. Use `cache:clear` for data caches.
- If boot-time optimize fails, the container exits and restarts in a loop. Before deploying config/route changes, dry-run it: `docker compose run --rm --no-deps pos php artisan optimize --except=views` (same for `accounts`).

Workers override the entrypoint, so they skip AUTORUN and read config uncached.

`accounts-scheduler` runs `schedule:work` (hourly secondary-stock sync, System Ops auto backup when enabled in the UI). It sets `QUEUE_WORKER_VIA_SCHEDULER=false` because `accounts-queue` already drains the queue; the scheduler-driven `queue:work --stop-when-empty` remains the default for cron-only hosts (Hostinger rollback). The POS and Accounts images include `default-mysql-client` (MariaDB) for Spatie backups; `DB_DUMP_SKIP_SSL=true` in `pos/.env` and `accounts/.env` because that client rejects MySQL's self-signed certificate. POS automatic backups run in `pos-scheduler`, so recreate it after a POS image rebuild. Nothing prunes old local backups yet (`data/accounts_storage/app/<APP_NAME>/`, ~9 MB per hourly zip). **Google Drive copies:** set `BACKUP_GOOGLE_DRIVE_ENABLED=true` and the `GOOGLE_DRIVE_*` keys in `pos/.env` and `accounts/.env` (different `GOOGLE_DRIVE_FOLDER` per app), recreate `pos`, `pos-queue`, `pos-scheduler`, `accounts`, `accounts-queue` and `accounts-scheduler` (env is read at container start), then run `docker compose exec -T -u www-data pos php artisan backup:drive-check` (and the same in `accounts`). See `docs/integrations/GOOGLE_DRIVE_BACKUPS.md`.

Storefront: the `storefront` image builds from the git checkout `pos/storefront-qwik` (`npm ci` + `npm run build` inside Docker, using the committed `.env.production`). Do not upload locally built bundles. Purge the Cloudflare cache after shop deploys.

Workers (`pos-queue`, `pos-scheduler`, `accounts-queue`, `accounts-scheduler`) use the image's `healthcheck-queue` / `healthcheck-schedule` scripts (process check), not the web image's HTTP check on port 8080. To recreate only the workers: `docker compose up -d --no-deps --scale pos-queue=2 pos-queue pos-scheduler accounts-queue accounts-scheduler`.

Shop-only deploy: `docker compose up -d --build --no-deps storefront`. Without `--no-deps`, Compose also rebuilds and recreates `pos` (a `depends_on` of `storefront`), taking staff POS down for ~30–60s.

### Troubleshooting: Cloudflare 504 on the shop

The shop renders by calling POS (`https://pos.gamesspoteg.com`, via Cloudflare). If POS's PHP-FPM pool (40 workers, one replica) saturates, nginx in the `pos` container runs out of connections/file handles, `/healthcheck` times out, Docker marks `pos` unhealthy and **Traefik drops the route** — every POS URL returns 404 (staff POS included) and the shop shows a 504. Check:

```bash
docker compose logs --timestamps --since 1h pos | grep -E 'max_children|Too many open files|upstream timed out'
docker compose logs --timestamps --since 1h storefront | grep 'loader failed'
docker compose logs --no-log-prefix --since 1h pos | grep '"node"' | awk '{print $7}' | cut -d'?' -f1 | sort | uniq -c | sort -rn | head   # SSR fan-out by endpoint
```

The storefront SSR cache (see [API](./API.md) rate-limit notes) absorbs bursts; identify the visitors behind a burst in Cloudflare Analytics / Security Events.

---

## Cutover from Hostinger (runbook)

Scripts run on the VPS as root from `/opt/spot-staging`:

1. `HOSTINGER_SSH_PASS=… bash scripts/pull-from-hostinger.sh` — DB dumps, POS uploads/storage, Accounts storage/assets/logos, live `.env` files into `data/live-env/` (mode 600). Incremental; re-run during the window.
2. `bash scripts/configure-production-env.sh` — writes `pos/.env` and `accounts/.env` from the live files (keeps `APP_KEY`, mail, payments) and rewires hosts, DB, Redis, `TRUSTED_PROXIES`.
3. `bash scripts/import-db.sh` — drops and re-imports `pos_stg` / `accounts_stg`.
4. `migrate --force` on both apps, then recreate the web containers and workers (boot rebuilds the Laravel caches).

The live databases hold no `new.gamesspoteg.com` or WordPress URLs (checked at cutover), so no content rewrite is needed; `.env` hosts are rewritten in step 2.

Maintenance window order: Hostinger `php artisan down` + cron off → steps 1–4 → DNS switch in Cloudflare → SSL Full (strict) → purge cache → smoke tests.

DNS switch detail: `pos` and `accounts` are CNAMEs to the apex, and so are Hostinger-only hosts (`itq`, `smk`, `ulsp`, `staging`). Pin those to Hostinger's IP **before** moving the apex A record, delete the apex AAAA records (they pointed at Hostinger), and point `www` at the VPS.

---

## Smoke checklist

Before DNS points at the VPS, test through Traefik with Host headers (HTTP redirects to HTTPS; certificates already exist via DNS-01):

```bash
IP=82.29.178.160
curl -sk --resolve pos.gamesspoteg.com:443:$IP https://pos.gamesspoteg.com/api/storefront/v1/settings | head -c 300
curl -skI --resolve accounts.gamesspoteg.com:443:$IP https://accounts.gamesspoteg.com/manager/login
curl -skI --resolve gamesspoteg.com:443:$IP https://gamesspoteg.com/en/
```

After the switch: valid HTTPS on all hosts, POS login + test sale, Accounts login + Send to POS, shop EN/AR + checkout to the payment page, track order, queue/scheduler logs healthy, `robots.txt` still blocks indexing, activity log shows real visitor IPs.

---

## FTP / file access

User **`spotftp`** is chrooted to `/opt/spot-staging` (port 21, explicit FTPS, passive `40000–40100`; password in local `deploy/staging/.vps-secrets.env` → `FTP_PASSWORD`). Code under `pos/` / `accounts/` / `storefront/` is baked into images; edits apply after `docker compose up -d --build …`. Media and logs under `data/` apply immediately.

- Never commit `/opt/spot-staging/.env`, app `.env`, `data/live-env/`, SQL dumps, or `acme.json`.
- VPS root password lives only in local `deploy/staging/.vps-secrets.env` (gitignored). Prefer SSH keys.

---

## Load testing (k6)

See [`load-tests/k6/README.md`](../../load-tests/k6/README.md). Point `BASE_URL` at `https://pos.gamesspoteg.com/api/storefront/v1` only with operator approval — it is production now.

---

## Repo layout

```
deploy/staging/
  docker-compose.yml
  .env.example
  traefik/traefik.yml
  mysql/init/01-databases.sql
  pos/Dockerfile  accounts/Dockerfile  storefront/Dockerfile
  scripts/
    pull-from-hostinger.sh
    configure-production-env.sh
    import-db.sh
    bootstrap-vps.py
```
