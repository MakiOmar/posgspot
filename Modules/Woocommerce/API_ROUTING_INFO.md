# API Routing Structure

## Overview
The WooCommerce module uses Laravel's standard routing structure with separate files for web and API routes.

## Route Files

### 1. `Routes/api.php`
- **Purpose:** API endpoints (stateless, no session required)
- **Prefix:** `/api`
- **Middleware:** `api`
- **Authentication:** Custom (API keys, tokens, etc.)

### 2. `Routes/web.php`
- **Purpose:** Web routes and webhooks
- **Prefix:** None (or `/woocommerce` for admin routes)
- **Middleware:** `web` (with session, CSRF, etc.)
- **Authentication:** Laravel auth/session

## Current API Endpoints

### Update Order Custom Meta
- **File:** `Routes/api.php`
- **Full URL:** `/api/update-order-custom-meta/{business_id}`
- **Method:** POST
- **Controller:** `WoocommerceWebhookController@updateOrderCustomMeta`
- **Authentication:** X-API-Key header (uses `woocommerce_wh_ou_secret`)

**Example:**
```bash
POST /api/update-order-custom-meta/1
Headers:
  X-API-Key: your_secret_key
  Content-Type: application/json
Body:
  {"woocommerce_order_id": 5525}
```

## Current Webhook Endpoints

All webhooks are in `Routes/web.php` (without web middleware):

### Order Created
- **URL:** `/webhook/order-created/{business_id}`
- **Method:** POST
- **Validation:** HMAC signature using `woocommerce_wh_oc_secret`

### Order Updated
- **URL:** `/webhook/order-updated/{business_id}`
- **Method:** POST
- **Validation:** HMAC signature using `woocommerce_wh_ou_secret`

### Order Deleted
- **URL:** `/webhook/order-deleted/{business_id}`
- **Method:** POST
- **Validation:** HMAC signature using `woocommerce_wh_od_secret`

### Order Restored
- **URL:** `/webhook/order-restored/{business_id}`
- **Method:** POST
- **Validation:** HMAC signature using `woocommerce_wh_or_secret`

## Route Registration

Routes are registered in `Providers/RouteServiceProvider.php`:

```php
// API Routes
Route::prefix('api')
    ->middleware('api')
    ->group(__DIR__.'/../Routes/api.php');

// Web Routes (including webhooks)
Route::middleware('web')
    ->group(__DIR__.'/../Routes/web.php');
```

## Why Separate Files?

### API Routes (`api.php`)
- ✅ Stateless (no session)
- ✅ JSON responses
- ✅ Custom authentication (API keys, tokens)
- ✅ Used by external systems
- ✅ Higher rate limits
- ✅ CORS-friendly

### Web Routes (`web.php`)
- ✅ Session-based
- ✅ CSRF protection
- ✅ Cookie authentication
- ✅ Used by web interface
- ✅ HTML responses

### Webhooks (currently in `web.php`)
- Stateless endpoints (no session)
- Custom signature validation
- Could be moved to `api.php` for better organization
- Keep in `web.php` to maintain consistency with existing setup

## Best Practices

1. **New API Endpoints** → Add to `Routes/api.php`
2. **New Admin Pages** → Add to `Routes/web.php` with middleware
3. **New Webhooks** → Currently in `Routes/web.php` (could be refactored to `api.php`)

## Testing API Routes

```bash
# Test API endpoint
curl -X POST "https://yoursite.com/api/update-order-custom-meta/1" \
  -H "X-API-Key: your_secret" \
  -H "Content-Type: application/json" \
  -d '{"woocommerce_order_id": 5525}'

# Test webhook endpoint
curl -X POST "https://yoursite.com/webhook/order-updated/1" \
  -H "X-WC-Webhook-Signature: hmac_signature" \
  -H "Content-Type: application/json" \
  -d '{"id": 5525, "status": "completed"}'
```

## Related Files

- **API Routes:** `Modules/Woocommerce/Routes/api.php`
- **Web Routes:** `Modules/Woocommerce/Routes/web.php`
- **Route Provider:** `Modules/Woocommerce/Providers/RouteServiceProvider.php`
- **API Controller:** `Modules/Woocommerce/Http/Controllers/WoocommerceWebhookController.php`
- **Web Controller:** `Modules/Woocommerce/Http/Controllers/WoocommerceController.php`

