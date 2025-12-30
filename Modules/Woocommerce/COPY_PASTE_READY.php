<?php
/**
 * ========================================
 * COPY-PASTE READY CODE
 * ========================================
 * 
 * Add "Update POS Meta" button to WooCommerce order edit screen
 * 
 * INSTRUCTIONS:
 * 1. Update the 3 configuration values below (lines 18-20)
 * 2. Copy this entire file content
 * 3. Paste into your theme's functions.php
 * 4. Save
 * 5. Go to WooCommerce → Orders → Edit any order
 * 6. Select "Update POS Custom Meta" from Order Actions dropdown
 * 7. Click Update button
 * 
 * ========================================
 */

// ⚙️ CONFIGURATION - UPDATE THESE 3 VALUES:
define('POS_BUSINESS_ID', 1);                                              // Your POS business ID
define('POS_API_URL', 'https://pos.yoursite.com');                         // Your POS URL (no trailing slash)
define('POS_API_KEY', '>u!iXA@Gss~=kO$%wX0+-jB&Vt.aN+J9KOoa-+-ok!ZWe/u~QY'); // Your webhook secret

// ========================================
// DON'T EDIT BELOW THIS LINE
// ========================================

/**
 * Add "Update POS Meta" action to order actions dropdown
 */
add_action('woocommerce_order_actions', function($actions) {
    global $theorder;
    
    if ($theorder && $theorder->get_id()) {
        $actions['update_pos_meta'] = __('Update POS Custom Meta', 'woocommerce');
    }
    
    return $actions;
});

/**
 * Process the "Update POS Meta" action
 */
add_action('woocommerce_order_action_update_pos_meta', function($order) {
    $woocommerce_order_id = $order->get_id();
    
    // Call POS API
    $response = wp_remote_post(
        POS_API_URL . '/api/update-order-custom-meta/' . POS_BUSINESS_ID,
        [
            'headers' => [
                'Content-Type' => 'application/json',
                'X-API-Key' => POS_API_KEY
            ],
            'body' => json_encode([
                'woocommerce_order_id' => $woocommerce_order_id
            ]),
            'timeout' => 30,
            'sslverify' => true
        ]
    );
    
    // Handle response and add order note
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
                'Invoice: ' . ($body['invoice_no'] ?? 'N/A') . "\n" .
                'Order ID: ' . $woocommerce_order_id,
                false,
                true
            );
        } else {
            $order->add_order_note(
                '❌ POS Meta Update Failed: ' . ($body['msg'] ?? 'Unknown error') . ' (HTTP ' . $http_code . ')',
                false,
                true
            );
        }
    }
});

// Optional: Add admin notice to verify configuration on first load
add_action('admin_notices', function() {
    $screen = get_current_screen();
    
    // Only show on order edit pages
    if ($screen && $screen->id === 'shop_order') {
        if (POS_API_KEY === '>u!iXA@Gss~=kO$%wX0+-jB&Vt.aN+J9KOoa-+-ok!ZWe/u~QY') {
            echo '<div class="notice notice-warning"><p>';
            echo '⚠️ <strong>POS Meta Update:</strong> Please update POS_API_KEY in functions.php with your actual API key.';
            echo '</p></div>';
        }
    }
});

