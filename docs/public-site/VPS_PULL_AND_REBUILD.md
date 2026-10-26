# VPS: pull and rebuild containers

Steps only. Run on the VPS as root, after the code is committed and pushed. Background and troubleshooting: [STAGING_VPS.md](./STAGING_VPS.md).

Keep `--scale pos=2` and `--scale pos-queue=2` on every command that touches those services; without them Compose scales them back to one container.

## Start (every time)

```bash
cd /opt/spot-staging
```

## Only when infra files changed (compose, Traefik, Dockerfiles)

```bash
cp pos/deploy/staging/docker-compose.yml . && cp -r pos/deploy/staging/traefik pos/deploy/staging/storefront .
cp pos/deploy/staging/pos/{Dockerfile,.dockerignore} pos/ && cp pos/deploy/staging/accounts/{Dockerfile,.dockerignore} accounts/
```

## POS only

```bash
git -C pos pull
docker compose up -d --build --scale pos=2 pos
docker compose up -d --no-deps --force-recreate --scale pos-queue=2 pos-queue pos-scheduler
docker compose exec -u www-data pos php artisan migrate --force
```

## Accounts only

```bash
git -C accounts pull
docker compose up -d --build accounts
docker compose up -d --no-deps --force-recreate accounts-queue accounts-scheduler
docker compose exec -u www-data accounts php artisan migrate --force
```

## Storefront only

```bash
git -C pos pull
docker compose up -d --build --no-deps storefront
```

Then purge the Cloudflare cache.

## All at once

```bash
git -C pos pull && git -C accounts pull
docker compose up -d --build --scale pos=2 pos accounts storefront
docker compose up -d --no-deps --force-recreate --scale pos-queue=2 pos-queue pos-scheduler accounts-queue accounts-scheduler
docker compose exec -u www-data pos php artisan migrate --force
docker compose exec -u www-data accounts php artisan migrate --force
```

Then purge the Cloudflare cache if storefront or static assets changed.

## Check

```bash
docker compose ps
docker compose logs -f --tail=100 pos accounts storefront
```

Do not run `php artisan optimize:clear` after a deploy (use `cache:clear` for data caches).
