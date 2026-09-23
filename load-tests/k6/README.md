# Storefront API load tests (k6)

Guest **catalog browse** + **`POST /cart/validate`** against the Laravel Storefront API (`/api/storefront/v1`).

**Default host:** `https://pos.gamesspoteg.com/api/storefront/v1`  
Override with `-e BASE_URL=…` only when intentionally targeting another environment.

## Prerequisites

- [k6](https://k6.io/docs/get-started/installation/) installed (`k6 version`)
- Network access to `pos.gamesspoteg.com`

## Environment

| Variable | Required | Default | Meaning |
|----------|----------|---------|---------|
| `BASE_URL` | no | `https://pos.gamesspoteg.com/api/storefront/v1` | Full API prefix |
| `LOCALE` | no | `en` | Sent as `X-Content-Locale` |
| `SEARCH_Q` | no | `dual` | Query for `GET /search` |
| `SCENARIO` | no | `smoke` | `smoke` · `load` (→50) · `stress` (→200, hits per-IP limits) · `soak` (1 VU under throttle) |
| `SOAK_DURATION` | no | `3m` | Duration for `SCENARIO=soak` |
| `SMOKE_DURATION` | no | `30s` | Smoke duration override |
| `VUS` | no | `2` | Smoke VU count |

Business scoping uses server `storefront.business_id` — no extra business header.

## What the journey does

Each VU iteration:

1. `GET /ping`
2. `GET /settings` + `GET /homepage`
3. `GET /categories` (pick a slug when present)
4. `GET /products?per_page=24` (+ optional `category_slug`)
5. `GET /products/{idOrSlug}` for the first list row
6. `GET /search?q=…&limit=8`
7. `POST /cart/validate` with `{ items: [{ variation_id, quantity: 1 }], resolve: true }` when a variation exists

If the catalog is empty, cart validate is **skipped** (counter `cart_validate_skips`) and does not fail the run.

**Not included:** checkout, payments, auth, coupons, wishlist, newsletter, contact, support chat.

## Run

From repo root:

```bash
# Smoke (default → pos.gamesspoteg.com)
k6 run load-tests/k6/scenarios/browse.js

# Load + JSON summary
k6 run -e SCENARIO=load \
  --summary-export load-tests/k6/results/summary-load.json \
  load-tests/k6/scenarios/browse.js

# Stress / capacity probe (ramps to 200 VUs; soft thresholds; cart every 5th iter)
k6 run -e SCENARIO=stress \
  --summary-export load-tests/k6/results/summary-stress.json \
  load-tests/k6/scenarios/browse.js
```

PowerShell:

```powershell
k6 run -e SCENARIO=stress --summary-export load-tests/k6/results/summary-stress.json load-tests/k6/scenarios/browse.js
```

## Thresholds

| Metric | Smoke / load | Stress |
|--------|----------------|--------|
| `http_req_failed` | rate &lt; 1% | rate &lt; 15% (informational) |
| `http_req_duration` (overall) | p95 &lt; 800ms | p95 &lt; 3s |
| `http_req_duration{endpoint:cart_validate}` | p95 &lt; 1500ms | — |
| `checks` | smoke &gt; 95%, load &gt; 90% | &gt; 70% |

Stress uses soft thresholds so the run completes and you can see where latency/errors climb. Cart validate runs every 5th iteration under stress so the write rate limit does not dominate results.

## Reading results

- Terminal: check `http_req_duration` percentiles, `checks`, failed requests.
- `results/summary.json`: machine-readable export for comparisons (gitignored).
- High `cart_validate_skips`: catalog may lack products/variations for the locale.
- High `cart_validate_rate_limits`: POS write throttle (`STOREFRONT_RATE_LIMIT`, default 120/min) — slow the run or lower VUs.

## Safety

- Default target for `browse.js` is the live POS API.
- **`vps-mixed.js`** targets staging (`thespotmanagment.io` + `pos.thespotmanagment.io`) — prefer that for VPS capacity tests.
- **Per-IP Laravel throttle:** reads ~`STOREFRONT_RATE_LIMIT_READ` (default **600/min**), writes ~`STOREFRONT_RATE_LIMIT` (default **120/min**). Staging may raise these temporarily for capacity runs.
- Prefer **smoke** / **soak** for health; use **stress** only when you intend to probe the protective ceiling (and expect temporary 403/429 from WAF or throttle).
- Never include checkout/payment endpoints in these scripts.
- To measure real app capacity: temporarily raise `STOREFRONT_RATE_LIMIT_READ` on POS (or whitelist the test IP), then re-run `stress` / a higher `load`.

## Staging VPS (Redis cache)

```powershell
# API-only load against staging POS (Redis-backed cache)
k6 run -e SCENARIO=load -e BASE_URL=https://pos.thespotmanagment.io/api/storefront/v1 `
  --summary-export load-tests/k6/results/summary-staging-load.json `
  load-tests/k6/scenarios/browse.js

# Mixed: Qwik shop HTML + POS API + occasional Accounts
k6 run -e SCENARIO=load -e BASE_URL=https://pos.thespotmanagment.io/api/storefront/v1 `
  -e SHOP_URL=https://thespotmanagment.io `
  --summary-export load-tests/k6/results/summary-vps-load.json `
  load-tests/k6/scenarios/vps-mixed.js

k6 run -e SCENARIO=stress -e BASE_URL=https://pos.thespotmanagment.io/api/storefront/v1 `
  --summary-export load-tests/k6/results/summary-vps-stress.json `
  load-tests/k6/scenarios/vps-mixed.js
```

See also [`docs/public-site/STAGING_VPS.md`](../../docs/public-site/STAGING_VPS.md).
