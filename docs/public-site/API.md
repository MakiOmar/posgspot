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
| GET | `/settings` | Business + storefront public settings |
| GET | `/locations` | Selling locations (`sells_online = 1`) |
| GET | `/categories` | Category tree |
| GET | `/products` | Product listing (empty if no selling locations) |
| GET | `/products/{idOrSlug}` | Product detail |
| GET | `/products/{id}/availability?variation_id=` | Per-store stock modal |
| GET | `/search?q=&limit=` | Search autocomplete |
| POST | `/cart/validate` | Revalidate cart lines |
| POST | `/checkout` | Create order (idempotent) |
| POST | `/payments/{provider}/webhook` | Payment gateway callback |

## Auth (Sanctum bearer token on `Contact`)

| Method | Path | Description |
|--------|------|-------------|
| POST | `/auth/register` | Register customer |
| POST | `/auth/login` | Login |
| POST | `/auth/logout` | Logout (auth required) |
| POST | `/auth/forgot-password` | Request reset |
| POST | `/auth/reset-password` | Reset password |

## Account (auth required)

| Method | Path | Description |
|--------|------|-------------|
| GET | `/account/profile` | Profile |
| PUT | `/account/profile` | Update profile |
| PUT | `/account/address` | Update address |
| GET | `/account/orders` | Order history |
| GET | `/account/orders/{id}` | Order detail |

## Admin

Back-office: **Settings → Storefront Settings** (`/storefront/settings`)

- Select selling locations (catalog is empty when none selected)
- COD, shipping, announcement, gateway keys, contact/social

## Notes

- Independent of WooCommerce module
- `storefront_order_id` on transactions for checkout idempotency
- CORS: configure `CORS_ALLOWED_ORIGINS` in `.env`
