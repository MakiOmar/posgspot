# Add "Update POS Meta" Button to WooCommerce Order Screen

## Overview
This guide shows you how to add a custom button to the WooCommerce order edit screen that updates custom meta data in your POS system.

![Button Location: WooCommerce Edit Order → Order Actions metabox]

## 🎯 What This Does

When you click the button in WooCommerce admin:
1. Sends order ID to your POS API
2. POS fetches fresh order data from WooCommerce
3. Extracts custom meta: `game_title`, `type`, `_account`, `_password`
4. Updates the POS transaction `staff_note` field
5. Shows success/error message

## 📋 Before You Start

You need these values:
- **POS Business ID:** Your business ID in POS (e.g., `1`)
- **Bearer Token:** Your `woocommerce_wh_ou_secret` from POS database
- **POS URL:** Your POS site URL (e.g., `https://pos.yoursite.com`)

## 🔧 Installation Steps

### Step 1: Add Code to functions.php

Open your WordPress theme's `functions.php` file (or use a custom plugin) and add this code:

```php
<?php
/**
 * Add "Update POS Meta" button to WooCommerce order actions
 */
add_action('woocommerce_order_actions', 'add_update_pos_meta_order_action');
function add_update_pos_meta_order_action($actions) {
    global $theorder;
    
    // Only show for orders that exist
    if ($theorder && $theorder->get_id()) {
        $actions['update_pos_meta'] = __('Update POS Custom Meta', 'woocommerce');
    }
    
    return $actions;
}

/**
 * Process the "Update POS Meta" action
 */
add_action('woocommerce_order_action_update_pos_meta', 'process_update_pos_meta_action');
function process_update_pos_meta_action($order) {
    // ⚙️ CONFIGURATION - Update these values
    $pos_business_id = 1; // Your POS business ID
    $pos_api_url = 'https://pos.yoursite.com'; // Your POS URL (no trailing slash)
    $bearer_token = '>u!iXA@Gss~=kO$%wX0+-jB&Vt.aN+J9KOoa-+-ok!ZWe/u~QY'; // Your Bearer token
    
    $woocommerce_order_id = $order->get_id();
    
    // Call POS API
    $response = wp_remote_post(
        $pos_api_url . '/api/update-order-custom-meta/' . $pos_business_id,
        [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $bearer_token
            ],
            'body' => json_encode([
                'woocommerce_order_id' => $woocommerce_order_id
            ]),
            'timeout' => 30
        ]
    );
    
    // Handle response
    if (is_wp_error($response)) {
        $order->add_order_note(
            '❌ POS Meta Update Failed: ' . $response->get_error_message(),
            false,
            true
        );
    } else {
        $body = json_decode(wp_remote_retrieve_body($response), true);
        $http_code = wp_remote_retrieve_response_code($response);
        
        if ($http_code == 200 && isset($body['success']) && $body['success'] == 1) {
            $order->add_order_note(
                '✅ POS Custom Meta Updated Successfully' . "\n" . 
                'Invoice: ' . ($body['invoice_no'] ?? 'N/A'),
                false,
                true
            );
        } else {
            $order->add_order_note(
                '❌ POS Meta Update Failed: ' . ($body['msg'] ?? 'Unknown error'),
                false,
                true
            );
        }
    }
}
```

### Step 2: Configure Your Settings

In the code above, update these three values:

```php
$pos_business_id = 1; // ← Your POS business ID
$pos_api_url = 'https://pos.yoursite.com'; // ← Your POS site URL
$api_key = 'YOUR_ACTUAL_API_KEY_HERE'; // ← Your webhook secret
```

### Step 3: Save and Test

1. Save `functions.php`
2. Go to WooCommerce → Orders
3. Open any order
4. Look for the **"Order actions"** dropdown (right side)
5. Select **"Update POS Custom Meta"**
6. Click **"Update"** button
7. Check the order notes for success/error message

## 📸 Visual Guide

### Where to Find the Button

```
WooCommerce Admin → Orders → [Edit Order]

┌─────────────────────────────────┐
│ Order actions                   │
├─────────────────────────────────┤
│ Email invoice / order details ▼ │  ← Click this dropdown
│ Resend new order notification   │
│ Regenerate download permissions │
│ Update POS Custom Meta          │  ← New button here!
└─────────────────────────────────┘
         [Update] button             ← Click to execute
```

### Success Message

After clicking, you'll see an order note:
```
✅ POS Custom Meta Updated Successfully
Invoice: POS-0001
```

### Error Message

If something goes wrong:
```
❌ POS Meta Update Failed: Order not found in POS. WooCommerce Order ID: 5525
```

## 🔍 Finding Your Configuration Values

### 1. Find POS Business ID
- Login to POS admin
- Go to Settings → Business Settings
- Look at the URL: `/business/1/edit` ← The `1` is your business ID
- Or check the `businesses` table in your database

### 2. Find Bearer Token (Webhook Secret)
- Login to POS admin
- Go to WooCommerce Module → API Settings
- Look for **"Order Updated Webhook Secret"**
- Or check database: `businesses` table → `woocommerce_wh_ou_secret` column
- This will be used as your Bearer token

### 3. POS URL
- Your POS installation URL
- Example: `https://pos.yoursite.com` or `https://yoursite.com/pos`
- **Important:** No trailing slash

## 🚀 Alternative: JavaScript Button (AJAX)

