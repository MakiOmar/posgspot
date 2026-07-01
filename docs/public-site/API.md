# Storefront API v1

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

## Public endpoints

| Method | Path | Description |
|--------|------|-------------|
| GET | `/ping` | Health check |
| GET | `/settings` | Business + storefront public settings (includes `sale_badge`, `catalog.show_availability_on_cards`) |
| GET | `/locations` | Selling locations (`sells_online = 1`); location email is `email_encoded` (base64), not raw |
| GET | `/categories` | Category tree |
| GET | `/categories/{slug}` | Single category by slug (404 if unknown) |
| GET | `/products` | Product listing (empty if no selling locations); filter via `category_id` or `category_slug` |
| GET | `/products/{idOrSlug}` | Product detail |
| GET | `/products/{id}/availability?variation_id=` | Per-store stock modal — stock across **all active business locations** (incl. out-of-stock), not only public selling locations. Each location row includes `address`, `latitude`, `longitude`, and a ready `maps_url` (lat/lng preferred, address fallback). Coordinates are set per location in **Settings → Business Locations** |
| GET | `/search?q=&limit=` | Search autocomplete |
| POST | `/cart/validate` | Revalidate cart lines (price + stock). When `location_id` is sent, stock is checked at that fulfillment store only; otherwise stock is summed across all selling locations |
| POST | `/checkout` | Create order (idempotent) |
| POST | `/payments/{provider}/webhook` | Payment gateway callback |

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
- COD, shipping, announcement, gateway keys, contact/social
- Theme accent color (`theme.accent_color`, 6-digit hex) — drives the Qwik `--gs-accent` CSS variable
- Public `GET /settings` exposes `contact.email_encoded` (base64) instead of a raw email; the Qwik storefront decodes it client-side only (anti-harvesting)
- Public `GET /locations` uses the same `email_encoded` pattern per location (no raw `email` field)

## Notes

- Independent of WooCommerce module
- `storefront_order_id` on transactions for checkout idempotency
- CORS: configure `CORS_ALLOWED_ORIGINS` in `.env`
- Storefront site URL for reset emails: `STOREFRONT_URL` in `.env` (defaults to `APP_URL`, then `http://localhost:5173`)
