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
- `promo_codes.enabled_at_checkout`, `promo_codes.allow_stacking` (configured under **Storefront Settings** in POS)
- `payment_icons[]` — `{ label, icon_url }` for footer payment method icons (upload or external URL under **Storefront Settings → Footer payment icons**)
- `newsletter.enabled` — true when a provider is enabled and credentials are configured (no secrets exposed)

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
| GET | `/products/{idOrSlug}` | Product detail (`description` HTML is sanitized server-side). Includes `related_products[]` (ProductSummary shape, up to 8): same category/subcategory first, then same brand fill; excludes self; locale-filtered like list/search. |
| GET | `/products/{id}/availability?variation_id=` | Per-store stock modal — stock across **all active business locations** (incl. out-of-stock), not only public selling locations. Each location row includes `address`, `latitude`, `longitude`, and a ready `maps_url` (lat/lng preferred, address fallback). Coordinates are set per location in **Settings → Business Locations** |
| GET | `/search?q=&limit=` | Search autocomplete (header dropdown). Full results UI is the Qwik `/[lang]/search` page using `GET /products?q=`. |
| POST | `/contact` | Public contact form — emails the business SMTP username (`mail_username` from email settings). Optional `turnstile_token` when Turnstile is enabled in storefront settings |
| POST | `/newsletter/subscribe` | Footer newsletter signup — body `{ "email", optional "turnstile_token" }`. Requires newsletter enabled + provider credentials in Storefront Settings. Providers: Mailchimp, MailerLite, AWeber. Returns `{ status, message }` (`subscribed` / `pending` / `already_subscribed`). |
| POST | `/coupons/validate` | Validate a promo code against cart lines — body `{ "code", "items[]", optional "location_id", optional "coupon_codes[]" (already applied when stacking) }`. **Requires storefront customer auth** (Bearer token). Respects storefront settings `promo_codes.enabled_at_checkout` and `allow_stacking`. Returns `coupon`, `coupons[]`, `coupon_discount`, `shipping`, `total`, `stack_with_reward_points`. |
| POST | `/coupons/available` | List promo codes the signed-in customer can apply to the current cart — body `{ "items[]", optional "exclude_codes[]" (already applied when stacking) }`. **Requires auth.** Returns `{ coupons[] }` with `code`, `name`, `label`, `total_savings`, `discount_amount`, `free_shipping`, etc. Empty when checkout promos disabled or none eligible. |
| POST | `/cart/validate` | Revalidate cart lines (price + stock). Optional `coupon_code` or `coupon_codes[]` returns adjusted totals — **coupons require auth** and respect promo-code storefront settings. When `location_id` is sent, stock is checked at that fulfillment store only; otherwise stock is summed across all selling locations. Pass `resolve: true` to inspect lines without failing — response includes `line_status[]` with `max_quantity` per variation. |
| POST | `/checkout` | Create order (idempotent). Optional `coupon_code` or `coupon_codes[]` (**logged-in customers only**; settings-controlled; re-validated server-side; writes `coupon_redemptions` on success). `payment_method`: `cod`, `fawry`, or `card` (alias for `fawry`). Fawry responses include a signed `payment` block for hosted checkout. |
| POST | `/payments/{provider}/webhook` | Payment gateway server callback (Fawry: JSON body + signature) |
| POST | `/payments/{provider}/return` | Verify customer return URL payload after hosted checkout |
| POST | `/payments/{provider}/session` | Rebuild signed payment session for an existing pending order (`storefront_order_id`, optional `locale`) |

## Auth (Sanctum bearer token on `Contact`)

| Method | Path | Description |
|--------|------|-------------|
| POST | `/auth/register` | Register customer. Optional `turnstile_token` when Turnstile is enabled in storefront settings |
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

## Wishlist (auth required)

| Method | Path | Description |
|--------|------|-------------|
| GET | `/wishlist` | List saved products + `count` (product summaries for active locale) |
| POST | `/wishlist` | Add product — body `{ "product_id": 123 }`; returns updated list |
| POST | `/wishlist/merge` | Merge guest product ids on login — body `{ "product_ids": [1, 2] }` |
| DELETE | `/wishlist/{productId}` | Remove product; returns updated list |

