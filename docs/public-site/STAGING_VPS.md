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
pos-queue (×2), pos-scheduler, accounts-queue (same images)
```

Persistent host binds (survive image rebuilds):

- `data/pos_uploads` → POS `public/uploads`; `data/pos_storage` → POS `storage`
- `data/accounts_storage` → Accounts `storage`
- `data/accounts_assets/{uploads,ps4,ps5}` → Accounts `public/assets/...`; `data/accounts_logos` → Accounts `public/logos`
- Named volumes: `mysql_data`, `redis_data`, `traefik_letsencrypt`

Compose secrets: `/opt/spot-staging/.env` (mode 600) — see [`deploy/staging/.env.example`](../../deploy/staging/.env.example).

---

## Day-2 operations

```bash
cd /opt/spot-staging
docker compose ps
docker compose logs -f --tail=100 pos accounts storefront traefik

# Deploy new code (only after it is committed and pushed — never edit code on the VPS)
git -C pos pull && git -C accounts pull
cp pos/deploy/staging/docker-compose.yml . && cp -r pos/deploy/staging/traefik pos/deploy/staging/storefront .   # when infra changed
docker compose up -d --build pos accounts storefront
docker compose up -d --force-recreate --scale pos=2 --scale pos-queue=2 pos-queue pos-scheduler accounts-queue
docker compose exec -u www-data pos php artisan migrate --force
docker compose exec -u www-data accounts php artisan migrate --force
```

Storefront: the `storefront` image builds from the git checkout `pos/storefront-qwik` (`npm ci` + `npm run build` inside Docker, using the committed `.env.production`). Do not upload locally built bundles. Purge the Cloudflare cache after shop deploys.

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
4. `migrate --force` + `optimize:clear` on both apps; restart workers.

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
