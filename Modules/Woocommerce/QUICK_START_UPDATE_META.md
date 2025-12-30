# Quick Start: Update Order Custom Meta API

## 🚀 Quick Usage

Update custom meta data (game_title, type, _account, _password) for any synced order.

## 📋 What You Need

1. **Business ID:** Your POS business ID (e.g., `1`)
2. **WooCommerce Order ID:** The order ID to update (e.g., `5525`)
3. **API Key:** Your webhook secret (found in POS WooCommerce settings)

## 🔑 Find Your Bearer Token

Your Bearer token is the **"Order Updated Webhook Secret"** from your WooCommerce module settings.

**Database location:** `businesses` table → `woocommerce_wh_ou_secret` column

## ⚡ Simple Test (cURL)

```bash
curl -X POST "https://your-pos-site.com/api/update-order-custom-meta/1" \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -H "Content-Type: application/json" \
  -d '{"woocommerce_order_id": 5525}'
```

## 📝 Example Response

```json
{
    "success": 1,
    "msg": "Custom meta data updated successfully for Order #12345",
    "invoice_no": "POS-0001",
    "staff_note": "\nGame Title: FIFA 24\nType: Digital\nAccount: user@example.com\nPassword: ******\n<br>----------------------<br>"
}
```

## 🔗 Integration with WooCommerce

Add this to your `functions.php` to auto-update when orders are completed:

```php
add_action('woocommerce_order_status_completed', function($order_id) {
    wp_remote_post('https://your-pos-site.com/api/update-order-custom-meta/1', [
        'headers' => [
            'Authorization' => 'Bearer YOUR_TOKEN_HERE',
            'Content-Type' => 'application/json'
        ],
        'body' => json_encode(['woocommerce_order_id' => $order_id])
    ]);
});
```

## 📚 Full Documentation

See `UPDATE_CUSTOM_META_API.md` for complete documentation with more examples.

## ❓ Common Issues

| Issue | Solution |
|-------|----------|
| 401 Unauthorized | Check your Bearer token is correct and format is `Authorization: Bearer token` |
| 404 Not Found | Make sure order was synced to POS first |
| 500 Server Error | Check Laravel logs for details |

## 🎯 What This Does

- ✅ Fetches fresh data from WooCommerce
- ✅ Extracts: `game_title`, `type`, `_account`, `_password`
- ✅ Updates POS transaction `staff_note` field
- ✅ Works from anywhere (WooCommerce, scripts, automation tools)
- ✅ No session/cookies required

