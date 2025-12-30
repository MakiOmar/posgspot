# Bearer Token Authentication Update

## Overview
The staff note API endpoint has been updated to use **Bearer Token** authentication as the primary method, following REST API best practices.

## What Changed

### Authentication Method

**Before:**
```
X-API-Key: your_secret
```

**Now (Recommended):**
```
Authorization: Bearer your_secret
```

### Backward Compatibility

✅ **Old methods still work** for backward compatibility:
- `X-API-Key` header
- `api_key` request parameter

But **Bearer token is now recommended** for all new integrations.

## How to Update Your Code

### cURL
**Before:**
```bash
curl -X POST "https://yoursite.com/api/update-order-custom-meta/1" \
  -H "X-API-Key: your_secret" \
  -d '{"woocommerce_order_id": 5525}'
```

**Now:**
```bash
curl -X POST "https://yoursite.com/api/update-order-custom-meta/1" \
  -H "Authorization: Bearer your_secret" \
  -d '{"woocommerce_order_id": 5525}'
```

### PHP (WooCommerce functions.php)
**Before:**
```php
$response = wp_remote_post($url, [
    'headers' => [
        'X-API-Key' => $api_key
    ],
    'body' => json_encode(['woocommerce_order_id' => $order_id])
]);
```

**Now:**
```php
$response = wp_remote_post($url, [
    'headers' => [
        'Authorization' => 'Bearer ' . $bearer_token
    ],
    'body' => json_encode(['woocommerce_order_id' => $order_id])
]);
```

### JavaScript/jQuery
**Before:**
```javascript
$.ajax({
    headers: {
        'X-API-Key': apiKey
    }
});
```

**Now:**
```javascript
$.ajax({
    headers: {
        'Authorization': 'Bearer ' + bearerToken
    }
});
```

### Python
**Before:**
```python
headers = {
    'X-API-Key': api_key
}
```

**Now:**
```python
headers = {
    'Authorization': f'Bearer {bearer_token}'
}
```

## Implementation Details

The controller now checks for authentication in this order:

1. **Authorization header** with `Bearer` prefix (recommended)
2. **X-API-Key header** (backward compatible)
3. **api_key parameter** (backward compatible)

```php
// Extract Bearer token
$auth_header = $request->header('Authorization');
if ($auth_header && str_starts_with($auth_header, 'Bearer ')) {
    $token = substr($auth_header, 7); // Remove "Bearer " prefix
}

// Fallback to old methods
if (empty($token)) {
    $token = $request->header('X-API-Key') ?? $request->input('api_key');
}
```

## Why Bearer Token?

✅ **Industry Standard** - Used by most modern APIs  
✅ **Better Security** - Clear separation of auth in Authorization header  
✅ **OAuth Compatible** - Works with OAuth2 flows if needed in future  
✅ **Tool Support** - Better support in API testing tools (Postman, Insomnia)  
✅ **Documentation** - Clearer API documentation  

## Error Messages

**New error message:**
```json
{
    "success": 0,
    "msg": "Unauthorized: Invalid or missing Bearer token"
}
```

**Old error message:**
```json
{
    "success": 0,
    "msg": "Unauthorized: Invalid API key"
}
```

## Testing

### Test Bearer Token
```bash
curl -X POST "https://yoursite.com/api/update-order-custom-meta/1" \
  -H "Authorization: Bearer >u!iXA@Gss~=kO$%wX0+-jB&Vt.aN+J9KOoa-+-ok!ZWe/u~QY" \
  -H "Content-Type: application/json" \
  -d '{"woocommerce_order_id": 5525}' \
  -v
```

### Test Backward Compatibility (X-API-Key still works)
```bash
curl -X POST "https://yoursite.com/api/update-order-custom-meta/1" \
  -H "X-API-Key: >u!iXA@Gss~=kO$%wX0+-jB&Vt.aN+J9KOoa-+-ok!ZWe/u~QY" \
  -H "Content-Type: application/json" \
  -d '{"woocommerce_order_id": 5525}' \
  -v
```

## Updated Files

### Code Files
- ✅ `Http/Controllers/WoocommerceWebhookController.php` - Updated authentication logic
- ✅ `COPY_PASTE_READY.php` - Updated to use Bearer token

### Documentation Files
- ✅ `UPDATE_CUSTOM_META_API.md` - Full API documentation
- ✅ `QUICK_START_UPDATE_META.md` - Quick start guide
- ✅ `WOOCOMMERCE_BUTTON_GUIDE.md` - WooCommerce button guide
- ✅ `QUICK_SETUP_BUTTON.md` - Quick setup guide

## Migration Guide

### Do You Need to Update?

**Existing integrations:**
- ✅ Will continue to work (backward compatible)
- ⚠️ But recommended to update when convenient

**New integrations:**
- ⚠️ Should use Bearer token from the start

### Update Priority

| Priority | Scenario |
|----------|----------|
| **Low** | Currently working integration using X-API-Key |
| **Medium** | Building new integration |
| **High** | Want to follow industry best practices |

### Update Steps

1. Identify where you're calling the API
2. Change `X-API-Key: token` to `Authorization: Bearer token`
3. Test the endpoint
4. Deploy

**Note:** You can update gradually. No breaking changes.

## Common Issues

### Issue: "Invalid or missing Bearer token"
**Cause:** Missing the space between "Bearer" and the token  
**Fix:** Use `Authorization: Bearer token` not `Authorization: Bearertoken`

### Issue: Still using old error message
**Cause:** Using old authentication method  
**Fix:** Switch to Bearer token for updated error messages

### Issue: Token not recognized
**Cause:** Token doesn't match `woocommerce_wh_ou_secret`  
**Fix:** Verify token from POS database `businesses` table

## Questions?

- **Is the token value the same?** Yes, still uses `woocommerce_wh_ou_secret`
- **Do I need to regenerate?** No, use existing webhook secret
- **Will old method stop working?** No, it's backward compatible
- **When should I update?** At your convenience, but recommended for new integrations

## Related Documentation

- `UPDATE_CUSTOM_META_API.md` - Complete API documentation
- `QUICK_START_UPDATE_META.md` - Quick start guide
- `COPY_PASTE_READY.php` - Ready-to-use code with Bearer token
- `API_ROUTING_INFO.md` - API routing structure

---

**Last Updated:** December 2025  
**Breaking Changes:** None (backward compatible)  
**Recommended Action:** Update to Bearer token for new integrations

