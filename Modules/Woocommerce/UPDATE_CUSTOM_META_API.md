# Update Order Custom Meta API Endpoint

## Overview
This API endpoint allows external systems (including WooCommerce) to fetch custom meta data from WooCommerce orders and update the `staff_note` field in the POS system for existing transactions.

**Key Features:**
- ✅ No session/authentication required (uses API key)
- ✅ Can be called from WooCommerce, external scripts, or automation tools
- ✅ RESTful JSON API
- ✅ Proper HTTP status codes

## Endpoint Details

**URL:** `/woocommerce/api/update-order-custom-meta/{business_id}`  
**Method:** `POST`  
**Content-Type:** `application/json` or `application/x-www-form-urlencoded`  
**Authentication:** API Key (X-API-Key header or api_key parameter)

## Authentication

The endpoint uses the **WooCommerce Order Update Webhook Secret** for authentication.

**Where to find the API Key:**
1. Go to POS Admin > WooCommerce Module > API Settings
2. Look for "Order Updated Webhook Secret" (or `woocommerce_wh_ou_secret` in the database)
3. Use this secret as your API key

**Authentication Methods:**

### Method 1: HTTP Header (Recommended)
```
X-API-Key: your_webhook_secret_here
```

### Method 2: Request Parameter
```
api_key=your_webhook_secret_here
```

## Request Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| business_id | integer | Yes | The business ID (in URL path) |
| woocommerce_order_id | integer | Yes | The WooCommerce order ID |
| api_key | string | Yes* | API key (if not using X-API-Key header) |

*Required if X-API-Key header is not provided

## Response Format

### Success Response (200 OK)
```json
{
    "success": 1,
    "msg": "Custom meta data updated successfully for Order #12345",
    "invoice_no": "POS-0001",
    "woocommerce_order_id": 5525,
    "staff_note": "\nGame Title: FIFA 24\nType: Digital\nAccount: user@example.com\nPassword: ******\n<br>----------------------<br>"
}
```

### Error Responses

**401 Unauthorized** - Invalid or missing API key
```json
{
    "success": 0,
    "msg": "Unauthorized: Invalid API key"
}
```

**400 Bad Request** - Missing woocommerce_order_id
```json
{
    "success": 0,
    "msg": "WooCommerce Order ID is required"
}
```

**404 Not Found** - Order not found
```json
{
    "success": 0,
    "msg": "Order not found in POS. WooCommerce Order ID: 12345"
}
```

**500 Internal Server Error** - Server error
```json
{
    "success": 0,
    "msg": "Failed to update: [error details]"
}
```

## Custom Meta Fields Extracted

The endpoint extracts the following custom meta fields from WooCommerce line items:

1. **game_title** - The game title
2. **type** - The product type (e.g., Digital, Physical)
3. **_account** - The account username/email
4. **_password** - The account password

These fields are formatted and stored in the transaction's `staff_note` field with the following format:

```
Game Title: [value or N/A]
Type: [value or N/A]
Account: [value or N/A]
Password: [value or N/A]
----------------------
```

## Usage Examples

### cURL Example
```bash
curl -X POST "https://yoursite.com/woocommerce/api/update-order-custom-meta/1" \
  -H "Content-Type: application/json" \
  -H "X-API-Key: >u!iXA@Gss~=kO$%wX0+-jB&Vt.aN+J9KOoa-+-ok!ZWe/u~QY" \
  -d '{"woocommerce_order_id": 5525}'
```

### cURL with API key in body
```bash
curl -X POST "https://yoursite.com/woocommerce/api/update-order-custom-meta/1" \
  -H "Content-Type: application/json" \
  -d '{
    "woocommerce_order_id": 5525,
    "api_key": ">u!iXA@Gss~=kO$%wX0+-jB&Vt.aN+J9KOoa-+-ok!ZWe/u~QY"
  }'
```

### PHP Example
```php
<?php
$business_id = 1;
$woocommerce_order_id = 5525;
$api_key = '>u!iXA@Gss~=kO$%wX0+-jB&Vt.aN+J9KOoa-+-ok!ZWe/u~QY';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://yoursite.com/woocommerce/api/update-order-custom-meta/{$business_id}");
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'woocommerce_order_id' => $woocommerce_order_id
]));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'X-API-Key: ' . $api_key
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$result = json_decode($response, true);
if ($http_code == 200 && $result['success'] == 1) {
    echo "Success: " . $result['msg'];
} else {
    echo "Error: " . $result['msg'];
}
```

### JavaScript/Node.js Example
```javascript
const axios = require('axios');

async function updateOrderCustomMeta(businessId, woocommerceOrderId, apiKey) {
    try {
        const response = await axios.post(
            `https://yoursite.com/woocommerce/api/update-order-custom-meta/${businessId}`,
            {
                woocommerce_order_id: woocommerceOrderId
            },
            {
                headers: {
                    'Content-Type': 'application/json',
                    'X-API-Key': apiKey
                }
            }
        );
        
        console.log('Success:', response.data.msg);
        return response.data;
    } catch (error) {
        console.error('Error:', error.response?.data?.msg || error.message);
        throw error;
    }
}