Response shape:

```json
{
  "items": [/* ProductSummary[] */],
  "count": 2
}
```

Invalid or unavailable products return **422** on add.

Limits (configurable via `config/storefront.php` / env):

| Setting | Env | Default |
|---------|-----|---------|
| Max saved items per customer | `STOREFRONT_WISHLIST_MAX_ITEMS` | 100 |
| Max IDs per merge request | `STOREFRONT_WISHLIST_MERGE_MAX_IDS` | 100 |

## Admin

Back-office: **Settings → Storefront Settings** (`/storefront/settings`)

- Select selling locations (catalog is empty when none selected)
- COD, shipping, announcement, gateway (FawryPay: merchant code, security key, staging), contact/social
- **Footer payment icons** (`payment_icons`) — label + uploaded image or external URL; public API returns `{ label, icon_url }`
- **Newsletter** (`newsletter`) — enable + provider (`mailchimp` / `mailerlite` / `aweber`) + encrypted API credentials; public `GET /settings` exposes `newsletter.enabled` only
- **Cloudflare Turnstile** (`turnstile.site_key`, encrypted `turnstile.secret_key`) — when both are set, contact, registration, and newsletter require verification; public `GET /settings` exposes `turnstile.enabled` and `turnstile.site_key` only (never the secret)
- Theme accent color (`theme.accent_color`, 6-digit hex) — drives the Qwik `--gs-accent` CSS variable
- Public `GET /settings` exposes `contact.email_encoded` (base64) instead of a raw email; the Qwik storefront decodes it client-side only (anti-harvesting)
- Public `GET /locations` uses the same `email_encoded` pattern per location (no raw `email` field)

**Promo codes (POS admin):** Settings → **Promo codes** (`/coupons`) — separate from automatic POS **Discounts**. Storefront visibility/stacking: **Storefront Settings → Promo codes (storefront checkout)** (`enabled_at_checkout`, `allow_stacking`). Types: percentage off order, fixed amount off order, free shipping. Limits: date window, min eligible subtotal, max discount cap, global/per-customer usage, first-order-only, exclude sale items, channel (`storefront` / `pos` / `both`). Permissions: `coupon.access`, `coupon.create`, `coupon.delete`.

### Checkout totals order

Sale price → **coupon** → shipping (free-shipping threshold uses **pre-coupon** subtotal) → reward points → payment. Coupon + reward points stacking is controlled per coupon (`stack_with_reward_points`, default allowed).

## Notes

- Independent of WooCommerce module
- `storefront_order_id` on transactions for checkout idempotency
- CORS: configure `CORS_ALLOWED_ORIGINS` in `.env`
- Storefront site URL for reset emails: `STOREFRONT_URL` in `.env` (defaults to `APP_URL`, then `http://localhost:5173`)
- Rate limit (`throttle:storefront`, per IP): reads (GET/HEAD) use `STOREFRONT_RATE_LIMIT_READ` (default **600**/min); writes use `STOREFRONT_RATE_LIMIT` (default **120**/min). The Qwik SSR process also caches settings/categories for ~30s so layout loaders do not hit Laravel on every navigation.
- Auth endpoints (`/auth/register`, `/auth/login`, `/auth/forgot-password`, `/auth/reset-password`) use a tighter `throttle:storefront-auth` budget (`STOREFRONT_AUTH_RATE_LIMIT`, default **20**/min per IP).
- Password reset tokens expire after `STOREFRONT_PASSWORD_RESET_EXPIRE_MINUTES` (default **60**).
- Customer Sanctum bearer tokens expire after `STOREFRONT_SANCTUM_EXPIRATION_MINUTES` (default **43200** = 30 days). Password reset revokes all active storefront tokens; a new login also replaces any prior token (single active session).
- Qwik storefront (production): CSP via `src/routes/plugin@security.ts` — nonce + `strict-dynamic`, allows Fawry/Google Fonts/Maps; set `PUBLIC_CSP_REPORT_ONLY=true` to test without enforcing. See `storefront-qwik/.env.example`.
