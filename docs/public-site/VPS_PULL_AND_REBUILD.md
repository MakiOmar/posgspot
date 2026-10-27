# VPS: pull and rebuild containers

Steps only. Run on the VPS as root, after the code is committed and pushed. Background and troubleshooting: [STAGING_VPS.md](./STAGING_VPS.md).

Live runs **one** `pos` and **one** `pos-queue` container, which is what these commands keep. Compose resets a service to one container unless you pass `--scale`, so if you scaled up (`--scale pos=2`, `--scale pos-queue=2`), add the same flag to every command that touches that service.

Building first and then dry-running `optimize` catches config/route errors before the running container is replaced (a failing boot `optimize` makes the container restart in a loop).

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
docker compose build pos
docker compose run --rm --no-deps pos php artisan optimize --except=views
docker compose up -d pos
docker compose up -d --no-deps --force-recreate pos-queue pos-scheduler
docker compose exec -u www-data pos php artisan migrate --force
```

## Accounts only

```bash
git -C accounts pull
docker compose build accounts
docker compose run --rm --no-deps accounts php artisan optimize --except=views
docker compose up -d accounts
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
docker compose build pos accounts storefront
docker compose run --rm --no-deps pos php artisan optimize --except=views
docker compose run --rm --no-deps accounts php artisan optimize --except=views
docker compose up -d pos accounts storefront
docker compose up -d --no-deps --force-recreate pos-queue pos-scheduler accounts-queue accounts-scheduler
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
