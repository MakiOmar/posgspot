# Deploy the Games Spot storefront (Qwik)

How to build and run the public shop (`storefront-qwik/`) against the Laravel POS Storefront API.

| Piece | Role |
|-------|------|
| **Laravel POS** | API + admin — `https://pos.example.com` (`/api/storefront/v1/*`) |
| **Qwik storefront** | Public SSR site — `https://shop.example.com` (this guide) |
| **Mobile app** | Separate Expo app; same API — see [`MOBILE.md`](./MOBILE.md) |

Related: [API contract](./API.md) · [Progress](./STOREFRONT_PROGRESS.md) · [Configuration](../CONFIGURATION.md) · [Qwik project README](../../storefront-qwik/README.md)

---

## Architecture (production)

```
Browser  →  Node (Express + Qwik SSR)  →  Laravel Storefront API  →  MySQL / POS
            shop.example.com              pos.example.com/api/storefront/v1
```

The shop is **not** a static export. Production uses the Express adapter (`npm run build` → `npm run serve`).

---

## Prerequisites

1. **Node.js** `^18.17 || ^20.3 || >=21` (see `storefront-qwik/package.json`).
2. **Laravel POS** deployed and reachable over HTTPS, with:
   - Migrations applied (`php artisan migrate`)
   - `APP_URL` = POS public URL
   - `STOREFRONT_URL` = shop public URL (no trailing slash), e.g. `https://shop.example.com`
   - `CORS_ALLOWED_ORIGINS` includes the shop origin (and staging if used)
   - Queue worker running if you rely on queued mail / digital fulfill jobs
3. DNS + TLS for the shop host (and reverse proxy if Node is not public).

---

## 1. Configure Laravel (POS) for the shop

In the POS `.env` (server):

```env
APP_URL=https://pos.example.com
STOREFRONT_URL=https://shop.example.com
CORS_ALLOWED_ORIGINS=https://shop.example.com

# Optional but common in production
STOREFRONT_BUSINESS_ID=1
STOREFRONT_SUPPORT_CHAT=true
OPENAI_API_KEY=sk-...
```

Then:

```bash
php artisan config:clear
php artisan migrate --force
php artisan storage:link   # if not already
```

Payment gateways, Mailgun/SMTP, Turnstile, Fawry/Geidea, Bosta, etc. stay on the POS — see [API.md](./API.md) and [CONFIGURATION.md](../CONFIGURATION.md).

**Social login:** set Google/Facebook env on POS; redirect URIs must point at  
`{APP_URL}/api/storefront/v1/auth/social/{provider}/callback`, and the exchange return uses `STOREFRONT_URL`.

---

## 2. Configure Qwik env for the build

In `storefront-qwik/`:

```bash
cp .env.example .env.production
```

Edit `.env.production` (Vite loads this for production builds):

```env
# Laravel API origin — no trailing slash
PUBLIC_API_BASE=https://pos.example.com

# Optional
PUBLIC_HEADER_STYLE=one
# PUBLIC_FONT_FAMILY=playfair
# PUBLIC_ROBOTS_DISALLOW_ALL=true   # staging only — blocks all crawlers
# PUBLIC_CSP_REPORT_ONLY=true       # test CSP without enforcing
```

Dev uses `.env.ssr` with `npm start` / `npm run dev` (defaults to `http://localhost:8000`).

Never put OpenAI or POS secrets in Qwik env — only `PUBLIC_*` values reach the browser.

---

## 3. Build

On the build machine (or CI), from `storefront-qwik/`:

```bash
npm ci
npm run build
```

This produces:

- Client assets under `dist/`
- Express SSR entry under `server/` (run with `npm run serve`)

Smoke-check locally:

```bash
ORIGIN=https://shop.example.com PORT=3000 npm run serve
# open http://localhost:3000
```

`ORIGIN` must match the public site URL (Qwik City CSRF / origin checks).

---

## 4. Run in production (Node + reverse proxy)

### Process

Recommended: keep the app running with **systemd**, **PM2**, or Docker. Example:

```bash
cd /var/www/storefront-qwik
export ORIGIN=https://shop.example.com
export PORT=3000
export NODE_ENV=production
node server/entry.express
# or: npm run serve
```

PM2 example:

```bash
pm2 start server/entry.express --name storefront --env production
# ensure ORIGIN and PORT are in the PM2 env / ecosystem file
```

### Nginx (TLS terminate → Node)

```nginx
server {
    listen 443 ssl http2;
    server_name shop.example.com;

    # ssl_certificate ...;
    # ssl_certificate_key ...;

    location / {
        proxy_http_version 1.1;
        proxy_set_header Host $host;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_set_header X-Forwarded-Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_pass http://127.0.0.1:3000;
    }
}
```