// Usage
updateOrderCustomMeta(1, 5525, '>u!iXA@Gss~=kO$%wX0+-jB&Vt.aN+J9KOoa-+-ok!ZWe/u~QY');
```

### Python Example
```python
import requests
import json

def update_order_custom_meta(business_id, woocommerce_order_id, api_key):
    url = f"https://yoursite.com/woocommerce/api/update-order-custom-meta/{business_id}"
    
    headers = {
        'Content-Type': 'application/json',
        'X-API-Key': api_key
    }
    
    data = {
        'woocommerce_order_id': woocommerce_order_id
    }
    
    response = requests.post(url, headers=headers, json=data)
    
    if response.status_code == 200:
        result = response.json()
        if result['success'] == 1:
            print(f"Success: {result['msg']}")
            return result
    else:
        print(f"Error: {response.json()['msg']}")
        return None

# Usage
update_order_custom_meta(1, 5525, '>u!iXA@Gss~=kO$%wX0+-jB&Vt.aN+J9KOoa-+-ok!ZWe/u~QY')
```

### WooCommerce Function (functions.php)
```php
<?php
/**
 * Update POS custom meta when order is completed
 */
add_action('woocommerce_order_status_completed', 'update_pos_custom_meta', 10, 1);

function update_pos_custom_meta($order_id) {
    $pos_api_url = 'https://yourpos.com/woocommerce/api/update-order-custom-meta/1';
    $api_key = '>u!iXA@Gss~=kO$%wX0+-jB&Vt.aN+J9KOoa-+-ok!ZWe/u~QY';
    
    $response = wp_remote_post($pos_api_url, [
        'headers' => [
            'Content-Type' => 'application/json',
            'X-API-Key' => $api_key
        ],
        'body' => json_encode([
            'woocommerce_order_id' => $order_id
        ]),
        'timeout' => 15
    ]);
    
    if (is_wp_error($response)) {
        error_log('POS API Error: ' . $response->get_error_message());
    } else {
        $body = json_decode(wp_remote_retrieve_body($response), true);
        if ($body['success'] == 1) {
            error_log('POS custom meta updated for order #' . $order_id);
        }
    }
}
```

## Database Impact

**Table:** `transactions`  
**Column Updated:** `staff_note`  

The `staff_note` field is completely replaced with the new custom meta data from WooCommerce. Any existing staff notes will be overwritten.

## Security Considerations

1. **API Key Protection:** Keep your API key secret. It's the same as your webhook secret.
2. **HTTPS Required:** Always use HTTPS in production to protect the API key in transit.
3. **Rate Limiting:** Consider implementing rate limiting if calling frequently.
4. **IP Whitelisting:** For additional security, you can whitelist WooCommerce server IPs in your firewall.

## Use Cases

1. **WooCommerce Action Hook** - Automatically update when order status changes
2. **Bulk Update Script** - Process multiple orders at once via external script
3. **Manual API Call** - Update specific orders on demand
4. **Third-party Integration** - Connect with automation tools (Zapier, Make.com, n8n)
5. **Scheduled Jobs** - Cron job to refresh meta data periodically

## Testing the API

### Test with Postman
1. Create new POST request
2. URL: `https://yoursite.com/woocommerce/api/update-order-custom-meta/1`
3. Headers: `X-API-Key: your_secret`
4. Body (JSON):
```json
{
    "woocommerce_order_id": 5525
}
```
5. Send request
6. Verify response

### Test with curl (quick test)
```bash
curl -X POST "https://yoursite.com/woocommerce/api/update-order-custom-meta/1" \
  -H "X-API-Key: your_secret" \
  -H "Content-Type: application/json" \
  -d '{"woocommerce_order_id": 5525}' \
  -v
```

## Related Files

- **Controller:** `Modules/Woocommerce/Http/Controllers/WoocommerceWebhookController.php`
- **Route:** `Modules/Woocommerce/Routes/web.php`
- **Utility:** `Modules/Woocommerce/Utils/WoocommerceUtil.php`

## Logs

All errors are logged using Laravel's emergency log:
```
Log file: storage/logs/laravel.log
Format: [timestamp] local.EMERGENCY: File:[file]Line:[line]Message:[message]
```

## Troubleshooting

### Error: "Unauthorized: Invalid API key"
- **Cause:** API key is missing or incorrect
- **Solution:** Verify the API key matches `woocommerce_wh_ou_secret` in the database

### Error: "Order not found in POS"
- **Cause:** Order hasn't been synced to POS yet
- **Solution:** Sync the order first, then call this API

### Error: "Order not found in WooCommerce"
- **Cause:** Invalid WooCommerce order ID or WooCommerce API connection issue
- **Solution:** Verify the order ID exists in WooCommerce and API credentials are correct

### No response / Timeout
- **Cause:** Server issue, network problem, or WooCommerce API is slow
- **Solution:** Check Laravel logs, increase timeout, verify WooCommerce API is responsive

## Notes

- This endpoint does NOT sync the entire order, only updates the `staff_note` field
- The transaction must already exist in the POS system
- Requires active WooCommerce API connection
- No session or web authentication required (stateless API)
- Can be called unlimited times for the same order

