# Storefront API v1

**Progress tracker:** [`STOREFRONT_PROGRESS.md`](./STOREFRONT_PROGRESS.md)

Base URL: `/api/storefront/v1`

All responses use the envelope:

```json
{
  "success": true,
  "data": {},
  "meta": {}
}
```

Errors:

```json
{
  "success": false,
  "message": "Error description",
  "errors": {}
}
```

## Content locale

Send the active storefront language on catalog and settings requests:

| Header | Values | Default |
|--------|--------|---------|
| `X-Content-Locale` | `en`, `ar` | `en` |

The API resolves this via `ResolveStorefrontContentLocale` middleware. Catalog list/search endpoints return **only** products/categories with content for the requested locale (no fallback to English on `ar`). Product detail returns **404** when Arabic is requested but no `ar` translation row exists.

The Qwik storefront sets this header from the URL prefix (`/en/…`, `/ar/…`) on every `storefrontFetch` call.

### Localized settings fields

`GET /settings` resolves these strings for the requested locale:

- `announcement.message`
- `sale_badge.text`
- `reward_points.name`

Stored in admin as `{ "en": "…", "ar": "…" }` on **Storefront Settings** (`/storefront/settings`). Legacy single-string values are treated as `en` on read.

Public `GET /settings` also exposes:

- `cod_enabled`
- `online_payments.enabled`, `online_payments.provider`, `online_payments.label` (no secrets)

### Checkout + Fawry payment

`POST /checkout` with `payment_method: "fawry"` when online payments are enabled returns:

```json
{
  "id": 123,
  "storefront_order_id": "web-…",
  "payment_status": "due",
  "payment": {
    "provider": "fawry",
    "sdk_url": "https://www.atfawry.com/atfawry/plugin/assets/payments/js/fawrypay-payments.js",
    "return_url": "https://{STOREFRONT_URL}/en/checkout/payment/return/?order=…",
    "locale": "en",
    "charge": {
      "merchantCode": "…",
      "merchantRefNum": "…",
      "signature": "…",
      "chargeItems": [],
      "returnUrl": "…"
    }
  }
}
```

Register Fawry webhook URL: `{APP_URL}/api/storefront/v1/payments/fawry/webhook`

Configure merchant code + security key under **Storefront Settings → Payment gateway → FawryPay**.

## Public endpoints

| Method | Path | Description |
|--------|------|-------------|
| GET | `/ping` | Health check |
| GET | `/settings` | Business + storefront public settings (includes `sale_badge`, `catalog.show_availability_on_cards`) |
| GET | `/locations` | Selling locations; `address` uses **Storefront display address** when set on the location, else landmark/city/state/country/zip. Location email is `email_encoded` (base64), not raw |
| GET | `/categories` | Category tree |
| GET | `/categories/{slug}` | Single category by slug (404 if unknown) |
| GET | `/products` | Product listing (empty if no selling locations); filter via `category_id` or `category_slug` |
| GET | `/products/{idOrSlug}` | Product detail |
| GET | `/products/{id}/availability?variation_id=` | Per-store stock modal — stock across **all active business locations** (incl. out-of-stock), not only public selling locations. Each location row includes `address`, `latitude`, `longitude`, and a ready `maps_url` (lat/lng preferred, address fallback). Coordinates are set per location in **Settings → Business Locations** |
| GET | `/search?q=&limit=` | Search autocomplete |
| POST | `/contact` | Public contact form — emails the business SMTP username (`mail_username` from email settings) |
| POST | `/cart/validate` | Revalidate cart lines (price + stock). When `location_id` is sent, stock is checked at that fulfillment store only; otherwise stock is summed across all selling locations |
| POST | `/checkout` | Create order (idempotent). `payment_method`: `cod`, `fawry`, or `card` (alias for `fawry`). Fawry responses include a signed `payment` block for hosted checkout. |
| POST | `/payments/{provider}/webhook` | Payment gateway server callback (Fawry: JSON body + signature) |
| POST | `/payments/{provider}/return` | Verify customer return URL payload after hosted checkout |
| POST | `/payments/{provider}/session` | Rebuild signed payment session for an existing pending order (`storefront_order_id`, optional `locale`) |

## Auth (Sanctum bearer token on `Contact`)

| Method | Path | Description |
|--------|------|-------------|
| POST | `/auth/register` | Register customer |
| POST | `/auth/login` | Login |
| POST | `/auth/logout` | Logout (auth required) |
| POST | `/auth/forgot-password` | Request reset (email contains link to `{STOREFRONT_URL}/reset-password?email=&token=`) |
| POST | `/auth/reset-password` | Reset password (`email`, `token`, `password`, `password_confirmation`) |

## Account (auth required)

| Method | Path | Description |
|--------|------|-------------|
| GET | `/account/profile` | Profile |
| PUT | `/account/profile` | Update profile |
| PUT | `/account/address` | Update address |
| GET | `/account/orders` | Order history |
| GET | `/account/orders/{id}` | Order detail (lines, shipping address, fulfillment location). When `payment_status` is `paid`, includes `invoice_print_url` — same POS invoice page with `print_on_load=true`. |
| GET | `/account/orders/{id}/invoice` | Paid-order invoice print URL only (fallback when detail omits `invoice_print_url`) |

## Admin

Back-office: **Settings → Storefront Settings** (`/storefront/settings`)

- Select selling locations (catalog is empty when none selected)
- COD, shipping, announcement, gateway (FawryPay: merchant code, security key, staging), contact/social
- Theme accent color (`theme.accent_color`, 6-digit hex) — drives the Qwik `--gs-accent` CSS variable
- Public `GET /settings` exposes `contact.email_encoded` (base64) instead of a raw email; the Qwik storefront decodes it client-side only (anti-harvesting)
- Public `GET /locations` uses the same `email_encoded` pattern per location (no raw `email` field)

## Notes

- Independent of WooCommerce module
- `storefront_order_id` on transactions for checkout idempotency
- CORS: configure `CORS_ALLOWED_ORIGINS` in `.env`
- Storefront site URL for reset emails: `STOREFRONT_URL` in `.env` (defaults to `APP_URL`, then `http://localhost:5173`)
- Rate limit (`throttle:storefront`, per IP): reads (GET/HEAD) use `STOREFRONT_RATE_LIMIT_READ` (default **600**/min); writes use `STOREFRONT_RATE_LIMIT` (default **120**/min). The Qwik SSR process also caches settings/categories for ~30s so layout loaders do not hit Laravel on every navigation.