If you terminate TLS at Nginx, keep `ORIGIN=https://shop.example.com`. Uncomment / use `getOrigin` from forwarded headers in `src/entry.express.tsx` only if origin detection fails behind the proxy.

### Apache / LiteSpeed

Proxy `/` to `http://127.0.0.1:3000` the same way (mod_proxy / LiteSpeed reverse proxy). Do **not** point the shop vhost at Laravel `public/` — that is the POS, not the Qwik app.

---

## 5. Deploy checklist

| Step | Check |
|------|--------|
| POS API | `GET https://pos.example.com/api/storefront/v1/ping` (or `/settings`) returns JSON |
| CORS | Browser Network: shop origin allowed on API calls |
| Shop HTML | Homepage loads EN/AR; images from POS `/uploads/…` |
| Uploads CORS | Trust-badge SVGs: see [API.md Notes](./API.md) (`storefront_homepage` / `storefront_library` ACAO) |
| Auth | Register / login / password reset email links use `STOREFRONT_URL` |
| Payments | Fawry/Geidea return URL hits `{STOREFRONT_URL}/…/checkout/payment/return/` |
| Staging | `PUBLIC_ROBOTS_DISALLOW_ALL=true` on non-production shop hosts (Lighthouse SEO ~69 / `is-crawlable` fail is **expected** on preview/staging). For the **live** shop build, leave this unset/false or SEO stays blocked. |
| Homepage images | Hero slides ≤~200–300 KB WebP; promo tiles ≤~100–150 KB; header logo ≥~2× display size (CSS ~40px tall → upload ≥80px tall / ~560px wide). Oversized CMS assets dominate mobile LCP. |
| AI chat | Optional: `STOREFRONT_SUPPORT_CHAT` + `OPENAI_API_KEY` on POS; widget appears when settings flag is on |

---

## 6. Update / redeploy

```bash
cd storefront-qwik
git pull
npm ci
# confirm .env.production still points at the correct PUBLIC_API_BASE
npm run build
# restart Node process (pm2 restart storefront | systemctl restart …)
```

POS-only changes (settings, catalog, payments) usually need **no** shop rebuild — only Qwik/code or `PUBLIC_*` env changes do.

**CSS / theme looks broken (Times New Roman, black page):** usually a UTF-8 BOM at the start of `src/global.css` survived into `dist/assets/*-style.css`, so the browser treats the first rule as `ï»¿ :root` and theme variables never apply. Rebuild from a BOM-free `global.css` (file must start with `/**` or `:root`, bytes `2F-2A` or `3A-72`), re-upload **both** `dist/` and `server/`, then `tmp/restart.txt`. Hard-refresh after deploy — the CSS filename hash changes every build.

---

## 7. Local development (not production)

```bash
# Terminal 1 — Laravel POS (WAMP / php artisan serve)
# Terminal 2 — Qwik
cd storefront-qwik
cp .env.example .env.ssr   # PUBLIC_API_BASE=http://localhost:8000
npm install
npm start                  # http://localhost:5173
```

Set POS `STOREFRONT_URL=http://localhost:5173` and CORS accordingly.

---

## Troubleshooting

| Symptom | Likely cause |
|---------|----------------|
| Shop loads, catalog empty / CORS errors | Wrong `PUBLIC_API_BASE` or POS `CORS_ALLOWED_ORIGINS` |
| Login works but emails link to wrong host | POS `STOREFRONT_URL` |
| Payment return 404 on shop | Shop routes not deployed / wrong `ORIGIN` host |
| Staging indexed by Google | Missing `PUBLIC_ROBOTS_DISALLOW_ALL=true` at **build** time |
| Lighthouse SEO ~69 on preview | `PUBLIC_ROBOTS_DISALLOW_ALL` is on (meta + `X-Robots-Tag` noindex) — intentional for staging; unset for production builds |
| Mobile LCP / huge image delivery | Compress POS library hero/promo assets; code only mounts the active hero slide |
| Support chat missing | POS `STOREFRONT_SUPPORT_CHAT` / `OPENAI_API_KEY`; rebuild not required |
| CSP blocks gateway script | See `plugin@security.ts`; use `PUBLIC_CSP_REPORT_ONLY` to diagnose |

---

## Out of scope

- Deploying the **POS** Laravel app itself (standard PHP/Laravel + MySQL + queue).
- **Mobile** store builds (EAS) — [`MOBILE.md`](./MOBILE.md).
- Cloudflare Workers / Netlify adapters — this repo ships the **Express** adapter; other hosts need `npm run qwik add` and a different build.