If you prefer a custom button with JavaScript instead of using order actions:

```php
<?php
/**
 * Add custom button to order edit page
 */
add_action('woocommerce_admin_order_data_after_order_details', 'add_pos_meta_update_button');
function add_pos_meta_update_button($order) {
    ?>
    <div class="order_data_column" style="clear: both; padding-top: 15px;">
        <button type="button" id="update-pos-meta-btn" class="button button-primary" 
                data-order-id="<?php echo $order->get_id(); ?>">
            🔄 Update POS Custom Meta
        </button>
        <div id="pos-meta-result" style="margin-top: 10px;"></div>
    </div>
    
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        $('#update-pos-meta-btn').on('click', function() {
            var button = $(this);
            var orderId = button.data('order-id');
            var resultDiv = $('#pos-meta-result');
            
            button.prop('disabled', true).text('⏳ Updating...');
            resultDiv.html('');
            
            // ⚙️ CONFIGURATION - Update these values
            var posBusiness = 1; // Your POS business ID
            var posUrl = 'https://pos.yoursite.com'; // Your POS URL
            var bearerToken = '>u!iXA@Gss~=kO$%wX0+-jB&Vt.aN+J9KOoa-+-ok!ZWe/u~QY'; // Your Bearer token
            
            $.ajax({
                url: posUrl + '/api/update-order-custom-meta/' + posBusiness,
                method: 'POST',
                headers: {
                    'Authorization': 'Bearer ' + bearerToken,
                    'Content-Type': 'application/json'
                },
                data: JSON.stringify({
                    woocommerce_order_id: orderId
                }),
                success: function(response) {
                    if (response.success == 1) {
                        resultDiv.html('<p style="color: green;">✅ ' + response.msg + '</p>');
                    } else {
                        resultDiv.html('<p style="color: red;">❌ ' + response.msg + '</p>');
                    }
                },
                error: function(xhr) {
                    var msg = xhr.responseJSON?.msg || 'Request failed';
                    resultDiv.html('<p style="color: red;">❌ Error: ' + msg + '</p>');
                },
                complete: function() {
                    button.prop('disabled', false).text('🔄 Update POS Custom Meta');
                }
            });
        });
    });
    </script>
    <?php
}
```

## ❓ Troubleshooting

### Button doesn't appear
- **Cause:** Code not added to `functions.php` correctly
- **Solution:** Check for PHP syntax errors, look at WordPress debug log

### Click button but nothing happens
- **Cause:** JavaScript error or wrong configuration
- **Solution:** Open browser console (F12), check for errors

### Error: "Unauthorized: Invalid or missing Bearer token"
- **Cause:** Wrong Bearer token or incorrect format
- **Solution:** 
  - Verify the token matches your POS `woocommerce_wh_ou_secret`
  - Ensure format is `Authorization: Bearer your_token` (note the space after "Bearer")

### Error: "Order not found in POS"
- **Cause:** Order hasn't been synced to POS yet
- **Solution:** Run full order sync first from POS admin

### Error: "CORS policy" or network error
- **Cause:** CORS restriction or wrong POS URL
- **Solution:** 
  - Verify POS URL is correct
  - Ensure HTTPS is used
  - Check POS server is accessible from WooCommerce server

### Button works but staff_note not updated
- **Cause:** Success response but no visible change
- **Solution:** 
  - Check POS transaction edit page → Staff Notes section
  - Verify order has custom meta fields in WooCommerce
  - Check Laravel logs for errors

## 🔐 Security Notes

1. **API Key:** Keep your API key secure, don't commit to public repos
2. **HTTPS:** Always use HTTPS in production
3. **Permissions:** Only admin users can see/use order action buttons
4. **Rate Limiting:** Don't spam the button, one click per order is enough

## 📊 What Gets Updated

### In POS Database
- **Table:** `transactions`
- **Column:** `staff_note`
- **Format:**
```
Game Title: FIFA 24
Type: Digital
Account: user@example.com
Password: ******
----------------------
```

### In WooCommerce
- Order note is added showing success/failure
- No order data is modified

## 🎯 Use Cases

1. **Manual Refresh** - Order was synced but custom meta shows "N/A"
2. **After Meta Update** - You updated game account details in WooCommerce
3. **Troubleshooting** - Force refresh specific problematic orders
4. **Data Correction** - Fix orders with incomplete/wrong meta data

## 📚 Related Documentation

- Full API docs: `UPDATE_CUSTOM_META_API.md`
- Quick start: `QUICK_START_UPDATE_META.md`

## 💡 Tips

1. **Bulk Update:** For multiple orders, use a script instead (see API docs)
2. **Automation:** Use WooCommerce hooks for automatic updates (see Quick Start)
3. **Testing:** Test on a staging site first
4. **Logging:** Check both WooCommerce order notes and POS Laravel logs
5. **Backup:** Always backup before adding code to `functions.php`

## ✅ Checklist

- [ ] Added code to `functions.php`
- [ ] Updated `$pos_business_id` value
- [ ] Updated `$pos_api_url` value  
- [ ] Updated `$api_key` value
- [ ] Saved file
- [ ] Tested on one order
- [ ] Verified order note appears
- [ ] Checked POS staff_note field
- [ ] Ready for production use

---

**Need help?** Check Laravel logs at: `storage/logs/laravel.log` on your POS server

